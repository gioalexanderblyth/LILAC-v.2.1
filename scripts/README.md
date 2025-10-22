# LILAC PowerShell Scripts

This folder contains all the PowerShell scripts for setting up and running the LILAC application.

## Scripts Overview

### `start-server.ps1`
**Main server launcher** - Provides options to start the server with either Apache (recommended for remote access) or PHP built-in server (localhost only).

### `start-apache.ps1`
**Apache server starter** - Specifically designed to start the LILAC application with Apache, including remote access configuration and IP detection.

### `setup-apache.ps1`
**Apache configuration script** - Automatically configures Apache for the LILAC application, including:
- Finding Apache installation
- Copying configuration files
- Setting up remote access
- Configuring document root

### `install-tesseract.ps1`
**Tesseract OCR installer** - Helps install Tesseract OCR for image text extraction functionality in the awards system.

## Usage

### Quick Start
From the project root directory, run:
```powershell
.\start.ps1
```

### Individual Scripts
You can also run individual scripts directly from the project root:
```powershell
.\scripts\start-server.ps1
.\scripts\setup-apache.ps1
.\scripts\install-tesseract.ps1
```

## Requirements

- **Apache**: For remote access functionality (recommended)
- **PHP**: For the built-in server option
- **PowerShell**: All scripts require PowerShell 5.0 or later
- **Administrator privileges**: Recommended for Apache setup and Tesseract installation

## Remote Access

When using Apache, the scripts will automatically detect your IP address and provide URLs for remote access:
- `http://YOUR_IP/pages/dashboard.html`
- `http://YOUR_IP:8000/pages/dashboard.html`

## Troubleshooting

1. **Scripts not found**: Ensure you're running from the LILAC project root directory
2. **Apache not starting**: Check if Apache is installed and the service is running
3. **Remote access not working**: Verify Windows Firewall settings and Apache configuration
4. **Tesseract not working**: Run the install script and restart Apache

## File Structure

```
LILAC-v.2.1/
├── start.ps1                 # Main launcher (run this)
├── scripts/
│   ├── start-server.ps1      # Server options
│   ├── start-apache.ps1      # Apache starter
│   ├── setup-apache.ps1      # Apache configurator
│   ├── install-tesseract.ps1 # OCR installer
│   └── README.md            # This file
└── [other project files...]
```
