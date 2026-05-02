FROM php:8.4-fpm-alpine

# 1. Instalación de paquetes y extensiones
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev zip postgresql-dev libpng-dev
RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 2. Variables para el proceso de construcción
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- EL TRUCO PARA QUE SYMFONY NO EXPLOTE AL COMPILAR ---
# Le damos valores cualquiera a las variables que exige la configuración
ENV DATABASE_URL="postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=16&charset=utf8"
ENV DEFAULT_URI="https://localhost"
ENV MERCURE_URL="http://localhost/.well-known/mercure"
ENV MERCURE_JWT_SECRET="brainhub_secret_32_characters_long_key"
# ----------------------------------------------

# 3. Copiamos y preparamos archivos
# ...

# 3. Copiamos y preparamos archivos
COPY . .
RUN rm -f .env .env.local .env.test .env.prod
RUN touch .env

# 4. Instalación de dependencias (ahora con DATABASE_URL definida no fallará)
RUN composer install --no-dev --optimize-autoloader

# 5. Permisos y configuración final
RUN chown -R www-data:www-data var/
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
