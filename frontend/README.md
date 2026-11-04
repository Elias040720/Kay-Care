# Kay Care · Frontend

Aplicación Vue 3 para registro de pacientes, atenciones CRED, confirmación de actividades HIS y consulta de documentos. Interfaz en español peruano, con navegación responsive desde 360 px y un modo de demostración independiente del backend.

## Requisitos e instalación

- **Node.js 24 LTS recomendado**. También Node 22.12+; Node 20.18 del entorno original no es compatible con las herramientas actuales.
- npm 10 o superior.
- Google Chrome instalado para la configuración E2E incluida. No se descarga ningún navegador automáticamente.

Desde este directorio `frontend/`:

```sh
npm install
npm run dev
```

Abre <http://localhost:5173>. El modo predeterminado es `mock`, sin necesidad de crear `.env`. Las versiones exactas están fijadas en `package-lock.json`; las instalaciones posteriores también admiten `npm ci`.

Para este entorno Windows, existe Node 24 en el runtime de Codex. Si `node --version` muestra 20.18, puede usarse temporalmente sin modificar el sistema:

```powershell
$runtimeNode = "$env:USERPROFILE\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin"
$env:PATH = "$runtimeNode;$env:PATH"
# El npm.ps1 del Node anterior puede preferir su propio node.exe. Invócalo explícitamente:
& "$runtimeNode\node.exe" "$env:USERPROFILE\tools\nodejs\node_modules\npm\bin\npm-cli.js" run dev
```

