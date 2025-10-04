# Auto-sync PowerShell Script for LILAC Project
# This script watches for file changes and automatically commits and pushes to GitHub

param(
    [string]$Path = ".",
    [int]$DelaySeconds = 5
)

Write-Host "Starting LILAC Auto-Sync Watcher..." -ForegroundColor Green
Write-Host "Watching directory: $Path" -ForegroundColor Yellow
Write-Host "Delay before sync: $DelaySeconds seconds" -ForegroundColor Yellow
Write-Host "Press Ctrl+C to stop" -ForegroundColor Red
Write-Host ""

# Function to perform git operations
function Sync-ToGit {
    param([string]$ChangeDescription)
    
    try {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Changes detected: $ChangeDescription" -ForegroundColor Cyan
        
        # Check if there are any changes
        $status = git status --porcelain
        if (-not $status) {
            Write-Host "No changes to commit." -ForegroundColor Yellow
            return
        }
        
        # Add all changes
        git add . | Out-Null
        
        # Commit with timestamp
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        $commitMessage = "Auto-sync: $timestamp - $ChangeDescription"
        git commit -m $commitMessage | Out-Null
        
        # Push to GitHub
        git push origin main | Out-Null
        
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Successfully synced to GitHub!" -ForegroundColor Green
        
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Error during sync: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Function to debounce file changes
function Start-FileWatcher {
    $lastChange = Get-Date
    $changeTimer = $null
    
    $watcher = New-Object System.IO.FileSystemWatcher
    $watcher.Path = $Path
    $watcher.Filter = "*.*"
    $watcher.IncludeSubdirectories = $true
    $watcher.EnableRaisingEvents = $true
    
    # Exclude .git directory from watching
    $watcher.NotifyFilter = [System.IO.NotifyFilters]::LastWrite -bor [System.IO.NotifyFilters]::FileName
    
    Register-ObjectEvent -InputObject $watcher -EventName "Changed" -Action {
        $lastChange = Get-Date
        if ($changeTimer) {
            $changeTimer.Dispose()
        }
        $changeTimer = New-Object System.Timers.Timer
        $changeTimer.Interval = $DelaySeconds * 1000
        $changeTimer.AutoReset = $false
        $changeTimer.Add_Elapsed({
            $fileName = Split-Path $Event.SourceEventArgs.FullPath -Leaf
            Sync-ToGit "File modified: $fileName"
        })
        $changeTimer.Start()
    } | Out-Null
    
    Register-ObjectEvent -InputObject $watcher -EventName "Created" -Action {
        $lastChange = Get-Date
        if ($changeTimer) {
            $changeTimer.Dispose()
        }
        $changeTimer = New-Object System.Timers.Timer
        $changeTimer.Interval = $DelaySeconds * 1000
        $changeTimer.AutoReset = $false
        $changeTimer.Add_Elapsed({
            $fileName = Split-Path $Event.SourceEventArgs.FullPath -Leaf
            Sync-ToGit "File created: $fileName"
        })
        $changeTimer.Start()
    } | Out-Null
    
    Register-ObjectEvent -InputObject $watcher -EventName "Deleted" -Action {
        $lastChange = Get-Date
        if ($changeTimer) {
            $changeTimer.Dispose()
        }
        $changeTimer = New-Object System.Timers.Timer
        $changeTimer.Interval = $DelaySeconds * 1000
        $changeTimer.AutoReset = $false
        $changeTimer.Add_Elapsed({
            $fileName = Split-Path $Event.SourceEventArgs.FullPath -Leaf
            Sync-ToGit "File deleted: $fileName"
        })
        $changeTimer.Start()
    } | Out-Null
    
    # Keep the script running
    try {
        while ($true) {
            Start-Sleep -Seconds 1
        }
    } finally {
        $watcher.Dispose()
        Get-EventSubscriber | Unregister-Event
    }
}

# Start the file watcher
Start-FileWatcher
