# Herramientas para el día de la exposición sin instalar nada en Windows:
# Azure CLI + Terraform en una sola imagen. La usan azure-preflight.ps1 y
# deploy-azure.ps1 cuando `az` o `terraform` no están instalados.
#
#   docker build -t kcf-azure-tools -f infra/docker/azure-tools.Dockerfile infra/docker
#   docker run --rm -it -v kcf-azure-cli:/root/.azure kcf-azure-tools az login --use-device-code
#
# La sesión de Azure queda en el volumen kcf-azure-cli (no en el repositorio).
FROM hashicorp/terraform:1.9.8 AS terraform
FROM mcr.microsoft.com/azure-cli:2.67.0
COPY --from=terraform /bin/terraform /usr/local/bin/terraform
