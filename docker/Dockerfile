FROM php:7.1-cli

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql
