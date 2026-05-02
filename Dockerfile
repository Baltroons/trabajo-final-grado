FROM php:8.4-fpm-alpine

# ... (Instalación de paquetes de Alpine igual que antes) ...
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev zip postgresql-dev libpng-dev
RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- ESTA ES LA PARTE CLAVE ---
ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1
# ------------------------------

COPY . .
RUN rm -f .env .env.local .env.test

# Ahora Composer sabrá que no debe buscar el DebugBundle al limpiar la caché
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data var/

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
