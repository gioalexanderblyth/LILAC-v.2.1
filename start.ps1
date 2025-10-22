# LILAC Server Launcher
# This script launches the main server script from the scripts folder

Write-Host "LILAC Server Launcher" -ForegroundColor Green
Write-Host "====================" -ForegroundColor Green
Write-Host ""

# Check if scripts folder exists
if (-not (Test-Path ".\scripts\start-server.ps1")) {
    Write-Host "Error: Scripts folder not found!" -ForegroundColor Red
    Write-Host "Please ensure you're running this from the LILAC project root directory." -ForegroundColor Yellow
    exit 1
}

# Launch the main server script
Write-Host "Launching LILAC server..." -ForegroundColor Cyan
& ".\scripts\start-server.ps1"