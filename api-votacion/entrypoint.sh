#!/bin/bash

# Reemplaza la configuración por defecto de Apache con la nuestra
cp /home/site/wwwroot/laravel.conf /etc/apache2/sites-enabled/000-default.conf

# Inicia el servidor Apache
apache2-foreground