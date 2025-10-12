@echo off
echo Starting LILAC Events System Server...
echo.
echo Make sure you have PHP installed and in your PATH.
echo.
echo Preparing server...
echo Press Ctrl+C to stop the server
echo.
cd /d "%~dp0"
set "PORT=8000"

echo Starting PHP server on localhost:%PORT%
echo Server will start in a new window...
echo.
echo Opening browser in 3 seconds...
timeout /t 3 /nobreak > nul
start http://localhost:%PORT%/dashboard.html

echo Starting server...
php -S localhost:%PORT% -t "%cd%"
pause
