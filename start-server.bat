@echo off
echo Starting LILAC Events System Server...
echo.
echo Make sure you have PHP installed and in your PATH.
echo.
echo Preparing server...
echo Press Ctrl+C to stop the server
echo.
echo Note: Make sure PHP is installed and in your PATH
echo.
cd /d "%~dp0"
set "PORT=8000"
set "DOCROOT=%cd%"

echo Starting PHP server on localhost:%PORT%
start "LILAC PHP Server" cmd /k "D:\php\php.exe" -S 127.0.0.1:%PORT% -t "%DOCROOT%"
timeout /t 2 /nobreak > nul

echo Opening browser...
start http://localhost:%PORT%/dashboard.html
