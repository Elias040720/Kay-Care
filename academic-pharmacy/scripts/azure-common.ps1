# Funciones compartidas por azure-preflight.ps1 y deploy-azure.ps1.
# Compatible con Windows PowerShell 5.1 y PowerShell 7.
# Este archivo solo define funciones: cargarlo no ejecuta nada en Azure.

Set-StrictMode -Version Latest

$script:KcfRoot = Split-Path -Parent $PSScriptRoot              # academic-pharmacy/
$script:KcfInfra = Join-Path $script:KcfRoot 'infra\terraform'
$script:KcfToolsImage = 'kcf-azure-tools'
$script:KcfAzureVolume = 'kcf-azure-cli'
$script:KcfRepoOwner = 'elias040720'
$script:KcfGithubRepo = 'Elias040720/Kay-Care'
$script:KcfToolMode = $null
$script:KcfResults = New-Object System.Collections.Generic.List[object]

function Write-KcfStep([string]$Text) {
    Write-Host ''
    Write-Host "== $Text" -ForegroundColor Cyan
}

function Add-KcfResult {
    param(
        [Parameter(Mandatory)][string]$Check,
        [Parameter(Mandatory)][ValidateSet('OK', 'AVISO', 'FALLO', 'INFO')][string]$Status,
        [string]$Detail = ''
    )
    $script:KcfResults.Add([pscustomobject]@{ Verificacion = $Check; Estado = $Status; Detalle = $Detail })
    $color = @{ OK = 'Green'; AVISO = 'Yellow'; FALLO = 'Red'; INFO = 'Gray' }[$Status]
    Write-Host ("  [{0,-5}] {1}" -f $Status, $Check) -ForegroundColor $color -NoNewline
    if ($Detail) { Write-Host " — $Detail" -ForegroundColor DarkGray } else { Write-Host '' }
}

function Get-KcfResults { return $script:KcfResults }

function Test-KcfCommand([string]$Name) {
    return [bool](Get-Command $Name -ErrorAction SilentlyContinue)
}

<#
  Decide cómo se ejecutan az/terraform:
  - 'native': ambos instalados en Windows.
  - 'docker': imagen kcf-azure-tools (se construye si falta) con la sesión
    de Azure en el volumen kcf-azure-cli.
#>
function Initialize-KcfToolchain {
    param([switch]$PreferDocker)
    $hasAz = Test-KcfCommand 'az'
    $hasTf = Test-KcfCommand 'terraform'
    if (-not $PreferDocker -and $hasAz -and $hasTf) {
        $script:KcfToolMode = 'native'
        return 'native'
    }
    if (-not (Test-KcfCommand 'docker')) {
        throw 'No se encontró Azure CLI + Terraform ni Docker. Instala Docker Desktop o ambas herramientas (winget install Microsoft.AzureCLI Hashicorp.Terraform).'
    }
    & docker info *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Docker está instalado pero el motor no responde. Abre Docker Desktop y reintenta.' }
    & docker image inspect $script:KcfToolsImage *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  Construyendo la imagen local $($script:KcfToolsImage) (Azure CLI + Terraform)…" -ForegroundColor DarkGray
        $dockerDir = Join-Path $script:KcfRoot 'infra\docker'
        & docker build -q -t $script:KcfToolsImage -f (Join-Path $dockerDir 'azure-tools.Dockerfile') $dockerDir | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo construir la imagen kcf-azure-tools.' }
    }
    $script:KcfToolMode = 'docker'
    return 'docker'
}

<#
  Ejecuta az o terraform y devuelve la salida como texto.
  -WorkDir: carpeta del entorno de Terraform (environments/<env>).
  -Interactive: conserva la consola (para apply / login).
