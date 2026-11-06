# Kay Care Farmacia — tienda e-commerce académica

> **PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES**
> Productos y marcas ficticios de venta libre. Sin pagos, sin envíos y sin datos personales reales.

Módulo **autónomo** dentro del repositorio Kay Care. No comparte base de datos, tablas, sesiones ni credenciales con el sistema clínico Kay Care Enfermería (`backend/`, `frontend/` en la raíz), que no se modifica.

| Carpeta | Contenido |
|---|---|
| `backend/` | API Laravel 12 en `/api/v1/store` (Form Requests, servicios de dominio, admin con sesión, 65 pruebas) |
| `frontend/` | SPA Vue 3 + TypeScript + Vite + Tailwind + Vue Router + Pinia + Lucide (37 pruebas + E2E Playwright) |
| `docker-compose.yml` | PostgreSQL 16 + API + nginx, volumen persistente |
| `infra/terraform/` | Módulos y entornos `demo` y `ha` para Azure |
| `scripts/` | Smoke test, prueba de concurrencia, render de diagramas |
| `docs/` | Informe, diagramas, evidencia, matrices, costos y guion |
| `../.github/workflows/pharmacy-*.yml` | CI y CD de este módulo |

## Ejecutar todo con Docker

```bash
cd academic-pharmacy
cp .env.example .env   # opcional: define contraseñas propias
docker compose up --build
```

- Tienda: <http://localhost:8088>
- Administración: <http://localhost:8088/admin> — usuario `admin@farmacia.demo`. Si `PHARMACY_ADMIN_PASSWORD` está vacía, la contraseña temporal aparece **una sola vez** en `docker compose logs backend`.
- Datos: el volumen `kaycare-pharmacy_pharmacy_pgdata` persiste entre reinicios; `docker compose down -v` lo elimina.

Verificaciones:

```bash
set -a; . ./.env; set +a
ADMIN_PASSWORD="$PHARMACY_ADMIN_PASSWORD" bash scripts/smoke-test.sh
node scripts/concurrency-check.mjs
```

## Desarrollo sin Docker

```bash
# API (requiere PHP 8.2+ con pdo_pgsql o pdo_sqlite)
cd backend && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve          # http://127.0.0.1:8000

# Frontend (Vite hace proxy de /api al puerto 8000)
cd frontend && npm ci && npm run dev                      # http://localhost:5174
```

## Pruebas

```bash
cd backend  && vendor/bin/phpunit && vendor/bin/pint --test
cd frontend && npm test && npm run lint && npm run typecheck && npm run build
cd frontend && ADMIN_PASSWORD=... npx playwright test     # evidencia → docs/evidence/
```

## Nube (Azure)

```bash
cd infra/terraform/environments/demo   # o ha
cp terraform.tfvars.example terraform.tfvars
terraform init && terraform plan
```

**No ejecutar `terraform apply` sin autorización del propietario de la suscripción y sin revisar los costos** (`docs/report/costos.md`). El despliegue previsto se hace con `pharmacy-deploy.yml` (OIDC + aprobación). Estado actual: **URL pendiente de despliegue**.

## Documentación

- Informe: [`docs/report/informe.md`](docs/report/informe.md) · [PDF](docs/report/informe.pdf)
- Diagramas: [`docs/diagrams/`](docs/diagrams/) (regenerar con `scripts/render-diagrams.sh`)
- Evidencia: [`docs/evidence/`](docs/evidence/)
- Amenazas: [`docs/security/threat-matrix.md`](docs/security/threat-matrix.md)
- DEMO vs HA: [`docs/architecture/demo-vs-ha.md`](docs/architecture/demo-vs-ha.md)
- Presentación: [`docs/PRESENTACION.md`](docs/PRESENTACION.md)
