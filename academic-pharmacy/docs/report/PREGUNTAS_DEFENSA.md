# Preguntas de defensa — Kay Care Farmacia

Hay 33 preguntas que podría hacer un docente de Cloud Computing. Cada una tiene:
- **Respuesta corta:** para decir en 20 a 40 segundos.
- **Explicación:** el detalle técnico por si repreguntan.
- **Ejemplo en Kay Care:** cómo se aplica en el proyecto.
- **Evidencia:** qué archivo o pantalla mostrar. Las rutas son relativas a la raíz del repositorio.

## Parte A — Preguntas básicas

### 1. ¿Por qué eligieron Azure?

**Respuesta corta:** El curso permitía AWS, Azure o Google Cloud. Elegimos Azure porque tenemos la suscripción Azure for Students y porque sus servicios PaaS se ajustan al proyecto: Container Apps ejecuta contenedores sin administrar servidores y PostgreSQL Flexible Server es una base de datos gestionada con alta disponibilidad por zonas.

**Explicación:** Container Apps escala de cero a N réplicas, entrega HTTPS con certificado gestionado y tiene ingress interno para la API. PostgreSQL Flexible Server ofrece el modo ZoneRedundant con conmutación automática. Terraform tiene un proveedor oficial (`azurerm`), y GitHub Actions se autentica en Azure con OIDC sin guardar contraseñas.

**Ejemplo en Kay Care:** el entorno DEMO usa Container Apps con escala a cero, de modo que no cobra cuando nadie visita la tienda.

**Evidencia:** `academic-pharmacy/infra/terraform/environments/demo/main.tf`.

### 2. ¿Por qué utilizaron Docker?

**Respuesta corta:** Para que la aplicación funcione igual en cualquier lugar. Cada servicio viaja en un contenedor con sus dependencias, así que mi laptop, la del profesor y Azure ejecutan exactamente lo mismo. Además, Docker Compose levanta el sistema completo con un comando.

**Explicación:** sin contenedores habría que instalar PHP, extensiones, Node y PostgreSQL en cada equipo, en versiones compatibles. Las imágenes son inmutables y reproducibles, y son las mismas que la CI construye y publica en GHCR para Azure.

**Ejemplo en Kay Care:** `docker compose up --build` levanta `db`, `backend` y `frontend`, y la tienda queda en http://localhost:8088.

**Evidencia:** `academic-pharmacy/docker-compose.yml` y Figura 4 del informe (`docs/evidence/23-docker-compose.png`).

### 3. ¿Qué diferencia existe entre una imagen y un contenedor?

**Respuesta corta:** La imagen es la plantilla: un paquete de solo lectura con el sistema base, las librerías y el código. El contenedor es una instancia en ejecución de esa imagen. De una sola imagen pueden salir muchos contenedores.

**Explicación:** la imagen se construye con `docker build` a partir de un `Dockerfile`, por capas. El contenedor agrega una capa escribible y un proceso en ejecución. Si se borra el contenedor, la imagen sigue intacta.

**Ejemplo en Kay Care:** en la prueba de alta disponibilidad, una misma imagen de la API generó dos contenedores, `backend-1` y `backend-2`.

**Evidencia:** `academic-pharmacy/backend/Dockerfile` · `scripts/ha-local-test.sh`.

### 4. ¿Qué es Infrastructure as Code?

**Respuesta corta:** Es describir la infraestructura (redes, bases de datos, contenedores) en archivos de código, en lugar de crearla a mano en un portal. Así se versiona en Git, se revisa como cualquier código y se puede recrear igual las veces que se necesite.

**Explicación:** evita la «deriva de configuración» (nadie sabe qué se cambió a mano), permite revisar los cambios antes de aplicarlos y sirve como documentación viva.

**Ejemplo en Kay Care:** el grupo de recursos, Container Apps, PostgreSQL y Log Analytics están escritos en Terraform; no hay ningún recurso creado a mano.

**Evidencia:** `academic-pharmacy/infra/terraform/`.

### 5. ¿Qué ventaja ofrece Terraform?

