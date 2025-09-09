# ---- 1. Etapa de Dependencias (Composer) ----
FROM composer:2 as vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.json
COPY composer.lock composer.lock
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

# ---- 2. Etapa de Assets (NPM) ----
FROM node:20 as frontend
WORKDIR /app
COPY package.json package.json
COPY package-lock.json package-lock.json
COPY vite.config.js vite.config.js
COPY postcss.config.js postcss.config.js
COPY tailwind.config.js tailwind.config.js
COPY resources/ resources/
RUN npm install
RUN npm run build

# ---- 3. Etapa Final (Producción) ----
FROM appsvc/php:8.2-fpm_20250722.4.tuxprod

# Copiamos las dependencias y assets de las etapas anteriores
COPY --from=vendor /app/vendor/ /var/www/html/vendor/
COPY --from=frontend /app/public/build/ /var/www/html/public/build/
COPY --from=frontend /app/public/hot /var/www/html/public/hot

# Copiamos el resto de la aplicación
COPY . /var/www/html/

# Instalamos Nginx
RUN apt-get update && apt-get install -y \
    nginx

# Copiamos nuestra configuración de Nginx
COPY docker/nginx/default.conf /etc/nginx/sites-enabled/default

# Damos los permisos correctos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponemos el puerto que Azure usará
EXPOSE 80

# Script de inicio (reemplaza el entrypoint.sh anterior)
# Este script inicia PHP-FPM y Nginx
ENTRYPOINT ["/bin/sh", "-c", "service php8.2-fpm start && nginx -g 'daemon off;'"]