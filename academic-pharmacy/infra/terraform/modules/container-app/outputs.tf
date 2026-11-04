output "id" {
  description = "ID de la Container App."
  value       = azurerm_container_app.this.id
}

output "name" {
  description = "Nombre de la Container App."
  value       = azurerm_container_app.this.name
}

output "fqdn" {
  description = "FQDN del ingress (público o interno)."
  value       = azurerm_container_app.this.ingress[0].fqdn
}

output "latest_revision_name" {
  description = "Revisión activa."
  value       = azurerm_container_app.this.latest_revision_name
}