**Respuesta corta:** Es declarativo: describo cómo quiero que quede la infraestructura y Terraform calcula qué crear, cambiar o borrar. Con `terraform plan` veo el impacto antes de aplicar nada, y con módulos reutilizo el mismo código en DEMO y en HA.

**Explicación:** Terraform guarda un **estado** con lo que creó. Funciona con muchos proveedores, no solo Azure, y su ecosistema es amplio. `validate` detecta errores sin tocar la nube.

**Ejemplo en Kay Care:** los módulos `postgres` y `container-app` se reutilizan en ambos entornos. Solo cambian parámetros como el SKU o las réplicas.

**Evidencia:** `infra/terraform/modules/` y Figura 5 del informe (`terraform validate`).

### 6. ¿Qué diferencia existe entre Terraform y Docker?

**Respuesta corta:** Resuelven problemas distintos. Docker empaqueta y ejecuta **la aplicación**. Terraform crea **la infraestructura** donde se ejecuta esa aplicación. Docker es la caja; Terraform construye el local donde se pone la caja.

**Explicación:** Terraform crea en Azure el entorno de Container Apps y la base de datos. Luego, Container Apps descarga y ejecuta las imágenes Docker que construyó la CI.

**Ejemplo en Kay Care:** en `environments/demo/main.tf` la variable `backend_image` apunta a la imagen Docker publicada en GHCR.

**Evidencia:** `infra/terraform/environments/demo/variables.tf` (`backend_image`) y `backend/Dockerfile`.

### 7. ¿Qué hace GitHub Actions?

**Respuesta corta:** Ejecuta tareas automáticas en servidores de GitHub cuando ocurre un evento, como un push o un pull request. En nuestro caso prueba el backend y el frontend, valida Terraform, construye las imágenes Docker y levanta todo para un smoke test.

**Explicación:** los workflows son archivos YAML con jobs y pasos. Cada job corre en una máquina limpia. Usamos permisos mínimos (`contents: read`), y `packages: write` solo para publicar imágenes desde `main`.

**Ejemplo en Kay Care:** al abrir el pull request #2, `pharmacy-ci` ejecutó sus 6 jobs y todos pasaron en verde.

**Evidencia:** `.github/workflows/pharmacy-ci.yml` y Figura 6 del informe.

### 8. ¿Qué diferencia hay entre CI y CD?

**Respuesta corta:** La CI, integración continua, comprueba automáticamente que cada cambio funciona: compila y prueba. La CD, entrega o despliegue continuo, publica la versión aprobada en un entorno. En Kay Care la CI es automática y la CD es manual, porque desplegar crea recursos que cuestan dinero.

**Explicación:**
- En la **entrega continua**, la versión queda lista para desplegar con aprobación humana.
- En el **despliegue continuo**, se publica sin intervención.

Elegimos la entrega con aprobación, que es más segura en costos.

**Ejemplo en Kay Care:** `pharmacy-deploy.yml` solo tiene el disparador `workflow_dispatch`, exige escribir `DESPLEGAR-demo` y requiere aprobación del Environment.

**Evidencia:** `.github/workflows/pharmacy-deploy.yml` y Figura 7 (cero ejecuciones).

### 9. ¿Qué es alta disponibilidad?

**Respuesta corta:** Es la capacidad del sistema de seguir funcionando aunque falle una de sus partes. Se logra eliminando los puntos únicos de fallo: varias réplicas, un balanceador que reparte el tráfico, distribución en zonas de disponibilidad y una base de datos con respaldo sincronizado.

**Explicación:** se mide como el porcentaje de tiempo disponible. No la medimos en la nube porque no desplegamos. Nuestro diseño HA tolera la caída de una zona, pero no la de una región completa.

**Ejemplo en Kay Care:** el entorno HA define de 3 a 10 réplicas de la API en 3 zonas y PostgreSQL ZoneRedundant.

**Evidencia:** `infra/terraform/environments/ha/main.tf` · Figura 8 · `docs/architecture/demo-vs-ha.md`.

### 10. ¿Qué sucede si se cae un contenedor?

