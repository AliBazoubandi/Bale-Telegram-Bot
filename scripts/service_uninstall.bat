@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0.."
set "PROJECT_DIR=%CD%"
title SendMessageBots - Windows Service Uninstaller

:: Auto-Request Administrator Privileges
>nul 2>&1 "%SYSTEMROOT%\system32\cacls.exe" "%SYSTEMROOT%\system32\config\system"
if %ERRORLEVEL% NEQ 0 (
    echo Requesting Administrator privileges...
    powershell -Command "Start-Process cmd -ArgumentList '/c cd /d \"\"%~dp0..\"\" && \"\"%~dpnx0\"\"' -Verb RunAs"
    exit /b
)

echo ========================================================
echo   SendMessageBots - Windows Service Removal
echo ========================================================
echo.

set SERVICE_NAME=SendMessageBots
set TASK_NAME=SendMessageBots_Server
set PORT=8080

:: 1. Remove NSSM Service
set NSSM_BIN=%PROJECT_DIR%\tools\nssm.exe
if not exist "%NSSM_BIN%" (
    where nssm >nul 2>&1
    if %ERRORLEVEL% EQU 0 set NSSM_BIN=nssm
)

if exist "%NSSM_BIN%" (
    echo Stopping and removing NSSM service '%SERVICE_NAME%'...
    "%NSSM_BIN%" stop %SERVICE_NAME% >nul 2>&1
    "%NSSM_BIN%" remove %SERVICE_NAME% confirm >nul 2>&1
) else (
    sc stop %SERVICE_NAME% >nul 2>&1
    sc delete %SERVICE_NAME% >nul 2>&1
)

:: 2. Remove Task Scheduler task
echo Removing scheduled task '%TASK_NAME%'...
schtasks /End /TN "%TASK_NAME%" >nul 2>&1
schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1

:: 3. Kill any lingering PHP process listening on port 8080
echo Checking for active PHP server processes on port %PORT%...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%PORT%" ^| findstr "LISTENING"') do (
    echo Terminating process PID %%a...
    taskkill /PID %%a /F >nul 2>&1
)

echo.
echo ========================================================
echo [SUCCESS] SendMessageBots service and background tasks have been removed.
echo ========================================================
echo.
pause
