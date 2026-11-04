# Validación de la integración

Fecha: 4 de noviembre de 2026. Worktree principal `Kay-Care`, rama `main`. Windows PowerShell, PHP 8.2.33, SQLite, Node 24.22.1, npm 10.8.2 y Google Chrome. Solo información ficticia.

## Historial conservado

- El merge pendiente del backend tenía `MERGE_HEAD=58965f1`, cambios staged iguales a esa rama y ningún conflicto. Se completó con `git commit --no-edit`: merge `684a056`.
- Se integró `feat/codex-frontend` (`ffb4648`) mediante merge no fast-forward: `f061ead`.
- Ambos historiales y sus worktrees se conservaron. No se usaron reset destructivo, push forzado ni borrado de ramas/worktrees.

## Correcciones de integración

- Validaciones Vue alineadas con Laravel: HC, nombres/apellidos, documentos y CNV, fecha mínima, localidad, notas, límites y precisión de mediciones, LAB.
- El login muestra los mensajes 422 por campo y lleva el foco al error.
- El modo HTTP real conserva advertencias DEMO: el backend incluido solo ofrece catálogo ficticio y plantillas PDF demostrativas. `validated` se presenta como consistencia estructural, sin certificación clínica ni normativa.
- Se puede guardar o limpiar una lista HIS vacía aunque no exista catálogo vigente. Listas no vacías siguen rechazándose, y la vista previa sin catálogo permanece no validada.
- PHPUnit utiliza una clave exclusivamente de prueba, configuración aislada y SQLite en memoria. Se fuerzan tanto variables de entorno como `$_SERVER` (relevante en Windows). Un guard comprueba la configuración antes de `RefreshDatabase`.
- Los scripts de raíz sustituyen los comandos Composer de plantilla que intentaban ejecutar npm dentro de `backend/` y regeneraban la clave. El setup conserva claves, configuración y bases existentes; solo siembra una base creada por él.
- La prueba E2E original exige puertos libres para evitar reutilizar accidentalmente un servidor de otro modo. La suite contra Laravel vivo es independiente y no intercepta tráfico.

## Comprobaciones aprobadas

| Comprobación | Resultado |
| --- | --- |
| `composer install --no-interaction --prefer-dist` | Dependencias instaladas desde lock |
| `composer validate --no-check-publish` | Configuración válida |
| `composer check-platform-reqs` | Requisitos satisfechos |
| Migraciones + seed en SQLite nueva aislada | Completados |
| `php artisan test` | **108 tests, 536 assertions** |
| Suite Laravel con `DB_CONNECTION=mysql`, `DB_DATABASE` y `DB_URL` externos ficticios heredados | **108 tests, 536 assertions**, usando SQLite `:memory:` |
| `npm install` | Completado |
| `npm run typecheck` | Aprobado |
| `npm test` | **55 tests** |
| `npm run build` | Compilación de producción aprobada |
| `npm run test:e2e` | **10 tests** en Chrome; demo, contrato interceptado y axe |
| `scripts/test-integration.ps1` | **2 recorridos reales**, 20,2 segundos |
| `npm audit` | **0 vulnerabilidades reportadas** |
| Repetir setup sobre la base existente | `.env` y SQLite conservaron sus hashes; sin reseed |
| Setup completo y arranque conjunto PowerShell | Laravel 8000 y Vue 5173 respondieron correctamente |
| Cierre con Ctrl+C en Windows | Puertos liberados; terminación limitada a los árboles de procesos propios |

Los recorridos reales verifican CORS con origen exacto y credenciales, cookie de sesión HttpOnly, cookie XSRF, rechazo 419 sin CSRF, 401 sin sesión/después de logout y 422 por credenciales incorrectas/HC duplicada. Completan alta y edición de paciente, atención CRED, sugerencias recibidas de Laravel sin seleccionar, selección manual de una actividad, tipo D y LAB manual, guardado, vista previa y ambos PDF binarios. Comprueban historial, recarga, nueva sesión y existencia de filas HIS mediante otro proceso PHP conectado a SQLite. El estado permanece `draft` y la validación es `structural`.

La captura `frontend/qa-output/laravel-review-desktop.png` se revisó visualmente: resumen, tabla HIS, advertencias y descargas legibles. Las pruebas existentes cubren móvil de 360 px, teclado, foco, reduced motion y axe en las pantallas del recorrido. Capturas, reportes, logs, bases de prueba y credenciales están excluidos de Git.

Durante la preparación se corrigieron un error de sintaxis del script y una condición Vue huérfana. Las primeras ejecuciones de integración también detectaron esperas faltantes en el test antes de recargar y volver a iniciar sesión; se corrigieron esperando las rutas finales. La prueba adversa inicial de variables heredadas fue bloqueada por el guard antes de acceder a una base; se ajustó `$_SERVER` y se repitió la suite completa correctamente. Los resultados de la tabla corresponden a ejecuciones posteriores aprobadas.

## Límites de esta validación

No valida códigos HIS oficiales, decisiones clínicas, normativa sanitaria, operación productiva, MySQL ni despliegue. Los PDF tienen marca DEMO; la comprobación de marca y advertencias en plantillas está cubierta por los tests Laravel, y los recorridos reales verifican MIME, cabecera de descarga y contenido PDF. No existe transición API a `reviewed`, firma digital ni resolución de ediciones concurrentes. El historial está limitado a 200 atenciones por paciente.

La aplicación está lista para evaluación local con datos ficticios. Para reproducirla y levantar ambos servidores, sigue el [README principal](../README.md).
