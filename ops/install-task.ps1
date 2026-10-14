<#
  Registers "GameIndo Newsroom" as a per-user scheduled task that launches the
  supervisor at logon and never times out.

  No administrator rights are needed, which is also the one limitation: a
  per-user task can only start once this user logs on. It survives reboots,
  sign-outs and crashes, but not a machine sitting at the lock screen after a
  restart with nobody signed in.

  Re-running this is safe — it replaces the existing registration.
#>

$TaskName = 'GameIndo Newsroom'
$Script   = 'C:\Claude Home\gameindo-newsroom\ops\supervise.ps1'

$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$Script`""

# At logon, then re-fire every 5 minutes forever. The repetition is the real
# watchdog: RestartCount only covers the task reporting failure, and in the
# 2026-08-20 outage the supervisor died without it ever restarting. With
# MultipleInstances=IgnoreNew a repeat tick is a no-op while it is alive, and
# resurrects it within 5 minutes if it is not.
$logonTrigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME

# A second, always-armed trigger. Repetition hung off the logon trigger does
# NOT arm until that trigger next fires, so on an already-logged-on session it
# watches nothing -- verified the hard way: the supervisor was killed and
# nothing resurrected it for 9 minutes. A -Once trigger dated in the past is
# active immediately and repeats forever.
$repeatTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(-1)
# Build the repetition directly: an omitted Duration means "repeat forever".
# Do NOT use [TimeSpan]::MaxValue -- it serialises to P99999999DT23H59M59S,
# which Task Scheduler rejects as out of range, and Register-ScheduledTask
# then fails *after* the Unregister above has already removed the task.
$repeatTrigger.Repetition = New-CimInstance -ClassName MSFT_TaskRepetitionPattern `
    -Namespace Root/Microsoft/Windows/TaskScheduler -ClientOnly `
    -Property @{ Interval = "PT5M"; StopAtDurationEnd = $false }

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -DontStopOnIdleEnd `
    -StartWhenAvailable `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -MultipleInstances IgnoreNew

$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited

Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue

Register-ScheduledTask -TaskName $TaskName `
    -Action $action -Trigger $logonTrigger, $repeatTrigger -Settings $settings -Principal $principal `
    -Description 'Keeps the GameIndo newsroom server (dashboard + API + pipeline cron) running on port 3000.' | Out-Null

"Registered task: $TaskName"
(Get-ScheduledTask -TaskName $TaskName).Settings |
    Select-Object ExecutionTimeLimit, RestartCount, RestartInterval, StartWhenAvailable |
    Format-List
