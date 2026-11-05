# Estimación de costos — Azure (USD / mes)

> **Estimación, no cotización.** No se consultó la Calculadora de precios de Azure en la fecha de este informe y no se revisó la suscripción (la CLI `az` no está instalada en el equipo de trabajo). Los precios unitarios son valores de lista de pago por uso (región East US 2) usados como referencia y **deben verificarse** en <https://azure.microsoft.com/pricing/calculator/> antes de cualquier despliegue. No incluyen impuestos ni descuentos de suscripciones de estudiante.

## Supuestos comunes
- Mes de 730 horas (2 628 000 s).
- Tráfico académico: < 50 000 solicitudes/mes, < 5 GB de salida (dentro de los 100 GB gratuitos de transferencia).
- Imágenes en GHCR: paquetes públicos (sin costo); si son privados, el plan gratuito de GitHub incluye almacenamiento limitado.
- Container Apps de consumo: vCPU activa ≈ US$ 0,000024/s, memoria ≈ US$ 0,000003/GiB·s; concesión gratuita mensual de 180 000 vCPU·s, 360 000 GiB·s y 2 millones de solicitudes por suscripción.
- Log Analytics ≈ US$ 2,30/GB ingerido (primeros 5 GB/mes gratuitos por cuenta de facturación).

## DEMO

| Recurso | Configuración | Supuesto de uso | Estimación |
|---|---|---|---|
| PostgreSQL Flexible | B1ms (1 vCore burstable, 2 GiB) + 32 GiB | Encendido 24/7 | ≈ 12,4 (cómputo) + 3,7 (almacenamiento) = **≈ 16** |
| Container Apps | Web 0,25 vCPU/0,5 GiB; API 0,5 vCPU/1 GiB; mín. 0 réplicas | ~40 h activas/mes en demostraciones | **≈ 0–3** (cubierto casi por la concesión gratuita) |
| Log Analytics | Tope 0,5 GB/día | ~2 GB/mes | **≈ 0–5** |
| Azure Files LRS | 5 GiB de cuota, < 100 MB usados | — | **< 1** |
| Almacenamiento de estado Terraform | Cuenta LRS | < 1 MB | **< 1** |
| **Total DEMO** | | | **≈ US$ 17–25/mes** |

Ahorro adicional: detener el servidor PostgreSQL cuando no se use (`az postgres flexible-server stop`) elimina el costo de cómputo (Azure lo reinicia automáticamente a los 7 días); el almacenamiento se sigue cobrando.

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
