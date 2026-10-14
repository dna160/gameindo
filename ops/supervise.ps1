<#
  GameIndo newsroom supervisor.

  Keeps the newsroom server alive forever. That single process serves the
  dashboard on /, the REST API on /api, and runs the node-cron pipeline worker,
  so "the cron always works" reduces to "this process never stays dead".

  Two failure modes are covered:
    - the process exits  -> detected via the process handle, restarted (backoff)
    - the process wedges -> detected via an HTTP probe, killed, restarted
      (this also covers the Prisma pool going stale after the database
       restarts, which the server cannot recover from on its own)

  PostgreSQL is NOT managed here. This machine runs PostgreSQL 16 as the
  Windows service "postgresql-x64-16" with StartType=Automatic, holding both
  the popshck and gameindo databases. It comes up at boot on its own, and
  starting a second cluster on 5432 would fight it for the port. We only wait
  for it to answer, and nudge the service if we happen to have rights.

  Run by the "GameIndo Newsroom" scheduled task at logon. Never exits on its
  own; stop it by ending the task.

  ASCII only, deliberately: Windows PowerShell 5.1 reads a BOM-less .ps1 as
  ANSI, and non-ASCII punctuation in a BOM-less file is a parse error.
#>

$ErrorActionPreference = 'Continue'

$Root    = Split-Path -Parent $PSScriptRoot   # repo root = parent of ops/
# Node/PostgreSQL client location. Override with GAMEINDO_RUNTIME when the
# portable runtime lives elsewhere; otherwise fall back to PATH.
$Runtime = if ($env:GAMEINDO_RUNTIME) { $env:GAMEINDO_RUNTIME } else { Join-Path (Join-Path $env:SystemDrive 'Claude Home') 'runtime' }
$NodeExe = Join-Path $Runtime 'node\node.exe'
$PgReady = Join-Path $Runtime 'pgsql\bin\pg_isready.exe'
$LogDir  = Join-Path $Root 'ops\logs'
$Port    = 3000

$PgService        = 'postgresql-x64-16'
$LmsExe           = Join-Path (Join-Path $env:USERPROFILE '.lmstudio') (Join-Path 'bin' 'lms.exe')
$LlmModel         = 'google/gemma-4-e4b'
$LlmContextLength = 16384
$LlmMinHeadroomGB = 8      # below this, a ~5.9 GB load will fail outright
$LlmRetryMinutes  = 10
# Liveness only. The dashboard's index.html is served from memory by
# express.static and touches neither Prisma nor the pipeline, so a slow
# database cannot make a healthy server look dead.
#
# This used to probe /api/pipeline/status, which hits Prisma. When a pipeline
# run starts, the worker thread saturates CPU and the connection pool, that
# endpoint stops answering within ~2 minutes, and the supervisor killed the
# server mid-run. It destroyed the 2026-08-28 11:00 run (0 articles) -- the
# watchdog was the outage.
$HealthUrl        = "http://127.0.0.1:$Port/"
$CheckIntervalSec = 15
$MaxSickChecks    = 8     # ~2min unresponsive before we force a restart
$MaxServerRamMB   = 3072  # restart the server before it can starve PostgreSQL
$MinFreeSystemMB  = 600   # below this, the box is about to OOM something
$BackoffMax       = 60

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# Only ever one supervisor. Two of them fight over the same port: each starts a
# server, the loser dies on EADDRINUSE, and both restart forever. A mutex is
# released automatically when the holder dies, so a killed supervisor does not
# leave a stale lock behind the way a PID file would.
$script:SupervisorMutex = New-Object System.Threading.Mutex($false, 'GameIndoNewsroomSupervisor')
if (-not $script:SupervisorMutex.WaitOne(0)) {
    Write-Output "another supervisor already holds the lock -- exiting"
    exit 0
}

function Write-Log {
    param([string]$Message)
    $line = "{0}  {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Write-Output $line
    Add-Content -Path (Join-Path $LogDir 'supervisor.log') -Value $line -Encoding utf8
}

function Remove-OldLogs {
    Get-ChildItem $LogDir -Filter 'server-*.log' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending |
        Select-Object -Skip 40 |
        Remove-Item -Force -ErrorAction SilentlyContinue
}

# == PostgreSQL (system service; we only wait on it) ==========================
function Test-PostgresUp {
    if (-not (Test-Path $PgReady)) { return $true }   # no client: let the app decide
    & $PgReady -h 127.0.0.1 -p 5432 -q 2>$null | Out-Null
    return ($LASTEXITCODE -eq 0)
}

function Wait-Postgres {
    if (Test-PostgresUp) { return $true }
    Write-Log "[pg] 127.0.0.1:5432 not answering -- waiting for service $PgService"
    try {
        $svc = Get-Service -Name $PgService -ErrorAction Stop
        if ($svc.Status -ne 'Running') {
            Start-Service -Name $PgService -ErrorAction Stop
            Write-Log "[pg] started service $PgService"
        }
    } catch {
        Write-Log "[pg] cannot control the service (needs admin): $($_.Exception.Message)"
    }
    for ($i = 0; $i -lt 60; $i++) {
        if (Test-PostgresUp) { Write-Log '[pg] up'; return $true }
        Start-Sleep -Seconds 5
    }
    Write-Log '[pg] still down after 5 minutes -- starting the server anyway'
    return $false
}

# == Local LLM ===============================================================
# A reboot leaves LM Studio with no model resident, and the pipeline cannot
# write a single article without one. That cost three separate runs before this
# existed, each time needing a manual "lms load". The supervisor now does it.
#
# llama-server is the process LM Studio spawns per loaded model, so its presence
# is the cheapest reliable "is a model resident" test -- /v1/models lists models
# that are merely AVAILABLE and reports healthy when nothing is loaded.
$script:LastLlmAttempt = [datetime]::MinValue

function Test-ModelLoaded {
    return (@(Get-Process llama-server -ErrorAction SilentlyContinue).Count -gt 0)
}

function Get-CommitHeadroomGB {
    # FreeVirtualMemory IS the commit headroom (KB). Using CIM rather than
    # a performance-counter path keeps this free of path separators, which
    # repeatedly got mangled on the way into this file.
    try {
        $os = Get-CimInstance Win32_OperatingSystem -ErrorAction Stop
        return [math]::Round($os.FreeVirtualMemory / 1MB, 2)
    } catch { return 999 }
}

function Ensure-Model {
    if (Test-ModelLoaded) { return }
    if (((Get-Date) - $script:LastLlmAttempt).TotalMinutes -lt $LlmRetryMinutes) { return }
    $script:LastLlmAttempt = Get-Date

    if (-not (Test-Path $LmsExe)) { Write-Log '[llm] lms CLI not found -- cannot auto-load'; return }

    # Do not even try when the box has no commit headroom: that is the known
    # "unable to allocate CUDA0 buffer" condition, and it needs a reboot, not
    # a retry loop.
    $head = Get-CommitHeadroomGB
    if ($head -lt $LlmMinHeadroomGB) {
        Write-Log "[llm] only ${head}GB commit headroom (need ${LlmMinHeadroomGB}GB) -- skipping load; the box needs a reboot"
        return
    }

    Write-Log "[llm] no model resident -- loading $LlmModel (context $LlmContextLength)"
    try {
        & $LmsExe load $LlmModel --context-length $LlmContextLength --gpu max --yes 2>&1 | Out-Null
    } catch {
        Write-Log "[llm] load threw: $($_.Exception.Message)"
        return
    }
    if (Test-ModelLoaded) { Write-Log '[llm] model loaded' }
    else { Write-Log '[llm] load did not take -- will retry in ' + $LlmRetryMinutes + ' min' }
}

# == Newsroom server =========================================================
function Get-PortOwner {
    try {
        $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction Stop |
                Select-Object -First 1
        if ($null -eq $conn) { return $null }
        return Get-Process -Id $conn.OwningProcess -ErrorAction Stop
    } catch {
        return $null
    }
}

# A supervisor that is killed leaves its server child running and holding the
# port. The replacement supervisor must take that orphan over rather than try
# to bind on top of it, or it crash-loops on EADDRINUSE forever.
function Resolve-ExistingServer {
    $owner = Get-PortOwner
    if ($null -eq $owner) { return $null }
    $isOurs = $false
    try { $isOurs = ($owner.Path -eq $NodeExe) } catch { }
    if ($isOurs) {
        Write-Log "[server] adopting orphaned server already on port $Port (pid=$($owner.Id))"
        return $owner
    }
    Write-Log "[server] port $Port held by $($owner.ProcessName) (pid=$($owner.Id)) -- evicting"
    try { Stop-Process -Id $owner.Id -Force -ErrorAction Stop } catch {
        Write-Log "[server] could not evict pid=$($owner.Id): $($_.Exception.Message)"
    }
    Start-Sleep -Seconds 2
    return $null
}

function Start-Server {
    $adopted = Resolve-ExistingServer
    if ($null -ne $adopted) { return $adopted }

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $out   = Join-Path $LogDir "server-$stamp.out.log"
    $err   = Join-Path $LogDir "server-$stamp.err.log"
    Remove-OldLogs
    $p = Start-Process -FilePath $NodeExe `
                       -ArgumentList 'dist\server.js' `
                       -WorkingDirectory (Join-Path $Root 'backend') `
                       -RedirectStandardOutput $out `
                       -RedirectStandardError  $err `
                       -WindowStyle Hidden -PassThru
    Write-Log "[server] started pid=$($p.Id) log=server-$stamp.out.log"
    return $p
}

function Get-ServerRamMB {
    param($Proc)
    try { return [math]::Round((Get-Process -Id $Proc.Id -ErrorAction Stop).WorkingSet64 / 1MB) } catch { return 0 }
}

function Get-FreeSystemMB {
    try { return [math]::Round((Get-CimInstance Win32_OperatingSystem -ErrorAction Stop).FreePhysicalMemory / 1KB) } catch { return 99999 }
}

function Test-ServerHealthy {
    try {
        $r = Invoke-WebRequest -Uri $HealthUrl -TimeoutSec 25 -UseBasicParsing
        return ($r.StatusCode -eq 200)
    } catch {
        return $false
    }
}

function Stop-Server {
    param($Proc)
    if ($null -eq $Proc) { return }
    try {
        if (-not $Proc.HasExited) {
            Stop-Process -Id $Proc.Id -Force -ErrorAction SilentlyContinue
            Write-Log "[server] killed pid=$($Proc.Id)"
        }
    } catch { }
}

# == Main loop ===============================================================
Write-Log '=== supervisor starting ==='
Wait-Postgres | Out-Null

$proc      = $null
$backoff   = 5
$sick      = 0
$startedAt = Get-Date

while ($true) {
  try {
    Ensure-Model
    $needsStart = $false

    if ($null -eq $proc) {
        $needsStart = $true
    } elseif ($proc.HasExited) {
        Write-Log "[server] exited (code $($proc.ExitCode)) -- restarting in ${backoff}s"
        Start-Sleep -Seconds $backoff
        $backoff = [Math]::Min($backoff * 2, $BackoffMax)
        # A startup crash is usually the database being unreachable.
        Wait-Postgres | Out-Null
        $needsStart = $true
    } else {
        $ram = Get-ServerRamMB $proc
        $free = Get-FreeSystemMB
        if ($ram -gt $MaxServerRamMB) {
            # The 2026-08-20 outage: our own process ate the box and PostgreSQL
            # was what died of OOM -- and that service needs admin to restart,
            # so we must never let it get that far again.
            Write-Log "[server] using ${ram}MB (cap ${MaxServerRamMB}MB) -- restarting before it starves PostgreSQL"
            Stop-Server $proc
            $proc = $null
            $needsStart = $true
        } elseif (Test-ServerHealthy) {
            if ($sick -gt 0) { Write-Log "[server] healthy again" }
            $sick = 0
            if ($free -lt $MinFreeSystemMB) { Write-Log "[warn] only ${free}MB system RAM free (server ${ram}MB)" }
            # Stable for 2 minutes -> forget the crash-loop backoff.
            if (((Get-Date) - $startedAt) -gt [TimeSpan]::FromMinutes(2)) { $backoff = 5 }
        } else {
            $sick++
            Write-Log "[server] health probe failed ($sick/$MaxSickChecks)"
            if ($sick -ge $MaxSickChecks) {
                Write-Log '[server] wedged -- forcing restart'
                Stop-Server $proc
                Wait-Postgres | Out-Null
                $sick = 0
                $needsStart = $true
            }
        }
    }

    if ($needsStart) {
        $proc      = Start-Server
        $startedAt = Get-Date
        $sick      = 0
        Start-Sleep -Seconds 10   # grace period before the first probe
    }

    Start-Sleep -Seconds $CheckIntervalSec
  } catch {
    # A terminating error here used to kill the supervisor outright, leaving
    # nothing watching the server. Swallow it, log it, keep looping.
    Write-Log "[supervisor] recovered from error: $($_.Exception.Message)"
    Start-Sleep -Seconds $CheckIntervalSec
  }
}
