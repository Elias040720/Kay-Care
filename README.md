# Kay Care

Vue 3 + TypeScript y Laravel 12 integrados mediante API v1, Sanctum y SQLite. Permite registrar pacientes ficticios, crear atenciones CRED, confirmar actividades HIS y descargar dos documentos PDF.

**Este MVP es de demostración, también conectado a Laravel.** Utiliza únicamente datos ficticios. El catálogo `DEMO-*` y sus reglas no son oficiales; los PDF no tienen validez clínica.

## Ejecutar en Windows PowerShell

Requisitos: PHP 8.2+ con SQLite, mbstring, OpenSSL, DOM/XML, fileinfo y curl; Composer 2; Node 24 (o 22.12+) y npm 10+. Google Chrome es necesario para las pruebas de navegador. Los scripts buscan un Node compatible en PATH y, si existe, en el runtime local de Codex.

Desde la carpeta principal del repositorio:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start.ps1
```

`setup.ps1` instala las dependencias, crea los `.env` locales si faltan, genera una clave y contraseña aleatorias, ejecuta las migraciones y carga el catálogo y pacientes ficticios **solo si creó una base SQLite nueva**. Conserva los `.env`, claves y registros existentes. No utiliza `migrate:fresh`. Para omitir la instalación de dependencias ya disponibles, añade `-SkipInstall`.

El segundo comando inicia **Laravel y Vue simultáneamente en una terminal**:

- Aplicación: [http://localhost:5173](http://localhost:5173).
- Estado de API: [http://localhost:8000/api/v1/health](http://localhost:8000/api/v1/health).
- Cuenta ficticia: consulta `DEMO_NURSE_EMAIL` y `DEMO_NURSE_PASSWORD` en `backend/.env`. La contraseña no se imprime ni se incorpora al repositorio.
- `Ctrl+C` detiene los servidores iniciados por el script. Los logs están en `.local/`, excluido de Git.

Usa `localhost` en ambos servidores. Si un puerto está ocupado, el script se detiene sin cerrar procesos ajenos. El arranque automático exige `APP_ENV=local`, `KAYCARE_MODE=demo`, SQLite y `DB_URL` vacío. Una base preexistente sin cuenta o catálogo necesita configuración explícita; el setup no vuelve a sembrarla porque los seeders pueden actualizar registros demo existentes.

Los datos guardados en Laravel sobreviven recargas y cierres de sesión. La sesión usa cookies HttpOnly y protección CSRF; el frontend no guarda credenciales ni registros en localStorage/sessionStorage. `VITE_API_MODE=real` selecciona la API, y no convierte este MVP en un sistema clínico validado.

### Dos terminales, si se prefiere

Después del setup, desde la raíz, en la primera terminal:

```powershell
Set-Location .\backend
php artisan serve --host=localhost --port=8000
```

En la segunda, con Node compatible y npm activos:

```powershell
Set-Location .\frontend
npm run dev
```

El setup crea `frontend/.env.local` con `VITE_API_MODE=real` y `VITE_API_BASE_URL=http://localhost:8000` si no existe. No guardes secretos en variables `VITE_*`. El comando conjunto fuerza estas dos variables para esa ejecución.

## Verificación

Desde la raíz, la integración real se ejecuta con:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-integration.ps1
```

El script crea una **nueva base SQLite aislada en `.local/` en cada ejecución**, migra y siembra solo información ficticia, crea credenciales efímeras y levanta Laravel/Vue en 8001/5175. Chrome comprueba login, CORS/CSRF, 401/419/422, paciente, edición, CRED, sugerencias sin preselección, actividad confirmada, PDF HIS y resumen clínico, historial, recarga y persistencia tras otra sesión. También consulta las filas mediante un proceso PHP separado. No intercepta ni simula respuestas HTTP. Detiene sus servidores y conserva la base ficticia para inspección.

Pruebas individuales (Node compatible activo):

```powershell
Set-Location .\backend
php artisan test
Set-Location ..\frontend
npm run typecheck
npm test
npm run build
npm run test:e2e
```

Antes de `test:e2e`, detén el arranque normal: esa suite utiliza 5173 para el adaptador en memoria y 5174 para el contrato HTTP interceptado. No acredita por sí sola la integración Laravel; esa comprobación corresponde al script anterior. PHPUnit fuerza SQLite `:memory:` y verifica el aislamiento antes de migrar, incluso con variables de base de datos heredadas.

Si npm usa un Node antiguo en este equipo, invócalo así desde `frontend/`:

```powershell
. ..\scripts\runtime.ps1
$kayNode = Get-KayCareNode
$env:PATH = "$(Split-Path $kayNode);$env:PATH"
& $kayNode "$env:USERPROFILE\tools\nodejs\node_modules\npm\bin\npm-cli.js" run build
```

La alternativa en memoria se activa con `$env:VITE_API_MODE='mock'` antes de `npm run dev`. Su botón «Explorar demostración» no requiere cuenta y sus datos se reinician al recargar o cerrar sesión.

## Alcance y límites

- La validación HIS es **estructural**, no clínica ni normativa. Todos los códigos y reglas incluidos son demostrativos y requieren sustitución y revisión profesional antes de cualquier uso clínico.
- Solo se guardan las actividades confirmadas; ninguna sugerencia implica que se realizó una actividad. No se infieren diagnósticos de mediciones.
- Las atenciones se conservan como `draft`: el contrato no incluye transición a `reviewed`, firma ni aprobación clínica. «Finalizar revisión» termina la navegación, no certifica el registro.
- El historial devuelve hasta 200 atenciones por paciente. Los conteos del dashboard representan una muestra indicada en la UI; no son métricas globales.
- Faltan catálogo normativo validado, formatos oficiales, revisión clínica y de privacidad, operación HTTPS, respaldos/restauración, gestión de usuarios y resolución de ediciones concurrentes para considerar un despliegue clínico. No se realizó despliegue de producción ni validación con MySQL.

Detalles: [validación integrada](docs/INTEGRATION.md), [frontend](frontend/README.md), [backend](backend/README.md), [contrato OpenAPI](backend/docs/openapi.yaml), [reglas HIS](backend/docs/his-rules.md) y [seguridad](backend/docs/security.md).
