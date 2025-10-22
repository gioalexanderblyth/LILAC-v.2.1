# LILAC Events System - Apache Server Starter (Remote Access Enabled)
Write-Host "Starting LILAC Events System with Apache (Remote Access)..." -ForegroundColor Green
Write-Host ""

# Configuration
$PROJECT_PATH = (Get-Location).Parent
$APACHE_CONFIG = Join-Path $PROJECT_PATH "lilac-apache.conf"

Write-Host "Project Path: $PROJECT_PATH" -ForegroundColor Cyan
Write-Host "Apache Config: $APACHE_CONFIG" -ForegroundColor Cyan
Write-Host ""

# Get IP address for remote access information
try {
    $YourIP = (Invoke-RestMethod -Uri "https://api.ipify.org" -TimeoutSec 5)
    Write-Host "Your external IP address: $YourIP" -ForegroundColor Yellow
} catch {
    $networkAdapters = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"}
    if ($networkAdapters) {
        $YourIP = $networkAdapters[0].IPAddress
        Write-Host "Your local IP address: $YourIP" -ForegroundColor Yellow
    } else {
        $YourIP = "YOUR_IP_ADDRESS"
        Write-Host "Could not detect IP address automatically" -ForegroundColor Yellow
    }
}

# Check if Apache is installed and running
$apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
if ($apacheService) {
    Write-Host "Found Apache service: $($apacheService.Name)" -ForegroundColor Yellow
    
    if ($apacheService.Status -eq "Running") {
        Write-Host "Apache is already running!" -ForegroundColor Green
    } else {
        Write-Host "Starting Apache service..." -ForegroundColor Yellow
        Start-Service $apacheService.Name
        Start-Sleep -Seconds 3
    }
} else {
    Write-Host "Apache service not found. Please ensure Apache is installed." -ForegroundColor Red
    Write-Host ""
    Write-Host "Installation options:" -ForegroundColor Yellow
    Write-Host "1. XAMPP: Download from https://www.apachefriends.org/" -ForegroundColor White
    Write-Host "2. WAMP: Download from https://www.wampserver.com/" -ForegroundColor White
    Write-Host "3. Standalone Apache: Download from https://httpd.apache.org/" -ForegroundColor White
    exit 1
}

Write-Host ""
Write-Host "🌐 Remote Access Information:" -ForegroundColor Green
Write-Host "============================" -ForegroundColor Green
Write-Host "Your colleague can access the system remotely at:" -ForegroundColor Yellow
Write-Host "  http://$YourIP/pages/dashboard.html" -ForegroundColor Cyan
Write-Host "  or" -ForegroundColor White
Write-Host "  http://$YourIP:8000/pages/dashboard.html" -ForegroundColor Cyan
Write-Host ""
Write-Host "Local access URLs:" -ForegroundColor Yellow
Write-Host "  http://localhost/pages/dashboard.html" -ForegroundColor Cyan
Write-Host "  http://localhost:8000/pages/dashboard.html" -ForegroundColor Cyan
Write-Host ""

# Instructions for configuration
Write-Host "📋 Configuration Steps:" -ForegroundColor Yellow
Write-Host "1. Run setup script: .\scripts\setup-apache.ps1" -ForegroundColor White
Write-Host "2. Or manually copy lilac-apache.conf to Apache conf/extra/" -ForegroundColor White
Write-Host "3. Add 'Include conf/extra/lilac.conf' to httpd.conf" -ForegroundColor White
Write-Host "4. Restart Apache service" -ForegroundColor White
Write-Host ""

# Try to find Apache configuration directory
$possiblePaths = @(
    "C:\Program Files\Apache Software Foundation\Apache*\conf",
    "C:\xampp\apache\conf",
    "C:\wamp\bin\apache\apache*\conf",
    "C:\Apache24\conf",
    "C:\Apache\conf"
)

$apacheConfDir = $null
foreach ($path in $possiblePaths) {
    $expandedPath = Get-ChildItem $path -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
    if ($expandedPath -and (Test-Path $expandedPath)) {
        $apacheConfDir = $expandedPath
        break
    }
}

if ($apacheConfDir) {
    Write-Host "Found Apache config directory: $apacheConfDir" -ForegroundColor Green
    Write-Host ""
    $configure = Read-Host "Do you want to configure Apache automatically now? (y/N)"
    if ($configure -eq "y" -or $configure -eq "Y") {
        Write-Host "Running setup script..." -ForegroundColor Yellow
        & ".\scripts\setup-apache.ps1" -ApacheConfDir $apacheConfDir -YourIP $YourIP
        exit 0
    }
} else {
    Write-Host "Could not locate Apache configuration directory automatically." -ForegroundColor Yellow
    Write-Host "Please run: .\scripts\setup-apache.ps1" -ForegroundColor White
}

Write-Host ""
Write-Host "Opening browser in 3 seconds..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

# Try to open the application - check both ports
try {
    Start-Process "http://localhost/pages/dashboard.html"
} catch {
    try {
        Start-Process "http://localhost:8000/pages/dashboard.html"
    } catch {
        Write-Host "Could not open browser automatically." -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "🚀 LILAC is ready for remote access!" -ForegroundColor Green
Write-Host "Share this URL with your colleague: http://$YourIP/pages/dashboard.html" -ForegroundColor Cyan
Write-Host ""
Write-Host "⚠️  Firewall Note: If your colleague cannot connect," -ForegroundColor Yellow
Write-Host "   check Windows Firewall settings for Apache/HTTP traffic." -ForegroundColor White
