# Kay Care: backend (Laravel 12, API v1)

Backend de **Kay Care**, un sistema de apoyo a la documentación de enfermería en el primer nivel de atención
(Perú), centrado en los controles de crecimiento y desarrollo (CRED) y la sugerencia de actividades HIS.

> ⚠️ **ENTORNO DEMO.** Datos ficticios. Los códigos HIS (`DEMO-*`) **no son oficiales** y **no han
> sido validados por el MINSA**. Los PDFs llevan la marca "SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO".
> No use este entorno con datos de pacientes reales.

Documentación: [`docs/openapi.yaml`](docs/openapi.yaml) · [`docs/architecture.md`](docs/architecture.md) ·
[`docs/his-rules.md`](docs/his-rules.md) · [`docs/security.md`](docs/security.md)

## Requisitos

- **PHP 8.2 o superior** (probado con 8.2.33) con las extensiones `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `dom`, `xml`, `fileinfo` y `curl` (más `pdo_mysql` para MySQL). `gd` no es necesaria.
- **Composer 2**.
- SQLite 3 (desarrollo y tests) · MySQL 8 (despliegue futuro).

## Instalación

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

## Variables de entorno principales (`.env`)

| Variable | Valor de desarrollo | Descripción |
|---|---|---|
| `APP_URL` | `http://localhost:8000` | Use `localhost`, **no** `127.0.0.1` |
| `APP_DEBUG` | `true` | **`false`** en cualquier despliegue |
| `KAYCARE_MODE` | `demo` | El seeder demo solo corre en `demo` |
| `KAYCARE_TIMEZONE` | `America/Lima` | Zona horaria del establecimiento |
| `FRONTEND_URL` | `http://localhost:5173` | Origen(es) exactos permitidos por CORS (lista separada por comas) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,localhost:8000` | Hosts con autenticación por cookie |
| `SESSION_DRIVER` | `database` | Sesiones en BD |
| `SESSION_DOMAIN` | `null` | `null` en localhost |
| `SESSION_SECURE_COOKIE` | `false` | **`true`** con HTTPS |
| `DB_CONNECTION` | `sqlite` | `mysql` en el futuro |
| `DEMO_NURSE_EMAIL` / `DEMO_NURSE_PASSWORD` | *(vacío)* | Cuenta demo de enfermera (contraseña de 12 caracteres o más) |
| `DEMO_ADMIN_EMAIL` / `DEMO_ADMIN_PASSWORD` | *(vacío)* | Cuenta demo de administrador |

Las credenciales demo **no están en el código**: defínalas en su `.env` local con correos ficticios (p. ej. `enfermera@kaycare.test`). Si las deja vacías, el seeder omite la cuenta.

## Base de datos

### SQLite (desarrollo)

```bash
touch database/database.sqlite        # PowerShell: New-Item database/database.sqlite
php artisan migrate
```

### MySQL 8 (despliegue futuro)

Las migraciones son compatibles con MySQL 8 (UUID `char(36)`, FK, índices y únicos compuestos).

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kaycare
DB_USERNAME=kaycare
DB_PASSWORD=********
```

```sql
CREATE DATABASE kaycare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Migraciones y seeder demo

```bash
php artisan migrate               # crea las tablas
php artisan db:seed               # catálogo DEMO + cuentas demo (.env) + 3 pacientes ficticios
php artisan migrate:fresh --seed  # reinicia la BD de desarrollo
```

`db:seed` se niega a ejecutarse con `APP_ENV=production` o `KAYCARE_MODE` distinto de `demo`.

## Ejecución local

```bash
php artisan serve --host=localhost --port=8000
```

- API: `http://localhost:8000/api/v1` (estado: `GET /api/v1/health`)
- Frontend Vue (otro repositorio o carpeta): `http://localhost:5173`

## Sanctum SPA y CORS

- Autenticación por **cookie de sesión y CSRF** (sin tokens Bearer ni `localStorage`).
- `statefulApi()` activa la sesión y el CSRF en `/api/*` para los orígenes de `SANCTUM_STATEFUL_DOMAINS`.
- CORS (`config/cors.php`): rutas `api/*` y `sanctum/csrf-cookie`, `supports_credentials=true` y orígenes exactos según `FRONTEND_URL`. **Sin `*`.**
- Ambos servidores deben usar `localhost` para que las cookies coincidan.

