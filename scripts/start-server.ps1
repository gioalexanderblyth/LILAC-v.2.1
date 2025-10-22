# LILAC Events System Server Starter
Write-Host "Starting LILAC Events System Server..." -ForegroundColor Green
Write-Host ""

# Check if user wants to use Apache or PHP built-in server
Write-Host "Choose server option:" -ForegroundColor Yellow
Write-Host "1. Apache (recommended for remote access by colleagues)" -ForegroundColor White
Write-Host "2. PHP built-in server (localhost only)" -ForegroundColor White
Write-Host ""
$choice = Read-Host "Enter choice (1 or 2)"

if ($choice -eq "1") {
    Write-Host "Starting with Apache (supports remote access)..." -ForegroundColor Cyan
    
    # Check if Apache configuration setup exists
    if (Test-Path ".\scripts\setup-apache.ps1") {
        Write-Host "Running Apache setup..." -ForegroundColor Yellow
        & ".\scripts\setup-apache.ps1"
    } else {
        Write-Host "Running Apache startup..." -ForegroundColor Yellow
        & ".\scripts\start-apache.ps1"
    }
    exit 0
}

if ($choice -eq "2" -or -not $choice) {
    Write-Host "Starting PHP built-in server (localhost only)..." -ForegroundColor Yellow
    Write-Host "⚠️  Note: PHP server only accepts local connections" -ForegroundColor Red
    Write-Host "   Colleagues will not be able to access remotely." -ForegroundColor Red
    Write-Host ""
    Write-Host "Make sure you have PHP installed and in your PATH." -ForegroundColor Yellow
    Write-Host ""

    # Set the port - but make it accessible to remote users
    $PORT = 8000
    $DOCROOT = (Get-Location).Parent
    
    Write-Host "Starting PHP server on ALL interfaces:$PORT" -ForegroundColor Cyan
    Write-Host "Document root: $DOCROOT" -ForegroundColor Cyan
    Write-Host "Project will be accessible at: http://localhost:$PORT/LILAC-v.2.1/" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opening browser in 3 seconds..." -ForegroundColor Yellow

    # Wait 3 seconds then open browser
    Start-Sleep -Seconds 3
    Start-Process "http://localhost:$PORT/LILAC-v.2.1/pages/dashboard.html"

    Write-Host "Starting server... Press Ctrl+C to stop" -ForegroundColor Green
    Write-Host "⚠️  For remote access, colleagues should use your IP address instead of localhost" -ForegroundColor Yellow
    Write-Host ""

    # Start the PHP server on all interfaces (0.0.0.0) instead of just localhost
    php -S 0.0.0.0:$PORT -t "$DOCROOT"
}
