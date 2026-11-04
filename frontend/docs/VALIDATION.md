# Registro de verificación

**Actualización de integración en `main`:** consulta [el registro integrado](../../docs/INTEGRATION.md) para las pruebas actuales contra Laravel vivo, cookies/CORS, PDF y persistencia. El registro que sigue conserva la validación original de la rama frontend, anterior al merge.

Fecha: 4 de noviembre de 2026, zona America/Bogota. Worktree `Kay-Care-frontend`, rama `feat/codex-frontend`. Todos los cambios de código están dentro de `frontend/`.

Entorno de ejecución: Windows, Node 24.22.1, npm 10.8.2, Chrome. Se actualizó el conjunto de dependencias tras detectar vulnerabilidades en las versiones iniciales de Vitest/Zod. La instalación final no reporta vulnerabilidades conocidas en `npm audit`.

## Verificado

- `npm install`, `npm run typecheck` y `npm run build`: completados correctamente.
- `npm run test`: **35/35 pruebas aprobadas** (adaptadores, validaciones, fechas, edad de referencia, confirmación HIS, errores y PDF demo).
- `npm run test:e2e`: **10/10 pruebas aprobadas**, ejecución final en Chrome (40 segundos).
- `npm audit`: **0 vulnerabilidades reportadas**.
- El flujo principal en navegador registra un paciente, lo edita, crea una atención, confirma una actividad, descarga un PDF DEMO, reanuda mediante PATCH y verifica que no se duplicó la atención.
- Se comprueba que no se escriben datos en `localStorage` ni `sessionStorage`.
- Contrato real probado con HTTP interceptado: CSRF previo, errores 422, error 500 recuperable, expiración 401/419 y ausencia de fallback demo.
- Pruebas de teclado, confirmación de descarte, retorno de foco, drawer, navegación por query, búsquedas paginadas y reduced motion.
- Capturas en escritorio y móvil 360 px, revisión visual y análisis automatizado axe WCAG A/AA en las pantallas incluidas en E2E.

Las primeras ejecuciones detectaron problemas de contraste y foco que fueron corregidos antes de la validación final. Las pruebas de contrato interceptadas no equivalen a validar un servidor Laravel real ni la exactitud normativa del catálogo HIS.

La comprobación de `http://localhost:8000/api/v1/health` no encontró un servidor disponible en el momento de validar. Queda pendiente la integración con Laravel vivo, cookies/CORS y el catálogo oficial.

Los reportes locales están excluidos del commit por contener capturas y archivos de ejecución. Se revisaron visualmente el dashboard de escritorio, dashboard móvil y la revisión de atención en móvil. Se corrigieron desbordamiento horizontal, contraste y acceso por teclado a la tabla HIS.
