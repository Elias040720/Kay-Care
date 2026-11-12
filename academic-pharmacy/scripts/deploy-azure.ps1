<#
.SYNOPSIS
  Despliegue de Kay Care Farmacia (entorno DEMO) en Azure — SOLO el día de la
  exposición y SOLO con autorización expresa del titular de la suscripción.

.DESCRIPTION
  La existencia de este script NO es una autorización para desplegar.
  Crea recursos facturables (Container Apps, PostgreSQL Flexible Server,
  Log Analytics, presupuesto) en el grupo rg-kcf-demo. Antes de crear nada:

    1. muestra la suscripción y pide escribir su nombre,
    2. ejecuta azure-preflight.ps1 (solo lectura) y se detiene si falla,
    3. genera un terraform plan y lista los recursos que se crearán,
    4. muestra el costo estimado,
    5. exige escribir una frase de confirmación exacta.

  Después: aplica el plan, espera a que las migraciones terminen (las ejecuta
  la propia API con bloqueo), comprueba health checks, hace un smoke test,
  realiza una compra simulada de punta a punta y muestra la URL pública y los
  recursos que siguen cobrando. El estado de Terraform queda en
  infra/terraform/environments/demo/terraform.tfstate (ignorado por Git): es
  imprescindible para retirar después SOLO estos recursos.

.PARAMETER Location
  Región (debe estar permitida por la política de la suscripción).
.PARAMETER ImageTag
  sha-xxxxxxx publicada por pharmacy-ci. Por defecto, la del último commit de main.
.PARAMETER BuildAndPushImages
  Construye y publica las imágenes en GHCR desde este equipo (requiere haber
  hecho antes `docker login ghcr.io` con un token propio).
.PARAMETER ActiveHours / LifetimeHours
  Supuestos para la estimación de costo que se muestra antes de confirmar.
.PARAMETER UseDocker
  Usa la imagen kcf-azure-tools aunque az/terraform estén instalados.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\deploy-azure.ps1
#>
[CmdletBinding()]
param(
    [string]$Location = 'canadacentral',
    [ValidatePattern('^(sha-[0-9a-f]{7})?$')][string]$ImageTag = '',
    [switch]$BuildAndPushImages,
    [double]$ActiveHours = 4,
    [double]$LifetimeHours = 8,
    [switch]$UseDocker
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'azure-common.ps1')

$environment = 'demo'   # HA es solo de diseño: este script nunca la despliega.
$resourceGroup = 'rg-kcf-demo'
$envDir = Get-KcfEnvironmentDir $environment
$planFile = 'tfplan-exposicion'

function Stop-Deploy([string]$Message) {
    Write-Host ''
    Write-Host "DESPLIEGUE CANCELADO: $Message" -ForegroundColor Red
    Write-Host 'No se creó ningún recurso en Azure.' -ForegroundColor DarkGray
    exit 1
}

Write-Host '==============================================================' -ForegroundColor Yellow
Write-Host ' Kay Care Farmacia — DESPLIEGUE EN AZURE (crea recursos que COBRAN)' -ForegroundColor Yellow
Write-Host '==============================================================' -ForegroundColor Yellow

# 1) Suscripción --------------------------------------------------------------
Write-KcfStep '1/14 Suscripción'
Initialize-KcfToolchain -PreferDocker:$UseDocker | Out-Null
$account = Invoke-KcfAzJson @('account', 'show')
if ($null -eq $account) { Stop-Deploy 'no hay sesión de Azure. Inicia sesión (ver azure-preflight.ps1).' }
Write-Host "  Suscripción: $($account.name)"
Write-Host "  Id:          $($account.id)"
Write-Host "  Usuario:     $($account.user.name)"
$typed = Read-Host "  Escribe el NOMBRE de la suscripción para confirmar que es la correcta"
if ($typed -ne $account.name) { Stop-Deploy 'el nombre de la suscripción no coincide.' }

