# ---- 1. Etapa de Dependencias (Composer) ----
FROM composer:2 as vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- 2. Etapa de Assets (NPM) ----
FROM node:20 as frontend
WORKDIR /app
COPY . .
RUN npm install && npm run build

# ---- 3. Etapa Final (Producción) ----
FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema: Nginx, Supervisor y extensiones PHP
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath intl zip

WORKDIR /var/www/html

# Copiar vendor y assets ya construidos
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public /var/www/html/public

# Copiar el resto del proyecto
COPY . /var/www/html

# Copiar configuraciones de Nginx y Supervisor
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Permisos correctos
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Sobrescribimos entrypoint/cmd del php-fpm-alpine
ENTRYPOINT []
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
