output "workspace_id" {
  description = "ID del workspace de Log Analytics."
  value       = azurerm_log_analytics_workspace.this.id
}

output "workspace_name" {
  description = "Nombre del workspace."
  value       = azurerm_log_analytics_workspace.this.name
}
