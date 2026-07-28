# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding()]
param(
    [string]$HostPath = (Join-Path $PSScriptRoot '..\..\App')
)

$ErrorActionPreference = 'Stop'
$console = Join-Path $HostPath 'bin\console'

if (-not (Test-Path -LiteralPath $console -PathType Leaf)) {
    throw "Symfony console was not found: $console"
}

Write-Host 'Delivering transport statistics'
& php $console messenger:stats delivering_async delivering_failed --no-interaction

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
