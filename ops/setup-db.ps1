<#
  One-time: create the `gameindo` database that DATABASE_URL in ../.env points
  at, and push the Prisma schema into it.

  This machine already runs PostgreSQL 16 as the Windows service
  "postgresql-x64-16" (data dir C:\Program Files\PostgreSQL\16\data), which is
  where popshck's database lives. GameIndo becomes a second database in that
  same cluster -- do NOT start a second cluster on port 5432, the two will
  fight over the port and whichever loses takes its app down with it.

  Idempotent -- skips whatever already exists.

  ASCII only: see the note in supervise.ps1.
#>

$ErrorActionPreference = 'Stop'

# Node/PostgreSQL client location. Override with GAMEINDO_RUNTIME when the
# portable runtime lives elsewhere; otherwise fall back to PATH.
$Runtime = if ($env:GAMEINDO_RUNTIME) { $env:GAMEINDO_RUNTIME } else { Join-Path (Join-Path $env:SystemDrive 'Claude Home') 'runtime' }
$PgBin   = Join-Path $Runtime 'pgsql\bin'    # client tools only
$Root    = Split-Path -Parent $PSScriptRoot   # repo root = parent of ops/

$env:PGPASSWORD = 'postgres'

# == Server reachable? =======================================================
& (Join-Path $PgBin 'pg_isready.exe') -h 127.0.0.1 -p 5432 -q 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "PostgreSQL is not answering on 127.0.0.1:5432. Start the 'postgresql-x64-16' service and retry."
}
"PostgreSQL reachable. data_directory = " + (& (Join-Path $PgBin 'psql.exe') -h 127.0.0.1 -p 5432 -U postgres -tAc 'SHOW data_directory')

# == Database ================================================================
$exists = & (Join-Path $PgBin 'psql.exe') -h 127.0.0.1 -p 5432 -U postgres -tAc "SELECT 1 FROM pg_database WHERE datname='gameindo'"
if ($exists -ne '1') {
    "Creating database gameindo"
    & (Join-Path $PgBin 'createdb.exe') -h 127.0.0.1 -p 5432 -U postgres gameindo
} else {
    "Database gameindo already exists"
}

# == Schema ==================================================================
"Pushing Prisma schema"
$env:Path = "$Runtime\node;$env:Path"
Set-Location (Join-Path $Root 'backend')
# The Prisma CLI only auto-loads a .env sitting next to schema.prisma or in the
# cwd; this project keeps a single .env at the repo root, so go through the
# project's own wrapper.
node scripts/with-root-env.js npx prisma db push --skip-generate
if ($LASTEXITCODE -ne 0) { throw "prisma db push failed" }
"Database ready."
