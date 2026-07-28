# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding(SupportsShouldProcess)]
param(
    [ValidateSet('show', 'retry', 'remove')]
    [string]$Action = 'show',
    [string]$HostPath = (Join-Path $PSScriptRoot '..\App'),
    [string]$Id,
    [switch]$All,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$console = Join-Path $HostPath 'bin\console'

if (-not (Test-Path -LiteralPath $console -PathType Leaf)) {
    throw "Symfony console was not found: $console"
}

if ($Action -eq 'show') {
    & php $console messenger:failed:show --transport delivering_failed --no-interaction
    exit $LASTEXITCODE
}

if (-not $All -and [string]::IsNullOrWhiteSpace($Id)) {
    throw 'Specify -Id <message-id> or explicitly pass -All.'
}

$command = if ($Action -eq 'retry') { 'messenger:failed:retry' } else { 'messenger:failed:remove' }
$arguments = @($console, $command, '--transport', 'delivering_failed', '--no-interaction')

if ($All) {
    $arguments += '--all'
} else {
    $arguments += $Id
}

if ($Force) {
    $arguments += '--force'
}

$target = if ($All) { 'all Delivering failed messages' } else { "Delivering failed message $Id" }

if ($PSCmdlet.ShouldProcess($target, $Action)) {
    & php @arguments
    exit $LASTEXITCODE
}