# 2) Preflight ----------------------------------------------------------------
Write-KcfStep '2/14 Verificación previa (solo lectura)'
$preflightArgs = @{ Environment = $environment; Location = $Location; ActiveHours = $ActiveHours; LifetimeHours = $LifetimeHours }
if ($ImageTag) { $preflightArgs.ImageTag = $ImageTag }
if ($UseDocker) { $preflightArgs.UseDocker = $true }
& (Join-Path $PSScriptRoot 'azure-preflight.ps1') @preflightArgs
$preflightCode = $LASTEXITCODE
if (-not $ImageTag) { $ImageTag = Get-KcfLatestMainTag }

$backendImage = "ghcr.io/$($script:KcfRepoOwner)/kay-care-pharmacy-backend"
$frontendImage = "ghcr.io/$($script:KcfRepoOwner)/kay-care-pharmacy-frontend"

if ($preflightCode -ne 0) {
    $imagesMissing = -not (Test-KcfPublicImage -Name 'kay-care-pharmacy-backend' -Tag $ImageTag) -or -not (Test-KcfPublicImage -Name 'kay-care-pharmacy-frontend' -Tag $ImageTag)
    if (-not ($BuildAndPushImages -and $imagesMissing)) { Stop-Deploy 'la verificación previa informó fallos.' }
    Write-Host '  El preflight falló por imágenes no publicadas; se publicarán en el paso 7.' -ForegroundColor Yellow
}

# 3) Plan ---------------------------------------------------------------------
Write-KcfStep '3/14 Recursos que se crearán (terraform plan)'
$tfVars = @('-var', "location=$Location", '-var', "backend_image=${backendImage}:$ImageTag", '-var', "frontend_image=${frontendImage}:$ImageTag")
Invoke-KcfTool -Tool terraform -Arguments @('init', '-input=false', '-no-color') -WorkDir $envDir | Out-Null
$plan = Invoke-KcfTool -Tool terraform -Arguments (@('plan', '-input=false', '-no-color', "-out=$planFile") + $tfVars) -WorkDir $envDir
$summary = ($plan.Output -split "`n" | Where-Object { $_ -match '^Plan:|will be created|will be destroyed|will be updated|must be replaced' }) -join "`n"
Write-Host $summary
if ($plan.Output -match 'will be destroyed|must be replaced') {
    Write-Host '  ATENCIÓN: el plan destruye o reemplaza recursos existentes.' -ForegroundColor Red
}
$planJson = (Invoke-KcfTool -Tool terraform -Arguments @('show', '-json', $planFile) -WorkDir $envDir).Output | ConvertFrom-Json
$outsideRg = @($planJson.resource_changes | Where-Object {
        $_.change.actions -contains 'create' -and $_.type -notin @('random_string', 'random_password', 'random_bytes', 'azurerm_consumption_budget_subscription', 'azurerm_resource_group') -and
        $_.change.after -and $_.change.after.PSObject.Properties['resource_group_name'] -and $_.change.after.resource_group_name -ne $resourceGroup
    })
if ($outsideRg.Count -gt 0) { Stop-Deploy "el plan crea recursos fuera de $resourceGroup ($($outsideRg.address -join ', '))." }

# 4) Costos -------------------------------------------------------------------
Write-KcfStep '4/14 Costo estimado (USD)'
$cost = Get-KcfCostEstimate -Location $Location -ActiveHours $ActiveHours -LifetimeHours $LifetimeHours
$cost | Format-List

# 5) Confirmación explícita -----------------------------------------------------
Write-KcfStep '5/14 Confirmación'
$phrase = "DESPLEGAR KCF-DEMO EN $($account.name)"
Write-Host '  Esto creará recursos FACTURABLES en Azure hasta que se retiren con terraform destroy.' -ForegroundColor Yellow
Write-Host "  Para continuar escribe exactamente: $phrase"
$answer = Read-Host '  Confirmación'
if ($answer -cne $phrase) { Stop-Deploy 'la frase de confirmación no coincide.' }

# 6) Infraestructura ----------------------------------------------------------
Write-KcfStep '6/14 Creando infraestructura con Terraform'
foreach ($ns in @('Microsoft.App', 'Microsoft.DBforPostgreSQL', 'Microsoft.OperationalInsights')) {
    $state = (Invoke-KcfTool -Tool az -Arguments @('provider', 'show', '-n', $ns, '--query', 'registrationState', '-o', 'tsv') -AllowFailure).Output.Trim()
    if ($state -ne 'Registered') {
        Write-Host "  Registrando proveedor $ns (sin costo)…"
        Invoke-KcfTool -Tool az -Arguments @('provider', 'register', '-n', $ns, '--wait') | Out-Null
    }
}