**Respuesta corta:** En local, Docker Compose lo reinicia automáticamente (`restart: unless-stopped`) y el health check muestra si está sano. Con varias réplicas, las otras siguen atendiendo. Lo probamos: apagamos una de dos réplicas de la API con tráfico y respondieron 179 de 180 solicitudes.

**Explicación:** nginx resuelve el nombre `backend` hacia todas las réplicas; si una rechaza la conexión, reintenta en otra. La única solicitud perdida fue la que estaba en curso en el instante del fallo. En Azure, Container Apps recrea la réplica para mantener el mínimo configurado.

**Ejemplo en Kay Care:** `bash scripts/ha-local-test.sh`.

**Evidencia:** `academic-pharmacy/scripts/ha-local-test.sh` · `docs/evidence/README.md`.

### 11. ¿Qué sucede si falla PostgreSQL?

**Respuesta corta:** En el entorno DEMO, la base de datos es única: si falla, la tienda deja de funcionar hasta que Azure la recupere. Ese es su punto único de fallo, y lo declaramos. En el diseño HA hay un servidor en espera en otra zona, con replicación síncrona, que toma el control automáticamente.

**Explicación:** durante la conmutación (típicamente de 60 a 120 segundos, según la documentación de Azure) las solicitudes fallan; la aplicación no reintenta las transacciones sola. Las copias de seguridad automáticas permiten restaurar: 7 días en DEMO y 35 días geo-redundantes en HA.

**Ejemplo en Kay Care:** `GET /api/v1/store/health` informa `"database":"ok"`; si la base cae, el health check falla y la plataforma lo detecta.

**Evidencia:** `docs/architecture/demo-vs-ha.md` (sección de puntos únicos de fallo) · `infra/terraform/modules/postgres/`.

### 12. ¿Cómo se protege la base de datos?

**Respuesta corta:** Con varias capas:
- en Docker, PostgreSQL está en una red interna sin puerto publicado;
- en Azure exige TLS, y en HA solo tiene acceso privado por VNet;
- usa un usuario propio con contraseña aleatoria generada por Terraform;
- las consultas son parametrizadas;
- tiene restricciones como `CHECK (stock >= 0)`.

**Explicación:** el frontend ni siquiera puede llegar a la base de datos: lo comprobamos con `wget db:5432` desde el contenedor web y falló. Un riesgo aceptado es que en DEMO PostgreSQL tiene acceso público limitado a servicios de Azure, con TLS.

**Ejemplo en Kay Care:** en `docker-compose.yml`, la red `data` está declarada `internal: true`.

**Evidencia:** `academic-pharmacy/docker-compose.yml` · `docs/security/threat-matrix.md`.

### 13. ¿Cómo garantizan que no se venda un producto sin stock?

**Respuesta corta:** Con cuatro defensas:
- una transacción, que guarda todo o nada;
- el bloqueo de filas con `SELECT … FOR UPDATE`, para que dos compras no tomen la misma unidad;
- la restricción `CHECK (stock >= 0)` en la base de datos;
- una clave de idempotencia contra los reintentos.

Lo probamos con 9 compras simultáneas y stock 4: se aceptaron exactamente 4.

**Explicación:** las filas se bloquean en orden ascendente de id, para evitar interbloqueos. El precio y el stock se leen de la fila bloqueada, no del navegador. Si no alcanza, se responde 409 y no se graba nada.

**Ejemplo en Kay Care:** `CheckoutService::createOrder()` con `->orderBy('id')->lockForUpdate()`.

**Evidencia:** `academic-pharmacy/backend/app/Services/Checkout/CheckoutService.php` · `scripts/concurrency-check.mjs`.

### 14. ¿Qué es escalabilidad horizontal?

**Respuesta corta:** Es aumentar la capacidad agregando más copias del mismo servicio, en lugar de comprar una máquina más potente (eso sería escalabilidad vertical). Es como abrir más cajas en lugar de pedirle al cajero que trabaje más rápido.

**Explicación:** requiere que el servicio no guarde estado en memoria. En Kay Care, las sesiones y la caché están en PostgreSQL, así que cualquier réplica puede atender a cualquier usuario.

