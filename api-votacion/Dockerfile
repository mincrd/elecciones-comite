# ---- 1. Etapa de Dependencias (Composer) ----
FROM composer:2 as vendor
WORKDIR /app

# Copiamos todo el proyecto para que artisan exista al correr composer
COPY . .

# Instalamos dependencias PHP (sin dev, optimizado)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- 2. Etapa de Assets (NPM) ----
FROM node:20 as frontend
WORKDIR /app
COPY . .
RUN npm install && npm run build

# ---- 3. Etapa Final (Producción) ----
FROM php:8.2-fpm-alpine

# Instalamos dependencias del sistema: Nginx, Supervisor y extensiones PHP
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath intl zip

WORKDIR /var/www/html

# Copiamos vendors y assets desde las etapas anteriores
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public /var/www/html/public

# Copiamos todo el resto del proyecto
COPY . /var/www/html

# Copiamos configuración de Nginx y Supervisor
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Permisos correctos para Laravel
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Ejecutamos supervisor que mantiene PHP-FPM + Nginx
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
