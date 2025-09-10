# ---- 1. Etapa de Dependencias (Composer) ----
FROM composer:2 as vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.json
COPY composer.lock composer.lock
RUN composer install --ignore-platform-reqs --no-interaction --no-plugins --no-scripts --prefer-dist

# ---- 2. Etapa de Assets (NPM) ----
FROM node:20 as frontend
WORKDIR /app
COPY . .
RUN npm install && npm run build

# ---- 3. Etapa Final (Producción) ----
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath

WORKDIR /var/www/html

COPY --from=vendor /app/vendor/ /var/www/html/vendor/
COPY --from=frontend /app/public/ /var/www/html/public/
COPY . /var/www/html/

# Nginx conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor conf
COPY docker/supervisord.conf /etc/supervisord.conf

RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
