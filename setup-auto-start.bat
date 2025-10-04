@echo off
echo Setting up LILAC Auto-Sync to start automatically...
echo.

REM Get the current directory
set "SCRIPT_DIR=%~dp0"
set "BATCH_FILE=%SCRIPT_DIR%start-auto-sync.bat"

REM Create a scheduled task that runs at startup
schtasks /create /tn "LILAC Auto-Sync" /tr "%BATCH_FILE%" /sc onstart /ru "SYSTEM" /f

if %errorlevel% equ 0 (
    echo ✅ Auto-sync scheduled task created successfully!
    echo The auto-sync will now start automatically when Windows boots up.
    echo.
    echo To manually start auto-sync now, run: start-auto-sync.bat
    echo To stop auto-sync, open Task Manager and end the process.
    echo To remove auto-start, run: schtasks /delete /tn "LILAC Auto-Sync" /f
) else (
    echo ❌ Failed to create scheduled task.
    echo You may need to run this as Administrator.
    echo.
    echo You can still manually start auto-sync by running: start-auto-sync.bat
)

echo.
pause
