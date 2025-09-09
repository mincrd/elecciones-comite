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
COPY package.json package.json
COPY package-lock.json package-lock.json
COPY vite.config.js vite.config.js
# Copia los archivos de configuración de frontend si existen
COPY postcss.config.js .
COPY tailwind.config.js .
COPY resources/ resources/
RUN npm install
RUN npm run build

# ---- 3. Etapa Final (Producción) ----
# Usamos una imagen oficial de PHP 8.2 con FPM y Alpine Linux (más ligera)
FROM php:8.2-fpm-alpine

# Instala dependencias del sistema: Nginx, supervisor y extensiones de PHP
RUN apk add --no-cache \
    nginx \
    supervisor \
    # Extensiones de PHP requeridas por Laravel
    && docker-php-ext-install pdo pdo_mysql bcmath

# Establece el directorio de trabajo
WORKDIR /var/www/html

# Copiamos las dependencias y assets de las etapas anteriores
COPY --from=vendor /app/vendor/ /var/www/html/vendor/
COPY --from=frontend /app/public/ /var/www/html/public/

# Copiamos el resto de la aplicación
COPY . /var/www/html/

# Copiamos nuestra configuración de Nginx y Supervisor
# Nota: La ruta de destino ha cambiado a /etc/nginx/http.d/
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Damos los permisos correctos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponemos el puerto 80 para Nginx
EXPOSE 80

# Script de inicio usando Supervisor para gestionar Nginx y PHP-FPM
# Creamos un archivo de configuración para supervisor sobre la marcha
CMD ["/bin/sh", "-c", "echo '[supervisord]\\nnodaemon=true\\n\\n[program:php-fpm]\\ncommand=php-fpm\\nautostart=true\\nautorestart=true\\n\\n[program:nginx]\\ncommand=nginx -g \"daemon off;\"\\nautostart=true\\nautorestart=true' > /etc/supervisord.conf && supervisord -c /etc/supervisord.conf"]
