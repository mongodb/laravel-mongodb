ARG PHP_VERSION=8.1
ARG COMPOSER_VERSION=2.5.4

FROM php:${PHP_VERSION}-cli

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev git libzip-dev zlib1g-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    docker-php-ext-install -j$(nproc) pdo_mysql zip

COPY --from=composer:${COMPOSER_VERSION} /usr/bin/composer /usr/local/bin/composer

WORKDIR /code

COPY composer.* ./

RUN composer install

COPY ./ ./

RUN composer install

CMD ["./vendor/bin/phpunit"]
