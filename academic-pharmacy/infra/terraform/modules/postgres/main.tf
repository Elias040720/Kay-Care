# Azure Database for PostgreSQL Flexible Server — base de datos PROPIA de
# la farmacia (no se comparte con el sistema clínico).

resource "azurerm_postgresql_flexible_server" "this" {
  name                = var.name
  resource_group_name = var.resource_group_name
  location            = var.location
  version             = var.postgres_version
  sku_name            = var.sku_name
  storage_mb          = var.storage_mb
  zone                = var.zone

  administrator_login    = var.administrator_login
  administrator_password = var.administrator_password

  backup_retention_days        = var.backup_retention_days
  geo_redundant_backup_enabled = var.geo_redundant_backup_enabled

  # Acceso público (demo, con reglas de firewall) o integración en VNet (HA).
  public_network_access_enabled = var.delegated_subnet_id == null
  delegated_subnet_id           = var.delegated_subnet_id
  private_dns_zone_id           = var.private_dns_zone_id

  authentication {
    password_auth_enabled         = true
    active_directory_auth_enabled = false
  }

  dynamic "high_availability" {
    for_each = var.high_availability_mode == null ? [] : [var.high_availability_mode]
    content {
      mode                      = high_availability.value
      standby_availability_zone = high_availability.value == "ZoneRedundant" ? var.standby_zone : null
    }
  }

  tags = var.tags

  lifecycle {
    # Azure puede intercambiar zonas tras una conmutación por error.
    ignore_changes = [zone, high_availability[0].standby_availability_zone]
  }
}

resource "azurerm_postgresql_flexible_server_database" "app" {
  name      = var.database_name
  server_id = azurerm_postgresql_flexible_server.this.id
  charset   = "UTF8"
  collation = "en_US.utf8"
}

# TLS obligatorio en todas las conexiones.
resource "azurerm_postgresql_flexible_server_configuration" "require_tls" {
  name      = "require_secure_transport"
  server_id = azurerm_postgresql_flexible_server.this.id
  value     = "on"
}

resource "azurerm_postgresql_flexible_server_configuration" "min_tls" {
  name      = "ssl_min_protocol_version"
  server_id = azurerm_postgresql_flexible_server.this.id
  value     = "TLSv1.2"
}

# Solo en modo público: permite tráfico desde servicios de Azure (las salidas
# de Container Apps de consumo no tienen IP fija). La autenticación y TLS
# siguen siendo obligatorios; en HA se usa red privada.
resource "azurerm_postgresql_flexible_server_firewall_rule" "azure_services" {
  count            = var.delegated_subnet_id == null && var.allow_azure_services ? 1 : 0
  name             = "allow-azure-services"
  server_id        = azurerm_postgresql_flexible_server.this.id
  start_ip_address = "0.0.0.0"
  end_ip_address   = "0.0.0.0"
}

resource "azurerm_monitor_diagnostic_setting" "postgres" {
  count                      = var.log_analytics_workspace_id == null ? 0 : 1
  name                       = "diag-${var.name}"
  target_resource_id         = azurerm_postgresql_flexible_server.this.id
  log_analytics_workspace_id = var.log_analytics_workspace_id

  enabled_log {
    category = "PostgreSQLLogs"
  }
}