La solución portable es activar Node 24 con el gestor de versiones de tu equipo. `.nvmrc` declara la versión mayor recomendada. [Requisitos de Vite](https://vite.dev/guide/).

## Variables de entorno

Copia `.env.example` a `.env` cuando necesites cambiar de modo:

```dotenv
VITE_API_MODE=mock
VITE_API_BASE_URL=http://localhost:8000
```

| Variable            | Valores                         | Uso                                                                                  |
| ------------------- | ------------------------------- | ------------------------------------------------------------------------------------ |
| `VITE_API_MODE`     | `mock` o `real`                 | Selecciona el adaptador al iniciar Vite. Un valor desconocido detiene la aplicación. |
| `VITE_API_BASE_URL` | URL del servidor, sin `/api/v1` | Base de Laravel y `/sanctum/csrf-cookie`. Predeterminado: `http://localhost:8000`.   |

Reinicia Vite después de cambiar `.env`. Estas variables se incluyen en el bundle público: **no contienen secretos ni contraseñas**.

## Modo demostración

Pulsa **Explorar demostración**. No exige credenciales reales ni simula autenticación segura. El indicador DEMO permanece visible durante toda la sesión.

- 14 pacientes claramente ficticios, sin números DNI, y cuatro atenciones de ejemplo.
- Registro, edición, búsqueda por nombre/documento/HC, paginación, historial y flujo CRED completo.
- Datos guardados únicamente en memoria del módulo; **recargar, cerrar la pestaña o cerrar sesión reinicia la demostración**.
- No se usa `localStorage`, `sessionStorage`, IndexedDB ni service workers.
- Códigos `DEMO-*`; todas las sugerencias comienzan sin seleccionar. El LAB se ingresa manualmente cuando corresponde.
- PDFs simples de ejemplo marcados **DEMO / SIN VALIDEZ OFICIAL**. No son historias clínicas ni registros HIS oficiales.

No ingreses datos reales en este modo.

## Conexión con Laravel

```dotenv
VITE_API_MODE=real
VITE_API_BASE_URL=http://localhost:8000
```

El adaptador real utiliza exclusivamente el contrato API v1 de la solicitud. No hay fallback hacia fixtures, ni usuarios/contraseñas predeterminados en este frontend.

1. Ejecuta el backend en `http://localhost:8000` y el frontend en `http://localhost:5173`. Mantén el mismo hostname; no mezcles `localhost` y `127.0.0.1`.
2. Laravel Sanctum debe admitir el origen `http://localhost:5173`, sesiones stateful, cookies y CORS con credenciales. El backend es responsable de su configuración, autorización y protección CSRF.
3. El frontend solicita `GET /sanctum/csrf-cookie` antes de `POST /api/v1/auth/login`. Axios está centralizado con `withCredentials: true`, `withXSRFToken: true` y `Accept: application/json`.
4. La sesión se restaura con `/api/v1/auth/me`. No hay JWT ni credenciales en almacenamiento del navegador.
5. Los errores 401/419 de operaciones protegidas borran la caché de la interfaz y redirigen a acceso. Los errores 422 se muestran junto a los campos; 500/red se pueden reintentar sin sustituir los datos por demo.
6. Los PDFs reales se descargan desde los endpoints protegidos `/documents/his.pdf` y `/documents/clinical-note.pdf`, validando el tipo MIME. Las URLs temporales de los blobs se revocan.

El cuerpo de `PatientWrite`, `EncounterWrite` y `HisLineWrite` está tipado. Zod valida respuestas relevantes antes de presentarlas. Se envían campos opcionales como `null`, cumpleaños como `YYYY-MM-DD` y atenciones ISO 8601 con zona `-05:00`. Las fechas y edades se muestran en `America/Lima`.

## Pantallas y flujo

- `/acceso`: acceso real o entrada explícita a demo; sesión expirada y errores.
- `/`: bienvenida, conteos basados en datos disponibles y atenciones recientes.
- `/pacientes`: búsqueda, paginación y registro en panel lateral.
- `/pacientes/:id`: ficha, edición e historial de atenciones.
- `/atenciones/nueva`: paciente → evaluación → actividades HIS → revisión/documentos.
- `/atenciones/nueva?patient=UUID`: nueva atención desde la ficha.
- `/atenciones/nueva?encounter=UUID`: reanudar o editar el mismo borrador, usando PATCH.
- `/atenciones/:id`: resumen estructurado, advertencias, vista previa HIS y descargas.
- `/documentos`: localizar paciente y atención para acceder a documentación.

La evaluación se guarda antes de consultar sugerencias HIS. Después solo se guardan las actividades que la enfermera confirma. Los valores propuestos se muestran como referencia, sin preselección por edad ni inferencia de controles previos. No se generan diagnósticos a partir de mediciones.

Los formularios avisan antes de descartar cambios por navegación, cambios de query, cierre del panel y cierre de sesión. El navegador muestra su aviso nativo ante recarga/cierre con cambios pendientes. Una sesión expirada siempre prioriza retirar la información de la pantalla; los cambios no guardados no se persisten.

## Comandos

```sh
npm run dev         # localhost:5173, live reload
npm run typecheck   # TypeScript estricto y plantillas Vue
npm run test        # Vitest, ejecución única
npm run test:watch  # Vitest interactivo
npm run test:e2e    # Chrome, demo y contrato real interceptado
npm run build      # typecheck + compilación de producción a dist/
npm run preview    # sirve dist/ en localhost:4173
npm audit          # revisión de dependencias
```

Playwright inicia servidores separados: demo en 5173 y real en 5174. Puede reutilizar servidores locales existentes; asegúrate de que cada puerto tenga el modo correcto. La suite `real-contract` intercepta las respuestas HTTP con datos ficticios: prueba el adaptador real y la UI, **no acredita integración con un servidor Laravel vivo**. No ejecuta migraciones ni modifica el backend.

Los reportes de Playwright y capturas quedan en `playwright-report/`, `test-results/` y `qa-output/`, excluidos de Git. Las pruebas usan únicamente fixtures y no deben ejecutarse contra datos de pacientes reales. No se habilitan trazas que almacenen cuerpos clínicos.

## Arquitectura

```text
src/
  app/                 QueryClient y política de reintentos
  components/ui/       encabezados, estados, drawer accesible y toasts
  composables/         debounce, protección de cambios, notificaciones
  features/
    auth/              sesión Pinia y acceso
    dashboard/         resumen de datos disponibles
    patients/          listado, registro, ficha y edición
    encounters/        wizard, validación y revisión clínica
    his/               selección y edición de actividades sugeridas
    documents/         búsqueda de documentos por paciente/atención
  layouts/             navegación y marco responsive
  lib/api/             ApiAdapter real, Axios, Zod y errores
  lib/mock/            adaptador en memoria, fixtures y PDF demo
  lib/validation/      validaciones de pacientes
  lib/format.ts        fechas, edad de referencia y nombres
  router/              rutas diferidas y protección de sesión
  styles/              Tailwind y sistema visual
  types/               contrato API v1 interno
tests/unit/            validación y adaptadores
tests/e2e/             recorridos y accesibilidad con Playwright/axe
```

Los componentes consumen `ApiAdapter` y nunca llaman Axios ni fixtures. `isDemo` se usa solamente para rotular el entorno y las descargas. TanStack Vue Query gestiona carga/error/caché e invalidación después de mutaciones; Pinia gestiona únicamente la sesión. Los datos clínicos no se envían a analytics, consola ni APIs de IA. Las rutas de pantallas se cargan de forma diferida.

El diseño usa fuentes de sistema y SVG/CSS propios para la ilustración decorativa. Lucide se importa desde su paquete Vue actual `@lucide/vue`. No hay fuentes privadas, fotografías de personas ni recursos externos de diseño.

## Límites del contrato y pendientes reales

- **`reviewed` es de solo lectura**: `EncounterWrite` no admite `status`. Finalizar el recorrido no envía un campo inventado; conserva el estado devuelto por el backend.
- Los códigos oficiales, reglas por edad, fuentes normativas, validación LAB y validez de documentos corresponden al backend y al catálogo validado. El frontend no certifica esas reglas.
- No existe endpoint global de atenciones ni dashboard. Los conteos de atenciones recientes/borradores corresponden a hasta ocho pacientes de la primera página, claramente indicados; no son métricas de producción globales.
- La documentación se consulta por paciente y atención porque no existe endpoint global de documentos.
- No hay recuperación de contraseña, eliminación de pacientes, citas, vacunas, firma digital ni sincronización offline: el contrato no contempla esas funciones.
- Sin ETag/versionado/idempotency-key en el contrato, no es posible garantizar resolución de ediciones concurrentes ni reintentos de creación después de una respuesta de red ambigua. Las mutaciones no se reintentan automáticamente.
- Los borradores guardados persisten en Laravel en modo real; cambios aún no guardados no sobreviven una sesión expirada o el cierre forzado de la página.
- Para servir `dist/`, el hosting debe redirigir rutas SPA a `index.html`. No se modificó hosting ni configuración raíz del repositorio.

Consulta `docs/VALIDATION.md` para el registro de comprobaciones realizadas en esta entrega.
