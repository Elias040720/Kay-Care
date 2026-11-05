# Matriz de amenazas y controles — Kay Care Farmacia

Metodología: STRIDE aplicada a los flujos del diagrama A. Probabilidad (P) e impacto (I) en escala 1–3 **tras** los controles. «Evidencia» indica dónde se verifica el control.

| # | Amenaza (STRIDE) | Activo / flujo | Controles implementados | Evidencia | P | I |
|---|---|---|---|---|---|---|
| 1 | Suplantación del administrador por fuerza bruta (S) | `POST /admin/login` | Rate limit 5/min por correo+IP y 20/min por IP; mensaje genérico; contraseñas con bcrypt; sin registro público | `AdminAuthTest` (rate limit, mensaje genérico, sin `/register`, hash) | 1 | 3 |
| 2 | Robo de sesión mediante XSS (S/I) | Cookie de sesión | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` en Azure, cifrada (`SESSION_ENCRYPT`); CSP estricta `script-src 'self'`; Vue escapa todo el texto; regla ESLint `vue/no-v-html` | Smoke test (HttpOnly), Playwright (CSP bloqueó un script inline), `ProductCard.test.ts` (XSS) | 1 | 3 |
| 3 | CSRF en acciones que modifican datos (T) | Checkout y panel | Sanctum SPA: token `XSRF-TOKEN` + cabecera `X-XSRF-TOKEN`; mismo origen vía proxy nginx; CORS con lista blanca | Smoke test: POST sin token → **419** | 1 | 2 |
| 4 | Inyección SQL (T/I) | Búsqueda y filtros | Eloquent con parámetros enlazados; `LIKE` con escape de comodines; columnas fijas; validación con Form Requests | `CatalogTest` (comodines y `' OR 1=1 --` literales) | 1 | 3 |
| 5 | Manipulación de precios desde el cliente (T) | `POST /checkout` | El servidor ignora precios/totales enviados y los calcula desde la fila bloqueada | `CheckoutTest::client_supplied_prices…` | 1 | 3 |
| 6 | Sobreventa por condiciones de carrera (T) | Stock | Transacción + `SELECT … FOR UPDATE` en orden de id; `CHECK (stock >= 0)` en PostgreSQL | `scripts/concurrency-check.mjs`: 7 compras simultáneas, stock 2 → 2 creadas, 5 rechazadas, stock final 0 | 1 | 2 |
| 7 | Pedidos duplicados por reintentos (T) | Checkout | Clave de idempotencia obligatoria, `UNIQUE`, hash del contenido; misma clave + otro contenido → 409 | `CheckoutTest` + smoke test (201 → 200 → 409) | 1 | 2 |
| 8 | Cantidades negativas o absurdas (T) | Carrito, stock | Validación `integer`, `min:1`, `max:10`; ajustes de stock nunca dejan negativo | `CheckoutTest`, `AdminProductTest`, `cart.test.ts` | 1 | 2 |
| 9 | Subida de archivos maliciosos (T/E) | Imágenes de productos | Solo JPG/PNG/WebP verificados por contenido (finfo), sin SVG, máx. 2 MB, dimensiones acotadas, nombre aleatorio, `nosniff` | `AdminProductTest` (SVG con script, PHP disfrazado, tamaños) | 1 | 2 |
| 10 | Enumeración de pedidos ajenos (I) | `GET /orders/{n}` | Requiere token opaco de 40 caracteres y comparación en tiempo constante | `CheckoutTest::confirmation_requires…` | 1 | 1 |
| 11 | Recolección de datos personales (I) | Checkout | Solo alias ficticio; se rechazan secuencias de ≥ 7 dígitos, correos y HTML; no hay campos de DNI, dirección ni pago | `CheckoutTest`, `CheckoutView.test.ts` | 1 | 2 |
| 12 | Fuga de información técnica (I) | Errores | `APP_DEBUG=false` (el contenedor no arranca con `true` en producción); JSON de error uniforme sin traza; `expose_php=Off`; `server_tokens off` | `SecurityAndHealthTest`, entrypoint | 1 | 2 |
| 13 | Clickjacking y sniffing (T) | Frontend y API | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS bajo HTTPS | Smoke test, `SecurityAndHealthTest` | 1 | 1 |
| 14 | Denegación de servicio por abuso (D) | API pública | Rate limiting por ruta (120/min catálogo, 10/min checkout), límites de cuerpo (3 MB), timeouts en nginx, escalado HTTP en Container Apps | `CheckoutTest::checkout_is_rate_limited` | 2 | 2 |
| 15 | Falsificación de IP para eludir el rate limit (S) | Cabeceras de proxy | nginx sobrescribe `X-Forwarded-For` con la IP real (módulo realip con rangos privados) | `nginx/proxy.conf` | 1 | 1 |
| 16 | Escalada de privilegios en contenedores (E) | Docker | Usuarios sin root (UID 10001 / 101), `cap_drop: ALL`, `no-new-privileges`, sistema de archivos de solo lectura, código sin permisos de escritura | `docker compose exec … id` (uid 10001 y 101) | 1 | 2 |
| 17 | Movimiento lateral hacia la base de datos (E/I) | Red | Red Docker `data` interna sin puerto publicado; en HA PostgreSQL solo en subred privada; API con ingress interno | Comprobación de red (frontend → db inaccesible) | 1 | 3 |
| 18 | Secretos expuestos en Git (I) | Repositorio | `.env` y `*.tfvars` ignorados; contraseñas generadas por Terraform (`random_password`) y guardadas como secretos de Container Apps; OIDC sin secretos de larga duración | `.gitignore`, `pharmacy-deploy.yml` | 1 | 3 |
| 19 | Cambios no autorizados en la nube (T/E) | Despliegue | Despliegue solo manual, aprobación con GitHub Environments, plan exacto aplicado, permisos mínimos por job | `pharmacy-deploy.yml` | 1 | 3 |
| 20 | Dependencias vulnerables (T) | Cadena de suministro | `composer audit` y `npm audit` en CI; lockfiles versionados; imágenes base fijadas por versión | `pharmacy-ci.yml` (0 vulnerabilidades al momento del commit) | 2 | 2 |
| 21 | Mezcla de datos con el sistema clínico (I) | Aislamiento | Base de datos, usuario, cookie, APP_KEY, red y proyecto Compose propios; ninguna tabla compartida | `docker-compose.yml`, `git diff` del sistema clínico vacío | 1 | 3 |
| 22 | Repudio de cambios administrativos (R) | Stock y pedidos | `stock_movements` y `order_status_history` con administrador, motivo y fecha; `X-Request-Id` en logs | `AdminProductTest`, `AdminOrderTest` | 1 | 1 |

## Riesgos aceptados / pendientes
- **Sin WAF ni protección DDoS dedicada** (fuera del alcance académico).
- **Secretos en Container Apps en lugar de Key Vault** (siguiente paso recomendado: Key Vault + identidad gestionada).
- **Sin MFA para el administrador** (cuenta demo única).
- **PostgreSQL público en DEMO** (mitigado con TLS, contraseña de 32 caracteres y firewall de servicios de Azure).
