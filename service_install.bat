@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
title SendMessageBots - Windows Service Installer

:: Ensure administrator privileges
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo  [!] ADMINISTRATOR PRIVILEGES REQUIRED
    echo ========================================================
    echo  This script needs Administrator rights to register
    echo  a background service or scheduled task in Windows.
    echo.
    echo  HOW TO RUN:
    echo  1. Right-click on 'service_install.bat'
    echo  2. Select 'Run as administrator'
    echo ========================================================
    echo.
    pause
    exit /b 1
)

:: Detect PHP Path
set PHP_BIN=
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    for /f "delims=" %%i in ('where php') do (
        if not defined PHP_BIN set "PHP_BIN=%%i"
    )
)
if not defined PHP_BIN (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else if exist "D:\xampp\php\php.exe" (
        set "PHP_BIN=D:\xampp\php\php.exe"
    )
)

if not defined PHP_BIN (
    echo.
    echo ========================================================
    echo [ERROR] PHP executable not found in PATH or C:\xampp\php\
    echo Please make sure PHP or XAMPP is installed.
    echo ========================================================
    echo.
    pause
    exit /b 1
)

:: Ensure directories exist
if not exist "%~dp0logs" mkdir "%~dp0logs"
if not exist "%~dp0tools" mkdir "%~dp0tools"

:: Read Port from .env or default to 8080
set PORT=8080
if exist "%~dp0.env" (
    for /f "tokens=1,2 delims==" %%a in ('findstr /r "^APP_PORT=" "%~dp0.env"') do (
        if "%%a"=="APP_PORT" if not "%%b"=="" set "PORT=%%b"
    )
)

set SERVICE_NAME=SendMessageBots

:menu
cls
echo ========================================================
echo   SendMessageBots - Windows Background Service Setup
echo ========================================================
echo.
echo  [OK] Administrator Privileges: Active
echo  [OK] Detected PHP: %PHP_BIN%
echo  [OK] Service Port: %PORT% (Configured in .env as APP_PORT)
echo  [OK] Project Path: %~dp0
echo.
echo Select an installation method:
echo.
echo [1] Install as Windows Service (via NSSM)
echo     - Registered in Windows services.msc
echo     - Auto-starts on server reboot (no user login required)
echo     - Auto-restarts on crash
echo     - Redirects stdout/stderr to logs\
echo.
echo [2] Install via Windows Task Scheduler (100%% Native - Zero Downloads)
echo     - Uses Windows built-in Task Scheduler
echo     - Runs at system boot (AtStartup) with SYSTEM privileges
echo     - 100%% offline, works immediately without internet
echo.
echo [3] Exit
echo.
set /p CHOICE="Enter choice [1, 2, or 3] and press Enter: "

if "%CHOICE%"=="1" goto :install_nssm
if "%CHOICE%"=="2" goto :install_schtasks
if "%CHOICE%"=="3" exit /b 0
goto :menu

:: ──────────────────────────────────────────────────────────
:: NSSM Installation
:: ──────────────────────────────────────────────────────────
:install_nssm
echo.
echo ========================================================
echo [1/3] Preparing NSSM Service Manager...
echo ========================================================

set NSSM_BIN=%~dp0tools\nssm.exe
if not exist "%NSSM_BIN%" (
    where nssm >nul 2>&1
    if %ERRORLEVEL% EQU 0 (
        set NSSM_BIN=nssm
    ) else (
        echo NSSM not found locally. Downloading lightweight nssm.exe...
        powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; try { Invoke-WebRequest -Uri 'https://github.com/kirillkovalenko/nssm-win64/raw/master/nssm.exe' -OutFile '%~dp0tools\nssm.exe' -TimeoutSec 10 } catch { Write-Host 'Download failed or timed out.' }"
    )
)

if not exist "%NSSM_BIN%" (
    where nssm >nul 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo.
        echo [!] Could not download nssm.exe (network restriction on server).
        echo [!] Automatically falling back to Native Windows Task Scheduler (Option 2)...
        echo.
        timeout /t 2 >nul
        goto :install_schtasks
    ) else (
        set NSSM_BIN=nssm
    )
)