# 7) Imágenes -----------------------------------------------------------------
Write-KcfStep '7/14 Imágenes de contenedor'
if ($BuildAndPushImages) {
    foreach ($svc in @(@{ dir = 'backend'; image = $backendImage }, @{ dir = 'frontend'; image = $frontendImage })) {
        & docker build -t "$($svc.image):$ImageTag" (Join-Path $script:KcfRoot $svc.dir)
        if ($LASTEXITCODE -ne 0) { Stop-Deploy "falló el build de $($svc.dir) (aún no se aplicó Terraform)." }
        & docker push "$($svc.image):$ImageTag"
        if ($LASTEXITCODE -ne 0) { Stop-Deploy "falló el push de $($svc.dir): ¿hiciste docker login ghcr.io? (aún no se aplicó Terraform)." }
    }
}
foreach ($name in @('kay-care-pharmacy-backend', 'kay-care-pharmacy-frontend')) {
    if (-not (Test-KcfPublicImage -Name $name -Tag $ImageTag)) { Stop-Deploy "la imagen ${name}:${ImageTag} no es pública en GHCR." }
}
Write-Host "  Imágenes $ImageTag disponibles."

Invoke-KcfTool -Tool terraform -Arguments @('apply', '-input=false', $planFile) -WorkDir $envDir -Interactive
if ($LASTEXITCODE -ne 0) {
    Write-Host 'terraform apply falló. Revisa el error; los recursos ya creados siguen en el estado local.' -ForegroundColor Red
    Write-Host "Para retirarlos: terraform -chdir=$envDir destroy" -ForegroundColor Yellow
    exit 1
}
$outputs = (Invoke-KcfTool -Tool terraform -Arguments @('output', '-json') -WorkDir $envDir).Output | ConvertFrom-Json
$url = $outputs.frontend_url.value.TrimEnd('/')

# 8-9) Migraciones y configuración --------------------------------------------
Write-KcfStep '8/14 Migraciones (las aplica la API al arrancar, con bloqueo --isolated)'
Write-KcfStep '9/14 Configuración (variables y secretos inyectados por Terraform)'
Write-Host "  Frontend: $url"
Write-Host "  API interna: $($outputs.backend_internal_fqdn.value)"

# 10) Health checks -------------------------------------------------------------
Write-KcfStep '10/14 Health checks (el primer arranque puede tardar varios minutos)'
$healthy = $false
for ($i = 1; $i -le 60; $i++) {
    try {
        $front = Invoke-WebRequest -Uri "$url/healthz" -UseBasicParsing -TimeoutSec 15
        $api = Invoke-WebRequest -Uri "$url/api/v1/store/health" -UseBasicParsing -TimeoutSec 30
        if ($front.StatusCode -eq 200 -and $api.StatusCode -eq 200) { $healthy = $true; break }
    } catch {
        Write-Host "  intento $i/60: aún no responde…" -ForegroundColor DarkGray
    }
    Start-Sleep -Seconds 10
}
if (-not $healthy) {
    Write-Host '  La aplicación no respondió en 10 minutos. Revisa los logs:' -ForegroundColor Red
    Write-Host "  az containerapp logs show -g $resourceGroup -n ca-kcf-demo-api --tail 100"
    Write-Host '  Los recursos SIGUEN CREADOS (y cobrando). Retíralos con terraform destroy si no se va a usar.' -ForegroundColor Yellow
    exit 1
}
Write-Host '  Frontend y API responden 200.' -ForegroundColor Green

