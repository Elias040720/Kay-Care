variable "name" {
  description = "Nombre del workspace de Log Analytics."
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

variable "retention_in_days" {
  description = "Días de retención de logs (30–730)."
  type        = number
  default     = 30

  validation {
    condition     = var.retention_in_days >= 30 && var.retention_in_days <= 730
    error_message = "La retención debe estar entre 30 y 730 días."
  }
}

variable "daily_quota_gb" {
  description = "Tope diario de ingesta en GB (-1 sin tope)."
  type        = number
  default     = -1
}

variable "tags" {
  description = "Etiquetas comunes."
  type        = map(string)
  default     = {}
}
