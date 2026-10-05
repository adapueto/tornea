FROM php:8.2-apache

# Extensión para conectarse a MySQL con PDO
RUN docker-php-ext-install pdo_mysql

# Configuración de PHP para desarrollo (zona horaria y errores visibles)
COPY docker/php.ini /usr/local/etc/php/conf.d/tornea.ini

# El código usa rutas absolutas /tornea/..., así que la app vive en /var/www/html/tornea
COPY . /var/www/html/tornea

# La raíz del servidor redirige a la app
RUN printf '<?php header("Location: /tornea/index.php");\n' > /var/www/html/index.php

EXPOSE 80
