#!/usr/bin/env bash

sleep 3 && composer install --prefer-source --no-interaction && php ./vendor/bin/phpunit
