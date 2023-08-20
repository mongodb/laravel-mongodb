ARG PHP_VERSION=8.1


FROM php:${PHP_VERSION}-cli

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev git libzip-dev zlib1g-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    docker-php-ext-install -j$(nproc) pdo_mysql zip

# Copy the Composer binary from the specified stage
COPY --from=composer:2.5.4 /usr/bin/composer /usr/local/bin/composer

WORKDIR /code

COPY ./ ./

RUN composer install

CMD ["./vendor/bin/phpunit"]
