# Container App genérica (frontend o backend). Los secretos se guardan como
# secretos de la Container App y se exponen al contenedor por referencia.

resource "azurerm_container_app" "this" {
  name                         = var.name
  container_app_environment_id = var.environment_id
  resource_group_name          = var.resource_group_name
  revision_mode                = "Single"
  workload_profile_name        = "Consumption"
  tags                         = var.tags

  dynamic "secret" {
    for_each = nonsensitive(toset(keys(var.secrets)))
    content {
      name  = secret.value
      value = var.secrets[secret.value]
    }
  }

  dynamic "registry" {
    for_each = var.registry == null ? [] : [var.registry]
    content {
      server               = registry.value.server
      username             = registry.value.username
      password_secret_name = registry.value.password_secret_name
    }
  }

  ingress {
    external_enabled = var.external_ingress
    target_port      = var.target_port
    transport        = "http"
    # Interno: el tráfico no sale de la red del entorno. Externo: HTTPS
    # obligatorio (HTTP se redirige) con certificado gestionado por Azure.
    allow_insecure_connections = var.external_ingress ? false : var.allow_insecure_internal

    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  template {
    min_replicas = var.min_replicas
    max_replicas = var.max_replicas

    container {
      name   = var.container_name
      image  = var.image
      cpu    = var.cpu
      memory = var.memory

      dynamic "env" {
        for_each = var.env
        content {
          name  = env.key
          value = env.value
        }
      }

      dynamic "env" {
        for_each = var.secret_env
        content {
          name        = env.key
          secret_name = env.value
        }
      }

      liveness_probe {
        transport               = "HTTP"
        port                    = var.target_port
        path                    = var.liveness_path
        initial_delay           = 10
        interval_seconds        = 20
        timeout                 = 5
        failure_count_threshold = 3
      }

      readiness_probe {
        transport               = "HTTP"
        port                    = var.target_port
        path                    = var.readiness_path
        interval_seconds        = 10
        timeout                 = 5
        failure_count_threshold = 3
        success_count_threshold = 1
      }

      startup_probe {
        transport               = "HTTP"
        port                    = var.target_port
        path                    = var.liveness_path
        interval_seconds        = 5
        timeout                 = 3
        failure_count_threshold = 24
      }

      dynamic "volume_mounts" {
        for_each = var.storage_mount == null ? [] : [var.storage_mount]
        content {
          name = "data"
          path = volume_mounts.value.path
        }
      }
    }

    dynamic "volume" {
      for_each = var.storage_mount == null ? [] : [var.storage_mount]
      content {
        name         = "data"
        storage_name = volume.value.storage_name
        storage_type = "AzureFile"
      }
    }

    http_scale_rule {
      name                = "http-concurrency"
      concurrent_requests = tostring(var.scale_concurrent_requests)
    }
  }
}