**Ejemplo en Kay Care:** el entorno HA escala la API de 3 a 10 réplicas según las solicitudes concurrentes (regla HTTP de Container Apps).

**Evidencia:** `infra/terraform/modules/container-app/main.tf` (`http_scale_rule`).

### 15. ¿Cuánto cuesta desplegar el proyecto?

**Respuesta corta:** El desarrollo local no cuesta nada. El entorno DEMO cuesta alrededor de 0,60 dólares si vive 8 horas el día de la exposición y se retira ese mismo día. Si se olvida encendido un mes, unos 18 a 20 dólares, sobre todo por PostgreSQL. La HA costaría unos 650 a 700 dólares al mes. Hasta hoy hemos consumido cero dólares de créditos.

**Explicación:** son precios de lista para `canadacentral`, consultados en la API oficial de precios de Azure. Container Apps cobra solo mientras hay réplicas activas, y escala a cero. PostgreSQL cobra cada hora que existe, aunque no haya visitas. No asumimos capa gratuita, porque la concesión de Container Apps ya la consumen otras apps de la suscripción.

**Ejemplo en Kay Care:** `azure-preflight.ps1` vuelve a calcular el costo con precios actualizados antes de desplegar.

**Evidencia:** `academic-pharmacy/docs/report/costos.md`.

### 16. ¿Cuál es la diferencia entre el entorno DEMO y HA?

**Respuesta corta:**
- **DEMO** busca el menor costo: de 0 a 1 réplica por app, escala a cero, un PostgreSQL B1ms sin respaldo y sin zonas. No tiene alta disponibilidad.
- **HA** busca la continuidad: de 3 a 10 réplicas de API en 3 zonas, PostgreSQL con réplica síncrona, red privada y copias geo-redundantes.

**Explicación:** ambos usan los mismos módulos de Terraform con distintos parámetros. HA cuesta unas 35 veces más que dejar DEMO encendido un mes.

**Ejemplo en Kay Care:** solo DEMO se desplegaría el día de la exposición; HA queda como diseño validado.

**Evidencia:** `docs/architecture/demo-vs-ha.md` · `environments/demo/main.tf` frente a `environments/ha/main.tf`.

### 17. ¿Por qué usar Laravel en vez de otro framework?

**Respuesta corta:** Laravel trae resuelto lo que una tienda necesita:
- validación de datos (Form Requests);
- autenticación segura para SPA (Sanctum, con CSRF);
- ORM y migraciones de base de datos;
- límites de intentos (rate limiting);
- un buen soporte de pruebas.

Además, el sistema clínico del mismo repositorio ya usa Laravel, lo que facilita el mantenimiento.

**Explicación:** las migraciones versionan el esquema de la base de datos, igual que Terraform versiona la infraestructura. `DB::transaction()` y `lockForUpdate()` hacen explícita la lógica de concurrencia.

**Ejemplo en Kay Care:** el endpoint `POST /checkout` valida con `CheckoutRequest` y procesa con `CheckoutService`.

**Evidencia:** `backend/routes/api.php` · `backend/app/Http/Requests/Store/CheckoutRequest.php`.

### 18. ¿Qué beneficios tiene PostgreSQL?

**Respuesta corta:** Es una base de datos relacional robusta y de código abierto, con transacciones ACID, bloqueo de filas y restricciones `CHECK`, que necesitamos para proteger el inventario. Además, Azure la ofrece como servicio gestionado con alta disponibilidad por zonas.

**Explicación:** las restricciones de la base de datos protegen aunque el código tuviera un error. Ser de código abierto evita licencias y funciona igual en Docker que en Azure.

**Ejemplo en Kay Care:** `orders.idempotency_key` es UNIQUE: si dos solicitudes con la misma clave compiten, una falla y el servicio devuelve el pedido ganador.

**Evidencia:** `backend/database/migrations/2026_11_04_000200_create_orders_tables.php`.

### 19. ¿Cómo se realiza un despliegue?

**Respuesta corta:** Con dos scripts.
1. `azure-preflight.ps1`, de solo lectura, verifica la sesión, la región permitida, las imágenes, Terraform y el costo.
2. `deploy-azure.ps1` pide escribir el nombre de la suscripción, muestra el plan y el costo, y exige una frase de confirmación. Recién entonces aplica Terraform, espera los health checks, hace una compra de prueba y muestra la URL.

El mismo día se retira todo con `terraform destroy`.

**Explicación:** el script se detiene si el plan crea algo fuera del grupo `rg-kcf-demo`, para no afectar otros proyectos de la suscripción. Existe también el workflow manual `pharmacy-deploy.yml`, que usa OIDC.

**Ejemplo en Kay Care:** la frase de confirmación es `DESPLEGAR KCF-DEMO EN <suscripción>`.

**Evidencia:** `academic-pharmacy/docs/deploy/DIA-EXPOSICION.md` · `scripts/deploy-azure.ps1`.

### 20. ¿Qué partes funcionan y cuáles están solamente diseñadas?

**Respuesta corta:**
- **Funcionan y están probadas:** la tienda completa en Docker, la API, la base de datos, las pruebas automatizadas, la CI en GitHub y Terraform validado.
- **Solo diseñados:** el despliegue en Azure y la alta disponibilidad en la nube. La HA la probamos localmente, pero no en Azure.

**Explicación:** lo distinguimos explícitamente en el informe: desarrollado, probado, diseñado con Terraform y desplegado. Nada está desplegado todavía.

**Ejemplo en Kay Care:** el workflow `pharmacy-deploy` muestra «0 workflow runs».

**Evidencia:** informe, secciones 19 a 22 · Figura 7.

## Parte B — Preguntas más difíciles

### 21. ¿Por qué el frontend pasa por nginx y no llama directo a la API?

**Respuesta corta:** Para que el navegador vea un solo origen. Así la cookie de sesión es de primera parte, la protección CSRF funciona sin abrir CORS a otros dominios y la API no necesita estar expuesta a internet: en Azure tiene ingress interno.

**Explicación:** nginx sirve los archivos de Vue y reenvía `/api/*` al contenedor de Laravel. También agrega las cabeceras de seguridad (CSP, X-Frame-Options).

**Ejemplo en Kay Care:** `location /api/ { proxy_pass … }` en la plantilla de nginx.

**Evidencia:** `academic-pharmacy/frontend/nginx/default.conf.template`.

### 22. ¿Qué es la idempotencia y por qué la necesitan?

**Respuesta corta:** Una operación es idempotente si repetirla da el mismo resultado sin efectos adicionales. Si el usuario confirma la compra, se corta la red y vuelve a intentar, sin idempotencia se crearían dos pedidos. Con la clave de idempotencia, el servidor reconoce el reintento y devuelve el mismo pedido.

**Explicación:** el frontend genera una clave única por contenido de carrito y la envía en la cabecera `Idempotency-Key`. El servidor guarda la clave (UNIQUE) y un hash del contenido. Si llega la misma clave con el mismo contenido, responde 200 con el mismo pedido; con otro contenido, responde 409.

**Ejemplo en Kay Care:** el smoke test comprueba «reintento misma clave → 200» y «misma clave, otro contenido → 409».

**Evidencia:** `scripts/smoke-test.sh` · `CheckoutService.php` (método `replay`).

### 23. ¿Por qué guardan los precios en céntimos?

**Respuesta corta:** Porque los números decimales en coma flotante tienen errores de redondeo: 0,1 + 0,2 no da exactamente 0,3. Guardando enteros en céntimos, por ejemplo 2490 para S/ 24,90, las sumas de dinero son exactas.

**Explicación:** el IGV del 18 % está incluido en el precio y se desglosa con una fórmula entera, `round(total × 18 / 118)`.

**Ejemplo en Kay Care:** la columna `products.price_cents` es de tipo `unsignedInteger`.

**Evidencia:** `backend/database/migrations/2026_11_04_000100_create_catalog_tables.php`.

### 24. ¿Qué es el estado de Terraform y por qué no se sube a Git?

**Respuesta corta:** Es el archivo donde Terraform anota qué recursos creó y sus atributos, para poder modificarlos o destruirlos después. No se sube a Git porque contiene secretos, como la contraseña de la base de datos generada por Terraform.

