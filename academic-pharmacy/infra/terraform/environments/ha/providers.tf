provider "azurerm" {
  # null => se toma de ARM_SUBSCRIPTION_ID (OIDC en GitHub Actions / az login local).
  subscription_id = var.subscription_id

  # Autenticación sin secretos desde GitHub Actions (federación OIDC).
  use_oidc = var.use_oidc

  features {
    resource_group {
      # Evita borrar un grupo que contenga recursos no gestionados por este estado.
      prevent_deletion_if_contains_resources = true
    }
  }
}

provider "random" {}
