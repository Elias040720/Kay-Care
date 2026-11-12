<#
.SYNOPSIS
  Verificaciones previas al despliegue de Kay Care Farmacia en Azure.
  SOLO LECTURA: no crea, modifica, registra ni elimina nada en Azure.

.DESCRIPTION
  Revisa herramientas, sesión y suscripción de Azure, región permitida por
  política, proveedores, recursos existentes que afectan al costo, imágenes
  publicadas, Dockerfiles, Terraform (fmt/init -backend=false/validate) y
  calcula una estimación de costo con la API pública de precios de Azure.

  Comandos de Azure usados (todos de lectura): account show, policy
  assignment list, provider show, group exists, postgres flexible-server
  list / list-skus, containerapp list.

.PARAMETER Environment
  demo (por defecto) o ha.
.PARAMETER Location
  Región de Azure. Por defecto canadacentral.
.PARAMETER ImageTag
  Etiqueta de imagen (sha-xxxxxxx). Por defecto, la del último commit de main.
.PARAMETER ActiveHours
  Horas que las apps estarán recibiendo tráfico (para la estimación).
.PARAMETER LifetimeHours
  Horas que existirán los recursos antes de retirarlos (para la estimación).
.PARAMETER CheckBuild
  Construye localmente las imágenes Docker (no toca Azure).
.PARAMETER UseDocker
  Fuerza el uso de la imagen kcf-azure-tools aunque az/terraform estén instalados.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\azure-preflight.ps1
#>
[CmdletBinding()]
param(
    [ValidateSet('demo', 'ha')][string]$Environment = 'demo',
    [string]$Location = 'canadacentral',
    [ValidatePattern('^(sha-[0-9a-f]{7})?$')][string]$ImageTag = '',
    [double]$ActiveHours = 4,
    [double]$LifetimeHours = 8,
    [switch]$CheckBuild,
    [switch]$UseDocker
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'azure-common.ps1')

$project = 'kcf'
$resourceGroup = "rg-$project-$Environment"
$envDir = Get-KcfEnvironmentDir $Environment

Write-Host 'Kay Care Farmacia — verificación previa (solo lectura)' -ForegroundColor White
Write-Host "Entorno: $Environment · Región: $Location · Grupo de recursos previsto: $resourceGroup" -ForegroundColor DarkGray

# 1) Herramientas ------------------------------------------------------------
Write-KcfStep 'Herramientas'
try {
    $mode = Initialize-KcfToolchain -PreferDocker:$UseDocker
    Add-KcfResult 'Azure CLI y Terraform' 'OK' ($(if ($mode -eq 'native') { 'instalados en Windows' } else { "vía Docker (imagen $($script:KcfToolsImage))" }))
    $tfVersion = (Invoke-KcfTool -Tool terraform -Arguments @('version') -AllowFailure).Output -split "`n" | Select-Object -First 1
    Add-KcfResult 'Versión de Terraform' 'INFO' $tfVersion.Trim()
} catch {
    Add-KcfResult 'Azure CLI y Terraform' 'FALLO' $_.Exception.Message
    Get-KcfResults | Format-Table -AutoSize
    exit 1
}
Add-KcfResult 'Docker' ($(if (Test-KcfCommand 'docker') { 'OK' } else { 'AVISO' })) 'necesario solo para construir imágenes localmente'

# 2) Sesión y suscripción ----------------------------------------------------
Write-KcfStep 'Sesión y suscripción de Azure'
$account = Invoke-KcfAzJson @('account', 'show')
$loggedIn = $null -ne $account
if (-not $loggedIn) {
    $loginCmd = if ($script:KcfToolMode -eq 'native') { 'az login --use-device-code' } else { "docker run --rm -it -v $($script:KcfAzureVolume):/root/.azure $($script:KcfToolsImage) az login --use-device-code" }
    Add-KcfResult 'Sesión iniciada' 'FALLO' "ejecuta: $loginCmd"
} else {
    Add-KcfResult 'Sesión iniciada' 'OK' "usuario $($account.user.name)"
    Add-KcfResult 'Suscripción seleccionada' 'INFO' "$($account.name) ($($account.id)) · estado $($account.state)"
    if ($account.state -ne 'Enabled') { Add-KcfResult 'Suscripción habilitada' 'FALLO' $account.state }
}

if ($loggedIn) {
    # 3) Región permitida por política -------------------------------------------
    Write-KcfStep 'Región'
    $assignments = Invoke-KcfAzJson @('policy', 'assignment', 'list')
    $allowed = @()
    foreach ($a in @($assignments)) {
        if ($null -ne $a -and $a.PSObject.Properties['parameters'] -and $a.parameters -and $a.parameters.PSObject.Properties['listOfAllowedLocations']) {
            $allowed += @($a.parameters.listOfAllowedLocations.value)
        }
    }
    if ($allowed.Count -eq 0) {
        Add-KcfResult 'Política de regiones' 'INFO' 'no se encontró una política de regiones permitidas'
    } elseif ($allowed -contains $Location) {
        Add-KcfResult "Región $Location permitida" 'OK' ("permitidas: " + ($allowed -join ', '))
    } else {
        Add-KcfResult "Región $Location permitida" 'FALLO' ("permitidas: " + ($allowed -join ', '))
    }

    # 4) Proveedores de recursos ------------------------------------------------
    Write-KcfStep 'Proveedores de recursos (no se registran aquí)'
    foreach ($ns in @('Microsoft.App', 'Microsoft.DBforPostgreSQL', 'Microsoft.OperationalInsights', 'Microsoft.Consumption')) {
        $state = (Invoke-KcfTool -Tool az -Arguments @('provider', 'show', '-n', $ns, '--query', 'registrationState', '-o', 'tsv') -AllowFailure).Output.Trim()
        Add-KcfResult $ns ($(if ($state -eq 'Registered') { 'OK' } else { 'AVISO' })) ($(if ($state) { $state } else { 'desconocido' }))
    }

    # 5) Recursos existentes que afectan costo o colisionan --------------------
    Write-KcfStep 'Recursos existentes (lectura)'
    $rgExists = (Invoke-KcfTool -Tool az -Arguments @('group', 'exists', '-n', $resourceGroup) -AllowFailure).Output.Trim()
    if ($rgExists -eq 'true') {
        Add-KcfResult "Grupo $resourceGroup" 'AVISO' 'YA EXISTE: puede haber recursos de la farmacia cobrando ahora mismo'
    } else {
        Add-KcfResult "Grupo $resourceGroup" 'OK' 'no existe todavía (se creará en el despliegue)'
    }

    $servers = @(Invoke-KcfAzJson @('postgres', 'flexible-server', 'list', '--query', '[].{name:name, rg:resourceGroup, sku:sku.name, state:state}'))
    $servers = @($servers | Where-Object { $null -ne $_ })
    Add-KcfResult 'Servidores PostgreSQL existentes' 'INFO' ("{0} en la suscripción" -f $servers.Count)

    $alwaysOn = @(Invoke-KcfAzJson @('containerapp', 'list', '--query', '[?properties.template.scale.minReplicas > `0`].name'))
    $alwaysOn = @($alwaysOn | Where-Object { $null -ne $_ })
    if ($alwaysOn.Count -gt 0) {
        Add-KcfResult 'Concesión gratuita de Container Apps' 'AVISO' ("{0} apps siempre encendidas la consumen; la farmacia pagará su uso activo" -f $alwaysOn.Count)
    } else {
        Add-KcfResult 'Concesión gratuita de Container Apps' 'INFO' 'no hay apps con réplicas mínimas > 0'
    }

    # 6) Disponibilidad de PostgreSQL B1ms en la región ---------------------------
    $skus = Invoke-KcfAzJson @('postgres', 'flexible-server', 'list-skus', '--location', $Location)
    if ($null -eq $skus) {
        Add-KcfResult "PostgreSQL B1ms en $Location" 'AVISO' 'no se pudo consultar (list-skus); se comprobará en el plan'
    } else {
        $raw = $skus | ConvertTo-Json -Depth 20
        if ($raw -match 'Standard_B1ms') {
            Add-KcfResult "PostgreSQL B1ms en $Location" 'OK' 'SKU ofrecido en la región'
        } else {
            Add-KcfResult "PostgreSQL B1ms en $Location" 'FALLO' 'el SKU no aparece en la región'
        }
        if ($raw -match '"reason"\s*:\s*"[^"]+"' -and $raw -match 'restrict') {
            Add-KcfResult 'Restricciones de la región' 'AVISO' 'list-skus informa restricciones: revisar antes de desplegar'
        }
    }
}

