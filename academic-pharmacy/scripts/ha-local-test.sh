#!/usr/bin/env bash
# Prueba LOCAL de tolerancia a fallos (no usa la nube).
#
# 1. Escala la API a 2 réplicas detrás del nginx del frontend.
# 2. Envía tráfico continuo a la tienda.
# 3. Detiene una réplica a mitad de la prueba (fallo simulado).
# 4. Mide cuántas solicitudes fallaron y vuelve a 1 réplica.
#
# nginx resuelve "backend" con el DNS de Docker (resolver con valid=10s) y,
# si una réplica rechaza la conexión, reintenta en la otra
# (proxy_next_upstream por defecto: error timeout).
#
# Uso (desde academic-pharmacy/, con el stack levantado):
#   bash scripts/ha-local-test.sh            # 60 solicitudes por fase
#   REQUESTS=120 bash scripts/ha-local-test.sh
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8088}"
REQUESTS="${REQUESTS:-60}"
cd "$(dirname "$0")/.."

log() { printf '\n== %s\n' "$*"; }

# Ritmo ~1,5 solicitudes/s: por debajo del rate limit de la API (120/min por IP),
# para que un 429 no se confunda con un fallo de disponibilidad.
burst() {
  local label="$1" ok=0 fail=0 code codes=""
  for _ in $(seq 1 "$REQUESTS"); do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$BASE_URL/api/v1/store/products?per_page=1" || true)"
    if [ "$code" = "200" ]; then ok=$((ok + 1)); else fail=$((fail + 1)); codes="$codes $code"; fi
    sleep "${INTERVAL:-0.65}"
  done
  printf '%-34s OK=%-4s FALLOS=%s%s\n' "$label" "$ok" "$fail" "${codes:+ (códigos:$codes)}"
  RESULT_FAIL=$fail
}

log "Escalando la API a 2 réplicas"
docker compose up -d --no-recreate --scale backend=2 --wait --wait-timeout 180 backend >/dev/null
docker compose ps backend --format '{{.Name}}  {{.Status}}'
sleep 11 # deja expirar la caché DNS de nginx (valid=10s)

log "Fase 1: tráfico con 2 réplicas sanas"
burst "2 réplicas"
fail_healthy=$RESULT_FAIL

victim="$(docker compose ps backend --format '{{.Name}}' | tail -1)"
log "Fase 2: se detiene la réplica $victim (fallo simulado) con tráfico en curso"
( sleep 2; docker stop -t 2 "$victim" >/dev/null && echo "   réplica detenida: $victim" ) &
burst "1 réplica caída"
fail_degraded=$RESULT_FAIL
wait

log "Fase 3: recuperación (docker start)"
docker start "$victim" >/dev/null
for _ in $(seq 1 30); do
  status="$(docker inspect -f '{{.State.Health.Status}}' "$victim" 2>/dev/null || echo unknown)"
  [ "$status" = "healthy" ] && break
  sleep 3
done
echo "   estado de $victim: $status"
sleep 11
burst "recuperada"
fail_recovered=$RESULT_FAIL

log "Restaurando 1 réplica"
docker compose up -d --scale backend=1 --wait --wait-timeout 180 backend >/dev/null
docker compose ps backend --format '{{.Name}}  {{.Status}}'

log "Resumen"
total=$((REQUESTS * 3))
fails=$((fail_healthy + fail_degraded + fail_recovered))
echo "Solicitudes: $total · fallidas: $fails (sanas=$fail_healthy, durante el fallo=$fail_degraded, tras recuperar=$fail_recovered)"
if [ "$fails" -eq 0 ]; then
  echo "✔ La tienda siguió respondiendo con una réplica caída."
else
  echo "⚠ Hubo solicitudes fallidas: revisar la ventana de conmutación."
fi
echo "Nota: es una prueba local con Docker; no demuestra alta disponibilidad en Azure."
