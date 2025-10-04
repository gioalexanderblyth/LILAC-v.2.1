@echo off
echo Stopping LILAC Auto-Sync...

REM Kill any running PowerShell processes that might be running the auto-sync
taskkill /f /im powershell.exe /fi "WINDOWTITLE eq LILAC Auto-Sync*" 2>nul

REM Remove the scheduled task
schtasks /delete /tn "LILAC Auto-Sync" /f 2>nul

echo Auto-sync stopped and removed from startup.
pause
