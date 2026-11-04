# ============================================================================
# Kay Care Farmacia — entorno DEMO (bajo costo)
#
# - Container Apps de consumo con escala a cero.
# - PostgreSQL Flexible Server Burstable B1ms, sin HA, acceso público con TLS
#   obligatorio y firewall limitado a servicios de Azure.
# - Sin VNet ni redundancia de zona: existen puntos únicos de fallo
#   (ver docs/architecture/demo-vs-ha.md).
# ============================================================================

locals {
  env    = "demo"
  prefix = "${var.project}-${local.env}"
  tags = merge({
    project     = "kay-care-farmacia"
    environment = local.env
    purpose     = "academic-demo"
    managed_by  = "terraform"
    no_real_pii = "true"
  }, var.tags)

  backend_name  = "ca-${local.prefix}-api"
  frontend_name = "ca-${local.prefix}-web"
  # FQDN público calculado (evita un ciclo de dependencias entre las dos apps).
  frontend_fqdn = "${local.frontend_name}.${module.environment.default_domain}"

  registry_secret = var.registry_password == null ? {} : { "registry-password" = var.registry_password }
  registry = var.registry_password == null ? null : {
    server               = var.registry_server
    username             = var.registry_username
    password_secret_name = "registry-password"
  }
}

resource "random_string" "suffix" {
  length  = 5
  upper   = false
  special = false
}

resource "random_password" "db" {
  length           = 32
  special          = true
  override_special = "-_.~"
}

resource "random_password" "admin" {
  length           = 24
  special          = true
  override_special = "-_.~"
  min_upper        = 2
  min_lower        = 2
  min_numeric      = 2
}

resource "random_bytes" "app_key" {
  length = 32
}

resource "azurerm_resource_group" "this" {
  name     = "rg-${local.prefix}"
  location = var.location
  tags     = local.tags
}

module "monitoring" {
  source              = "../../modules/monitoring"
  name                = "log-${local.prefix}"
  location            = var.location
  resource_group_name = azurerm_resource_group.this.name
  retention_in_days   = 30
  daily_quota_gb      = 0.5
  tags                = local.tags
}

module "environment" {
  source                     = "../../modules/container-environment"
  name                       = "cae-${local.prefix}"
  location                   = var.location
  resource_group_name        = azurerm_resource_group.this.name
  log_analytics_workspace_id = module.monitoring.workspace_id
  storage_account_name       = "st${var.project}${local.env}${random_string.suffix.result}"
  storage_replication_type   = "LRS"
  share_quota_gb             = 5
  tags                       = local.tags
}

module "postgres" {
  source                       = "../../modules/postgres"
  name                         = "psql-${local.prefix}-${random_string.suffix.result}"
  location                     = var.location
  resource_group_name          = azurerm_resource_group.this.name
  sku_name                     = "B_Standard_B1ms"
  storage_mb                   = 32768
  administrator_password       = random_password.db.result
  backup_retention_days        = 7
  geo_redundant_backup_enabled = false
  high_availability_mode       = null
  allow_azure_services         = true
  log_analytics_workspace_id   = null
  tags                         = local.tags
}

module "backend" {
  source              = "../../modules/container-app"
  name                = local.backend_name
  container_name      = "api"
  resource_group_name = azurerm_resource_group.this.name
  environment_id      = module.environment.id
  image               = var.backend_image
  cpu                 = 0.5
  memory              = "1Gi"
  external_ingress    = false
  min_replicas        = 0
  max_replicas        = 2
  liveness_path       = "/up"
  readiness_path      = "/api/v1/store/health"
  registry            = local.registry
  storage_mount = {
    storage_name = module.environment.uploads_storage_name
    path         = "/app/storage/app/public"
  }

  secrets = merge(local.registry_secret, {
    "app-key"        = "base64:${random_bytes.app_key.base64}"
    "db-password"    = random_password.db.result
    "admin-password" = random_password.admin.result
  })

  secret_env = {
    APP_KEY                 = "app-key"
    DB_PASSWORD             = "db-password"
    PHARMACY_ADMIN_PASSWORD = "admin-password"
  }

  env = {
    APP_NAME                 = "Kay Care Farmacia"
    APP_ENV                  = "production"
    APP_DEBUG                = "false"
    APP_URL                  = "https://${local.frontend_fqdn}"
    APP_LOCALE               = "es"
    LOG_CHANNEL              = "stderr"
    LOG_LEVEL                = "info"
    DB_CONNECTION            = "pgsql"
    DB_HOST                  = module.postgres.fqdn
    DB_PORT                  = "5432"
    DB_DATABASE              = module.postgres.database_name
    DB_USERNAME              = module.postgres.administrator_login
    DB_SSLMODE               = "require"
    SESSION_DRIVER           = "database"
    SESSION_LIFETIME         = "60"
    SESSION_ENCRYPT          = "true"
    SESSION_COOKIE           = "kcf_pharmacy_session"
    SESSION_SECURE_COOKIE    = "true"
    SESSION_SAME_SITE        = "lax"
    CACHE_STORE              = "database"
    QUEUE_CONNECTION         = "sync"
    SANCTUM_STATEFUL_DOMAINS = local.frontend_fqdn
    PHARMACY_ALLOWED_ORIGINS = "https://${local.frontend_fqdn}"
    TRUSTED_PROXIES          = "*"
    RUN_MIGRATIONS           = "true"
    RUN_SEEDERS              = tostring(var.seed_demo_data)
    PHARMACY_ADMIN_EMAIL     = var.admin_email
  }

  tags = local.tags
}

module "frontend" {
  source              = "../../modules/container-app"
  name                = local.frontend_name
  container_name      = "web"
  resource_group_name = azurerm_resource_group.this.name
  environment_id      = module.environment.id
  image               = var.frontend_image
  cpu                 = 0.25
  memory              = "0.5Gi"
  external_ingress    = true
  min_replicas        = 0
  max_replicas        = 2
  liveness_path       = "/healthz"
  readiness_path      = "/healthz"
  registry            = local.registry
  secrets             = local.registry_secret

  env = {
    BACKEND_UPSTREAM = "http://${local.backend_name}"
  }

  tags = local.tags

  depends_on = [module.backend]
}
