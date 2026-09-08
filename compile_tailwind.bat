@echo off
setlocal
cd /d "%~dp0"

echo Compiling Tailwind CSS...

where npx >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo Using local Node.js / npx...
    npx -y tailwindcss@4 -i admin/assets/input.css -o admin/assets/tailwind.css
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Compiled successfully to admin/assets/tailwind.css
        exit /b 0
    )
)

where docker >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo Using Docker container fallback...
    docker run --rm -v "%cd%:/app" -w /app node:20-alpine sh -c "npx -y tailwindcss@4 -i admin/assets/input.css -o admin/assets/tailwind.css"
    exit /b 0
)

echo [WARN] Neither Node.js (npx) nor Docker was found.
echo Note: The pre-compiled 'admin/assets/tailwind.css' is already included in this repository.
echo You only need to run this script if you modify 'admin/assets/input.css'.
echo.
pause
