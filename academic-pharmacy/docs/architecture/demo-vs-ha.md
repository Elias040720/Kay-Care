# Matriz DEMO vs HA — Kay Care Farmacia

## Estado de la alta disponibilidad

| Nivel | Estado | Evidencia |
|---|---|---|
| HA **diseñada** con Terraform | Sí | `infra/terraform/environments/ha`: réplicas mínimas configurables, escalado HTTP, sondas de salud, entorno con redundancia de zona, PostgreSQL ZoneRedundant, VNet privada. `terraform validate` OK. Diagrama D. |
| HA **probada localmente** | Sí, parcialmente | `scripts/ha-local-test.sh`: 2 réplicas de la API detrás de nginx; se detiene una con tráfico en curso. Resultado del 12-nov-2026: **179 de 180 solicitudes OK**; se perdió 1 (timeout) en el instante del fallo y ninguna después. Solo cubre la capa de API: PostgreSQL local sigue siendo único. |
| HA **desplegada y verificada en Azure** | **No** | No se desplegó ningún entorno. No se afirma disponibilidad medida en la nube. |

> **Ningún entorno ha sido desplegado en Azure** (verificado en solo lectura el 12-nov-2026). Las columnas describen lo que **define el código Terraform** (`infra/terraform/environments/demo` y `…/ha`), validado con `terraform validate`. No se reportan cifras de disponibilidad medidas porque no existen mediciones.

## Comparación por componente

| Componente | DEMO (bajo costo) | HA (alta disponibilidad regional) |
|---|---|---|
| Región | 1 región (`canadacentral`, permitida por la política de la suscripción) | 1 región con 3 zonas de disponibilidad (`canadacentral`) |
| Red | Red gestionada por Azure, sin VNet propia | VNet `10.40.0.0/16`: subred delegada para Container Apps y subred delegada para PostgreSQL |
| Entorno Container Apps | Perfil de consumo, **sin** redundancia de zona | Perfil de consumo **con** redundancia de zona |
| Frontend (nginx) | 0–1 réplica, 0,25 vCPU / 0,5 GiB (escala a cero) | 2–6 réplicas repartidas entre zonas |
| API (Laravel) | 0–1 réplica, 0,25 vCPU / 0,5 GiB, ingress interno | 3–10 réplicas, ingress interno |
| Arranque en frío | Sí (primera petición tras inactividad: varios segundos) | No (réplicas mínimas siempre activas) |
| PostgreSQL | Flexible Server **Burstable B1ms**, sin HA, acceso público con TLS obligatorio y firewall «servicios de Azure» | Flexible Server **General Purpose D2ds_v5**, HA **ZoneRedundant** (réplica en espera síncrona en otra zona), acceso **solo privado** |
| Copias de seguridad | 7 días, redundancia local | 35 días, **geo-redundantes** |
| Imágenes subidas | Disco efímero (sin Azure Files, para no cobrar almacenamiento) | Azure Files **ZRS** |
| Observabilidad | Log Analytics, 30 días, tope 0,1 GB/día · presupuesto con alertas | Log Analytics, 90 días, diagnósticos de PostgreSQL |
| Migraciones | Al arrancar el contenedor (`migrate --isolated`) | Igual (con bloqueo para varias réplicas) |
| Registro de imágenes | GHCR | GHCR |
| Costo estimado | ≈ US$ 0,60 el día de la exposición (8 h); ≈ US$ 18–20 si se deja un mes (ver `docs/report/costos.md`) | ≈ US$ 650–700/mes |

## Puntos únicos de fallo (SPOF) que siguen existiendo

### En DEMO (aceptados por diseño, para minimizar el costo)
1. **Base de datos única** (B1ms sin réplica en espera). Si cae el servidor o su zona, la tienda deja de funcionar hasta que Azure lo recupere; la RPO depende de las copias automáticas (hasta 5 min de log).
2. **Sin redundancia de zona** en el entorno de Container Apps: la pérdida de una zona puede detener ambas apps.
3. **Escala a cero**: no es una caída, pero la primera petición tras inactividad tarda varios segundos.
4. **Acceso público a PostgreSQL** (con TLS y contraseña fuerte, pero con regla `0.0.0.0` para servicios de Azure): superficie de ataque mayor que en HA.
5. **Imágenes subidas en disco efímero**: se pierden si la API se reinicia o escala a cero (los productos semilla usan ilustraciones vectoriales, así que la tienda sigue completa).

### En HA (riesgos residuales, declarados honestamente)
1. **Una sola región.** Una caída regional de Azure detiene todo el servicio. No hay región secundaria, ni réplica de lectura entre regiones, ni Azure Front Door/Traffic Manager. Las copias geo-redundantes permiten **restaurar** en otra región, pero es un proceso manual (RTO de horas).
2. **GHCR como dependencia externa.** Si GHCR no está disponible, no se pueden crear revisiones ni escalar con nuevas réplicas (las réplicas existentes siguen funcionando). Mitigación posible: ACR Premium con geo-replicación.
3. **Secretos en Container Apps** (no en Key Vault) y en el estado de Terraform: el estado debe estar en un backend remoto cifrado con acceso restringido.
4. **Migraciones en el arranque**: una migración defectuosa afecta a todas las réplicas nuevas. Mitigación recomendada: Container Apps Job dedicado y migraciones compatibles hacia atrás.
5. **Sesiones y caché en PostgreSQL**: la base de datos es una dependencia crítica de todo (incluido el login). Está protegida por HA de zona, pero no hay caché distribuida (Redis) independiente.
6. **Conmutación por error de PostgreSQL**: durante el failover (típicamente 60–120 s según la documentación de Azure) las peticiones fallan; la aplicación no reintenta automáticamente las transacciones.
7. **Sin WAF**: el ingress de Container Apps no incluye firewall de aplicaciones web; se depende del rate limiting de la aplicación.
8. **Ingress gestionado de Container Apps**: es un servicio de plataforma; su disponibilidad depende del SLA de Azure, no de esta solución.

## Qué cambia entre ambos (resumen para la defensa)

- DEMO prioriza **costo**: todo escala a cero salvo PostgreSQL, que puede detenerse manualmente (Azure lo reanuda automáticamente a los 7 días).
- HA prioriza **continuidad ante la pérdida de una zona**: réplicas en varias zonas, base de datos con réplica síncrona y red privada.
- Ninguno ofrece **continuidad ante la pérdida de una región**; sería el siguiente paso (multi-región activa-pasiva).
