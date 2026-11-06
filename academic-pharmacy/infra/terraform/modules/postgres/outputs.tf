output "server_id" {
  description = "ID del servidor."
  value       = azurerm_postgresql_flexible_server.this.id
}

output "fqdn" {
  description = "FQDN del servidor."
  value       = azurerm_postgresql_flexible_server.this.fqdn
}

output "database_name" {
  description = "Nombre de la base de datos de la aplicación."
  value       = azurerm_postgresql_flexible_server_database.app.name
}

output "administrator_login" {
  description = "Usuario administrador."
  value       = azurerm_postgresql_flexible_server.this.administrator_login
}
