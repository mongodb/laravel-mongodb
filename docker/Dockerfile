FROM php:7.1-cli

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/ \
    && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer
