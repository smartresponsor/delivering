param()

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

$textRoots = @(
    (Join-Path $root 'src'),
    (Join-Path $root 'tests'),
    (Join-Path $root 'config')
)
$textFiles = @()
foreach ($textRoot in $textRoots) {
    if (Test-Path $textRoot) {
        $textFiles += Get-ChildItem -Path $textRoot -Recurse -File | Where-Object { $_.Extension -in @('.php', '.yaml', '.yml') }
    }
}
$readme = Join-Path $root 'README.md'
if (Test-Path $readme) {
    $textFiles += Get-Item $readme
}

$pattern = '\bDelivering(?!Bundle\b)(?=[A-Z])'
foreach ($file in $textFiles) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $updated = [System.Text.RegularExpressions.Regex]::Replace($content, $pattern, 'Delivery')
    if ($updated -ne $content) {
        [System.IO.File]::WriteAllText($file.FullName, $updated, [System.Text.UTF8Encoding]::new($false))
    }
}

$renameRoots = @((Join-Path $root 'src'), (Join-Path $root 'tests'))
foreach ($renameRoot in $renameRoots) {
    Get-ChildItem -Path $renameRoot -Recurse -File -Filter 'Delivering*.php' |
        Sort-Object { $_.FullName.Length } -Descending |
        ForEach-Object {
            if ($_.Name -eq 'DeliveringBundle.php') {
                return
            }
            $newName = $_.Name -replace '^Delivering', 'Delivery'
            Rename-Item -LiteralPath $_.FullName -NewName $newName
        }
}
