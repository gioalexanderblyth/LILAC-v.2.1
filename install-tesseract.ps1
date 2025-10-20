# Tesseract OCR Installation Helper Script
Write-Host "Tesseract OCR Installation Helper" -ForegroundColor Green
Write-Host "=================================" -ForegroundColor Green
Write-Host ""

# Check if we're running as administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")

if (-not $isAdmin) {
    Write-Host "⚠️  Warning: This script should be run as Administrator for best results" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host "This script will help you install Tesseract OCR for image text extraction." -ForegroundColor Cyan
Write-Host ""

# Check if Tesseract is already installed
$tesseractPaths = @(
    "C:\Program Files\Tesseract-OCR\tesseract.exe",
    "C:\Program Files (x86)\Tesseract-OCR\tesseract.exe"
)

$tesseractInstalled = $false
foreach ($path in $tesseractPaths) {
    if (Test-Path $path) {
        Write-Host "✅ Tesseract already found at: $path" -ForegroundColor Green
        $tesseractInstalled = $true
        break
    }
}

if ($tesseractInstalled) {
    Write-Host ""
    Write-Host "Tesseract appears to be already installed!" -ForegroundColor Green
    Write-Host "You may need to restart your Apache server to make it available." -ForegroundColor Yellow
    
    $restart = Read-Host "Do you want to restart Apache service now? (y/N)"
    if ($restart -eq "y" -or $restart -eq "Y") {
        try {
            $apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
            if ($apacheService) {
                Write-Host "Restarting Apache..." -ForegroundColor Yellow
                Restart-Service $apacheService.Name
                Write-Host "Apache restarted successfully!" -ForegroundColor Green
            } else {
                Write-Host "Apache service not found. Please restart it manually." -ForegroundColor Yellow
            }
        } catch {
            Write-Host "Failed to restart Apache: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
    exit 0
}

Write-Host "Tesseract OCR is not installed. Follow these steps:" -ForegroundColor Yellow
Write-Host ""

Write-Host "1. Download the installer from:" -ForegroundColor White
Write-Host "   https://github.com/UB-Mannheim/tesseract/wiki" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Or direct download link:" -ForegroundColor White
Write-Host "   https://github.com/UB-Mannheim/tesseract/releases/latest" -ForegroundColor Cyan
Write-Host ""

# Check if installer exists in current directory
if (Test-Path "tesseract-installer.exe") {
    Write-Host "✅ Found tesseract-installer.exe in current directory!" -ForegroundColor Green
    Write-Host ""
    $install = Read-Host "Do you want to run the installer now? (y/N)"
    
    if ($install -eq "y" -or $install -eq "Y") {
        Write-Host "Starting Tesseract installer..." -ForegroundColor Yellow
        Write-Host "IMPORTANT: Make sure to select 'Add to PATH' during installation!" -ForegroundColor Red
        Write-Host ""
        
        try {
            Start-Process -FilePath ".\tesseract-installer.exe" -Wait
            Write-Host ""
            Write-Host "Installation completed! Testing Tesseract..." -ForegroundColor Green
            
            # Test if installation was successful
            Start-Sleep -Seconds 2
            $testResult = & "C:\Program Files\Tesseract-OCR\tesseract.exe" --version 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "✅ Tesseract installation successful!" -ForegroundColor Green
                Write-Host "Version: $($testResult[0])" -ForegroundColor Cyan
                
                Write-Host ""
                $restart = Read-Host "Do you want to restart Apache service to make Tesseract available? (y/N)"
                if ($restart -eq "y" -or $restart -eq "Y") {
                    try {
                        $apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
                        if ($apacheService) {
                            Restart-Service $apacheService.Name
                            Write-Host "Apache restarted successfully!" -ForegroundColor Green
                        }
                    } catch {
                        Write-Host "Failed to restart Apache: $($_.Exception.Message)" -ForegroundColor Yellow
                    }
                }
            } else {
                Write-Host "⚠️  Installation may not have completed successfully." -ForegroundColor Yellow
                Write-Host "You may need to restart your computer or manually add Tesseract to PATH." -ForegroundColor Yellow
            }
        } catch {
            Write-Host "Failed to run installer: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
} else {
    Write-Host "📥 Download Instructions:" -ForegroundColor Yellow
    Write-Host "   1. Go to: https://github.com/UB-Mannheim/tesseract/wiki" -ForegroundColor Cyan
    Write-Host "   2. Download the Windows installer (64-bit version)" -ForegroundColor White
    Write-Host "   3. Save the installer in this directory: $(Get-Location)" -ForegroundColor White
    Write-Host "   4. Rename it to 'tesseract-installer.exe'" -ForegroundColor White
    Write-Host "   5. Run this script again" -ForegroundColor White
    Write-Host ""
    
    $download = Read-Host "Would you like to open the download page in your browser? (y/N)"
    if ($download -eq "y" -or $download -eq "Y") {
        Start-Process "https://github.com/UB-Mannheim/tesseract/releases/latest"
    }
}

Write-Host ""
Write-Host "After installation:" -ForegroundColor Green
Write-Host "1. Restart your Apache server" -ForegroundColor White
Write-Host "2. Try uploading an image again in your LILAC application" -ForegroundColor White
Write-Host "3. The OCR should now work for image text extraction!" -ForegroundColor White
