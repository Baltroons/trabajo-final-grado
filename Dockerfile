# ETAPA 1: Compilar activos (JS/CSS)
FROM node:20-alpine AS assets_builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ETAPA 2: Configuración de PHP y Servidor
FROM php:8.2-fpm-alpine

# Instalar extensiones necesarias para Symfony y BBDD
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

# Copiar el código del proyecto
COPY . .

# Copiar los activos compilados desde la etapa 1
COPY --from=assets_builder /app/public/build ./public/build

# Instalar dependencias de PHP sin scripts de desarrollo
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Configurar permisos para Symfony
RUN chown -R mw-data:www-data var/

# Copiar configuraciones de Nginx y Supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Railway usa la variable $PORT, pero Nginx suele usar el 80.
# Exponemos el puerto pero Railway lo mapeará solo.
EXPOSE 80

# Comando para arrancar Supervisor (que gestiona Nginx y PHP-FPM)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
