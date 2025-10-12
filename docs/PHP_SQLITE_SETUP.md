# PHP SQLite Setup Guide

## Issue: "could not find driver" Error

This error occurs when the PDO SQLite extension is not enabled in your PHP installation.

## Quick Fix (Already Implemented)

The system now includes a **file-based fallback** that will work even without SQLite. You should see mock data in the analytics dashboard.

## Enable SQLite Extension (Recommended)

### Windows (XAMPP/WAMP)

1. **Open `php.ini` file:**
   - XAMPP: `C:\xampp\php\php.ini`
   - WAMP: `C:\wamp\bin\php\php[version]\php.ini`

2. **Find and uncomment these lines:**
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```

3. **Remove semicolon (;) from the beginning:**
   ```ini
   ;extension=pdo_sqlite  ← Remove semicolon
   ;extension=sqlite3     ← Remove semicolon
   
   ; Becomes:
   extension=pdo_sqlite
   extension=sqlite3
   ```

4. **Restart your web server** (Apache/Nginx)

### Windows (Standalone PHP)

1. **Download SQLite DLL files:**
   - `php_pdo_sqlite.dll`
   - `php_sqlite3.dll`

2. **Place in PHP extensions directory:**
   - Usually `C:\php\ext\`

3. **Edit `php.ini`:**
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```

### Linux (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install php-sqlite3 php-pdo-sqlite
sudo systemctl restart apache2
```

### macOS (Homebrew)

```bash
brew install php
# SQLite is usually included by default
```

## Verify Installation

### Method 1: Create Test File

Create `test-sqlite.php`:
```php
<?php
if (extension_loaded('pdo_sqlite')) {
    echo "✅ PDO SQLite is enabled";
} else {
    echo "❌ PDO SQLite is NOT enabled";
}

echo "\n\nAvailable PDO drivers:\n";
foreach (PDO::getAvailableDrivers() as $driver) {
    echo "- " . $driver . "\n";
}
?>
```

### Method 2: Use Built-in Test

Visit: `http://localhost:8080/api/test-db.php`

## Alternative Solutions

### Option 1: Use MySQL/MariaDB

If you have MySQL available, update `api/config.php`:

```php
// Replace SQLite connection with MySQL
$pdo = new PDO('mysql:host=localhost;dbname=lilac_awards', $username, $password);
```

### Option 2: Use File-Based Storage

The system already includes a file-based fallback that works without any database.

## Troubleshooting

### Common Issues:

1. **"could not find driver"**
   - SQLite extension not enabled
   - **Solution:** Enable extensions in php.ini

2. **"Access denied"**
   - Database directory not writable
   - **Solution:** Set proper permissions on `database/` folder

3. **"Database is locked"**
   - Multiple processes accessing database
   - **Solution:** Check for concurrent access

### Check PHP Configuration:

```bash
php -m | grep -i sqlite
php -m | grep -i pdo
```

## Current Status

- ✅ **File-based fallback implemented**
- ✅ **Mock data provided for demo**
- ✅ **Error handling improved**
- ⚠️ **SQLite extension needs to be enabled for full functionality**

## Next Steps

1. **Enable SQLite extension** using instructions above
2. **Restart web server**
3. **Test with** `http://localhost:8080/api/test-db.php`
4. **Verify analytics dashboard** loads without errors

The award analysis functionality will work regardless of database status, but analytics and data persistence require SQLite to be enabled.
