param([switch]$SkipInstall)
. (Join-Path $PSScriptRoot 'runtime.ps1')
$kayNode = Get-KayCareNode
$arguments = @((Join-Path $PSScriptRoot 'local.mjs'), 'setup')
if ($SkipInstall) { $arguments += '--skip-install' }
& $kayNode @arguments
if ($LASTEXITCODE -ne 0) { throw 'No se pudo completar la preparación de Kay Care.' }
