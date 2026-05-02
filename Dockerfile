# CAMBIA ESTA LÍNEA (la versión 8.2 por la 8.4)
FROM php:8.4-fpm-alpine

# El resto del archivo se mantiene igual
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    zip \
    postgresql-dev \
    libpng-dev

RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip gd

# ... resto del archivo ...

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
