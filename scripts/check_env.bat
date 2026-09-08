@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0.."
title SendMessageBots - System Diagnostic Check

echo Checking PHP and Environment requirements...
echo.

where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PATH=%PATH%;C:\xampp\php"
    ) else (
        echo [ERROR] PHP is not found in your system PATH or C:\xampp\php\
        echo.
        pause
        exit /b 1
    )
)

php bin\check_requirements.php
echo.
pause
