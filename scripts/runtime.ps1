$ErrorActionPreference = 'Stop'
function Get-KayCareNode {
    $candidates = @((Get-Command node -All -ErrorAction SilentlyContinue | ForEach-Object { $_.Source }))
    $candidates += Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
    foreach ($candidate in ($candidates | Select-Object -Unique)) {
        if (-not (Test-Path -LiteralPath $candidate)) { continue }
        $version = (& $candidate --version).TrimStart('v').Split('.')
        if (($version[0] -eq '22' -and [int]$version[1] -ge 12) -or [int]$version[0] -ge 24) { return $candidate }
    }
    throw 'Kay Care requiere Node 24 LTS (o Node 22.12+). Instala o activa esa versión y repite el comando.'
}