# 7) Imágenes, variables y secretos -----------------------------------------
Write-KcfStep 'Imágenes y variables'
if (-not $ImageTag) { $ImageTag = Get-KcfLatestMainTag }
if (-not $ImageTag) {
    Add-KcfResult 'Etiqueta de imagen' 'FALLO' 'no se pudo leer el último commit de main; usa -ImageTag sha-xxxxxxx'
} else {
    Add-KcfResult 'Etiqueta de imagen' 'INFO' $ImageTag
    foreach ($name in @('kay-care-pharmacy-backend', 'kay-care-pharmacy-frontend')) {
        $ok = Test-KcfPublicImage -Name $name -Tag $ImageTag
        Add-KcfResult "ghcr.io/$($script:KcfRepoOwner)/${name}:$ImageTag" ($(if ($ok) { 'OK' } else { 'FALLO' })) ($(if ($ok) { 'pública y descargable sin token' } else { 'no publicada: ejecuta pharmacy-ci en main o usa -BuildAndPushImages al desplegar' }))
    }
}
$tfvars = Join-Path $envDir 'terraform.tfvars'
if (Test-Path $tfvars) {
    $content = Get-Content $tfvars -Raw
    Add-KcfResult 'terraform.tfvars' 'INFO' 'presente (ignorado por Git)'
    if ($content -match '(?i)(password|token|secret)\s*=\s*"[^"]+"') {
        Add-KcfResult 'Secretos en terraform.tfvars' 'AVISO' 'hay valores con aspecto de secreto: usa variables de entorno TF_VAR_*'
    }
} else {
    Add-KcfResult 'terraform.tfvars' 'INFO' 'no existe: deploy-azure.ps1 pasa ubicación e imágenes con -var'
}
# Secretos: Terraform genera APP_KEY y contraseñas (random_*). Solo se informa presencia.
$registryToken = [Environment]::GetEnvironmentVariable('TF_VAR_registry_password')
Add-KcfResult 'TF_VAR_registry_password' 'INFO' ($(if ($registryToken) { 'definida (no se muestra)' } else { 'no definida: correcto con imágenes públicas' }))
Add-KcfResult 'APP_KEY / contraseñas' 'OK' 'las genera Terraform (random_*) y viven solo en el estado local'

# 8) Dockerfiles y build opcional --------------------------------------------
Write-KcfStep 'Dockerfiles'
foreach ($svc in @('backend', 'frontend')) {
    $df = Join-Path $script:KcfRoot "$svc\Dockerfile"
    Add-KcfResult "$svc/Dockerfile" ($(if (Test-Path $df) { 'OK' } else { 'FALLO' }))
    if ($CheckBuild -and (Test-Path $df)) {
        & docker build -q -t "kcf-preflight-${svc}:local" (Join-Path $script:KcfRoot $svc) | Out-Null
        Add-KcfResult "Build local de $svc" ($(if ($LASTEXITCODE -eq 0) { 'OK' } else { 'FALLO' }))
    }
}

# 9) Terraform ----------------------------------------------------------------
Write-KcfStep 'Terraform (sin backend remoto, sin plan ni apply)'
$fmt = Invoke-KcfTool -Tool terraform -Arguments @('fmt', '-check', '-recursive') -WorkDir $script:KcfInfra -AllowFailure
Add-KcfResult 'terraform fmt -check' ($(if ($fmt.ExitCode -eq 0) { 'OK' } else { 'FALLO' })) ($fmt.Output.Trim())
$init = Invoke-KcfTool -Tool terraform -Arguments @('init', '-backend=false', '-input=false', '-no-color') -WorkDir $envDir -AllowFailure
Add-KcfResult 'terraform init -backend=false' ($(if ($init.ExitCode -eq 0) { 'OK' } else { 'FALLO' }))
$validate = Invoke-KcfTool -Tool terraform -Arguments @('validate', '-no-color') -WorkDir $envDir -AllowFailure
Add-KcfResult 'terraform validate' ($(if ($validate.ExitCode -eq 0) { 'OK' } else { 'FALLO' })) (($validate.Output -split "`n" | Select-Object -First 1).Trim())

# 10) Costos -------------------------------------------------------------------
Write-KcfStep 'Estimación de costo (USD, peor caso)'
if ($Environment -eq 'ha') {
    Add-KcfResult 'Entorno HA' 'AVISO' 'HA es solo de diseño: ≈ US$ 650–700/mes (docs/report/costos.md). No desplegar sin presupuesto aprobado.'
} else {
    $cost = Get-KcfCostEstimate -Location $Location -ActiveHours $ActiveHours -LifetimeHours $LifetimeHours
    $cost | Format-List
    Add-KcfResult 'Costo estimado del día' 'INFO' ("≈ US$ {0} ({1} h activas, {2} h de vida)" -f $cost.TotalEstimadoUSD, $ActiveHours, $LifetimeHours)
    Add-KcfResult 'Recursos que cobran sin visitas' 'INFO' "PostgreSQL B1ms (≈ US$ $($cost.PostgresPorHoraUSD)/h) y su almacenamiento mientras existan; Container Apps no cobra escalada a cero"
}

# Resumen ----------------------------------------------------------------------
Write-KcfStep 'Resumen'
$results = Get-KcfResults
$fails = @($results | Where-Object Estado -eq 'FALLO').Count
$warnings = @($results | Where-Object Estado -eq 'AVISO').Count
Write-Host ("{0} verificaciones · {1} fallos · {2} avisos" -f $results.Count, $fails, $warnings)
Write-Host 'Este script no creó ni modificó ningún recurso de Azure.' -ForegroundColor DarkGray
if ($fails -gt 0) {
    Write-Host 'Corrige los FALLOS antes de desplegar.' -ForegroundColor Red
    exit 1
}
Write-Host 'Listo para revisar el plan con deploy-azure.ps1 (requiere confirmación explícita).' -ForegroundColor Green
exit 0
