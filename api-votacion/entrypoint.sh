#!/bin/bash

# Actualiza la configuración de Apache para apuntar a la carpeta /public de Laravel
sed -i 's|/var/www/html|/home/site/wwwroot/public|g' /etc/apache2/sites-available/000-default.conf

# Inicia el servidor Apache
apache2-foreground