<#
  Bring a fresh clone to a running deployment.

      git clone https://github.com/dna160/gameindo.git
      cd gameindo
      copy .env.example .env     # then fill it in
      powershell -ExecutionPolicy Bypass -File ops\setup.ps1

  Idempotent: safe to re-run after a git pull to rebuild.

  Prerequisites this does NOT install:
    - Node 22 (on PATH, or a portable copy at %GAMEINDO_RUNTIME%\node)
    - PostgreSQL 16 reachable at the DATABASE_URL host
    - An OpenAI-compatible LLM server (LM Studio) at LLM_BASE_URL

  ASCII only, saved UTF-8 with BOM: Windows PowerShell 5.1 reads a BOM-less
  script as ANSI and a stray non-ASCII character is then a parse error.
#>

$ErrorActionPreference = 'Stop'

$Root    = Split-Path -Parent $PSScriptRoot
$Runtime = if ($env:GAMEINDO_RUNTIME) { $env:GAMEINDO_RUNTIME } else { Join-Path (Join-Path $env:SystemDrive 'Claude Home') 'runtime' }

Write-Host "Repo root: $Root"

# == Node ====================================================================
$nodeDir = Join-Path $Runtime 'node'
if (Test-Path (Join-Path $nodeDir 'node.exe')) {
    $env:Path = "$nodeDir;$env:Path"
    Write-Host "Node: portable at $nodeDir"
} elseif (Get-Command node -ErrorAction SilentlyContinue) {
    Write-Host "Node: $((Get-Command node).Source)"
} else {
    throw "Node not found. Install Node 22, or set GAMEINDO_RUNTIME to a folder containing node\node.exe"
}
Write-Host "  version $(node -v)"

# == .env ====================================================================
$envFile = Join-Path $Root '.env'
if (-not (Test-Path $envFile)) {
    throw "No .env at $envFile. Copy .env.example to .env and fill it in first."
}
Write-Host "Config: .env present"

# == Dependencies ============================================================
Write-Host "Installing dependencies (root, backend, frontend)..."
Push-Location $Root
try {
    npm install --no-audit --no-fund
    npm install --prefix backend --no-audit --no-fund
    npm install --prefix frontend --no-audit --no-fund
} finally { Pop-Location }

# == Prisma ==================================================================
Write-Host "Generating Prisma client..."
Push-Location (Join-Path $Root 'backend')
try {
    npx prisma generate
    # The Prisma CLI only auto-loads a .env next to schema.prisma or in cwd;
    # this project keeps one .env at the repo root, hence the wrapper.
    Write-Host "Pushing schema to the database..."
    node scripts/with-root-env.js npx prisma db push --skip-generate
} finally { Pop-Location }

# == Build ===================================================================
Write-Host "Building dashboard..."
Push-Location (Join-Path $Root 'frontend')
try { npm run build } finally { Pop-Location }

$public = Join-Path $Root 'backend\public'
if (Test-Path $public) { Remove-Item $public -Recurse -Force }
New-Item -ItemType Directory -Force -Path $public | Out-Null
Copy-Item (Join-Path $Root 'frontend\dist\*') $public -Recurse -Force
Write-Host "  dashboard -> backend\public"

Write-Host "Compiling backend..."
Push-Location (Join-Path $Root 'backend')
try {
    npx tsc
    if ($LASTEXITCODE -ne 0) { throw "tsc failed" }
    # The package.json build script ends in a Unix `cp -r`, which cmd.exe does
    # not have, so the asset copy is done here instead.
    $assetSrc = Join-Path $Root 'backend\src\agents\social_media\assets'
    $assetDst = Join-Path $Root 'backend\dist\agents\social_media\assets'
    if (Test-Path $assetSrc) {
        New-Item -ItemType Directory -Force -Path $assetDst | Out-Null
        Copy-Item (Join-Path $assetSrc '*') $assetDst -Recurse -Force
    }
} finally { Pop-Location }

Write-Host ""
Write-Host "=== Ready ===" -ForegroundColor Green
Write-Host "Run it once in the foreground:"
Write-Host "    cd backend; node dist\server.js"
Write-Host "Or install the always-on scheduled task:"
Write-Host "    powershell -ExecutionPolicy Bypass -File ops\install-task.ps1"
