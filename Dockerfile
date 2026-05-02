FROM php:8.4-fpm-alpine

# 1. Instalación de paquetes del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    zip \
    postgresql-dev \
    libpng-dev

# 2. Instalación de extensiones de PHP
RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip gd

# 3. Instalación de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 4. Variables de entorno para la construcción
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV COMPOSER_ALLOW_SUPERUSER=1

# 5. Copiar el código del proyecto
COPY . .

# 6. --- SOLUCIÓN AL ERROR DE .ENV ---
# Borramos los archivos locales para que no interfieran...
RUN rm -f .env .env.local .env.test .env.prod
# ...pero CREAMOS uno vacío para que Symfony no lance una excepción al buscarlo
RUN touch .env

# 7. Instalación de dependencias de PHP
RUN composer install --no-dev --optimize-autoloader

# 8. Permisos para Symfony
RUN chown -R www-data:www-data var/

# 9. Configuración de servicios
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

# 10. Comando de inicio
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
