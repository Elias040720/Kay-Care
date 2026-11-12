# Kay Care Farmacia — farmacia online académica

> **PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES**
> Productos y marcas ficticios de venta libre. Sin pagos, sin envíos y sin datos personales reales.

Módulo **autónomo** dentro del repositorio Kay Care. No comparte base de datos, tablas, sesiones ni credenciales con el sistema clínico Kay Care Enfermería (`backend/` y `frontend/` en la raíz), que no se modifica.

La interfaz sigue los patrones de navegación de las farmacias online peruanas (buscador central con sugerencias, menú de categorías, ofertas y tarjetas con «Agregar») con identidad visual propia: logo, ilustraciones y textos originales, sin marcas de terceros.

| Carpeta | Contenido |
|---|---|
| `backend/` | API Laravel 12 en `/api/v1/store` (Form Requests, servicios de dominio, admin con sesión, 70 pruebas) |
| `frontend/` | SPA Vue 3 + TypeScript + Vite + Tailwind + Vue Router + Pinia + Lucide (45 pruebas + E2E Playwright) |
| `docker-compose.yml` | PostgreSQL 16 + API + nginx, volumen persistente |
| `infra/terraform/` | Módulos y entornos `demo` y `ha` para Azure (**no desplegados**) |
| `scripts/` | Smoke test, concurrencia, alta disponibilidad local, diagramas, preflight y despliegue de Azure |
| `docs/` | Informe (md + pdf), diagramas, evidencias, costos, procedimiento del día de la exposición y guía |
| `../.github/workflows/pharmacy-*.yml` | CI, y CD solo manual |

## Ejecutar con Docker

```bash
cd academic-pharmacy
cp .env.example .env   # opcional: define contraseñas propias
docker compose up --build
```

- **Tienda:** <http://localhost:8088>
- **Administración:** <http://localhost:8088/admin>, usuario `admin@farmacia.demo`. Si `PHARMACY_ADMIN_PASSWORD` está vacía, la contraseña temporal aparece **una sola vez** en `docker compose logs backend`.
- **Datos:** el volumen `kaycare-pharmacy_pharmacy_pgdata` persiste entre reinicios. `docker compose down -v` lo elimina.

Verificaciones locales:

```bash
set -a; . ./.env; set +a
ADMIN_PASSWORD="$PHARMACY_ADMIN_PASSWORD" bash scripts/smoke-test.sh   # 18 comprobaciones
node scripts/concurrency-check.mjs                                     # sin sobreventa
bash scripts/ha-local-test.sh                                          # 2 réplicas, se cae una
```

## Desarrollo sin Docker

```bash
# API (PHP 8.2+ con pdo_pgsql o pdo_sqlite)
cd backend && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve          # http://127.0.0.1:8000

# Frontend (Vite hace de proxy de /api hacia el puerto 8000)
cd frontend && npm ci && npm run dev                      # http://localhost:5174
```

## Pruebas

```bash
cd backend  && vendor/bin/phpunit && vendor/bin/pint --test
cd frontend && npm test && npm run lint && npm run typecheck && npm run build
cd frontend && ADMIN_PASSWORD=... npx playwright test     # evidencia → docs/evidence/
```

## Azure: preparado, sin desplegar

No existe ningún recurso de la farmacia en Azure. **Consumo de créditos de la farmacia: US$ 0.** El despliegue se hará solo el día de la exposición, con autorización expresa. El procedimiento está en [`docs/deploy/DIA-EXPOSICION.md`](docs/deploy/DIA-EXPOSICION.md).

```powershell
powershell -ExecutionPolicy Bypass -File scripts\azure-preflight.ps1   # solo lectura
powershell -ExecutionPolicy Bypass -File scripts\deploy-azure.ps1      # crea recursos: exige confirmación escrita
```

- **Costo estimado del día:** ≈ US$ 0,60 si se retira ese mismo día. Detalle en [`docs/report/costos.md`](docs/report/costos.md).
- **GitHub Actions:** `pharmacy-deploy.yml` solo se ejecuta a mano desde `main`, con la confirmación `DESPLEGAR-<entorno>` y aprobación del Environment. Ningún push lo dispara, y la CI lo comprueba.
- **URL pública:** se obtendrá el día de la exposición.

## Documentación

- **Informe final para la universidad:** [`docs/report/KAY_CARE_INFORME_FINAL.pdf`](docs/report/KAY_CARE_INFORME_FINAL.pdf) · [Markdown](docs/report/KAY_CARE_INFORME_FINAL.md) · [DOCX editable](docs/report/KAY_CARE_INFORME_EDITABLE.docx)
- Preparación de la sustentación: [guion de 8–10 min](docs/report/GUIÓN_EXPOSICION.md) · [guía de estudio](docs/report/GUIA_DE_ESTUDIO.md) · [preguntas de defensa](docs/report/PREGUNTAS_DEFENSA.md)
- Informe técnico anterior (se conserva): [`docs/report/informe.md`](docs/report/informe.md) · [PDF](docs/report/informe.pdf)
- Diagramas: [`docs/diagrams/`](docs/diagrams/) (A flujo de compra, B Azure, C CI/CD, D alta disponibilidad, E arquitectura general, F modelo de datos; `informe-*` son versiones compactas para A4). Se regeneran con `scripts/render-diagrams.sh`.
- Evidencias: [`docs/evidence/`](docs/evidence/)
- Amenazas: [`docs/security/threat-matrix.md`](docs/security/threat-matrix.md)
- DEMO vs HA: [`docs/architecture/demo-vs-ha.md`](docs/architecture/demo-vs-ha.md)
- Guía de exposición: [`docs/PRESENTACION.md`](docs/PRESENTACION.md)
