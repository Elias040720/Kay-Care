# Guía de exposición — Kay Care Farmacia (6–7 minutos)

> **Antes de empezar:**
> - Levanta el stack con `docker compose up -d --build` en `academic-pharmacy/` y abre http://localhost:8088.
> - Ten a mano los diagramas (`docs/diagrams/export/`) y las capturas (`docs/evidence/`).
>
> Si ese día se autorizó el despliegue en Azure, usa la URL que mostró `deploy-azure.ps1`. Si no, di: «la URL pública se genera el día del despliegue autorizado».

| Tiempo | Bloque | Qué decir | Qué mostrar |
|---|---|---|---|
| 0:00–0:40 | **Contexto** | «Kay Care ya tenía un sistema clínico de enfermería. Agregamos una farmacia online demostrativa **sin tocarlo**: productos ficticios de venta libre, sin cobros ni datos personales.» Leer el aviso: «Proyecto académico — no se realizan ventas reales». | Inicio con la barra del aviso |
| 0:40–2:10 | **Compra en vivo** | «La navegación se inspira en las farmacias online peruanas, con identidad propia: buscador grande con sugerencias en vivo, categorías y ofertas.» Buscar «vitamina», entrar a una categoría, abrir un producto en oferta y agregarlo. «El carrito valida el stock contra el servidor; el checkout solo pide un alias ficticio.» Probar «45879632» (rechazado) y luego un alias válido. | Demo: buscador → categoría → detalle → carrito → checkout → «Pedido realizado en entorno de demostración» |
| 2:10–3:00 | **Inventario sin sobreventa** | «Si dos personas compran la última unidad a la vez, una transacción con `SELECT … FOR UPDATE` lo impide. Si la red falla y se reintenta, la clave de idempotencia devuelve el mismo pedido.» Prueba real: 9 compras simultáneas con stock 4 → 4 pedidos, 5 rechazos, stock 0. | Diagrama A (flujo de compra) y salida de `node scripts/concurrency-check.mjs` |
| 3:00–3:40 | **Administración** | «Login sin registro público y con límite de intentos; dashboard, productos con precio de oferta, stock auditado y pedidos por estados.» | Panel en vivo o `13-admin-dashboard.png` |
| 3:40–4:30 | **Contenedores y seguridad** | «Tres contenedores: nginx, Laravel y PostgreSQL en una red interna. Imágenes sin root y de solo lectura, CSP estricta, CSRF, rate limiting.» | `docker compose ps` y smoke test 18/18 |
| 4:30–5:30 | **Azure sin gastar** | «La infraestructura está en Terraform: Resource Group propio, Container Apps, PostgreSQL y Log Analytics. Hoy no hay nada creado. El día de la exposición, `deploy-azure.ps1` revisa la suscripción, muestra el plan y el costo (unos 60 centavos de dólar si se retira el mismo día) y exige una confirmación escrita. Los workflows nunca despliegan con un push.» | Diagrama B y `docs/report/costos.md` |
| 5:30–6:20 | **Alta disponibilidad, con honestidad** | «La HA está diseñada: réplicas en 3 zonas y PostgreSQL con réplica síncrona, por unos 650–700 dólares al mes, así que no la activamos. Localmente detuvimos una de dos réplicas de la API con tráfico: 179 de 180 solicitudes respondieron. No afirmamos HA real en Azure porque no la desplegamos.» | Diagrama D y `docs/architecture/demo-vs-ha.md` |
| 6:20–7:00 | **Cierre** | «Resultados: 70 pruebas de backend sobre PostgreSQL, 45 de frontend, 3 recorridos E2E sin violaciones de accesibilidad y la regresión clínica 108/108 sin cambios.» | Tabla de pruebas del informe |

## Preguntas probables

- **¿Copiaron Mifarma o Inkafarma?** No. Se tomó solo el patrón de navegación habitual: buscador central, menú de categorías y tarjetas con «Agregar». Logo, ilustraciones, colores y textos son propios; no hay marcas ni imágenes de terceros.
- **¿Por qué no está en Azure ya?** Para no consumir crédito. Todo está listo como código, y el despliegue es de un solo comando con confirmación.
- **¿Cuánto cuesta?** Unos US$ 0,60 un día con 4 horas de uso. Sin visitas cobra solo PostgreSQL y su almacenamiento. Lo que más importa es retirarlo el mismo día.
- **¿Por qué no microservicios?** Dos servicios desplegables y módulos de dominio bastan; más servicios añadirían red, consistencia distribuida y costo.
- **¿Cómo saben que enfermería no cambió?** El `git diff` fuera de `academic-pharmacy/` y de los workflows `pharmacy-*` está vacío, y su batería de pruebas pasa 108/108.
