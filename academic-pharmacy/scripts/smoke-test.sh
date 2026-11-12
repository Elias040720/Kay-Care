#!/usr/bin/env bash
# Prueba de humo del stack Docker de Kay Care Farmacia a través del proxy nginx.
#   BASE_URL=http://localhost:8088 ADMIN_PASSWORD=... ./scripts/smoke-test.sh
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8088}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@farmacia.demo}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:?Define ADMIN_PASSWORD}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT
pass=0; fail=0

check() { # nombre, condición (0 = ok)
  if [ "$2" -eq 0 ]; then pass=$((pass + 1)); printf '  ✔ %s\n' "$1"; else fail=$((fail + 1)); printf '  ✘ %s\n' "$1"; fi
}
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
xsrf() { grep XSRF-TOKEN "$JAR" | awk '{print $7}' | sed 's/%3D/=/g'; }

echo "Smoke test → $BASE_URL"
check "frontend /healthz 200" "$([ "$(code "$BASE_URL/healthz")" = 200 ]; echo $?)"
check "SPA index 200" "$([ "$(code "$BASE_URL/catalogo")" = 200 ]; echo $?)"
check "API health 200 (vía proxy)" "$([ "$(code "$BASE_URL/api/v1/store/health")" = 200 ]; echo $?)"

headers="$(curl -s -D - -o /dev/null "$BASE_URL/")"
for h in "Content-Security-Policy" "X-Frame-Options: DENY" "X-Content-Type-Options: nosniff" "Referrer-Policy"; do
  check "cabecera $h" "$(grep -qi "$h" <<<"$headers"; echo $?)"
done
check "sin versión de nginx" "$(! grep -qi 'Server: nginx/' <<<"$headers"; echo $?)"

products="$(curl -s "$BASE_URL/api/v1/store/products?per_page=48")"
total="$(sed -n 's/.*"total":\([0-9]*\).*/\1/p' <<<"$products")"
check "catálogo con productos (total=$total)" "$([ "${total:-0}" -ge 20 ]; echo $?)"
# Producto con stock para el checkout (corridas previas pueden agotar alguno).
in_stock="$(curl -s "$BASE_URL/api/v1/store/products?in_stock=1&sort=stock_desc&per_page=1")"
pid="$(sed -n 's/^{"data":\[{"id":\([0-9]*\).*/\1/p' <<<"$in_stock")"

check "admin sin sesión → 401" "$([ "$(code "$BASE_URL/api/v1/store/admin/dashboard" -H 'Accept: application/json')" = 401 ]; echo $?)"

# Flujo SPA: cookie CSRF → login → dashboard.
curl -s -c "$JAR" -b "$JAR" -o /dev/null "$BASE_URL/api/v1/store/sanctum/csrf-cookie" -H "Referer: $BASE_URL/"
check "cookie XSRF-TOKEN emitida" "$([ -n "$(xsrf)" ]; echo $?)"
check "POST sin token CSRF → 419" "$([ "$(code -b "$JAR" -X POST "$BASE_URL/api/v1/store/admin/login" -H 'Accept: application/json' -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/" -H 'Content-Type: application/json' -d '{}')" = 419 ]; echo $?)"
login="$(code -c "$JAR" -b "$JAR" -X POST "$BASE_URL/api/v1/store/admin/login" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/admin/login" -H "X-XSRF-TOKEN: $(xsrf)" \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")"
check "login administrador 200" "$([ "$login" = 200 ]; echo $?)"
check "cookie de sesión HttpOnly propia" "$(grep -q 'kcf_pharmacy_session' "$JAR" && grep -q '#HttpOnly_' "$JAR"; echo $?)"
check "dashboard con sesión 200" "$([ "$(code -b "$JAR" "$BASE_URL/api/v1/store/admin/dashboard" -H 'Accept: application/json' -H "Referer: $BASE_URL/admin")" = 200 ]; echo $?)"

# Checkout idempotente.
key="smoke-$(date +%s)-$RANDOM-kcf"
body="{\"buyer_alias\":\"Comprador Smoke\",\"accept_demo_terms\":true,\"items\":[{\"product_id\":$pid,\"quantity\":1}]}"
post() { curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST "$BASE_URL/api/v1/store/checkout" -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/checkout" -H "X-XSRF-TOKEN: $(xsrf)" -H "Idempotency-Key: $key" -d "$1"; }
check "checkout 201" "$([ "$(post "$body")" = 201 ]; echo $?)"
check "reintento misma clave 200 (replay)" "$([ "$(post "$body")" = 200 ]; echo $?)"
check "misma clave, otro contenido 409" "$([ "$(post "${body/\"quantity\":1/\"quantity\":2}")" = 409 ]; echo $?)"

echo "Resultado: $pass OK, $fail fallos"
[ "$fail" -eq 0 ]