## Integración con Vue (Axios)

```js
import axios from 'axios'

export const http = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

// 1) Obtener la cookie CSRF antes del login
await http.get('/sanctum/csrf-cookie')
// 2) Login → { data: User }
const { data } = await http.post('/api/v1/auth/login', { email, password })
// 3) Llamadas autenticadas
const patients = await http.get('/api/v1/patients', { params: { search: 'ana', page: 1 } })
// 4) Descarga de PDF (blob)
const pdf = await http.get(`/api/v1/encounters/${id}/documents/his.pdf`, { responseType: 'blob' })
```

Manejo de errores: **401** sin sesión o sesión expirada (ir al login) · **419** CSRF o sesión expirada (pedir de nuevo `csrf-cookie` y login) · **403** sin permiso · **422** `{message, errors}` · **429** demasiados intentos.

## Endpoints (contrato v1)

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/api/v1/health` (público) | `{status:"ok",version:"v1"}` |
| POST | `/api/v1/auth/login` | `{data:User}` |
| GET | `/api/v1/auth/me` | `{data:User}` |
| POST | `/api/v1/auth/logout` | `{message}` |
| GET | `/api/v1/patients?search=&page=` | `{data:Patient[],meta:{current_page,last_page,per_page,total}}` |
| POST | `/api/v1/patients` | `{data:Patient}` (201) |
| GET | `/api/v1/patients/{id}` | `{data:Patient}` |
| PATCH | `/api/v1/patients/{id}` | `{data:Patient}` |
| GET | `/api/v1/patients/{id}/encounters` | `{data:Encounter[]}` |
| POST | `/api/v1/encounters` | `{data:Encounter}` (201) |
| GET | `/api/v1/encounters/{id}` | `{data:Encounter}` |
| PATCH | `/api/v1/encounters/{id}` | `{data:Encounter}` |
| GET | `/api/v1/encounters/{id}/his-suggestions` | `{data:HisSuggestion[]}` |
| PUT | `/api/v1/encounters/{id}/his-lines` | `{data:HisLine[]}` |
| GET | `/api/v1/encounters/{id}/his-preview` | `{data:{patient,encounter,lines,warnings,validated}}` |
| GET | `/api/v1/encounters/{id}/documents/his.pdf` | `application/pdf` |
| GET | `/api/v1/encounters/{id}/documents/clinical-note.pdf` | `application/pdf` |

Los campos adicionales (aditivos) están documentados en `docs/openapi.yaml`: `age_at_encounter`, `revision`,
`created_at`/`updated_at`, `rationale`/`rule_code`/`catalog_version` y `validation_scope`.

## Tests

```bash
php artisan test
```

Usa SQLite en memoria (`phpunit.xml`) y no toca la BD de desarrollo. Cubre autenticación, CSRF, CORS, 401/403/404/422,
pacientes (duplicados, búsqueda, edición con revisiones), atenciones (fechas, rangos, permisos, trazabilidad),
cálculo de edad, sugerencias HIS, validación de líneas, vista previa y PDFs.

## Limitaciones clínicas y pendientes

- Los **códigos y reglas HIS son demostrativos**. Hace falta incorporar el catálogo oficial con un proceso versionado y revisión profesional (`docs/his-rules.md`).
- `validated` en la vista previa indica **consistencia estructural**, no una validación oficial.
- Los PDFs son demostrativos, no están firmados y no son formatos institucionales aprobados.
- El contrato v1 **no incluye un endpoint de revisión** (`draft` → `reviewed`). La lógica existe (`EncounterService::markReviewed`) pero no se expone; hace falta acordar una extensión del contrato.
- Alcance de un solo establecimiento: todo el personal clínico activo puede consultar todos los expedientes.
- No hay gestión de usuarios por API, MFA ni catálogo de diagnósticos. No hay extracción por IA (intencional).
- Los requisitos de despliegue seguro (HTTPS, respaldos cifrados, sesiones) están en `docs/security.md`.
