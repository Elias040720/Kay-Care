variable "name" {
  description = "Nombre de la Container App (también nombre DNS interno)."
  type        = string
}

variable "container_name" {
  description = "Nombre del contenedor dentro de la revisión."
  type        = string
}

variable "resource_group_name" {
  description = "Grupo de recursos destino."
  type        = string
}

variable "environment_id" {
  description = "ID del entorno de Container Apps."
  type        = string
}

variable "image" {
  description = "Imagen completa con etiqueta inmutable (p. ej. ghcr.io/org/app:sha)."
  type        = string
}

variable "cpu" {
  description = "vCPU por réplica (0.25, 0.5, 0.75, 1.0…)."
  type        = number
  default     = 0.25
}

variable "memory" {
  description = "Memoria por réplica (debe ser 2× la CPU: 0.5Gi, 1Gi…)."
  type        = string
  default     = "0.5Gi"
}

variable "target_port" {
  description = "Puerto en el que escucha el contenedor."
  type        = number
  default     = 8080
}

variable "external_ingress" {
  description = "true = accesible desde Internet (HTTPS); false = solo dentro del entorno."
  type        = bool
}

variable "allow_insecure_internal" {
  description = "Permite HTTP plano para tráfico interno del entorno."
  type        = bool
  default     = true
}

variable "min_replicas" {
  description = "Réplicas mínimas (0 = escala a cero)."
  type        = number
  default     = 0
}

variable "max_replicas" {
  description = "Réplicas máximas."
  type        = number
  default     = 2
}

variable "scale_concurrent_requests" {
  description = "Solicitudes concurrentes por réplica antes de escalar."
  type        = number
  default     = 50
}

variable "env" {
  description = "Variables de entorno no sensibles."
  type        = map(string)
  default     = {}
}

variable "secret_env" {
  description = "Variables de entorno que referencian secretos: NOMBRE_VAR => nombre-secreto."
  type        = map(string)
  default     = {}
}

variable "secrets" {
  description = "Secretos de la Container App: nombre-secreto => valor."
  type        = map(string)
  default     = {}
  sensitive   = true
}

variable "registry" {
  description = "Credenciales del registro privado (null = imagen pública)."
  type = object({
    server               = string
    username             = string
    password_secret_name = string
  })
  default = null
}

variable "liveness_path" {
  description = "Ruta de la sonda de vida."
  type        = string
  default     = "/"
}

variable "readiness_path" {
  description = "Ruta de la sonda de disponibilidad."
  type        = string
  default     = "/"
}

variable "storage_mount" {
  description = "Montaje de Azure Files (null = sin volumen)."
  type = object({
    storage_name = string
    path         = string
  })
  default = null
}

variable "tags" {
  description = "Etiquetas comunes."
  type        = map(string)
  default     = {}
}
