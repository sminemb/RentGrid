FROM composer:2 AS composer

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install dom mbstring pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/; s|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/rentgrid.conf \
    && a2enconf rentgrid

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["sh", "-c", "export APP_KEY=base64:$APP_KEY_RAW; php artisan migrate --force && exec apache2-foreground"]
