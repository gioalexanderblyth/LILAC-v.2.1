@echo off
echo Starting LILAC Events System Server...
echo.
echo Make sure you have PHP installed and in your PATH.
echo.
echo Opening browser in 3 seconds...
echo.

cd /d "%~dp0"
start http://localhost:8000/events-activities.html
timeout /t 3 /nobreak > nul

echo Starting PHP server on localhost:8000
echo Press Ctrl+C to stop the server
echo.
echo Note: Make sure PHP is installed and in your PATH
echo.
php -S localhost:8000 -t .
