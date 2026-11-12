# Día de la exposición — desplegar y retirar Kay Care Farmacia en Azure

> **Este documento no autoriza ningún despliegue.** Seguirlo crea recursos que cobran. Hazlo solo el día de la exposición, con la autorización expresa del titular de la suscripción y después de revisar el costo (≈ US$ 0,60 si se retira el mismo día; ver [`../report/costos.md`](../report/costos.md)).

## Antes del día (sin costo)

1. Integrar la rama final en `main` y esperar a que `pharmacy-ci` termine en verde. La CI publica en GHCR las imágenes `ghcr.io/elias040720/kay-care-pharmacy-{backend,frontend}:sha-<commit>`, que son públicas.
2. Tener Docker Desktop abierto. Si `az` y `terraform` no están instalados, los scripts usan la imagen local `kcf-azure-tools` (`infra/docker/azure-tools.Dockerfile`).
3. Iniciar sesión en Azure. La sesión queda en el volumen Docker `kcf-azure-cli`, nunca en el repositorio:

   ```powershell
   docker run --rm -it -v kcf-azure-cli:/root/.azure kcf-azure-tools az login --use-device-code
   ```

4. Ejecutar la verificación previa. Es **solo lectura**: no crea ni modifica nada.

   ```powershell
   cd academic-pharmacy
   powershell -ExecutionPolicy Bypass -File scripts\azure-preflight.ps1
   ```

   Revisa:
   - herramientas, sesión y suscripción;
   - que `canadacentral` esté entre las regiones permitidas;
   - los proveedores de recursos;
   - que `rg-kcf-demo` no exista todavía;
   - la disponibilidad de PostgreSQL B1ms;
   - que las imágenes publicadas se puedan descargar;
   - `terraform fmt` y `validate`;
   - la estimación de costo con precios actualizados.

## Desplegar (crea recursos facturables)

```powershell
cd academic-pharmacy
powershell -ExecutionPolicy Bypass -File scripts\deploy-azure.ps1
```

El script:

1. Muestra la suscripción y pide escribir su **nombre**.
2. Ejecuta el preflight y se detiene si hay fallos.
3. Ejecuta `terraform plan` y lista los recursos. Se detiene si alguno queda fuera de `rg-kcf-demo`.
4. Muestra el costo estimado.
5. Exige escribir exactamente `DESPLEGAR KCF-DEMO EN <nombre de la suscripción>`.
6. Registra los proveedores que falten y aplica **exactamente** el plan revisado.
7. Verifica que las imágenes estén publicadas. Con `-BuildAndPushImages` las construye y publica desde el equipo, lo que requiere un `docker login ghcr.io` propio.
8. Deja que la API aplique las migraciones al arrancar, con bloqueo `--isolated`.
9. Configura frontend y API con las variables y secretos que inyecta Terraform.
10. Espera los health checks. El primer arranque puede tardar varios minutos.
11. Ejecuta un smoke test: HTTPS, CSP, X-Frame-Options, HSTS, catálogo y 401 de la administración.
12. Muestra la **URL pública** (`https://ca-kcf-demo-web.<dominio>.canadacentral.azurecontainerapps.io`).
13. Hace una **compra simulada** de punta a punta y consulta el pedido persistido.
14. Lista los recursos que siguen cobrando y cómo retirarlos.

Contraseña del administrador demo: es aleatoria, la genera Terraform y no se imprime. Se obtiene con:

```powershell
terraform -chdir=infra\terraform\environments\demo output -raw admin_password
```

Con Docker, el equivalente es:

```powershell
docker run --rm -v kcf-azure-cli:/root/.azure -v "${PWD}\infra\terraform:/w" -w /w/environments/demo kcf-azure-tools terraform output -raw admin_password
```

**Anota la URL en el informe** (sección «Enlace público») solo después de verificar que responde.

### Alternativa con GitHub Actions

`pharmacy-deploy.yml` hace lo mismo con OIDC, pero solo se dispara a mano y solo desde `main`. El `apply` exige escribir `DESPLEGAR-demo` en el campo `confirmacion` y la aprobación del Environment `pharmacy-demo`. Antes hay que configurar:
- la identidad federada en Microsoft Entra;
- las variables `AZURE_*` y `TFSTATE_*`;
- la cuenta de almacenamiento del estado remoto, que también cobra.

Para un solo día, el script local es más simple y no requiere el estado remoto.

## Retirar los recursos al terminar (mismo día)

Solo se retiran los recursos que **este** estado de Terraform creó. Nunca se borra un grupo de recursos por nombre a mano.

```powershell
cd academic-pharmacy\infra\terraform\environments\demo
terraform plan -destroy -out tfdestroy
terraform show tfdestroy
terraform apply tfdestroy
```

Con Docker, antepón a cada comando `terraform` lo siguiente:

```powershell
docker run --rm -it -v kcf-azure-cli:/root/.azure -v "${PWD}\..\..:/w" -w /w/environments/demo kcf-azure-tools
```

Antes de confirmar, comprueba en el plan de destrucción que **todas** las direcciones sean de este proyecto:
- recursos dentro de `rg-kcf-demo`;
- el presupuesto `budget-kcf-demo`;
- los valores `random_*`.

Si aparece cualquier otro recurso, **no confirmes**.

Comprobación posterior (solo lectura):

```powershell
az group exists -n rg-kcf-demo            # debe responder false
az resource list --tag project=kay-care-farmacia -o table   # debe salir vacío
```

Guarda `terraform.tfstate` hasta haber confirmado que todo se retiró; después se puede borrar. Está ignorado por Git y contiene secretos.

## Qué no hacer

- No ejecutar `terraform apply` en `environments/ha`. La HA solo está diseñada y cuesta unos US$ 650–700 al mes.
- No usar `az group delete` sobre grupos que no sean `rg-kcf-demo`, ni tocar recursos de otros proyectos de la suscripción.
- No subir a Git `terraform.tfstate`, `terraform.tfvars` ni tokens.
