@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
title SendMessageBots - Web Server

:: Read Port from argument, then .env, then default to 8080
set PORT=%1
if "%PORT%"=="" (
    if exist "%~dp0.env" (
        for /f "tokens=1,2 delims==" %%a in ('findstr /r "^APP_PORT=" "%~dp0.env"') do (
            if "%%a"=="APP_PORT" if not "%%b"=="" set "PORT=%%b"
        )
    )
)
if "%PORT%"=="" set PORT=8080

:: Detect PHP
set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else if exist "D:\xampp\php\php.exe" (
        set PHP_BIN=D:\xampp\php\php.exe
    ) else (
        echo [ERROR] PHP executable not found.
        echo Please ensure PHP is added to your PATH or XAMPP is installed at C:\xampp
        echo.
        pause
        exit /b 1
    )
)

echo ========================================================
echo    SendMessageBots - Starting Web Server (No Docker)
echo ========================================================
echo.
echo PHP Path: %PHP_BIN%
echo Server URL: http://localhost:%PORT%
echo.
echo [Endpoints]
echo  - Admin Panel:       http://localhost:%PORT%/admin/
echo  - Telegram Webhook:  http://localhost:%PORT%/telegram_webhook.php
echo  - Bale Webhook:      http://localhost:%PORT%/bale_webhook.php
echo  - Send API:          http://localhost:%PORT%/api_send.php
echo.
echo Press Ctrl+C to stop the server at any time.
echo ========================================================
echo.

"%PHP_BIN%" -S 0.0.0.0:%PORT% router.php

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Server stopped with code %ERRORLEVEL%
    pause
)
