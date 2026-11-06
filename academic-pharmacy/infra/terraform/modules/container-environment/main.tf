# Entorno de Azure Container Apps (perfil de consumo) + recurso compartido de
# Azure Files para las imágenes de productos subidas por el administrador
# (el disco del contenedor es efímero y no se comparte entre réplicas).
# El recurso compartido es opcional: el perfil de costo cero lo desactiva.

resource "azurerm_container_app_environment" "this" {
  name                       = var.name
  location                   = var.location
  resource_group_name        = var.resource_group_name
  log_analytics_workspace_id = var.log_analytics_workspace_id

  # La redundancia de zona exige integración con una subred propia.
  infrastructure_subnet_id = var.infrastructure_subnet_id
  zone_redundancy_enabled  = var.infrastructure_subnet_id == null ? false : var.zone_redundancy_enabled

  workload_profile {
    name                  = "Consumption"
    workload_profile_type = "Consumption"
  }

  tags = var.tags

  lifecycle {
    ignore_changes = [infrastructure_resource_group_name]
  }
}

resource "azurerm_storage_account" "uploads" {
  count = var.enable_uploads_share ? 1 : 0

  name                            = var.storage_account_name
  resource_group_name             = var.resource_group_name
  location                        = var.location
  account_tier                    = "Standard"
  account_replication_type        = var.storage_replication_type
  min_tls_version                 = "TLS1_2"
  https_traffic_only_enabled      = true
  allow_nested_items_to_be_public = false
  shared_access_key_enabled       = true # requerido por el montaje SMB de Container Apps
  tags                            = var.tags
}

resource "azurerm_storage_share" "uploads" {
  count = var.enable_uploads_share ? 1 : 0

  name               = "product-images"
  storage_account_id = azurerm_storage_account.uploads[0].id
  quota              = var.share_quota_gb
}

resource "azurerm_container_app_environment_storage" "uploads" {
  count = var.enable_uploads_share ? 1 : 0

  name                         = "product-images"
  container_app_environment_id = azurerm_container_app_environment.this.id
  account_name                 = azurerm_storage_account.uploads[0].name
  share_name                   = azurerm_storage_share.uploads[0].name
  access_key                   = azurerm_storage_account.uploads[0].primary_access_key
  access_mode                  = "ReadWrite"
}
