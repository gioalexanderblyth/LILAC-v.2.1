# LILAC Events System Server Starter
Write-Host "Starting LILAC Events System Server..." -ForegroundColor Green
Write-Host ""
Write-Host "Make sure you have PHP installed and in your PATH." -ForegroundColor Yellow
Write-Host ""

# Set the port
$PORT = 8000
$DOCROOT = Get-Location

Write-Host "Starting PHP server on localhost:$PORT" -ForegroundColor Cyan
Write-Host "Document root: $DOCROOT" -ForegroundColor Cyan
Write-Host ""
Write-Host "Opening browser in 3 seconds..." -ForegroundColor Yellow

# Wait 3 seconds then open browser
Start-Sleep -Seconds 3
Start-Process "http://localhost:$PORT/dashboard.html"

Write-Host "Starting server... Press Ctrl+C to stop" -ForegroundColor Green
Write-Host ""

# Start the PHP server
php -S localhost:$PORT -t "$DOCROOT"
