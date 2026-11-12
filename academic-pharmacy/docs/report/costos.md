# Costos — Kay Care Farmacia en Azure (USD)

> **Estado actual: no existe ningún recurso de Kay Care Farmacia en Azure.**
> Comprobado en solo lectura el 12-nov-2026: ningún grupo de recursos `kcf`, `pharmacy` ni `farmacia` y ningún recurso con la etiqueta `project=kay-care-farmacia`. **Consumo de créditos Azure de Kay Care Farmacia hasta hoy: US$ 0.**
> El despliegue se hará **solo el día de la exposición** y únicamente con la autorización expresa del titular de la suscripción.

## Precios unitarios usados

Fuente: API pública de precios minoristas de Azure (`prices.azure.com`), región **canadacentral**, consultada el 12-nov-2026. Son precios de lista de pago por uso, sin impuestos. `scripts/azure-preflight.ps1` los vuelve a consultar al ejecutarse.

| Medidor | Precio |
|---|---|
| Container Apps · vCPU activa | US$ 0,000034 por vCPU·s |
| Container Apps · memoria activa | US$ 0,000004 por GiB·s |
| Container Apps · vCPU / memoria en reposo | US$ 0,000004 por unidad·s (solo si hay réplicas mínimas > 0) |
| Container Apps · solicitudes | US$ 0,40 por millón |
| PostgreSQL Flexible · Burstable B1ms | US$ 0,0185 por hora |
| PostgreSQL Flexible · almacenamiento | US$ 0,1265 por GB·mes |
| PostgreSQL Flexible · copias de seguridad | US$ 0,105 por GB·mes (por encima del 100 % del almacenamiento aprovisionado) |
| Log Analytics · ingesta | US$ 2,76 por GB (los primeros 5 GB/mes de la cuenta de facturación son gratuitos) |
| Log Analytics · retención | 31 días incluidos; luego US$ 0,12 por GB·mes |

## Restricciones de la suscripción (Azure for Students, revisión de solo lectura)

- **Regiones permitidas por política:** westus, mexicocentral, canadacentral, northcentralus y brazilsouth. `eastus2`, la región por defecto anterior, **no** está permitida; Terraform usa ahora `canadacentral`.
- **Concesión gratuita de Container Apps** (180 000 vCPU·s y 360 000 GiB·s al mes por suscripción): **ya la consumen** otras aplicaciones de la misma suscripción con réplicas mínimas 1. No se asume gratuidad para la tienda.
- **PostgreSQL B1ms gratuito (750 h/12 meses de Azure for Students):** no se asume. Depende de la antigüedad de la oferta y no se pudo verificar sin acceso al portal de facturación.
- **Log Analytics:** otro workspace de la suscripción tiene un tope de 1 GB/día y probablemente agota los 5 GB gratuitos; se calcula la ingesta al precio de lista.
- No hay tarjeta asociada: lo consumido se descuenta del crédito de estudiante.

## DEMO: qué se crea y qué cobra

| Recurso | Configuración | ¿Cobra sin visitantes? |
|---|---|---|
| Grupo de recursos `rg-kcf-demo` | — | No |
| Container Apps Environment (perfil Consumption) | sin VNet | No |
| Container App web (nginx) | 0,25 vCPU / 0,5 GiB, mín. 0 y máx. 1 réplica | **No** (escala a cero) |
| Container App API (Laravel) | 0,25 vCPU / 0,5 GiB, mín. 0 y máx. 1 réplica, ingress interno | **No** (escala a cero) |
| PostgreSQL Flexible B1ms | 32 GB, copias 7 días, sin HA | **Sí**: cómputo por hora y almacenamiento mientras exista |
| Log Analytics | tope 0,1 GB/día, retención 30 días | Solo por la ingesta de logs |
| Presupuesto de consumo (US$ 1/mes) | alertas por correo a los *Owner* | No (Cost Management es gratuito) |

## Costo estimado del día de la exposición

Supuestos: los recursos se crean la mañana de la exposición y se retiran ese mismo día. Las apps reciben tráfico unas 4 horas.

| Concepto | Cálculo | Estimación |
|---|---|---|
| Container Apps (2 apps activas) | 0,5 vCPU × 3 600 s × 0,000034 + 1 GiB × 3 600 s × 0,000004 = US$ 0,076/h × 4 h | ≈ US$ 0,30 |
| PostgreSQL B1ms (8 h de vida) | 8 h × 0,0185 | ≈ US$ 0,15 |
| Almacenamiento PostgreSQL (8 h) | 32 GB × 0,1265 × 8/730 | ≈ US$ 0,04 |
| Log Analytics | ≈ 0,03 GB × 2,76 | ≈ US$ 0,10 |
| Solicitudes y transferencia | < 1 millón de solicitudes; salida dentro de los 100 GB gratuitos | ≈ US$ 0 |
| **Total del día (8 h)** | | **≈ US$ 0,60** |
| Si se deja 24 h | PostgreSQL 24 h + almacenamiento 1 día + logs | ≈ US$ 1,20 |
| Si se olvida un mes | PostgreSQL 730 h (≈ US$ 13,5) + almacenamiento (≈ US$ 4) + logs | **≈ US$ 18–20/mes** |

**Lo que sigue cobrando aunque nadie visite la tienda:** el cómputo y el almacenamiento de PostgreSQL, y la ingesta de logs de la plataforma. Por eso el procedimiento ([`docs/deploy/DIA-EXPOSICION.md`](../deploy/DIA-EXPOSICION.md)) termina con `terraform destroy` el mismo día.

El presupuesto de US$ 1 avisa por correo, pero **no detiene** los recursos. Para dejar de cobrar hay que retirarlos.

## HA (solo diseño, no se despliega)

| Recurso | Configuración | Estimación mensual |
|---|---|---|
| PostgreSQL Flexible | GP D2ds_v5 **× 2** (primario + espera con redundancia de zona) + 64 GB × 2 | ≈ US$ 270 |
| Copias geo-redundantes | 35 días | ≈ US$ 10–15 |
| Container Apps API | 3 réplicas mínimas × 1 vCPU / 2 GiB | ≈ US$ 235 |
| Container Apps web | 2 réplicas mínimas × 0,5 vCPU / 1 GiB | ≈ US$ 79 |
| Log Analytics | retención 90 días, ~30 GB/mes | ≈ US$ 60 |
| Azure Files ZRS, VNet y DNS privado | — | ≈ US$ 3 |
| **Total HA** | | **≈ US$ 650–700/mes** |

La HA cuesta unas 35 veces más que dejar DEMO encendido un mes. Activarla exigiría un presupuesto aprobado; esta entrega no la despliega.

## Autorización

- No se ejecutó `terraform apply` ni `terraform destroy` ni ningún comando `az` que cree o modifique recursos.
- El día de la exposición, `scripts/deploy-azure.ps1` muestra este cálculo con precios actualizados y exige escribir una frase de confirmación antes de crear nada.
- Nunca se usan recursos de otros proyectos de la suscripción: todo vive en `rg-kcf-demo`, y el script se detiene si el plan crea algo fuera de ese grupo.
