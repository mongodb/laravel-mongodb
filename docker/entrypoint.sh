#!/usr/bin/env bash

sleep 3 &&
composer install --prefer-source --no-interaction &&
php ./vendor/bin/phpunit &&
mkdir -p ./build/logs &&
php ./vendor/bin/phpunit --coverage-clover ./build/logs/clover.xml &&
php ./vendor/bin/php-coverall -v