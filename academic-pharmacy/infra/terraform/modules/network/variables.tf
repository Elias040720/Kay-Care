variable "name" {
  description = "Nombre de la red virtual."
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

variable "address_space" {
  description = "Espacio de direcciones de la VNet."
  type        = string
  default     = "10.40.0.0/16"
}

variable "apps_subnet_prefix" {
  description = "Subred del entorno de Container Apps (mínimo /27 con perfiles de carga)."
  type        = string
  default     = "10.40.0.0/23"
}

variable "postgres_subnet_prefix" {
  description = "Subred delegada a PostgreSQL Flexible Server."
  type        = string
  default     = "10.40.4.0/24"
}

variable "dns_prefix" {
  description = "Prefijo de la zona DNS privada de PostgreSQL."
  type        = string
}

variable "tags" {
  description = "Etiquetas comunes."
  type        = map(string)
  default     = {}
}
