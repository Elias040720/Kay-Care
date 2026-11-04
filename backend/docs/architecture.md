# Arquitectura del backend de Kay Care

> Entorno **DEMO**: datos ficticios y catálogo HIS con códigos `DEMO-*` que no son oficiales.

## Visión general

API REST versionada (`/api/v1`) en **Laravel 12** que consume el SPA Vue (`frontend/`, otro agente).
La autenticación es **Laravel Sanctum en modo SPA**: cookie de sesión HttpOnly más protección CSRF.
No se emiten tokens, así que el frontend no guarda nada en `localStorage`.

```
Vue SPA (localhost:5173)
   │  withCredentials + X-XSRF-TOKEN, Accept: application/json
   ▼
routes/api.php ── middleware: ForceJsonResponse → Sanctum stateful (session + CSRF)
   │                          → auth:sanctum → EnsureActiveUser → throttle:api → SecureApiHeaders
   ▼
Controllers (Api/V1, delgados) ── FormRequests (validación + authorize() con Policies)
   │
   ▼
Services (lógica de negocio, testeables)
   ├─ Cred/AgeCalculationService     edad en días / meses / años cumplidos a la fecha de la atención
   ├─ His/HisCatalogResolver         catálogo vigente según la fecha de la atención
   ├─ His/HisRuleMatcher             evaluación pura de reglas (sin BD)
   ├─ His/HisSuggestionService       sugerencias (nunca registros automáticos)
   ├─ His/HisValidationService       validación estructural de líneas y vista previa
   ├─ His/HisLineService             reemplazo transaccional de líneas confirmadas
   ├─ Patients/PatientService        alta, edición con revisiones, búsqueda
   ├─ Encounters/EncounterService    alta (siempre draft), edición con snapshot, revisión explícita
   ├─ Documents/DocumentService      PDFs en memoria (dompdf) con plantillas configurables
   └─ Audit/AuditLogger              eventos (audit_logs) + snapshots (record_revisions)
   │
   ▼
Eloquent Models ── SQLite (desarrollo / tests) · MySQL 8 (despliegue futuro)
```

## Módulos

| Módulo | Endpoints | Piezas principales |
|---|---|---|
| Autenticación | `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | `AuthController`, `LoginRequest`, limitador `login`, `EnsureActiveUser` |
| Pacientes | `GET/POST /patients`, `GET/PATCH /patients/{id}`, `GET /patients/{id}/encounters` | `PatientController`, `PatientWriteRequest`, `PatientPolicy`, `PatientService` |
| Atenciones | `POST /encounters`, `GET/PATCH /encounters/{id}` | `EncounterController`, `EncounterWriteRequest`, `EncounterPolicy`, `EncounterService` |
| Motor HIS/CRED | `GET his-suggestions`, `PUT his-lines`, `GET his-preview` | `HisController`, servicios `His/*`, tablas `his_*` |
| Documentos | `GET documents/his.pdf`, `GET documents/clinical-note.pdf` | `DocumentController`, `DocumentService`, `resources/views/documents/demo/*` |
| Salud | `GET /health` (público) | `HealthController` |

## Modelo de datos

```
users (uuid) ─┬─< patients.created_by / updated_by
              ├─< encounters.responsible_user_id / created_by / updated_by / reviewed_by
              ├─< his_lines.confirmed_by
              ├─< audit_logs.user_id
              └─< record_revisions.user_id

patients (uuid) ──< encounters (uuid) ──< his_lines (uuid) >── his_catalog_items (uuid)
                                                                     │
his_catalog_versions (uuid) ──< his_catalog_items ──< his_rules >────┘
```

| Tabla | Propósito | Restricciones clave |
|---|---|---|
| `users` | Personal clínico (`role`: nurse/admin, `is_active`) | `email` único. Sin registro público |
| `patients` | Datos demográficos | `chart_number` único; `(document_type, document_number)` único (se permiten varios NULL); índices por nombre, apellido, documento y fecha de nacimiento |
| `encounters` | Atenciones CRED | FK `patient_id` RESTRICT; `occurred_at` en UTC más `occurred_at_offset`; `status` draft/reviewed; `revision` |
| `his_catalog_versions` | Versión del catálogo con procedencia y vigencia | `code` único; `valid_from`/`valid_to`; `verification_status` |
| `his_catalog_items` | Actividad / código | `(catalog_version_id, code)` único; tipos P/D/R permitidos; modo LAB |
| `his_rules` | Reglas de sugerencia | `(catalog_version_id, rule_code)` único; límites de edad inclusivos; condición; estrategia LAB |
| `his_lines` | Actividades **confirmadas** por la enfermera | `(encounter_id, catalog_item_id)` único |
| `audit_logs` | Eventos (quién, qué, cuándo, IP) | Sin texto clínico |
| `record_revisions` | Snapshot del estado **anterior** a cada edición | `(type, id, revision)` único |
| `sessions`, `cache`, `jobs` | Infraestructura Laravel (driver database) | — |
| `personal_access_tokens` | Lo requiere Sanctum, pero **no se usa** (no se emiten tokens) | — |

Todas las claves primarias son UUID (ordenados por tiempo, `HasUuids`). Todas las FK clínicas usan `RESTRICT`: no se borra información clínica en cascada y el MVP no expone borrado.

## Decisiones relevantes

- **Contrato inmutable**: las respuestas incluyen como mínimo los campos del contrato. Los campos extra son aditivos y están documentados en `openapi.yaml`.
- **Zona horaria**: se almacena en UTC y se conserva el desplazamiento original (`occurred_at_offset`). La edad usa la **fecha local** de la atención, de modo que una atención a las 21:30 −05:00 cuenta como ese día y no como el día UTC siguiente.
- **Autorización primero**: los FormRequest de escritura autorizan con la Policy (`Gate::inspect`) antes de validar, así que un usuario sin permiso recibe 403 y no los detalles de validación.
- **Mass assignment**: `$fillable` explícitos. `status`, `responsible_user_id`, `role`, `is_active` y las columnas de auditoría nunca se asignan masivamente. `id` y `status` en el payload devuelven 422.
- **Transacciones**: alta y edición más auditoría, y el reemplazo de líneas HIS (con `lockForUpdate` sobre la atención).
- **JSON siempre**: `ForceJsonResponse` en el grupo `api`, un fallback JSON 404 para cualquier método y `shouldRenderJsonWhen` para `api/*`.
- **Plantillas sustituibles**: `config('kaycare.documents.templates')` asigna cada documento a su vista Blade. Para cambiar a formatos institucionales validados basta con cambiar la configuración.
- **Revisión de atenciones**: `EncounterService::markReviewed()` y `EncounterPolicy::review()` ya existen, pero el contrato v1 no define endpoint de revisión. Por eso **no se expone** en la API (ver limitaciones en el README).
- **Lazy loading prohibido** fuera de producción (`Model::preventLazyLoading`), para evitar consultas N+1 ocultas.
