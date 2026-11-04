output "id" {
  description = "ID del entorno."
  value       = azurerm_container_app_environment.this.id
}

output "default_domain" {
  description = "Dominio por defecto del entorno (<app>.<dominio>)."
  value       = azurerm_container_app_environment.this.default_domain
}

output "static_ip_address" {
  description = "IP estática de entrada del entorno."
  value       = azurerm_container_app_environment.this.static_ip_address
}

output "uploads_storage_name" {
  description = "Nombre del almacenamiento del entorno para montar en la API."
  value       = azurerm_container_app_environment_storage.uploads.name
}
