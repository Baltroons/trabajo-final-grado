FROM php:8.4-fpm

# Instalamos las librerías necesarias de PostgreSQL para PHP
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql
