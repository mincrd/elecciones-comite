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
# Copia todos los archivos de la aplicación. .dockerignore se encargará de las exclusiones.
# Esto es más robusto que copiar archivos uno por uno.
COPY . .
RUN npm install
RUN npm run build

# ---- 3. Etapa Final (Producción) ----
FROM php:8.2-fpm-alpine

# Instala dependencias del sistema: Nginx, supervisor y extensiones de PHP
RUN apk add --no-cache \
    nginx \
    supervisor \
    && docker-php-ext-install pdo pdo_mysql bcmath

WORKDIR /var/www/html

# Copia los artefactos de las etapas anteriores
COPY --from=vendor /app/vendor/ /var/www/html/vendor/
COPY --from=frontend /app/public/ /var/www/html/public/

# Copia el resto del código de la aplicación
COPY . /var/www/html/

# Copia la configuración de Nginx
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Da los permisos correctos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

# Inicia Supervisor para gestionar Nginx y PHP-FPM
CMD ["/bin/sh", "-c", "echo '[supervisord]\\nnodaemon=true\\n\\n[program:php-fpm]\\ncommand=php-fpm\\nautostart=true\\nautorestart=true\\n\\n[program:nginx]\\ncommand=nginx -g \"daemon off;\"\\nautostart=true\\nautorestart=true' > /etc/supervisord.conf && supervisord -c /etc/supervisord.conf"]
