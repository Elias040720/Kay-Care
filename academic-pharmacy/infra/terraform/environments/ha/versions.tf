terraform {
  required_version = ">= 1.6.0, < 2.0.0"

  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 4.14"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }

  # Estado remoto recomendado (cifrado y con bloqueo). Crear la cuenta de
  # almacenamiento manualmente y descomentar; el estado contiene secretos.
  #
  # backend "azurerm" {
  #   resource_group_name  = "rg-kcf-tfstate"
  #   storage_account_name = "stkcftfstate<sufijo>"
  #   container_name       = "tfstate"
  #   key                  = "academic-pharmacy/ha.tfstate"
  #   use_oidc             = true
  #   use_azuread_auth     = true
  # }
}
