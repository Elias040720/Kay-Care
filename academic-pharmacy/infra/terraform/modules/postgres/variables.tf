variable "name" {
  description = "Nombre global del servidor PostgreSQL."
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

variable "postgres_version" {
  description = "Versión mayor de PostgreSQL."
  type        = string
  default     = "16"
}

variable "sku_name" {
  description = "SKU (p. ej. B_Standard_B1ms para demo, GP_Standard_D2ds_v5 para HA)."
  type        = string
}

variable "storage_mb" {
  description = "Almacenamiento en MB."
  type        = number
  default     = 32768
}

variable "zone" {
  description = "Zona de disponibilidad primaria."
  type        = string
  default     = "1"
}

variable "standby_zone" {
  description = "Zona del réplica en espera (solo ZoneRedundant)."
  type        = string
  default     = "2"
}

variable "high_availability_mode" {
  description = "null (sin HA), \"SameZone\" o \"ZoneRedundant\". Burstable no admite HA."
  type        = string
  default     = null

  validation {
    condition     = var.high_availability_mode == null || contains(["SameZone", "ZoneRedundant"], coalesce(var.high_availability_mode, "none"))
    error_message = "high_availability_mode debe ser null, SameZone o ZoneRedundant."
  }
}

variable "administrator_login" {
  description = "Usuario administrador."
  type        = string
  default     = "kcfadmin"
}

variable "administrator_password" {
  description = "Contraseña del administrador (generada por Terraform)."
  type        = string
  sensitive   = true
}

variable "database_name" {
  description = "Base de datos de la aplicación."
  type        = string
  default     = "kaycare_pharmacy"
}

variable "backup_retention_days" {
  description = "Retención de copias de seguridad (7–35 días)."
  type        = number
  default     = 7
}

variable "geo_redundant_backup_enabled" {
  description = "Copias de seguridad geo-redundantes."
  type        = bool
  default     = false
}

variable "delegated_subnet_id" {
  description = "Subred delegada (modo privado). null = acceso público con firewall."
  type        = string
  default     = null
}

variable "private_dns_zone_id" {
  description = "Zona DNS privada (obligatoria con subred delegada)."
  type        = string
  default     = null
}

variable "allow_azure_services" {
  description = "Regla 0.0.0.0 para servicios de Azure (solo modo público)."
  type        = bool
  default     = true
}

variable "log_analytics_workspace_id" {
  description = "Workspace para diagnósticos (null = sin diagnósticos)."
  type        = string
  default     = null
}

variable "tags" {
  description = "Etiquetas comunes."
  type        = map(string)
  default     = {}
}
