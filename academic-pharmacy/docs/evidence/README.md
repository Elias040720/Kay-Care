# Evidencia — Kay Care Farmacia

Capturas reales generadas con **Playwright (Chromium)** contra el stack local `docker compose up --build` (http://localhost:8088), con `prefers-reduced-motion: reduce`. La fecha exacta de generación está en `evidence-report.json`.

Para regenerarlas:

```bash
cd academic-pharmacy && docker compose up -d --build
cd frontend && ADMIN_PASSWORD=<contraseña del .env> npx playwright test
```

| Archivo | Contenido |
|---|---|
| `01-inicio.png` | Inicio completo: hero «Tu bienestar, nuestra prioridad», categorías con iconos, banners, ofertas, destacados y pie |
| `02-busqueda-sugerencias.png` | Buscador del header con sugerencias en vivo desde la API («vitamina») |
| `03-catalogo.png` | Catálogo completo con accesos rápidos por categoría, filtros y paginación |
| `04-catalogo-busqueda.png` | Búsqueda «termómetro» (1 resultado) |
| `05-catalogo-filtros-orden.png` | Categoría «Cuidado personal» + solo con stock + precio descendente |
| `06-catalogo-ofertas.png` | Filtro «Ofertas de la semana» con etiquetas de descuento |
| `07-detalle-producto.png` | Detalle con precio anterior y actual, ahorro, stock y cantidad |
| `08-carrito-drawer.png` | Carrito lateral validado contra el servidor |
| `09-checkout-validacion.png` | Rechazo de un alias con forma de DNI |
| `10-checkout-listo.png` | Checkout simulado listo para confirmar |
| `11-confirmacion.png` | «Pedido realizado en entorno de demostración» con número y totales |
| `12-admin-login-error.png` | Login con credenciales incorrectas (mensaje genérico) |
| `13-admin-dashboard.png` | Dashboard con datos demo |
| `14-admin-productos.png` | Listado de productos |
| `15-admin-producto-formulario.png` | Formulario de alta (incluye «precio antes» para ofertas) |
| `16-admin-ajuste-stock.png` | Ajuste de stock |
| `17-admin-stock-movimientos.png` | Movimientos auditados |
| `18-admin-pedidos.png` | Pedidos con estados |
| `19-admin-pedido-detalle.png` | Detalle tras un cambio de estado |
| `20-movil-inicio.png` | Inicio en 390 px con buscador a ancho completo |
| `21-movil-menu.png` | Menú lateral móvil con categorías |
| `22-movil-catalogo.png` | Catálogo móvil tras navegar desde el menú, sin desbordamiento horizontal |
| `evidence-report.json` | Violaciones de axe por página (0 en las 6 auditadas) y errores de consola: solo el 401 esperado de la comprobación de sesión y el 422 del login incorrecto |

## Otros resultados verificados (12 de noviembre de 2026, equipo local)

| Verificación | Comando | Resultado |
|---|---|---|
| Backend | `php vendor/bin/phpunit` sobre PostgreSQL 16 (contenedor temporal) y SQLite | 70 pruebas y 415 aserciones OK en ambos |
| Lint backend | `vendor/bin/pint --test` | OK |
| Frontend | `npm test`, `npm run lint`, `npm run typecheck` | 45 pruebas OK; lint y tipos OK |
| Build | `docker compose up -d --build` (Vite dentro de la imagen) | OK |
| E2E | `npx playwright test` contra http://localhost:8088 | 3/3 recorridos OK, 22 capturas |
| Pedido en BD | `psql` sobre el pedido del E2E | KCF-2026-000026 guardado (2 × KCF-VIT-001, S/ 49.80) con movimiento de stock −2 (34 → 32) |
| Smoke test | `scripts/smoke-test.sh` | 18/18 |
| Concurrencia | `node scripts/concurrency-check.mjs` | stock 4 y 9 intentos simultáneos → 4 creados, 5 × 409, stock final 0 |
| Persistencia | reconstrucción de los contenedores con el mismo volumen | 25 pedidos antes y después; la migración de datos no duplicó categorías ni productos |
| Alta disponibilidad local | `bash scripts/ha-local-test.sh` | 2 réplicas de API, una detenida con tráfico: 179/180 OK (1 timeout en el instante del fallo) |
| Red | `wget db:5432` desde el frontend | inaccesible (red `data` interna) |
| Usuarios | `id -u` en backend y frontend | 10001 y 101 |
| Terraform | `terraform fmt -check -recursive`, `init -backend=false`, `validate` (demo y ha) | OK |
| Workflows | `actionlint` 1.7.4 + comprobación «sin despliegues automáticos» | OK |
| Scripts PowerShell | analizador sintáctico de Windows PowerShell 5.1 | sin errores (no se ejecutaron contra Azure) |
| Regresión clínica | `php artisan test` en `backend/` del sistema de enfermería | 108/108, sin archivos modificados |

No hay evidencia de despliegue en Azure porque **no se desplegó**. La URL pública se obtendrá el día de la exposición.