# 11) Smoke test ----------------------------------------------------------------
Write-KcfStep '11/14 Smoke test'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$homePage = Invoke-WebRequest -Uri "$url/" -UseBasicParsing -WebSession $session
$checks = [ordered]@{
    'HTTPS'                   = $url.StartsWith('https://')
    'Content-Security-Policy' = [bool]$homePage.Headers['Content-Security-Policy']
    'X-Frame-Options'         = $homePage.Headers['X-Frame-Options'] -eq 'DENY'
    'Strict-Transport-Security' = [bool]$homePage.Headers['Strict-Transport-Security']
}
$catalog = Invoke-RestMethod -Uri "$url/api/v1/store/products?in_stock=1&sort=stock_desc&per_page=1" -WebSession $session
$checks['Catálogo con productos'] = $catalog.meta.total -gt 0
$adminStatus = try { (Invoke-WebRequest -Uri "$url/api/v1/store/admin/me" -UseBasicParsing -Headers @{ Accept = 'application/json' }).StatusCode } catch { [int]$_.Exception.Response.StatusCode }
$checks['Admin sin sesión → 401'] = $adminStatus -eq 401
foreach ($k in $checks.Keys) { Add-KcfResult $k ($(if ($checks[$k]) { 'OK' } else { 'FALLO' })) }

# 12) URL pública -----------------------------------------------------------------
Write-KcfStep '12/14 URL pública'
Write-Host "  $url" -ForegroundColor Green

# 13) Compra simulada de punta a punta ------------------------------------------
Write-KcfStep '13/14 Compra simulada'
Invoke-WebRequest -Uri "$url/api/v1/store/sanctum/csrf-cookie" -UseBasicParsing -WebSession $session -Headers @{ Referer = "$url/" } | Out-Null
$xsrf = [uri]::UnescapeDataString(($session.Cookies.GetCookies($url) | Where-Object Name -eq 'XSRF-TOKEN').Value)
$product = $catalog.data[0]
$body = @{ buyer_alias = 'Jurado Demo'; accept_demo_terms = $true; items = @(@{ product_id = $product.id; quantity = 1 }) } | ConvertTo-Json -Depth 5
$headers = @{ Accept = 'application/json'; 'X-XSRF-TOKEN' = $xsrf; 'Idempotency-Key' = "kcf-deploy-$([guid]::NewGuid())"; Origin = $url; Referer = "$url/checkout" }
$order = Invoke-RestMethod -Uri "$url/api/v1/store/checkout" -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($body)) -ContentType 'application/json; charset=utf-8' -Headers $headers -WebSession $session
$lookup = Invoke-RestMethod -Uri "$url/api/v1/store/orders/$($order.data.number)?token=$($order.lookup_token)" -WebSession $session
Add-KcfResult "Pedido $($order.data.number) creado" ($(if ($order.data.number) { 'OK' } else { 'FALLO' })) "total S/ $([math]::Round($order.data.total_cents / 100, 2))"
Add-KcfResult 'Pedido persistido y consultable' ($(if ($lookup.data.number -eq $order.data.number) { 'OK' } else { 'FALLO' }))

# 14) Recursos que siguen cobrando --------------------------------------------------
Write-KcfStep '14/14 Recursos que siguen cobrando mientras existan'
Write-Host "  - PostgreSQL Flexible B1ms (≈ US$ $($cost.PostgresPorHoraUSD)/h) + 32 GB de almacenamiento, aunque no haya visitas."
Write-Host "  - Container Apps: ≈ US$ $($cost.ContainerAppsPorHoraUSD) por hora de uso activo; US$ 0 escalado a cero."
Write-Host '  - Log Analytics: ingesta de logs (tope 0,1 GB/día).'
Write-Host ''
Write-Host 'Administrador demo: admin@farmacia.demo · contraseña (no se imprime):' -ForegroundColor White
Write-Host "  terraform -chdir=$envDir output -raw admin_password"
Write-Host ''
Write-Host 'AL TERMINAR LA EXPOSICIÓN retira SOLO estos recursos (docs/deploy/DIA-EXPOSICION.md):' -ForegroundColor Yellow
Write-Host "  terraform -chdir=$envDir plan -destroy   # revisar que todo esté en $resourceGroup"
Write-Host "  terraform -chdir=$envDir destroy"
Write-Host 'Conserva el archivo terraform.tfstate hasta haber retirado los recursos.' -ForegroundColor Yellow
