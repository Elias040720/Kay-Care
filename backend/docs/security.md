# Seguridad, datos sensibles y despliegue

## Entorno DEMO frente a un entorno con datos reales

| Aspecto | DEMO (estado actual) | Futuro entorno con datos reales |
|---|---|---|
| Datos | Solo ficticios (`DEMO-*`, sin documentos reales) | Datos personales y de salud: datos sensibles según la Ley N.º 29733 (Perú) |
| Catálogo HIS | `DEMO-CRED-2026.1`, `draft`, `source=demo` | Catálogo oficial versionado y verificado (ver `his-rules.md`) |
| Documentos | Marca de agua "SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO" | Formatos institucionales aprobados (plantillas sustituibles por configuración) |
| `KAYCARE_MODE` | `demo` | Distinto de `demo`: el seeder demo se niega a ejecutarse |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / **`false`** |
| Transporte | HTTP en localhost | **Solo HTTPS** (TLS 1.2 o superior) |

**Nunca** cargue datos reales en una base de datos de demostración ni copie una base de datos de producción a desarrollo.

## Controles implementados

### Autenticación y sesiones
- Sanctum SPA con cookie de sesión **HttpOnly**, `SameSite=Lax`, cifrada (`SESSION_ENCRYPT=true`) y almacenada en BD.
- CSRF: `GET /sanctum/csrf-cookie` emite `XSRF-TOKEN` y las peticiones mutantes sin el header `X-XSRF-TOKEN` correcto reciben **419**.
- El ID de sesión se regenera al iniciar sesión (evita la fijación de sesión). Al cerrar sesión se invalida la sesión y se regenera el token CSRF.
- Sin registro público y sin emisión de tokens: el frontend no guarda credenciales ni tokens en `localStorage`.
- Rate limiting de login: 5 intentos por minuto por cuenta e IP, y 20 por minuto por IP (429). La API autenticada admite 120 peticiones por minuto por usuario.
- Mensaje de error genérico ante credenciales inválidas o cuentas inactivas. Los intentos fallidos se auditan sin la contraseña.
- Las cuentas desactivadas pierden el acceso de inmediato, aunque tengan una sesión viva (`EnsureActiveUser`).
- La sesión expira tras `SESSION_LIFETIME` minutos de inactividad (120 por defecto). Después, la API responde 401 (sin sesión) o 419 (token CSRF caducado) y el SPA debe volver a pedir `csrf-cookie` y login.
- Las credenciales demo se leen de variables de entorno (`DEMO_*`) y nunca se escriben en el código. El seeder exige contraseñas de 12 caracteres o más.

### Autorización
- Policies (`PatientPolicy`, `EncounterPolicy`), evaluadas en los FormRequest **antes** de validar.
- Lectura de expedientes: personal clínico activo (nurse o admin) del establecimiento. Alcance del MVP: un solo establecimiento.
- Modificación de atenciones y líneas HIS: solo el profesional responsable o un admin, y solo en estado `draft`. Las atenciones `reviewed` son inmutables.
- No existe borrado de información clínica (las policies `delete` deniegan y no hay rutas).

### Integridad y trazabilidad
- `audit_logs`: creación y edición de pacientes y atenciones, reemplazo de líneas HIS (códigos antes y después), login, logout, intentos fallidos y documentos generados, con usuario, fecha e IP. **No guarda notas clínicas**: solo nombres de campos, identificadores y códigos.
- `record_revisions`: snapshot del estado **anterior** de cada edición de paciente o atención (append-only), con número de revisión y usuario.
- FK `RESTRICT`, UUID, restricciones únicas y transacciones en operaciones compuestas.
- Protección contra mass assignment: `$fillable` explícitos; `id` y `status` en el payload devuelven 422; `preventSilentlyDiscardingAttributes` fuera de producción.

### Superficie HTTP
- CORS restringido a los orígenes exactos de `FRONTEND_URL` con credenciales. **Sin comodín `*`.**
- Respuestas API con `Cache-Control: no-store, private`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer` y `X-Frame-Options: DENY`.
- Respuestas siempre en JSON en `/api/*`, incluidas 401, 404, 419 y 422.
- Las IDs se validan como UUID en las rutas (`whereUuid`) y en los payloads.

### Documentos PDF
- Se generan **en memoria** en cada petición autenticada. No se escriben en disco ni en `public/`, no tienen URL permanente y se envían con `Content-Disposition: attachment` y `no-store`.
- dompdf endurecido: `enable_remote=false`, `enable_php=false`, `enable_javascript=false`, `chroot` limitado a `resources/views` y sin protocolos http(s).
- Llevan marca de agua, aviso de no validez y de códigos no verificados, y no incluyen firma.

### Datos y terceros
- No se envía información clínica a servicios externos ni a modelos de IA. No hay telemetría de terceros.
- No hay secretos en Git: `.env` está ignorado y `.env.example` no contiene valores sensibles.

## Checklist de despliegue (antes de manejar datos reales)

1. `APP_ENV=production`, **`APP_DEBUG=false`**, `APP_KEY` único generado en el servidor y `KAYCARE_MODE` distinto de `demo`.
2. **HTTPS obligatorio** (proxy inverso con TLS, HSTS y redirección 80→443). Configure `TrustProxies` según su infraestructura.
3. `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` con el dominio real, `SESSION_SAME_SITE=lax` (o `strict` si el SPA comparte dominio) y `SESSION_LIFETIME` según la política institucional.
4. `SANCTUM_STATEFUL_DOMAINS` y `FRONTEND_URL` solo con los dominios reales en HTTPS.
5. MySQL 8 con un usuario de mínimos privilegios, conexión TLS y cifrado en reposo (InnoDB tablespace encryption o un volumen cifrado).
6. **Respaldos cifrados** (p. ej. `mysqldump` → `gpg`/`age` con claves fuera del servidor), con retención definida, pruebas periódicas de restauración y almacenamiento fuera del sitio.
7. `php artisan config:cache route:cache view:cache`. Permisos de `storage/` y `bootstrap/cache/` solo para el usuario del servicio. El document root apunta únicamente a `public/`.
8. Logs: `LOG_LEVEL=warning` o superior, rotación y acceso restringido. No registre cuerpos de petición.
9. No ejecute `db:seed` (el `DatabaseSeeder` se niega en producción). Provisione las cuentas reales con un proceso administrativo y contraseñas robustas.
10. Cargue y verifique el catálogo oficial según `his-rules.md` y desactive el catálogo DEMO.
11. Sustituya las plantillas DEMO por formatos institucionales aprobados (`config/kaycare.php`).
12. Evaluación de cumplimiento con la normativa de protección de datos personales y de historia clínica aplicable, y gestión de consentimiento y acceso.

## Pendientes conocidos para producción
- Multi-establecimiento y alcance de expedientes por establecimiento.
- Endpoint de revisión explícita de atenciones (requiere extender el contrato API).
- Gestión de usuarios (alta, baja, desbloqueo) y MFA para administradores.
- Bloqueo o alerta ante patrones de acceso anómalos y exportación del audit log.