**Explicación:** el script local usa un estado en `environments/demo/terraform.tfstate`, que está en `.gitignore`. El workflow de GitHub usaría un backend remoto cifrado en Azure Storage. Si se pierde el estado, Terraform «olvida» los recursos; por eso se conserva hasta retirarlos.

**Ejemplo en Kay Care:** las reglas `infra/**/*.tfstate` del archivo `.gitignore`.

**Evidencia:** `academic-pharmacy/.gitignore` · `docs/deploy/DIA-EXPOSICION.md`.

### 25. ¿Cómo se autentica GitHub Actions en Azure sin contraseñas?

**Respuesta corta:** Con OIDC, una federación de identidad. GitHub emite un token temporal firmado para esa ejecución, y Microsoft Entra ID lo cambia por credenciales de corta duración. No hay ninguna contraseña ni clave guardada en GitHub.

**Explicación:** el job pide el permiso `id-token: write`, y en Entra ID se configura qué repositorio y qué rama pueden obtener credenciales. Es más seguro que guardar un secreto de larga duración. Todavía no está configurado, porque no se ha desplegado.

**Ejemplo en Kay Care:** `ARM_USE_OIDC: "true"` en `pharmacy-deploy.yml`.

**Evidencia:** `.github/workflows/pharmacy-deploy.yml`.

### 26. ¿Qué es el arranque en frío y cómo les afecta escalar a cero?

**Respuesta corta:** Cuando una app escalada a cero recibe la primera visita, la plataforma debe crear el contenedor. Esa primera respuesta tarda varios segundos más. A cambio, no se paga mientras no hay tráfico. Para la exposición basta con abrir la tienda unos minutos antes.

**Explicación:** en HA se evita con réplicas mínimas siempre activas, que cuestan más. Es un intercambio clásico entre costo y latencia.

**Ejemplo en Kay Care:** DEMO usa `min_replicas = 0`.

**Evidencia:** `infra/terraform/environments/demo/main.tf`.

### 27. ¿Qué pasa si se cae toda una región de Azure?

**Respuesta corta:** Nuestro diseño no lo resiste: incluso el entorno HA está en una sola región. Toleraría la caída de una zona, pero no de la región completa. Las copias geo-redundantes permitirían restaurar en otra región manualmente, lo que tomaría horas. La solución sería una arquitectura multirregión, que dejamos como trabajo futuro.

**Explicación:** una arquitectura multirregión requiere Azure Front Door o Traffic Manager, una réplica de la base de datos en otra región y una estrategia activo-pasivo, con un costo mucho mayor.

**Ejemplo en Kay Care:** esto está declarado como riesgo residual número 1 de HA.

**Evidencia:** `docs/architecture/demo-vs-ha.md`.

### 28. ¿Por qué Container Apps y no Kubernetes (AKS) o máquinas virtuales?

**Respuesta corta:** Container Apps nos da lo que necesitamos de Kubernetes (réplicas, escalado automático, balanceo, health checks y HTTPS) sin administrar un clúster. Además, escala a cero. Con máquinas virtuales tendríamos que administrar el sistema operativo y pagar aunque no haya tráfico.

**Explicación:** AKS ofrece más control, pero cobra los nodos del clúster de forma permanente y exige más operación. Para dos servicios, Container Apps (PaaS) es más simple y económico.

**Ejemplo en Kay Care:** el módulo `container-environment` crea un entorno con perfil de consumo.

**Evidencia:** `infra/terraform/modules/container-environment/main.tf`.

### 29. ¿Por qué GHCR y no Azure Container Registry (ACR)?

**Respuesta corta:** GHCR está integrado con GitHub Actions: publicamos con el token de la propia ejecución, sin secretos extra. No tiene costo para paquetes públicos y no agrega un recurso facturable en Azure. ACR Basic cuesta unos 5 dólares al mes.

**Explicación:** la contrapartida es que dependemos de un servicio externo a Azure. En producción usaríamos ACR Premium con geo-replicación e identidad gestionada.

**Ejemplo en Kay Care:** la CI publica `ghcr.io/elias040720/kay-care-pharmacy-backend:sha-<commit>`.

