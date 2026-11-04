#!/bin/sh
# Arranque del contenedor de la API (Kay Care Farmacia).
set -eu

log() { printf '[entrypoint] %s\n' "$*" >&2; }

# 1) Clave de aplicación: obligatoria. Solo en desarrollo local se permite
#    generar una efímera (las sesiones se invalidan al reiniciar).
if [ -z "${APP_KEY:-}" ]; then
  if [ "${ALLOW_EPHEMERAL_APP_KEY:-false}" = "true" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    log "APP_KEY no definida: se generó una clave efímera (solo desarrollo)."
  else
    log "ERROR: APP_KEY es obligatoria."
    exit 1
  fi
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "true" ]; then
  log "ERROR: APP_DEBUG=true no está permitido en producción."
  exit 1
fi

# 2) Espera a PostgreSQL (máx. ~90 s).
attempt=0
until php -r '
  $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE"), getenv("DB_SSLMODE") ?: "prefer");
  try { new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]); exit(0); } catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 30 ]; then
    log "ERROR: la base de datos no respondió a tiempo."
    exit 1
  fi
  log "Esperando a PostgreSQL ($attempt/30)…"
  sleep 3
done

# 3) Caché de configuración y rutas (código de solo lectura, cache escribible).
php artisan config:cache --no-ansi >/dev/null
php artisan route:cache --no-ansi >/dev/null

# 4) Migraciones con bloqueo (--isolated) para que varias réplicas no
#    migren a la vez. En HA se recomienda un job dedicado (ver docs).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  log "Aplicando migraciones…"
  php artisan migrate --force --isolated --no-ansi
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  log "Cargando datos demo (idempotente)…"
  php artisan db:seed --force --no-ansi
fi

log "Iniciando servidor en :8080"
exec "$@"
