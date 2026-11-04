. (Join-Path $PSScriptRoot 'runtime.ps1')
$kayNode = Get-KayCareNode
& $kayNode (Join-Path $PSScriptRoot 'local.mjs') start
if ($LASTEXITCODE -ne 0) { throw 'Kay Care se detuvo con un error. Revisa el mensaje anterior.' }
