#!/usr/bin/env bash
# Regenera los diagramas Mermaid (docs/diagrams/*.mmd) en SVG y PNG usando
# la imagen oficial de mermaid-cli (no requiere Node ni Chrome locales).
#   ./scripts/render-diagrams.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIAGRAMS="$ROOT/docs/diagrams"
OUT="$DIAGRAMS/export"
IMAGE="${MERMAID_CLI_IMAGE:-minlag/mermaid-cli:11.4.2}"
mkdir -p "$OUT"

# En Git Bash para Windows, Docker necesita rutas de Windows.
host_path() { if command -v cygpath >/dev/null 2>&1; then cygpath -w "$1"; else printf '%s' "$1"; fi; }

for src in "$DIAGRAMS"/*.mmd; do
  name="$(basename "$src" .mmd)"
  for fmt in svg png; do
    extra=()
    [ "$fmt" = png ] && extra=(-s 2 -b white)
    MSYS_NO_PATHCONV=1 docker run --rm -u "$(id -u 2>/dev/null || echo 1000):$(id -g 2>/dev/null || echo 1000)" \
      -v "$(host_path "$DIAGRAMS"):/data" "$IMAGE" \
      -i "/data/$name.mmd" -o "/data/export/$name.$fmt" -c /data/mermaid-config.json "${extra[@]}" >/dev/null
    echo "✔ export/$name.$fmt"
  done
done
