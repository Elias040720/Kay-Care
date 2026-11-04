# syntax=docker/dockerfile:1.7
# Kay Care Farmacia — API Laravel 12 (imagen multi-stage, sin root)

############################
# 1) Dependencias PHP (sin dev)
############################
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader --ignore-platform-req=ext-*
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

############################
# 2) Runtime: FrankenPHP (Caddy + PHP 8.3) sin privilegios
############################
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime

ARG APP_UID=10001
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":8080" \
    XDG_CONFIG_HOME=/tmp/caddy-config \
    XDG_DATA_HOME=/tmp/caddy-data \
    PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.d"

RUN set -eux; \
    install-php-extensions pdo_pgsql opcache intl bcmath; \
    apk add --no-cache tini; \
    adduser -D -H -u "${APP_UID}" -s /sbin/nologin app; \
    # Sin capacidad de enlazar puertos <1024: escucha en 8080 sin root.
    setcap -r /usr/local/bin/frankenphp; \
    mkdir -p /usr/local/etc/php/app.d

COPY docker/php.ini /usr/local/etc/php/app.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
WORKDIR /app
COPY --from=vendor --chown=root:root /app /app

RUN set -eux; \
    chmod 0755 /usr/local/bin/entrypoint; \
    rm -rf tests .env .env.* phpunit.xml database/database.sqlite storage/logs/*.log; \
    mkdir -p storage/app/public/products storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
    ln -sfn /app/storage/app/public /app/public/storage; \
    # El código queda de solo lectura para el usuario de ejecución; solo
    # storage/ y bootstrap/cache/ son escribibles.
    chown -R app:app storage bootstrap/cache; \
    chmod -R a-w app config database routes public vendor

USER app
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=5 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/api/v1/store/health || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8080"]
