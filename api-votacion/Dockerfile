# ---- 1. Dependencias de PHP (Composer) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- 2. Compilar assets (Node) ----
FROM node:20 AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .
RUN npm run build

# ---- 3. Imagen final (Nginx + PHP-FPM) ----
FROM php:8.2-fpm-alpine

# Dependencias del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    bash \
    && docker-php-ext-install pdo pdo_mysql bcmath intl zip

WORKDIR /var/www/html

# Copiar vendors y assets
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public /var/www/html/public
COPY . .

# Copiar configuración Nginx
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copiar configuración Supervisor
COPY docker/supervisord.conf /etc/supervisord.conf

# Permisos
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
