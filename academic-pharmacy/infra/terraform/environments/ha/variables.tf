variable "subscription_id" {
  description = "Suscripción de Azure (null = usar ARM_SUBSCRIPTION_ID)."
  type        = string
  default     = null
}

variable "use_oidc" {
  description = "Autenticación OIDC (GitHub Actions)."
  type        = bool
  default     = false
}

variable "location" {
  description = "Región de Azure con zonas de disponibilidad."
  type        = string
  default     = "eastus2"
}

variable "project" {
  description = "Prefijo corto del proyecto (minúsculas)."
  type        = string
  default     = "kcf"

  validation {
    condition     = can(regex("^[a-z0-9]{2,8}$", var.project))
    error_message = "Usa de 2 a 8 caracteres en minúscula o dígitos."
  }
}

variable "backend_image" {
  description = "Imagen de la API (etiqueta inmutable)."
  type        = string
}

variable "frontend_image" {
  description = "Imagen del frontend (etiqueta inmutable)."
  type        = string
}

variable "registry_server" {
  description = "Servidor del registro de contenedores."
  type        = string
  default     = "ghcr.io"
}

variable "registry_username" {
  description = "Usuario del registro (null = imágenes públicas)."
  type        = string
  default     = null
}

variable "registry_password" {
  description = "Token de solo lectura del registro. Nunca en Git."
  type        = string
  default     = null
  sensitive   = true
}

variable "admin_email" {
  description = "Correo de la cuenta administradora."
  type        = string
  default     = "admin@farmacia.demo"
}

variable "seed_demo_data" {
  description = "Cargar catálogo y pedidos ficticios al arrancar."
  type        = bool
  default     = true
}

variable "postgres_sku" {
  description = "SKU General Purpose / Memory Optimized (la HA no existe en Burstable)."
  type        = string
  default     = "GP_Standard_D2ds_v5"

  validation {
    condition     = startswith(var.postgres_sku, "GP_") || startswith(var.postgres_sku, "MO_")
    error_message = "La HA de PostgreSQL requiere General Purpose o Memory Optimized."
  }
}

variable "vnet_address_space" {
  description = "Espacio de direcciones de la VNet."
  type        = string
  default     = "10.40.0.0/16"
}

variable "apps_subnet_prefix" {
  description = "Subred del entorno de Container Apps (/23 recomendado)."
  type        = string
  default     = "10.40.0.0/23"
}

variable "postgres_subnet_prefix" {
  description = "Subred delegada a PostgreSQL."
  type        = string
  default     = "10.40.4.0/24"
}

variable "frontend_min_replicas" {
  description = "Réplicas mínimas del frontend (≥ 2)."
  type        = number
  default     = 2

  validation {
    condition     = var.frontend_min_replicas >= 2
    error_message = "En HA se requieren al menos 2 réplicas."
  }
}

variable "frontend_max_replicas" {
  description = "Réplicas máximas del frontend."
  type        = number
  default     = 6
}

variable "backend_min_replicas" {
  description = "Réplicas mínimas de la API (≥ 2; 3 para cubrir 3 zonas)."
  type        = number
  default     = 3

  validation {
    condition     = var.backend_min_replicas >= 2
    error_message = "En HA se requieren al menos 2 réplicas."
  }
}

variable "backend_max_replicas" {
  description = "Réplicas máximas de la API."
  type        = number
  default     = 10
}

variable "tags" {
  description = "Etiquetas adicionales."
  type        = map(string)
  default     = {}
}
