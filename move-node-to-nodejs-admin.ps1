$ErrorActionPreference = 'Stop'

$sourceRoot = 'C:\Program Files'
$destination = 'C:\Program Files\nodejs'
$items = @(
  'node.exe',
  'nodevars.bat',
  'npm',
  'npm.cmd',
  'npm.ps1',
  'npx',
  'npx.cmd',
  'npx.ps1',
  'node_modules'
)
$logPath = Join-Path $PSScriptRoot 'nodejs-move.log'

function Write-Log {
  param([string] $Message)
  $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $Message"
  Add-Content -Path $logPath -Value $line
  Write-Host $line
}

Write-Log 'Starting Node.js folder cleanup.'

if (-not (Test-Path -Path $sourceRoot -PathType Container)) {
  throw "Source folder not found: $sourceRoot"
}

New-Item -ItemType Directory -Path $destination -Force | Out-Null
Write-Log "Destination ready: $destination"

foreach ($item in $items) {
  $source = Join-Path $sourceRoot $item
  if (Test-Path -Path $source) {
    Write-Log "Moving $source"
    Move-Item -Path $source -Destination $destination -Force
  } else {
    Write-Log "Skipped missing item: $source"
  }
}

$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
$pathParts = @()
if ($userPath) {
  $pathParts = $userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }
}

if ($pathParts -notcontains $destination) {
  [Environment]::SetEnvironmentVariable('Path', (($pathParts + $destination) -join ';'), 'User')
  Write-Log "Added to user PATH: $destination"
} else {
  Write-Log 'User PATH already contains the nodejs folder.'
}

& (Join-Path $destination 'node.exe') -v | ForEach-Object { Write-Log "Node version: $_" }
& (Join-Path $destination 'npm.cmd') -v | ForEach-Object { Write-Log "NPM version: $_" }

Write-Log 'Done. You can close this window.'
Read-Host 'Press Enter to close'
