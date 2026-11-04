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
  description = "Región de Azure."
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
  description = "Imagen de la API (etiqueta inmutable, p. ej. ghcr.io/<owner>/kay-care-pharmacy-backend:sha-abc1234)."
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
  description = "Token de solo lectura del registro (read:packages). Nunca en Git."
  type        = string
  default     = null
  sensitive   = true
}

variable "admin_email" {
  description = "Correo de la cuenta administradora demo."
  type        = string
  default     = "admin@farmacia.demo"
}

variable "seed_demo_data" {
  description = "Cargar catálogo y pedidos ficticios al arrancar."
  type        = bool
  default     = true
}

variable "tags" {
  description = "Etiquetas adicionales."
  type        = map(string)
  default     = {}
}
