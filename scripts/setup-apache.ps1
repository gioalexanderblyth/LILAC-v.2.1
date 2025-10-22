# LILAC Apache Setup Script
# This script helps configure Apache for the LILAC application with remote access support

param(
    [string]$ApacheConfDir = "",
    [string]$YourIP = "",
    [switch]$SkipBackup = $false,
    [switch]$Force = $false
)

Write-Host "LILAC Apache Setup Script (Remote Access Support)" -ForegroundColor Green
Write-Host "=================================================" -ForegroundColor Green
Write-Host ""

$PROJECT_PATH = (Get-Location).Parent
$CONFIG_FILE = Join-Path $PROJECT_PATH "lilac-apache.conf"
$HTACCESS_FILE = Join-Path $PROJECT_PATH ".htaccess"

Write-Host "Project Path: $PROJECT_PATH" -ForegroundColor Cyan

# Get your IP address for remote access
if (-not $YourIP) {
    try {
        $YourIP = (Invoke-RestMethod -Uri "https://api.ipify.org" -TimeoutSec 5)
        Write-Host "Detected your IP address: $YourIP" -ForegroundColor Yellow
    } catch {
        $networkAdapters = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"}
        if ($networkAdapters) {
            $YourIP = $networkAdapters[0].IPAddress
            Write-Host "Local IP address: $YourIP" -ForegroundColor Yellow
        } else {
            $YourIP = "YOUR_IP_ADDRESS"
            Write-Host "Please enter your IP address manually" -ForegroundColor Yellow
        }
    }
}

# Validate files exist
if (-not (Test-Path $CONFIG_FILE)) {
    Write-Host "Error: Configuration file not found: $CONFIG_FILE" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $HTACCESS_FILE)) {
    Write-Host "Error: .htaccess file not found: $HTACCESS_FILE" -ForegroundColor Red
    exit 1
}

# Find Apache configuration directory
if (-not $ApacheConfDir) {
    Write-Host "Searching for Apache configuration directory..." -ForegroundColor Yellow
    
    $possiblePaths = @(
        "C:\Program Files\Apache Software Foundation\Apache*\conf",
        "C:\xampp\apache\conf",
        "C:\wamp\bin\apache\apache*\conf", 
        "C:\Apache24\conf",
        "C:\Apache\conf"
    )

    foreach ($path in $possiblePaths) {
        $foundPaths = Get-ChildItem $path -ErrorAction SilentlyContinue
        foreach ($foundPath in $foundPaths) {
            if (Test-Path (Join-Path $foundPath.FullName "httpd.conf")) {
                $ApacheConfDir = $foundPath.FullName
                Write-Host "Found Apache config: $ApacheConfDir" -ForegroundColor Green
                break
            }
        }
        if ($ApacheConfDir) { break }
    }
}

if (-not $ApacheConfDir -or -not (Test-Path $ApacheConfDir)) {
    Write-Host "Could not locate Apache configuration directory." -ForegroundColor Red
    Write-Host "Please specify manually with: -ApacheConfDir 'C:\path\to\apache\conf'" -ForegroundColor Yellow
    exit 1
}

$APACHE_CONF_TARGET = Join-Path $ApacheConfDir "extra\lilac.conf"
$HTTPD_CONF = Join-Path $ApacheConfDir "httpd.conf"

# Backup httpd.conf if needed
if (-not $SkipBackup -and (Test-Path $HTTPD_CONF)) {
    $backupPath = "$HTTPD_CONF.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Write-Host "Backing up httpd.conf to: $backupPath" -ForegroundColor Yellow
    Copy-Item $HTTPD_CONF $backupPath
}

# Copy configuration file
Write-Host "Copying LILAC configuration to: $APACHE_CONF_TARGET" -ForegroundColor Yellow

# Ensure the extra directory exists
$extraDir = Split-Path $APACHE_CONF_TARGET -Parent
if (-not (Test-Path $extraDir)) {
    New-Item -ItemType Directory -Path $extraDir -Force | Out-Null
}

# Update the configuration with the correct document root
$configContent = Get-Content $CONFIG_FILE -Raw
$configContent = $configContent -replace 'DocumentRoot ".*"', "DocumentRoot `"$PROJECT_PATH`""
Set-Content $CONFIG_FILE $configContent

Copy-Item $CONFIG_FILE $APACHE_CONF_TARGET -Force

# Update httpd.conf to include our configuration and enable remote access
if (Test-Path $HTTPD_CONF) {
    $httpdContent = Get-Content $HTTPD_CONF -Raw
    
    if ($httpdContent -notmatch "Include.*lilac\.conf") {
        Write-Host "Adding Include directive to httpd.conf..." -ForegroundColor Yellow
        
        $includeLine = "Include conf/extra/lilac.conf"
        Add-Content $HTTPD_CONF "`n# LILAC Application Configuration`n$includeLine`n"
        Write-Host "Added: $includeLine" -ForegroundColor Green
    } else {
        Write-Host "Include directive already exists in httpd.conf" -ForegroundColor Green
    }
    
    # Enable listening on all interfaces if not already configured
    if ($httpdContent -notmatch "Listen 0\.0\.0\.0") {
        Write-Host "Note: You may need to add 'Listen 0.0.0.0:80' to httpd.conf for remote access" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Configuration completed!" -ForegroundColor Green
Write-Host ""
Write-Host "Remote Access Information:" -ForegroundColor Yellow
Write-Host "=========================" -ForegroundColor Yellow
Write-Host "Your colleague can access the system at:" -ForegroundColor White
Write-Host "  http://$YourIP/pages/dashboard.html" -ForegroundColor Cyan
Write-Host "  or" -ForegroundColor White
Write-Host "  http://$YourIP:8000/pages/dashboard.html" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Restart Apache service:" -ForegroundColor White
Write-Host "   net stop Apache2.4 && net start Apache2.4" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Check Windows Firewall if connection fails:" -ForegroundColor White
Write-Host "   - Allow Apache through Windows Firewall" -ForegroundColor Cyan
Write-Host "   - Or temporarily disable firewall for testing" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Test local access first:" -ForegroundColor White
Write-Host "   http://localhost/pages/dashboard.html" -ForegroundColor Cyan
Write-Host ""

# Check if Apache service needs restart
$apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
if ($apacheService) {
    Write-Host "Apache service: $($apacheService.Name) - Status: $($apacheService.Status)" -ForegroundColor Yellow
    
    if ($apacheService.Status -eq "Running") {
        Write-Host ""
        $restart = Read-Host "Do you want to restart Apache now? (y/N)"
        if ($restart -eq "y" -or $restart -eq "Y") {
            Write-Host "Restarting Apache..." -ForegroundColor Yellow
            Restart-Service $apacheService.Name
            Start-Sleep -Seconds 3
            Write-Host "Apache restarted!" -ForegroundColor Green
            Write-Host ""
            Write-Host "Share this URL with your colleague:" -ForegroundColor Yellow
            Write-Host "http://$YourIP/pages/dashboard.html" -ForegroundColor Green
        }
    }
}
