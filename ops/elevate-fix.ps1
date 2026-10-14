<#
  Run this ONCE, elevated. It closes the two gaps that need administrator
  rights, and nothing else:

    1. Re-registers the "GameIndo Newsroom" task with LogonType = S4U, i.e.
       "run whether the user is logged on or not". Today the task is
       Interactive, so signing out stops it -- that is why every scheduled run
       on 2026-08-23 was missed (34.5h with the supervisor not running at all).
       S4U needs no stored password.

    2. Sets crash-recovery actions on the postgresql-x64-16 service, so Windows
       restarts it by itself. On 2026-08-20 PostgreSQL died of OOM and the
       supervisor could not restart it (access denied), leaving the site down
       overnight. This is deliberately done with recovery actions rather than
       by rewriting the service ACL -- a malformed SDDL can lock service
       control out entirely, and this achieves the same end more safely.

  Safe to re-run. The existing task is exported first and restored
  automatically if the new registration fails verification.

  ASCII only, saved UTF-8 with BOM -- see the note in supervise.ps1.
#>

$ErrorActionPreference = 'Stop'

$TaskName  = 'GameIndo Newsroom'
$PgService = 'postgresql-x64-16'
$Root      = Split-Path -Parent $PSScriptRoot   # repo root = parent of ops/
$BackupDir = Join-Path $Root 'ops\backup'

# == Must be elevated ========================================================
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
           ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "This script must be run as Administrator." -ForegroundColor Red
    Write-Host "Right-click PowerShell -> Run as administrator, then re-run it."
    exit 1
}

# == Which account should own the task? ======================================
# Reuse the account already on the task, so running this from a different
# admin account does not silently re-point the task at the wrong user.
$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($null -eq $existing) {
    Write-Host "Task '$TaskName' not found. Run ops\install-task.ps1 first." -ForegroundColor Red
    exit 1
}
$targetUser = $existing.Principal.UserId
Write-Host "Task account: $targetUser"
Write-Host "Current logon type: $($existing.Principal.LogonType)"

# == Back up the working task ================================================
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$stamp      = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupFile = Join-Path $BackupDir "task-$stamp.xml"
Export-ScheduledTask -TaskName $TaskName | Set-Content -Path $backupFile -Encoding utf8
Write-Host "Backed up current task -> $backupFile"

function Restore-Backup {
    Write-Host "Restoring the previous task definition..." -ForegroundColor Yellow
    $xml = Get-Content $backupFile -Raw
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Register-ScheduledTask -TaskName $TaskName -Xml $xml -User $targetUser | Out-Null
    Start-ScheduledTask -TaskName $TaskName
    Write-Host "Previous task restored and started."
}

# == 1. Re-register with S4U =================================================
try {
    $action = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$Root\ops\supervise.ps1`""

    $logonTrigger = New-ScheduledTaskTrigger -AtLogOn -User $targetUser

    # Always-armed watchdog. Repetition on the logon trigger does not arm until
    # that trigger next fires, so it must live on its own -Once trigger.
    $repeatTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(-1)
    $repeatTrigger.Repetition = New-CimInstance -ClassName MSFT_TaskRepetitionPattern `
        -Namespace Root/Microsoft/Windows/TaskScheduler -ClientOnly `
        -Property @{ Interval = "PT5M"; StopAtDurationEnd = $false }

    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -DontStopOnIdleEnd `
        -StartWhenAvailable -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
        -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew

    # S4U = run whether logged on or not, without storing a password.
    $principal = New-ScheduledTaskPrincipal -UserId $targetUser -LogonType S4U -RunLevel Limited

    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Register-ScheduledTask -TaskName $TaskName `
        -Action $action -Trigger $logonTrigger, $repeatTrigger `
        -Settings $settings -Principal $principal `
        -Description 'Keeps the GameIndo newsroom server (dashboard + API + pipeline cron) running on port 3000.' | Out-Null

    $new = Get-ScheduledTask -TaskName $TaskName
    if ($new.Principal.LogonType -ne 'S4U') { throw "logon type is $($new.Principal.LogonType), expected S4U" }
    if ($new.Triggers.Count -ne 2)          { throw "expected 2 triggers, got $($new.Triggers.Count)" }
    Write-Host "Task re-registered as S4U (runs whether logged on or not)." -ForegroundColor Green
}
catch {
    Write-Host "S4U registration FAILED: $($_.Exception.Message)" -ForegroundColor Red
    Restore-Backup
    exit 1
}

# == Start it and prove it serves ============================================
Start-ScheduledTask -TaskName $TaskName
Start-Sleep -Seconds 30
$healthy = $false
try {
    $r = Invoke-WebRequest 'http://127.0.0.1:3000/api/pipeline/status' -TimeoutSec 20 -UseBasicParsing
    $healthy = ($r.StatusCode -eq 200)
} catch { }

if (-not $healthy) {
    Write-Host "Task registered but port 3000 is not answering. Rolling back." -ForegroundColor Red
    Restore-Backup
    exit 1
}
Write-Host "Server healthy on port 3000." -ForegroundColor Green

# == 2. PostgreSQL crash recovery ============================================
# reset= 86400 : failure count resets after a day
# restart/60000: wait 60s before each restart attempt, three attempts
& sc.exe failure $PgService reset= 86400 actions= restart/60000/restart/60000/restart/60000 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "PostgreSQL will now be auto-restarted by Windows if it dies." -ForegroundColor Green
} else {
    Write-Host "Could not set recovery actions on $PgService (exit $LASTEXITCODE)." -ForegroundColor Yellow
}
& sc.exe qfailure $PgService

# == Summary =================================================================
$t = Get-ScheduledTask -TaskName $TaskName
Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Cyan
Write-Host "  task            : $($t.State)"
Write-Host "  logon type      : $($t.Principal.LogonType)   (S4U = survives sign-out and reboot)"
Write-Host "  triggers        : $($t.Triggers.Count)"
Write-Host "  execution limit : $($t.Settings.ExecutionTimeLimit)   (PT0S = no timeout)"
Write-Host "  postgres        : $((Get-Service $PgService).Status), StartType $((Get-Service $PgService).StartType)"
Write-Host "  backup          : $backupFile"
Write-Host ""
Write-Host "NOTE: this fixes the SCHEDULER, not the model. LM Studio is a desktop" -ForegroundColor Yellow
Write-Host "app in your interactive session -- if you sign out it stops, and a run" -ForegroundColor Yellow
Write-Host "that fires without it will fail at the first LLM call." -ForegroundColor Yellow
