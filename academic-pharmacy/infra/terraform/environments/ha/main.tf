# ============================================================================
# Kay Care Farmacia — entorno HA (alta disponibilidad regional)
#
# - VNet propia: entorno de Container Apps con redundancia de zona y
#   PostgreSQL con integración privada (sin acceso público).
# - PostgreSQL General Purpose con HA ZoneRedundant (réplica en espera
#   síncrona en otra zona), copias geo-redundantes y 35 días de retención.
# - Réplicas mínimas ≥ 2 por servicio, repartidas entre zonas.
# - Azure Files ZRS para imágenes subidas (compartidas entre réplicas).
# Límite honesto: sigue siendo UNA región (ver docs/architecture/demo-vs-ha.md).
# ============================================================================

locals {
  env    = "ha"
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
  retention_in_days   = 90
  daily_quota_gb      = -1
  tags                = local.tags
}

module "network" {
  source                 = "../../modules/network"
  name                   = "vnet-${local.prefix}"
  location               = var.location
  resource_group_name    = azurerm_resource_group.this.name
  address_space          = var.vnet_address_space
  apps_subnet_prefix     = var.apps_subnet_prefix
  postgres_subnet_prefix = var.postgres_subnet_prefix
  dns_prefix             = "${local.prefix}-${random_string.suffix.result}"
  tags                   = local.tags
}

module "environment" {
  source                     = "../../modules/container-environment"
  name                       = "cae-${local.prefix}"
  location                   = var.location
  resource_group_name        = azurerm_resource_group.this.name
  log_analytics_workspace_id = module.monitoring.workspace_id
  infrastructure_subnet_id   = module.network.apps_subnet_id
  zone_redundancy_enabled    = true
  storage_account_name       = "st${var.project}${local.env}${random_string.suffix.result}"
  storage_replication_type   = "ZRS"
  share_quota_gb             = 20
  tags                       = local.tags
}

module "postgres" {
  source                       = "../../modules/postgres"
  name                         = "psql-${local.prefix}-${random_string.suffix.result}"
  location                     = var.location
  resource_group_name          = azurerm_resource_group.this.name
  sku_name                     = var.postgres_sku
  storage_mb                   = 65536
  zone                         = "1"
  standby_zone                 = "2"
  high_availability_mode       = "ZoneRedundant"
  administrator_password       = random_password.db.result
  backup_retention_days        = 35
  geo_redundant_backup_enabled = true
  delegated_subnet_id          = module.network.postgres_subnet_id
  private_dns_zone_id          = module.network.postgres_private_dns_zone_id
  log_analytics_workspace_id   = module.monitoring.workspace_id
  tags                         = local.tags

  depends_on = [module.network]
}

module "backend" {
  source              = "../../modules/container-app"
  name                = local.backend_name
  container_name      = "api"
  resource_group_name = azurerm_resource_group.this.name
  environment_id      = module.environment.id
  image               = var.backend_image
  cpu                 = 1.0
  memory              = "2Gi"
  external_ingress    = false
  min_replicas        = var.backend_min_replicas
  max_replicas        = var.backend_max_replicas
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
    LOG_LEVEL                = "warning"
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
  cpu                 = 0.5
  memory              = "1Gi"
  external_ingress    = true
  min_replicas        = var.frontend_min_replicas
  max_replicas        = var.frontend_max_replicas
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
