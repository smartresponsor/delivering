# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding()]
param(
    [string]$HostPath = (Join-Path $PSScriptRoot '..\..\App'),
    [int]$TimeLimit = 3600,
    [int]$MemoryLimitMb = 256,
    [int]$MessageLimit = 0
)

$ErrorActionPreference = 'Stop'
$console = Join-Path $HostPath 'bin\console'

if (-not (Test-Path -LiteralPath $console -PathType Leaf)) {
    throw "Symfony console was not found: $console"
}

$arguments = @(
    $console,
    'messenger:consume',
    'delivering_async',
    '--time-limit', $TimeLimit,
    '--memory-limit', "${MemoryLimitMb}M",
    '--no-interaction'
)

if ($MessageLimit -gt 0) {
    $arguments += @('--limit', $MessageLimit)
}

& php @arguments
