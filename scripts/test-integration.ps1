. (Join-Path $PSScriptRoot 'runtime.ps1')
$kayNode = Get-KayCareNode
& $kayNode (Join-Path $PSScriptRoot 'local.mjs') test
if ($LASTEXITCODE -ne 0) { throw 'La prueba de integración no pasó. Los resultados están en frontend/test-results.' }
