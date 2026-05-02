# Usamos una sola etapa para evitar líos con NPM por ahora
FROM php:8.2-fpm-alpine

# Instalar extensiones necesarias
RUN apk add --no-cache \
    nginx \
    supervisor \
    icu-dev \
    libzip-dev \
    zip \
    postgresql-dev

RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiamos todo el proyecto
COPY . .

# Instalamos dependencias de PHP
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Permisos para Symfony
RUN chown -R www-data:www-data var/

# Copiar configuraciones de Nginx y Supervisor (asegúrate de tener estos archivos en tu carpeta docker/)
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
