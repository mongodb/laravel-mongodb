ARG COMPOSER_VERSION=1.8
ARG PHP_VERSION=7.2
FROM composer:${COMPOSER_VERSION}
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --update --no-cache -t .php-build-deps \
    autoconf \
    libmcrypt=2.5.8-r7 \
    git \
    make \
    g++ \
    gcc \
    openssl-dev \
    libzip-dev; \
    pecl install mongodb-1.5.3

RUN set -xe; \
    if [[ "${PHP_VERSION:0:3}" != "7.3" ]]; then \
        pecl install xdebug-2.6.1; \
        docker-php-ext-enable xdebug; \
    fi; 
    
RUN docker-php-ext-enable mongodb && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql zip

COPY --from=composer  /usr/bin/composer /usr/local/bin/composer
RUN composer global require "hirak/prestissimo:^0.3"
