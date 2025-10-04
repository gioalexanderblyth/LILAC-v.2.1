@echo off
title LILAC Auto-Sync
echo ================================================
echo     LILAC Automatic Sync to GitHub
echo ================================================
echo.
echo Starting automatic file watcher...
echo This will monitor your files and automatically
echo commit and push changes to GitHub.
echo.
echo Press Ctrl+C to stop the auto-sync
echo.

powershell -ExecutionPolicy Bypass -File "auto-sync-watcher.ps1"

pause
