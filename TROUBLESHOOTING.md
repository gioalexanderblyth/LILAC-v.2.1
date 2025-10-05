# LILAC Events System - Troubleshooting Guide

## Common Issues and Solutions

### 1. "Unexpected token '<'" Error

**Problem:** Console shows error: `SyntaxError: Unexpected token '<', "<?php hea"... is not valid JSON`

**Cause:** PHP server is returning raw PHP code instead of executing it.

**Solution:**
```bash
# Stop any running PHP servers
taskkill /F /IM php.exe

# Start server with correct document root
cd "C:\Users\Admin\Documents\GitHub\LILAC-v.2.1"
php -S localhost:8000 -t .
```

**Alternative:** Use the provided batch file:
```bash
# Double-click start-server.bat
```

### 2. Database Connection Issues

**Problem:** Events not saving to database, falling back to localStorage

**Solutions:**
1. **Check PHP Server:** Make sure server is running on localhost:8000
2. **Check Database:** Verify `database/mou_moa.db` exists
3. **Check Permissions:** Ensure PHP can write to database directory

### 3. Events Not Showing in Completed Events Section

**Problem:** Completed Events section shows placeholder cards

**Solutions:**
1. **Create Past Events:** Add events with dates in the past
2. **Check Date Format:** Use YYYY-MM-DD format (e.g., 2024-12-25)
3. **Refresh Page:** Reload to trigger event loading

### 4. Reminder Notifications Not Working

**Problem:** Reminders not showing up

**Solutions:**
1. **Check Browser Permissions:** Allow notifications in browser settings
2. **Check Console:** Look for JavaScript errors
3. **Verify Reminder Time:** Make sure reminder time is in the future

### 5. Images Not Loading

**Problem:** Event cards showing broken images

**Solutions:**
1. **Check Internet Connection:** Images are loaded from external sources
2. **Check Console:** Look for network errors
3. **Fallback Images:** System will show gradient backgrounds if images fail

## Server Commands

### Start Server (Correct Way)
```bash
php -S localhost:8000 -t .
```

### Start Server (Wrong Way - Will Cause Issues)
```bash
php -S localhost:8000  # Missing -t . flag
```

### Test API
```bash
# Test calendar endpoint
curl http://localhost:8000/api/events.php?action=calendar

# Test with PowerShell
Invoke-WebRequest -Uri "http://localhost:8000/api/events.php?action=calendar" -Method GET
```

## File Structure
```
LILAC-v.2.1/
├── events-activities.html    # Main events page
├── api/
│   └── events.php           # API endpoint
├── database/
│   └── mou_moa.db          # SQLite database
├── database.sql            # Database schema
├── start-server.bat        # Easy server startup
└── test-api.html          # API testing page
```

## Quick Fixes

### Reset Everything
1. Stop PHP server: `taskkill /F /IM php.exe`
2. Clear browser localStorage (F12 → Application → Storage → Clear)
3. Restart server: `php -S localhost:8000 -t .`
4. Refresh page

### Test Database
1. Open: http://localhost:8000/test-api.html
2. Click "Test API" buttons
3. Check console for errors

### Verify Setup
1. Open: http://localhost:8000/events-activities.html
2. Check console for "Server Mode" message
3. Try adding an event
4. Check if it appears in calendar and sections
