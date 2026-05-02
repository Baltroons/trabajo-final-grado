# Usamos una imagen de PHP con soporte para FPM y Alpine (ligera)
FROM php:8.2-fpm-alpine

# 1. Instalar dependencias del sistema y extensiones de PHP
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    zip \
    postgresql-dev \
    libpng-dev

RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip gd

# 2. Instalar Composer (el gestor de PHP que sí usas)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 3. Copiar los archivos de tu proyecto
COPY . .

# 4. Instalar las dependencias de Symfony (usando composer.json)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# 5. Configurar permisos para que Symfony pueda escribir logs y caché
RUN chown -R www-data:www-data var/

# 6. Copiar las configuraciones que creamos para Nginx y Supervisor
# (Asegúrate de tener la carpeta 'docker' con nginx.conf y supervisord.conf)
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# 7. Railway necesita que el puerto esté abierto
EXPOSE 80

# 8. Arrancar el gestor de procesos
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
