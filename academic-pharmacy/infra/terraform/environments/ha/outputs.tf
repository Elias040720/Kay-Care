output "resource_group" {
  description = "Grupo de recursos del entorno."
  value       = azurerm_resource_group.this.name
}

output "frontend_url" {
  description = "URL pública de la tienda (válida solo tras un apply verificado)."
  value       = "https://${module.frontend.fqdn}"
}

output "backend_internal_fqdn" {
  description = "FQDN interno de la API (no accesible desde Internet)."
  value       = module.backend.fqdn
}

output "postgres_fqdn" {
  description = "Servidor PostgreSQL."
  value       = module.postgres.fqdn
}

output "log_analytics_workspace" {
  description = "Workspace de Log Analytics."
  value       = module.monitoring.workspace_name
}

output "admin_email" {
  description = "Cuenta administradora demo."
  value       = var.admin_email
}

output "admin_password" {
  description = "Contraseña generada del administrador demo (terraform output -raw admin_password)."
  value       = random_password.admin.result
  sensitive   = true
}
