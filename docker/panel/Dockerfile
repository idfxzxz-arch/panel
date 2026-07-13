FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
FROM php:8.3-fpm-alpine
RUN apk add --no-cache git docker-cli docker-cli-compose icu-libs libzip mariadb-client && apk add --no-cache --virtual .build-deps icu-dev libzip-dev $PHPIZE_DEPS && docker-php-ext-install intl opcache pdo_mysql && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
