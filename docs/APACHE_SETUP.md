# LILAC Apache Setup Guide

This guide will help you configure Apache web server to run the LILAC application instead of the PHP built-in server.

## Prerequisites

- Apache web server installed (XAMPP, WAMP, or standalone)
- PHP with Apache module enabled
- All LILAC application files in place

## Quick Setup

### Option 1: Automated Setup (Recommended)

1. **Run the setup script:**
   ```powershell
   .\setup-apache.ps1
   ```

2. **Restart Apache** when prompted

3. **Access your application:**
   - http://localhost/dashboard.html
   - or http://localhost:8000/dashboard.html

### Option 2: Manual Setup

1. **Copy configuration file:**
   ```
   Copy lilac-apache.conf to your Apache conf/extra/ directory
   ```

2. **Edit the configuration:**
   - Update the DocumentRoot path in `lilac-apache.conf` to match your project location:
   ```apache
   DocumentRoot "C:/Users/Administrator/Documents/GitHub/LILAC-v.2.1"
   ```

3. **Include in httpd.conf:**
   Add this line to your main `httpd.conf` file:
   ```apache
   Include conf/extra/lilac.conf
   ```

4. **Restart Apache service**

## Configuration Details

### Virtual Hosts

The configuration sets up two virtual hosts:

- **Port 80:** Main application (http://localhost/)
- **Port 8000:** Alternative port (http://localhost:8000/) - matches original PHP server

### Key Features

- **PHP Processing:** Automatic handling of .php files
- **CORS Headers:** Enabled for API calls
- **File Upload Limits:** 10MB max file size
- **Security Headers:** XSS protection, content type sniffing prevention
- **Directory Protection:** Database and logs directories are protected
- **Error Logging:** Separate log files for LILAC

### File Structure

```
LILAC-v.2.1/
├── .htaccess              # Apache rules for the directory
├── lilac-apache.conf      # Virtual host configuration
├── setup-apache.ps1       # Automated setup script
├── start-apache.ps1       # Manual Apache start script
└── start-server.ps1       # Updated with Apache option
```

## Troubleshooting

### Apache Not Starting

1. **Check Apache service status:**
   ```powershell
   Get-Service -Name "*apache*"
   ```

2. **Check configuration syntax:**
   ```powershell
   # Navigate to Apache bin directory
   cd "C:\Program Files\Apache Software Foundation\Apache*\bin"
   httpd -t
   ```

3. **Check error logs:**
   - Look in `logs/error.log` in your Apache directory
   - Check `logs/lilac_error.log` in your project directory

### PHP Issues

1. **Verify PHP module is loaded:**
   - Check `httpd.conf` for: `LoadModule php_module "path/to/php_module"`

2. **Test PHP:**
   - Create a test file `phpinfo.php` with `<?php phpinfo(); ?>`
   - Visit http://localhost/phpinfo.php

### File Permissions

1. **Upload directory:**
   ```powershell
   # Ensure uploads directory is writable
   icacls uploads /grant "Everyone:F"
   ```

2. **Logs directory:**
   ```powershell
   # Ensure logs directory is writable
   icacls logs /grant "Everyone:F"
   ```

## Benefits of Using Apache

1. **Performance:** Better handling of concurrent requests
2. **Security:** More robust security features
3. **Production Ready:** Suitable for production deployment
4. **Modules:** Access to Apache modules (mod_rewrite, mod_headers, etc.)
5. **Logging:** Better logging and monitoring capabilities
6. **SSL/TLS:** Easy HTTPS setup
7. **Virtual Hosts:** Multiple applications on one server

## Switching Back to PHP Server

If you need to switch back to the PHP built-in server:

```powershell
.\start-server.ps1
# Choose option 2
```

Or run directly:
```powershell
php -S localhost:8000 -t .
```

## Port Configuration

By default, the setup uses:
- **Port 80:** Main application (requires admin privileges)
- **Port 8000:** Alternative port (matches original setup)

To change ports, edit `lilac-apache.conf` and update the `<VirtualHost>` directives.

## Next Steps

After successful setup:

1. **Test all functionality:** Upload files, API calls, database operations
2. **Configure SSL:** For production use with HTTPS
3. **Set up monitoring:** Log monitoring and performance tracking
4. **Backup configuration:** Keep backup of working configuration files

## Support

For issues specific to this setup:
1. Check Apache error logs
2. Verify PHP configuration
3. Ensure file permissions are correct
4. Test with the PHP built-in server first to isolate issues