echo.
echo [2/3] Registering Windows Service '%SERVICE_NAME%' on port %PORT%...

echo Stopping any existing service instance...
"%NSSM_BIN%" stop %SERVICE_NAME% >nul 2>&1
"%NSSM_BIN%" remove %SERVICE_NAME% confirm >nul 2>&1

"%NSSM_BIN%" install %SERVICE_NAME% "%PHP_BIN%" "-S 0.0.0.0:%PORT% router.php"
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Failed to install service with NSSM. Falling back to Option 2...
    goto :install_schtasks
)

"%NSSM_BIN%" set %SERVICE_NAME% AppDirectory "%~dp0"
"%NSSM_BIN%" set %SERVICE_NAME% DisplayName "SendMessageBots Web Service"
"%NSSM_BIN%" set %SERVICE_NAME% Description "SendMessageBots PHP background web service on port %PORT%"
"%NSSM_BIN%" set %SERVICE_NAME% Start SERVICE_AUTO_START
"%NSSM_BIN%" set %SERVICE_NAME% AppStdout "%~dp0logs\service_out.log"
"%NSSM_BIN%" set %SERVICE_NAME% AppStderr "%~dp0logs\service_err.log"
"%NSSM_BIN%" set %SERVICE_NAME% AppRotateFiles 1
"%NSSM_BIN%" set %SERVICE_NAME% AppRotateOnline 1
"%NSSM_BIN%" set %SERVICE_NAME% AppRotateBytes 5242880

echo.
echo [3/3] Starting Windows Service '%SERVICE_NAME%'...
"%NSSM_BIN%" start %SERVICE_NAME%

echo.
echo ========================================================
echo  [SUCCESS] Windows Service '%SERVICE_NAME%' is INSTALLED and RUNNING!
echo ========================================================
echo  - Status: Active in background (24/7)
echo  - Port:   %PORT%
echo  - Startup: Automatic on Windows reboot (No user login needed)
echo  - Local URL: http://localhost:%PORT%/admin/
echo  - Control with: service_control.bat
echo ========================================================
echo.
pause
exit /b 0

:: ──────────────────────────────────────────────────────────
:: Task Scheduler Installation
:: ──────────────────────────────────────────────────────────
:install_schtasks
echo.
echo ========================================================
echo Setting up Native Windows Task Scheduler Service on port %PORT%...
echo ========================================================

set TASK_NAME=SendMessageBots_Server
set RUNNER_VBS=%~dp0tools\run_hidden.vbs

echo Creating hidden background launcher...
> "%RUNNER_VBS%" (
    echo Set WshShell = CreateObject("WScript.Shell"^)
    echo WshShell.CurrentDirectory = "%~dp0"
    echo WshShell.Run """%PHP_BIN%"" -S 0.0.0.0:%PORT% router.php", 0, False
)

echo Removing any existing task '%TASK_NAME%'...
schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1

echo Registering system startup task '%TASK_NAME%'...
schtasks /Create /TN "%TASK_NAME%" /TR "wscript.exe \"%RUNNER_VBS%\"" /SC ONSTART /RU "SYSTEM" /RL HIGHEST /F

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Starting task in background...
    schtasks /Run /TN "%TASK_NAME%"
    wscript.exe "%RUNNER_VBS%"
    
    echo.
    echo ========================================================
    echo  [SUCCESS] Background Service '%TASK_NAME%' is ACTIVATED!
    echo ========================================================
    echo  - Status: Running in background
    echo  - Port:   %PORT%
    echo  - Auto-start: Configured for every Windows restart
    echo  - Local URL:  http://localhost:%PORT%/admin/
    echo  - Control with: service_control.bat
    echo ========================================================
) else (
    echo.
    echo [ERROR] Failed to register task with error code: %ERRORLEVEL%
)

echo.
pause
exit /b 0
