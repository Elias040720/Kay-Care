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
  default     = "canadacentral"
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

variable "backend_cpu" {
  description = "vCPU de la API (0,25 = combinación mínima de Container Apps)."
  type        = number
  default     = 0.25
}

variable "backend_memory" {
  description = "Memoria de la API (debe corresponder a la vCPU: 0,25 → 0.5Gi)."
  type        = string
  default     = "0.5Gi"
}

variable "max_replicas" {
  description = "Réplicas máximas por app (1 mantiene el consumo dentro de la concesión gratuita)."
  type        = number
  default     = 1
}

variable "enable_uploads_share" {
  description = "Azure Files para imágenes subidas (false = disco efímero, costo cero)."
  type        = bool
  default     = false
}

variable "log_daily_quota_gb" {
  description = "Tope diario de Log Analytics (0,1 GB/día ≈ 3 GB/mes, dentro de los 5 GB gratuitos)."
  type        = number
  default     = 0.1
}

variable "budget_amount" {
  description = "Presupuesto mensual en la moneda de facturación (0 = no crear presupuesto)."
  type        = number
  default     = 1
}

variable "tags" {
  description = "Etiquetas adicionales."
  type        = map(string)
  default     = {}
}
