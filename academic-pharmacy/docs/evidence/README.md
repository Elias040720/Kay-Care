# Evidencia — Kay Care Farmacia

Capturas generadas con **Playwright (Chromium)** contra el stack local `docker compose up --build` (http://localhost:8088), con `prefers-reduced-motion: reduce`. Fecha de generación: ver `evidence-report.json`.

Regenerar:

```bash
cd academic-pharmacy && docker compose up -d --build
cd frontend && ADMIN_PASSWORD=<contraseña del .env> npx playwright test
```

| Archivo | Contenido |
|---|---|
| `01-inicio.png` | Inicio con aviso académico, categorías y destacados |
| `02-catalogo.png` | Catálogo completo con stock y paginación |
| `03-catalogo-busqueda.png` | Búsqueda «termómetro» (1 resultado) |
| `04-catalogo-filtros-orden.png` | Filtro de categoría + solo con stock + precio descendente |
| `05-detalle-producto.png` | Detalle con stock y cantidad |
| `06-carrito-drawer.png` | Drawer del carrito validado contra el servidor |
| `07-checkout-validacion.png` | Rechazo de un alias con forma de DNI |
| `08-checkout-listo.png` | Checkout simulado listo para confirmar |
| `09-confirmacion.png` | Confirmación con número de pedido y aviso |
| `10-admin-login-error.png` | Login con credenciales incorrectas (mensaje genérico) |
| `11-admin-dashboard.png` | Dashboard con datos demo |
| `12-admin-productos.png` | Listado de productos |
| `13-admin-producto-formulario.png` | Formulario de alta |
| `14-admin-ajuste-stock.png` | Ajuste de stock |
| `15-admin-stock-movimientos.png` | Movimientos auditados |
| `16-admin-pedidos.png` | Pedidos con estados |
| `17-admin-pedido-detalle.png` | Detalle tras un cambio de estado |
| `18-movil-catalogo.png` | Catálogo en 390 px sin desbordamiento horizontal |
| `evidence-report.json` | Violaciones axe por página (0) y errores de consola (solo el 401 esperado de la comprobación de sesión y el 422 del login incorrecto) |

## Otros resultados verificados (5 de noviembre de 2026, equipo local)

| Verificación | Comando | Resultado |
|---|---|---|
| Backend | `php vendor/bin/phpunit` (PostgreSQL 16 y SQLite) | 65 pruebas, 393 aserciones OK |
| Frontend | `npm test`, `npm run lint`, `npm run typecheck`, `npm run build` | 37 pruebas OK; lint, tipos y build OK |
| Smoke test | `scripts/smoke-test.sh` | 18/18 |
| Concurrencia | `node scripts/concurrency-check.mjs` | stock 2, 7 intentos → 2 creados, 5 × 409, stock final 0 |
| Persistencia | `docker compose down && docker compose up -d` | 21 pedidos antes y después |
| Red | `wget db:5432` desde frontend | inaccesible (red `data` interna) |
| Usuarios | `docker compose exec backend id` / `frontend id` | UID 10001 / 101 |
| Terraform | `terraform fmt -check -recursive`, `terraform validate` (demo y ha) | OK (azurerm 4.81.0) |
| Workflows | `actionlint` 1.7.4 | OK |
| Regresión clínica | `php artisan test` en `backend/` del sistema de enfermería | 108/108 (main) y 106/106 (feat/claude-backend), sin cambios en los worktrees |

No hay evidencia de despliegue en Azure porque **no se desplegó**: URL pendiente de despliegue.
