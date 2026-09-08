@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0.."
title SendMessageBots - Database Setup

echo ========================================================
echo   SendMessageBots - MySQL Database Setup & Migrations
echo ========================================================
echo.

where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else (
        echo [ERROR] PHP is not found in PATH or at C:\xampp\php\php.exe
        echo.
        pause
        exit /b 1
    )
) else (
    set PHP_BIN=php
)

"%PHP_BIN%" bin\setup_db.php

echo.
pause