#>
function Invoke-KcfTool {
    param(
        [Parameter(Mandatory)][ValidateSet('az', 'terraform')][string]$Tool,
        [Parameter(Mandatory)][string[]]$Arguments,
        [string]$WorkDir,
        [hashtable]$Environment = @{},
        [switch]$Interactive,
        [switch]$AllowFailure
    )
    if (-not $script:KcfToolMode) { throw 'Llama primero a Initialize-KcfToolchain.' }
    # En Windows PowerShell 5.1, con 'Stop' cualquier línea en stderr de un
    # ejecutable nativo (avisos de az, progreso de docker) sería un error fatal.
    $ErrorActionPreference = 'Continue'

    if ($script:KcfToolMode -eq 'native') {
        $previous = @{}
        foreach ($k in $Environment.Keys) { $previous[$k] = [Environment]::GetEnvironmentVariable($k); [Environment]::SetEnvironmentVariable($k, $Environment[$k]) }
        $location = Get-Location
        try {
            if ($WorkDir) { Set-Location $WorkDir }
            if ($Interactive) { & $Tool @Arguments; $out = '' } else { $out = (& $Tool @Arguments 2>&1 | Out-String) }
            $code = $LASTEXITCODE
        } finally {
            Set-Location $location
            foreach ($k in $Environment.Keys) { [Environment]::SetEnvironmentVariable($k, $previous[$k]) }
        }
    } else {
        $dockerArgs = @('run', '--rm', '-v', "$($script:KcfAzureVolume):/root/.azure", '-v', "$($script:KcfInfra):/w")
        if ($Interactive) { $dockerArgs += '-it' }
        if ($WorkDir) {
            $relative = $WorkDir.Substring($script:KcfInfra.Length).TrimStart('\', '/') -replace '\\', '/'
            $dockerArgs += @('-w', "/w/$relative")
        }
        foreach ($k in $Environment.Keys) { $dockerArgs += @('-e', "$k=$($Environment[$k])") }
        $dockerArgs += $script:KcfToolsImage
        $dockerArgs += $Tool
        $dockerArgs += $Arguments
        if ($Interactive) { & docker @dockerArgs; $out = '' } else { $out = (& docker @dockerArgs 2>&1 | Out-String) }
        $code = $LASTEXITCODE
    }

    if ($code -ne 0 -and -not $AllowFailure) {
        throw "$Tool $($Arguments -join ' ') terminó con código $code.`n$out"
    }
    return [pscustomobject]@{ ExitCode = $code; Output = $out }
}

function Invoke-KcfAzJson {
    param([Parameter(Mandatory)][string[]]$Arguments)
    $result = Invoke-KcfTool -Tool az -Arguments ($Arguments + @('-o', 'json', '--only-show-errors')) -AllowFailure
    if ($result.ExitCode -ne 0) { return $null }
    $text = ($result.Output -split "`n" | Where-Object { $_ -notmatch '^WARNING:' }) -join "`n"
    if (-not $text.Trim()) { return $null }
    return $text | ConvertFrom-Json
}

function Get-KcfEnvironmentDir([string]$Environment) {
    return Join-Path $script:KcfInfra "environments\$Environment"
}

function Get-KcfLatestMainTag {
    try {
        $commit = Invoke-RestMethod -Uri "https://api.github.com/repos/$($script:KcfGithubRepo)/commits/main" -Headers @{ 'User-Agent' = 'kcf-preflight' } -TimeoutSec 20
        return 'sha-' + $commit.sha.Substring(0, 7)
    } catch {
        return $null
    }
}

# Comprueba sin credenciales que la imagen existe y es pública en GHCR.
function Test-KcfPublicImage {
    param([Parameter(Mandatory)][string]$Name, [Parameter(Mandatory)][string]$Tag)
    try {
        $repo = "$($script:KcfRepoOwner)/$Name"
        $token = (Invoke-RestMethod -Uri "https://ghcr.io/token?scope=repository:${repo}:pull" -TimeoutSec 20).token
        $headers = @{ Authorization = "Bearer $token"; Accept = 'application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.v2+json, application/vnd.oci.image.manifest.v1+json' }
        Invoke-WebRequest -Uri "https://ghcr.io/v2/$repo/manifests/$Tag" -Headers $headers -Method Head -UseBasicParsing -TimeoutSec 20 | Out-Null
        return $true
    } catch {
        return $false
    }
}

<#
  Estimación de costos con la API pública de precios minoristas de Azure
  (https://prices.azure.com, sin autenticación). Si no responde se usan los
  precios de canadacentral consultados el 12-nov-2026 (docs/report/costos.md).
#>
function Get-KcfCostEstimate {
    param(
        [string]$Location = 'canadacentral',
        [double]$ActiveHours = 4,
        [double]$LifetimeHours = 8
    )
    $prices = [ordered]@{
        source           = 'fallback 12-nov-2026'
        aca_vcpu_active  = 0.000034  # USD por vCPU·s
        aca_mem_active   = 0.000004  # USD por GiB·s
        pg_b1ms_hour     = 0.0185    # USD por hora
        pg_storage_gb_mo = 0.1265    # USD por GB·mes
        la_ingest_gb     = 2.76      # USD por GB (tras los 5 GB gratuitos de la cuenta)
    }
    function Get-RetailPrice([string]$Filter) {
        $url = 'https://prices.azure.com/api/retail/prices?$filter=' + [uri]::EscapeDataString($Filter)
        $items = (Invoke-RestMethod -Uri $url -TimeoutSec 20).Items | Where-Object { $_.type -eq 'Consumption' -and $_.retailPrice -gt 0 }
        return ($items | Select-Object -First 1).retailPrice
    }
    try {
        $p = Get-RetailPrice "serviceName eq 'Azure Container Apps' and armRegionName eq '$Location' and meterName eq 'Standard vCPU Active Usage'"
        if ($p) { $prices.aca_vcpu_active = [double]$p }
        $p = Get-RetailPrice "serviceName eq 'Azure Container Apps' and armRegionName eq '$Location' and meterName eq 'Standard Memory Active Usage'"
        if ($p) { $prices.aca_mem_active = [double]$p }
        $p = Get-RetailPrice "serviceName eq 'Azure Database for PostgreSQL' and armRegionName eq '$Location' and skuName eq 'B1MS' and contains(productName,'Burstable')"
        if ($p) { $prices.pg_b1ms_hour = [double]$p }
        $p = Get-RetailPrice "serviceName eq 'Azure Database for PostgreSQL' and armRegionName eq '$Location' and productName eq 'Azure Database for PostgreSQL Flex Server Storage' and meterName eq 'Storage Data Stored'"
        if ($p) { $prices.pg_storage_gb_mo = [double]$p }
        $prices.source = "prices.azure.com ($Location, $(Get-Date -Format 'yyyy-MM-dd'))"
    } catch {
        # Se mantienen los valores de respaldo documentados.
    }

    # DEMO: 2 apps x (0,25 vCPU + 0,5 GiB), escala a cero; PostgreSQL B1ms + 32 GB.
    $acaPerActiveHour = (0.5 * 3600 * $prices.aca_vcpu_active) + (1.0 * 3600 * $prices.aca_mem_active)
    $aca = $acaPerActiveHour * $ActiveHours
    $pgCompute = $prices.pg_b1ms_hour * $LifetimeHours
    $pgStorage = 32 * $prices.pg_storage_gb_mo * ($LifetimeHours / 730)
    $logs = [math]::Min(0.1, 0.02 * $LifetimeHours / 24 * 5) * $prices.la_ingest_gb
    $total = $aca + $pgCompute + $pgStorage + $logs

    return [pscustomobject]@{
        Fuente                  = $prices.source
        HorasActivasApps        = $ActiveHours
        HorasDeVida             = $LifetimeHours
        ContainerAppsUSD        = [math]::Round($aca, 2)
        PostgresComputoUSD      = [math]::Round($pgCompute, 2)
        PostgresAlmacenUSD      = [math]::Round($pgStorage, 2)
        LogAnalyticsUSD         = [math]::Round($logs, 2)
        TotalEstimadoUSD        = [math]::Round($total, 2)
        ContainerAppsPorHoraUSD = [math]::Round($acaPerActiveHour, 3)
        PostgresPorHoraUSD      = [math]::Round($prices.pg_b1ms_hour, 4)
        Nota                    = 'Peor caso sin capa gratuita. La concesión gratuita de Container Apps de la suscripción ya la consumen otras apps; los 750 h de B1ms gratis de Azure for Students no se asumen.'
    }
}
