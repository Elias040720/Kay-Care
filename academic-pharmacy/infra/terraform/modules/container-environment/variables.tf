variable "name" {
  description = "Nombre del entorno de Container Apps."
  type        = string
}

variable "location" {
  description = "Región de Azure."
  type        = string
}

variable "resource_group_name" {
  description = "Grupo de recursos destino."
  type        = string
}

variable "log_analytics_workspace_id" {
  description = "Workspace de Log Analytics para logs de consola y sistema."
  type        = string
}

variable "infrastructure_subnet_id" {
  description = "Subred delegada (null = red gestionada por Azure, sin redundancia de zona)."
  type        = string
  default     = null
}

variable "zone_redundancy_enabled" {
  description = "Distribuye réplicas entre zonas de disponibilidad (requiere subred)."
  type        = bool
  default     = false
}

variable "storage_account_name" {
  description = "Cuenta de almacenamiento para Azure Files (3–24 minúsculas/dígitos, única global)."
  type        = string

  validation {
    condition     = can(regex("^[a-z0-9]{3,24}$", var.storage_account_name))
    error_message = "Usa entre 3 y 24 caracteres en minúscula o dígitos."
  }
}

variable "storage_replication_type" {
  description = "Replicación de la cuenta (LRS en demo, ZRS en HA)."
  type        = string
  default     = "LRS"
}

variable "share_quota_gb" {
  description = "Cuota del recurso compartido en GB."
  type        = number
  default     = 5
}

variable "tags" {
  description = "Etiquetas comunes."
  type        = map(string)
  default     = {}
}
