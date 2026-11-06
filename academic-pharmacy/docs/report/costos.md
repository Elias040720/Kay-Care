# Estimación de costos — Azure (USD / mes)

> **Estimación, no cotización.** No se consultó la Calculadora de precios de Azure en la fecha de este informe y no se revisó la suscripción (la CLI `az` no está instalada en el equipo de trabajo). Los precios unitarios son valores de lista de pago por uso (región East US 2) usados como referencia y **deben verificarse** en <https://azure.microsoft.com/pricing/calculator/> antes de cualquier despliegue. No incluyen impuestos ni descuentos de suscripciones de estudiante.

## Supuestos comunes
- Mes de 730 horas (2 628 000 s).
- Tráfico académico: < 50 000 solicitudes/mes, < 5 GB de salida (dentro de los 100 GB gratuitos de transferencia).
- Imágenes en GHCR: paquetes públicos (sin costo); si son privados, el plan gratuito de GitHub incluye almacenamiento limitado.
- Container Apps de consumo: vCPU activa ≈ US$ 0,000024/s, memoria ≈ US$ 0,000003/GiB·s; concesión gratuita mensual de 180 000 vCPU·s, 360 000 GiB·s y 2 millones de solicitudes por suscripción.
- Log Analytics ≈ US$ 2,30/GB ingerido (primeros 5 GB/mes gratuitos por cuenta de facturación).

## DEMO — perfil de costo cero (Azure for Students)

El entorno `demo` está dimensionado para caber en los servicios gratuitos de una suscripción **Azure for Students** (o de la cuenta gratuita de 12 meses). Los límites de la capa gratuita deben confirmarse en el portal (*Suscripciones → Servicios gratuitos*) antes del despliegue.

| Recurso | Configuración | Límite gratuito usado | Estimación |
|---|---|---|---|
| PostgreSQL Flexible | B1ms + 32 GiB, copias 7 días, sin HA ni geo-copias | 750 h/mes de B1ms, 32 GB de almacenamiento y 32 GB de copias (12 meses) | **US$ 0** |
| Container Apps | Web y API a 0,25 vCPU / 0,5 GiB, mín. 0 y máx. 1 réplica | Concesión mensual: 180 000 vCPU·s y 360 000 GiB·s ≈ **100 h activas** de ambas apps a la vez | **US$ 0** si la actividad es < 100 h/mes |
| Log Analytics | Tope 0,1 GB/día, retención 30 días | 5 GB/mes de ingesta | **US$ 0** |
| Azure Files | **No se crea** (`enable_uploads_share = false`): las imágenes subidas se pierden al reiniciar la API | — | **US$ 0** |
| Estado de Terraform | Local (despliegue manual); no se crea cuenta de almacenamiento | — | **US$ 0** |
| Presupuesto | US$ 1/mes, alertas al 1 % real y 100 % previsto a los *Owner* | Cost Management es gratuito | **US$ 0** |
| **Total DEMO** | | | **US$ 0 dentro de los límites** |

Riesgos que romperían el costo cero y su mitigación:
- **Más de ~100 h activas al mes** de las apps (escala a cero tras unos minutos sin tráfico; ningún sondeo externo debe mantenerlas despiertas).
- **Pasar los 12 meses** del servicio gratuito de PostgreSQL → borrar el entorno (`terraform destroy`) antes del vencimiento.
- **Un segundo servidor PostgreSQL** en la misma suscripción consumiría horas fuera de las 750 gratuitas.
- En Azure for Students no hay tarjeta asociada: al agotarse el crédito los recursos se detienen, no se generan cargos al usuario.

### Hallazgo en la suscripción real (revisión de solo lectura, 5-nov-2026)
- Oferta **Azure for Students**; regiones permitidas por política: westus, mexicocentral, canadacentral, northcentralus, brazilsouth.
- **No hay servidores PostgreSQL**: las 750 h gratuitas de B1ms siguen disponibles.
- **La concesión gratuita de Container Apps ya está consumida** por otras apps de la misma suscripción con réplicas mínimas 1 encendidas 24/7. Por eso la tienda costaría ≈ **US$ 0,05 de crédito por hora activa** (2 apps × 0,25 vCPU / 0,5 GiB) y US$ 0 mientras esté escalada a cero: ≈ US$ 2/mes con 40 h de uso. No hay tarjeta asociada: se descuenta del crédito de estudiante.
- Log Analytics: ya existe un workspace con tope de 1 GB/día que comparte los 5 GB gratuitos.
- **Decisión del usuario: no desplegar por el momento.** Estado: URL pendiente de despliegue.

Configuración anterior (pago por uso, sin capa gratuita): ≈ US$ 17–25/mes, casi todo por PostgreSQL B1ms 24/7.

## HA

| Recurso | Configuración | Supuesto de uso | Estimación |
|---|---|---|---|
| PostgreSQL Flexible | GP D2ds_v5 (2 vCores) **× 2** (primario + espera ZR) + 64 GiB × 2 | 24/7 | ≈ 2 × 128 (cómputo) + 2 × 7,4 (almacenamiento) = **≈ 270** |
| Copias geo-redundantes | 35 días | ≈ 64 GiB por encima del incluido | **≈ 10–15** |
| Container Apps — API | 3 réplicas mín. × 1 vCPU / 2 GiB, activas | 24/7 | 3 × (63 + 15,8) ≈ **≈ 235** |
| Container Apps — Web | 2 réplicas mín. × 0,5 vCPU / 1 GiB | 24/7 | 2 × 39,4 ≈ **≈ 79** |
| Log Analytics | Retención 90 días | ~30 GB/mes | **≈ 60** |
| Azure Files ZRS | 20 GiB de cuota | < 1 GB usado | **≈ 2** |
| VNet + DNS privado | 1 zona privada | — | **≈ 1** |
| **Total HA** | | | **≈ US$ 650–700/mes** |

Notas:
- Las réplicas mínimas inactivas se facturan a una tarifa «idle» menor; la estimación asume el peor caso (siempre activas).
- Añadir Azure Front Door + WAF (recomendado para producción) sumaría ≈ US$ 35+/mes base más tráfico.
- Un despliegue multi-región duplicaría aproximadamente el costo de HA.

## Decisión y autorización
- **No se ejecutó `terraform apply` ni se creó ningún recurso facturable.**
- Para desplegar DEMO se necesita: (1) confirmar la suscripción y que el usuario tiene rol *Contributor* + *User Access Administrator* (o un administrador que cree la identidad federada), (2) verificar los precios en la calculadora, (3) **autorización explícita** del propietario de la suscripción.
- No deben tocarse recursos de MOVA ni de otros proyectos: todo vive en grupos de recursos propios `rg-kcf-demo` / `rg-kcf-ha`.
