# syntax=docker/dockerfile:1.7
# Kay Care Farmacia — SPA Vue servida por nginx sin privilegios

############################
# 1) Build (Vite + vue-tsc)
############################
FROM node:20.18-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

############################
# 2) Runtime: nginx-unprivileged (UID 101, puerto 8080)
############################
FROM nginxinc/nginx-unprivileged:1.27-alpine AS runtime

# BACKEND_UPSTREAM: dirección interna de la API (Docker: http://backend:8080,
# Container Apps: http://<nombre-app-backend>). Se resuelve en tiempo de
# ejecución con el DNS del entorno.
ENV BACKEND_UPSTREAM=http://backend:8080 \
    NGINX_ENTRYPOINT_LOCAL_RESOLVERS=1 \
    NGINX_ENVSUBST_FILTER="^(BACKEND_UPSTREAM|NGINX_LOCAL_RESOLVERS)$"

COPY --chown=root:root nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --chown=root:root nginx/proxy.conf /etc/nginx/snippets/proxy.conf
COPY --chown=root:root nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build --chown=root:root /app/dist /usr/share/nginx/html

USER 101
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=4s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
