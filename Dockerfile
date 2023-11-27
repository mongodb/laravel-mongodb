FROM php:8.1-cli

# Install extensions
RUN apt-get update && \
    apt-get install -y autoconf pkg-config libssl-dev git unzip libzip-dev zlib1g-dev && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    docker-php-ext-install -j$(nproc) zip

# Create php.ini and enable coverage mode in xdebug
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/xdebug.ini


# Install Composer
RUN curl https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
