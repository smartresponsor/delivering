# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding()]
param(
    [string]$HostPath = (Join-Path $PSScriptRoot '..\..\App'),
    [ValidateSet('dev', 'test', 'prod')]
    [string]$Environment = 'dev'
)

$ErrorActionPreference = 'Stop'
$console = Join-Path $HostPath 'bin\console'

if (-not (Test-Path -LiteralPath $console -PathType Leaf)) {
    throw "Symfony console was not found: $console"
}

& php $console cache:clear --env=$Environment --no-warmup --no-interaction -vvv

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

& php $console debug:config framework messenger --env=$Environment --no-interaction

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

exit 0