**Evidencia:** `.github/workflows/pharmacy-ci.yml` (paso «Publicar en GHCR»).

### 30. ¿Cómo evitan la inyección SQL y el XSS?

**Respuesta corta:**
- **Contra la inyección SQL:** todas las consultas usan parámetros, nunca se concatena texto del usuario, y en las búsquedas se escapan los comodines de `LIKE`.
- **Contra el XSS:** Vue escapa automáticamente lo que muestra, prohibimos `v-html` con una regla de ESLint y la CSP solo permite scripts del propio sitio.

**Explicación:** una prueba automática busca el texto `' OR 1=1 --` (y comodines como `%`) y verifica que se trate como texto literal. Otra muestra un nombre con `<img onerror=…>` y verifica que no se ejecute.

**Ejemplo en Kay Care:** las pruebas `test_search_treats_wildcards_and_sql_as_literal_text` y la de XSS en `ProductCard.test.ts`.

**Evidencia:** `backend/tests/Feature/CatalogTest.php` · `frontend/src/components/ProductCard.test.ts` · `frontend/nginx/security-headers.conf`.

### 31. ¿Cómo comprobaron que no modificaron el sistema de enfermería?

**Respuesta corta:** De dos formas. Con `git diff` desde el commit base: fuera de la carpeta de la farmacia solo cambiaron sus dos workflows, y el `backend/` y el `frontend/` clínicos tienen cero archivos modificados. Y ejecutamos las pruebas del sistema clínico: 108 de 108 pasan, sin modificar archivos.

**Explicación:** además, la farmacia usa su propia base de datos, su propio usuario, su propia cookie de sesión, su propia `APP_KEY` y su propio proyecto de Docker Compose.

**Ejemplo en Kay Care:** el proyecto Compose se llama `kaycare-pharmacy` y la cookie, `kcf_pharmacy_session`.

**Evidencia:** Figura 18 del informe (`docs/evidence/26-regresion-clinica.png`).

### 32. ¿Cómo retiran la infraestructura sin borrar otros proyectos?

**Respuesta corta:** Solo con `terraform destroy` sobre el mismo estado que creó los recursos, nunca borrando grupos de recursos a mano. Antes ejecutamos `terraform plan -destroy` y revisamos que todo pertenezca al grupo `rg-kcf-demo`. Si aparece cualquier otro recurso, no se confirma.

**Explicación:** Terraform solo conoce los recursos de su estado, así que no puede tocar recursos de otros proyectos de la suscripción. Después se verifica con `az group exists -n rg-kcf-demo` (debe responder false).

**Ejemplo en Kay Care:** todos los recursos llevan la etiqueta `project = kay-care-farmacia`.

**Evidencia:** `docs/deploy/DIA-EXPOSICION.md` (sección «Retirar los recursos»).

### 33. ¿Qué limitaciones tiene su prueba de alta disponibilidad?

**Respuesta corta:**
- Fue local, con Docker, no en Azure.
- Solo cubrió la capa de API: la base de datos local siguió siendo única.
- Se perdió una solicitud en el instante del fallo.

Por eso decimos «probada localmente» y no «alta disponibilidad comprobada en la nube».

**Explicación:** una prueba real en Azure requeriría desplegar el entorno HA, de unos 650 a 700 dólares al mes, y provocar fallos de zona y de base de datos. Una primera ejecución de la prueba dio falsos fallos por el límite de 120 solicitudes por minuto de la API; ajustamos el ritmo y ahora el script registra el código de cada error.

**Ejemplo en Kay Care:** resultado: 179 de 180 solicitudes atendidas.

**Evidencia:** `scripts/ha-local-test.sh` · `docs/architecture/demo-vs-ha.md` (tabla «Estado de la alta disponibilidad»).

## Consejo final para la defensa

Si no sabes algo, di: **«Eso no lo implementamos en esta versión; está en las recomendaciones del informe»**. Nunca afirmes que algo está desplegado en Azure ni que la alta disponibilidad se comprobó en la nube: el informe y las evidencias dicen exactamente lo contrario, y el profesor puede verificarlo.
