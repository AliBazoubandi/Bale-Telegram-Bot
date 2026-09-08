@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
cd /d "%~dp0.."
title SendMessageBots - Webhook Registration Tool

where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else (
        echo [ERROR] PHP is not found in PATH or C:\xampp\php\php.exe
        pause
        exit /b 1
    )
) else (
    set PHP_BIN=php
)

echo ========================================================
echo   SendMessageBots - Telegram ^& Bale Webhook Setup
echo ========================================================
echo.
echo Please enter your public HTTPS base URL.
echo Examples:
echo   https://lyrically-heritage-scion.ngrok-free.dev
echo   https://yourdomain.com
echo.
set /p BASE_URL="Enter Public Base URL (with https://): "

if "%BASE_URL%"=="" (
    echo [ERROR] Base URL cannot be empty.
    pause
    exit /b 1
)

:: Run unified webhook registration
"%PHP_BIN%" bin\register_webhooks.php "%BASE_URL%"

echo.
pause
