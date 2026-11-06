output "vnet_id" {
  description = "ID de la VNet."
  value       = azurerm_virtual_network.this.id
}

output "apps_subnet_id" {
  description = "Subred para el entorno de Container Apps."
  value       = azurerm_subnet.apps.id
}

output "postgres_subnet_id" {
  description = "Subred delegada a PostgreSQL."
  value       = azurerm_subnet.postgres.id
}

output "postgres_private_dns_zone_id" {
  description = "Zona DNS privada de PostgreSQL."
  value       = azurerm_private_dns_zone.postgres.id
}
