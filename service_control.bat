@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
cd /d "%~dp0"
title SendMessageBots - Service Control Panel

set SERVICE_NAME=SendMessageBots
set TASK_NAME=SendMessageBots_Server

:: Read Port from .env or default to 8080
set PORT=8080
if exist "%~dp0.env" (
    for /f "tokens=1,2 delims==" %%a in ('findstr /r "^APP_PORT=" "%~dp0.env"') do (
        if "%%a"=="APP_PORT" if not "%%b"=="" set "PORT=%%b"
    )
)

:: Locate NSSM
set NSSM_BIN=%~dp0tools\nssm.exe
if not exist "%NSSM_BIN%" (
    where nssm >nul 2>&1
    if %ERRORLEVEL% EQU 0 set NSSM_BIN=nssm
)

:menu
cls
echo ========================================================
echo       SendMessageBots - Service Control Panel
echo ========================================================
echo.
echo  Configured Port: %PORT%
echo.
echo [1] Start Service
echo [2] Stop Service
echo [3] Restart Service
echo [4] Check Status
echo [5] View Output Log
echo [6] View Error Log
echo [7] Open Admin Panel in Browser
echo [8] Run Pre-flight Diagnostic Check
echo [9] Exit
echo.
set /p OPT="Select option [1-9]: "

if "%OPT%"=="1" goto :start_svc
if "%OPT%"=="2" goto :stop_svc
if "%OPT%"=="3" goto :restart_svc
if "%OPT%"=="4" goto :status_svc
if "%OPT%"=="5" goto :view_out_log
if "%OPT%"=="6" goto :view_err_log
if "%OPT%"=="7" goto :open_browser
if "%OPT%"=="8" goto :run_check
if "%OPT%"=="9" exit /b 0
goto :menu

:start_svc
echo.
echo Starting service...
if exist "%NSSM_BIN%" (
    "%NSSM_BIN%" start %SERVICE_NAME%
) else (
    sc start %SERVICE_NAME% 2>nul || schtasks /Run /TN "%TASK_NAME%" 2>nul
)
if exist "%~dp0tools\run_hidden.vbs" (
    wscript.exe "%~dp0tools\run_hidden.vbs"
)
echo Service start requested.
echo.
pause
goto :menu

:stop_svc
echo.
echo Stopping service...
if exist "%NSSM_BIN%" (
    "%NSSM_BIN%" stop %SERVICE_NAME% >nul 2>&1
)
sc stop %SERVICE_NAME% >nul 2>&1
schtasks /End /TN "%TASK_NAME%" >nul 2>&1

echo Terminating PHP processes on port %PORT%...
powershell -Command "Get-NetTCPConnection -LocalPort %PORT% -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%PORT%" ^| findstr "LISTENING"') do (
    taskkill /PID %%a /F >nul 2>&1
)
echo.
echo Service stop completed.
echo.
pause
goto :menu

:restart_svc
echo.
echo Restarting service...
call :stop_svc_silent
timeout /t 2 /nobreak >nul
call :start_svc_silent
echo.
echo Restart complete.
pause
goto :menu

:stop_svc_silent
if exist "%NSSM_BIN%" "%NSSM_BIN%" stop %SERVICE_NAME% >nul 2>&1
sc stop %SERVICE_NAME% >nul 2>&1
schtasks /End /TN "%TASK_NAME%" >nul 2>&1
powershell -Command "Get-NetTCPConnection -LocalPort %PORT% -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%PORT%" ^| findstr "LISTENING"') do (
    taskkill /PID %%a /F >nul 2>&1
)
exit /b

:start_svc_silent
if exist "%NSSM_BIN%" (
    "%NSSM_BIN%" start %SERVICE_NAME% >nul 2>&1
) else (
    sc start %SERVICE_NAME% >nul 2>&1 || schtasks /Run /TN "%TASK_NAME%" >nul 2>&1
)
if exist "%~dp0tools\run_hidden.vbs" (
    wscript.exe "%~dp0tools\run_hidden.vbs"
)
exit /b

:status_svc
echo.
echo ========================================================
echo 1. Windows Service Status:
echo ========================================================
if exist "%NSSM_BIN%" (
    "%NSSM_BIN%" status %SERVICE_NAME%
) else (
    sc query %SERVICE_NAME% 2>nul || schtasks /Query /TN "%TASK_NAME%" 2>nul
)

echo.
echo ========================================================
echo 2. Port %PORT% Listener Status:
echo ========================================================
netstat -aon | findstr ":%PORT%" | findstr "LISTENING"
if %ERRORLEVEL% EQU 0 goto :port_is_open
goto :port_is_closed

:port_is_open
echo [OK] Web server is actively listening on port %PORT%.
goto :status_done

:port_is_closed
echo [INFO] Port %PORT% is NOT listening - Service is STOPPED.
goto :status_done

:status_done
echo ========================================================
echo.
pause
goto :menu

:view_out_log
echo.
if exist "%~dp0logs\service_out.log" (
    echo Last 30 lines of logs\service_out.log:
    echo ----------------------------------------------------
    powershell -Command "Get-Content -Path '%~dp0logs\service_out.log' -Tail 30"
) else (
    echo [INFO] Log file logs\service_out.log does not exist yet.
)
echo.
pause
goto :menu

:view_err_log
echo.
if exist "%~dp0logs\service_err.log" (
    echo Last 30 lines of logs\service_err.log:
    echo ----------------------------------------------------
    powershell -Command "Get-Content -Path '%~dp0logs\service_err.log' -Tail 30"
) else (
    echo [INFO] Log file logs\service_err.log does not exist yet.
)
echo.
pause
goto :menu

:open_browser
start http://localhost:%PORT%/admin/
goto :menu

:run_check
echo.
call check_env.bat
goto :menu
