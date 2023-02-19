FROM composer:2.5.4
FROM php:8.1-cli

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev git libzip-dev zlib1g-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    docker-php-ext-install -j$(nproc) pdo_mysql zip

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /code

COPY composer.* ./

RUN composer install

COPY ./ ./

CMD ["./vendor/bin/phpunit"]
