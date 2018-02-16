FROM php:7.1-cli

RUN pecl install xdebug

RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev git && \
    pecl install mongodb git zlib1g-dev && docker-php-ext-enable mongodb && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql zip && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/ \
    && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

ENV PATH="~/.composer/vendor/bin:./vendor/bin:${PATH}"