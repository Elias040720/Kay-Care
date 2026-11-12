# Guía de estudio — Kay Care Farmacia desde cero

Esta guía explica cada pieza del proyecto con analogías y ejemplos de la propia tienda. Al final hay una tabla con el archivo que conviene mostrar para cada requisito del curso y una hoja de repaso rápido.

## La analogía general: una farmacia de barrio

Imagina una farmacia física:

| En la farmacia física | En Kay Care Farmacia |
|---|---|
| La vitrina y el mostrador que ve el cliente | **Vue** (el frontend) |
| El farmacéutico que atiende, revisa el stock y cobra | **Laravel** (la API) |
| El almacén y el cuaderno de inventario | **PostgreSQL** (la base de datos) |
| El local completo, armado igual en cualquier distrito | **Docker** (contenedores) |
| Los planos del local para construirlo en otra ciudad | **Terraform** (infraestructura como código) |
| El supervisor que revisa cada cambio antes de abrir | **GitHub Actions** (CI/CD) |
| Tener varios cajeros y un almacén de respaldo | **Alta disponibilidad** |

## 1. ¿Qué hace Vue?

Vue construye la **interfaz**: lo que ves y tocas. Cuando escribes en el buscador o agregas un producto al carrito, Vue reacciona al instante, sin recargar la página.

- **Analogía:** la vitrina y el mostrador. Muestran los productos y reciben tu pedido, pero no deciden si hay stock.
- **En Kay Care:** `frontend/src/views/HomeView.vue` (inicio), `CatalogView.vue` (catálogo), `components/SearchBox.vue` (buscador con sugerencias) y `stores/cart.ts` (carrito, guardado en el navegador).
- **Dato clave:** Vue nunca habla directo con la base de datos; siempre le pregunta a Laravel.

## 2. ¿Qué hace Laravel?

Laravel es la **API**: recibe pedidos de Vue en formato JSON, aplica las reglas del negocio y responde.

- **Analogía:** el farmacéutico. Revisa que el producto exista, que haya stock y calcula el precio real.
- **En Kay Care:** las rutas están en `backend/routes/api.php`. Por ejemplo, `GET /api/v1/store/products` (catálogo) y `POST /api/v1/store/checkout` (compra). La lógica de compra está en `backend/app/Services/Checkout/CheckoutService.php`.
- **Dato clave:** el precio lo calcula siempre el servidor. Aunque alguien manipule el navegador, no puede pagar menos.

## 3. ¿Qué hace PostgreSQL?

PostgreSQL es la **base de datos relacional**: guarda productos, categorías, pedidos, movimientos de stock y sesiones en tablas relacionadas.

- **Analogía:** el almacén con su cuaderno de inventario, pero uno que no se equivoca: si algo sale mal a mitad de una venta, deshace todo (transacción).
- **En Kay Care:** las tablas `products`, `orders`, `order_items` y `stock_movements`. Tiene reglas como `CHECK (stock >= 0)`: la propia base de datos impide un stock negativo.

## 4. ¿Qué es un contenedor y por qué usamos Docker?

Un **contenedor** es una caja que lleva la aplicación con todo lo que necesita: el lenguaje, las librerías y la configuración. Una **imagen** es el molde de esa caja; el contenedor es la caja funcionando.

- **Analogía:** un local de franquicia «llave en mano». Se arma igual en Lima o en Arequipa porque viene con todo adentro.
- **Problema que resuelve:** el clásico «en mi máquina sí funciona». Con Docker funciona igual en tu laptop, en la del profesor y en Azure.
- **En Kay Care:** tres contenedores definidos en `docker-compose.yml`:
  - `db`: PostgreSQL;
  - `backend`: Laravel;
  - `frontend`: nginx con Vue.

  Se levantan con `docker compose up --build`.
- **Seguridad:** los contenedores no corren como administrador (root), y la base de datos está en una red interna sin salida a internet.

## 5. ¿Qué problema resuelve Terraform?

Terraform permite describir la infraestructura en archivos de texto: **infraestructura como código (IaC)**.

- **Analogía:** los planos de construcción del local. Con los planos, cualquier constructor levanta el mismo local, y si quieres cambiar algo, corriges el plano.
- **Problema que resuelve:** sin IaC, alguien crea los recursos a mano en el portal de Azure. Es lento, se olvidan pasos y nadie sabe exactamente qué se configuró. Con Terraform todo queda escrito, versionado en Git y se puede repetir.
- **En Kay Care:** `infra/terraform/environments/demo/main.tf` describe el grupo de recursos, Container Apps, PostgreSQL y Log Analytics.
- **Comandos:**
  - `terraform init` prepara;
  - `terraform validate` revisa;
  - `terraform plan` muestra qué haría;
  - `terraform apply` crea.

  **En el proyecto solo se ejecutaron `init` y `validate`.** `apply` está reservado para el día de la exposición.

## 6. ¿Qué hace GitHub Actions y qué es CI/CD?

GitHub Actions ejecuta tareas automáticas cada vez que pasa algo en el repositorio, por ejemplo un *push*.

- **CI (integración continua):** cada cambio se prueba automáticamente.
- **CD (entrega o despliegue continuo):** la versión aprobada se publica en un servidor.
- **Analogía:** el supervisor de calidad que revisa cada cambio antes de abrir la tienda (CI), y el encargado de abrir la sucursal cuando todo está aprobado (CD).
- **En Kay Care:**
  - `pharmacy-ci.yml`, automático: pruebas, validación de Terraform, construcción de imágenes y smoke test.
  - `pharmacy-deploy.yml`: **solo manual**, con confirmación escrita y aprobación. Nunca se ha ejecutado.

## 7. ¿Qué es escalabilidad?

Es la capacidad de atender más usuarios sin que el sistema colapse.

- **Escalabilidad vertical:** una máquina más potente. Un solo cajero más rápido.
- **Escalabilidad horizontal:** más copias del mismo servicio. Abrir más cajas.
- **En Kay Care:** Azure Container Apps hace escalabilidad horizontal; la API puede pasar de 0 a varias réplicas según el tráfico. En DEMO escala a cero cuando nadie entra, y así no cobra.

## 8. ¿Qué son las réplicas?

Una réplica es **otra copia idéntica** de un contenedor, todas sirviendo al mismo tiempo.

- **Analogía:** tres cajeros idénticos. Si uno sale a almorzar, los otros dos siguen atendiendo.
- **En Kay Care:** el diseño HA tiene de 3 a 10 réplicas de la API. En la prueba local usamos 2.

## 9. ¿Qué es un health check?

Una comprobación automática y periódica que pregunta «¿estás vivo y listo?».

- **Analogía:** el supervisor que cada cierto tiempo verifica que cada caja esté atendiendo. Si una no responde, manda a los clientes a otra.
- **En Kay Care:** `GET /api/v1/store/health` revisa la API y su conexión a la base de datos; `GET /healthz` revisa nginx. Docker Compose y Azure los usan para saber si un contenedor está sano.

## 10. ¿Qué es alta disponibilidad?

Que el servicio siga funcionando aunque falle una parte. Se logra **eliminando los puntos únicos de fallo** con redundancia.

- **Analogía:** una farmacia con varios cajeros, dos almacenes en distritos distintos y un generador eléctrico.
- **Piezas:**
  - **réplicas** (varios cajeros);
  - **balanceador de carga** (quien reparte a los clientes);
  - **zonas de disponibilidad** (centros de datos separados);
  - **base de datos redundante** (almacén de respaldo sincronizado).
- **En Kay Care**, distingue tres niveles:
  1. **Diseñada:** sí, en `environments/ha`.
  2. **Probada localmente:** sí, `scripts/ha-local-test.sh`, con 179 de 180 solicitudes atendidas con una réplica caída.
  3. **Desplegada en Azure:** **no**, por costo (≈ US$ 650–700 al mes).

## 11. ¿Cómo interactúan todos los componentes?

1. Abres http://localhost:8088. **nginx** te entrega la aplicación **Vue**.
2. Vue pide `/api/v1/store/products`. nginx reenvía esa llamada a **Laravel**, por el mismo dominio.
3. Laravel consulta **PostgreSQL** y devuelve los productos en JSON.
4. Agregas productos. Vue los guarda en el carrito y le pide a Laravel validar el stock (`POST /cart/validate`).
5. Confirmas la compra (`POST /checkout`). Laravel abre una transacción, **bloquea** las filas de los productos, verifica el stock, crea el pedido y descuenta el stock. Si algo falla, no se guarda nada.
6. Vue muestra «Pedido realizado en entorno de demostración».
7. Todo esto corre en **contenedores Docker**. En Azure correría en **Container Apps**, creado con **Terraform** y probado por **GitHub Actions**.

## 12. Cómo se evita vender sin stock (pregunta segura del profesor)

Son cuatro defensas que trabajan juntas:
1. **Transacción:** todo o nada.
2. **Bloqueo de filas** (`SELECT … FOR UPDATE`): mientras una compra revisa un producto, otra compra del mismo producto espera.
3. **Restricción `CHECK (stock >= 0)`** en la base de datos: última línea de defensa.
4. **Clave de idempotencia:** un reintento no duplica el pedido.

Prueba real: 9 compras simultáneas con stock 4 dieron 4 pedidos creados, 5 rechazados y stock final 0.

## ¿Qué archivo debo mostrar para demostrar cada requisito del curso?

Todas las rutas son relativas a la raíz del repositorio (`https://github.com/Elias040720/Kay-Care`).

| Requisito del curso | Qué mostrar | Ruta exacta |
|---|---|---|
| **Código** | Repositorio del módulo | `academic-pharmacy/` |
| Plataforma e-commerce visible en la web | La tienda corriendo | http://localhost:8088 (local) · URL de Azure el día de la exposición |
| **Contenedores** | Dockerfiles y Compose | `academic-pharmacy/backend/Dockerfile`, `academic-pharmacy/frontend/Dockerfile`, `academic-pharmacy/docker-compose.yml` |
| **Código IaC** | Terraform de Azure | `academic-pharmacy/infra/terraform/environments/demo/main.tf` y `academic-pharmacy/infra/terraform/modules/` |
| **Automatización** | Workflows de GitHub Actions | `.github/workflows/pharmacy-ci.yml` y `.github/workflows/pharmacy-deploy.yml` · pestaña Actions de GitHub |
| **Seguridad** | Cabeceras, validación, matriz de amenazas | `academic-pharmacy/frontend/nginx/security-headers.conf`, `academic-pharmacy/backend/app/Http/Requests/Store/CheckoutRequest.php`, `academic-pharmacy/docs/security/threat-matrix.md` |
| **Alta disponibilidad** | Terraform HA, prueba local y matriz | `academic-pharmacy/infra/terraform/environments/ha/main.tf`, `academic-pharmacy/scripts/ha-local-test.sh`, `academic-pharmacy/docs/architecture/demo-vs-ha.md` |
| **Diagrama de flujo del programa** | Flujo de compra | `academic-pharmacy/docs/diagrams/export/a-flujo-compra.png` (fuente: `a-flujo-compra.mmd`) |
| **Link** | URL pública (pendiente) y local | Informe, sección 23 · `academic-pharmacy/docs/deploy/DIA-EXPOSICION.md` |
| **Informe** | PDF final | `academic-pharmacy/docs/report/KAY_CARE_INFORME_FINAL.pdf` |
| Integridad del inventario | Bloqueo de filas | `academic-pharmacy/backend/app/Services/Checkout/CheckoutService.php` (método `createOrder`) |
| Pruebas | Tests y evidencias | `academic-pharmacy/backend/tests/`, `academic-pharmacy/frontend/e2e/evidence.spec.ts`, `academic-pharmacy/docs/evidence/` |
| Costos | Estimación verificada | `academic-pharmacy/docs/report/costos.md` |
| Despliegue del día | Scripts | `academic-pharmacy/scripts/azure-preflight.ps1` y `academic-pharmacy/scripts/deploy-azure.ps1` |

## Hoja de repaso rápido (una página)

**Qué es:** una farmacia online académica (productos ficticios, sin cobros) para demostrar Cloud Computing.

**Stack:** Vue 3 (interfaz) → nginx (mismo origen) → Laravel 12 (API) → PostgreSQL 16 (datos). Todo en Docker; Azure diseñado con Terraform; CI con GitHub Actions.

**Cinco números para recordar:**
- **70** pruebas de backend, **45** de frontend y **3** recorridos E2E;
- **4 de 9** compras simultáneas aceptadas con stock 4, sin sobreventa;
- **179 de 180** solicitudes atendidas con una réplica caída (prueba local);
- **≈ US$ 0,60**: costo de DEMO el día de la exposición; **US$ 0** consumidos hasta hoy;
- **≈ US$ 650–700/mes**: costo de HA, por eso no se desplegó.

**DEMO frente a HA:** DEMO tiene réplicas de 0 a 1, escala a cero, PostgreSQL B1ms único y no tiene HA. HA tiene de 3 a 10 réplicas de API en 3 zonas, PostgreSQL con réplica síncrona y red privada.

**Seguridad en una frase:** validación en el servidor, CSRF, CSP, bcrypt, límite de intentos, SQL parametrizado, contenedores sin root, red interna para la base de datos y secretos fuera de Git.

**Lo que funciona:** la tienda completa en Docker, las pruebas, la CI y Terraform validado.

**Lo que está solo diseñado:** el despliegue en Azure y la HA en la nube.

**Frase de cierre:** «Con infraestructura como código, la nube se prepara y se valida antes de pagar por ella.»

**Si te preguntan algo que no sabes:** «No lo implementamos en esta versión; lo dejamos como mejora futura en el informe». Es mejor que inventar.
