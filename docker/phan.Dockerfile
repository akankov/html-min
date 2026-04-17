ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

RUN pecl install ast \
    && docker-php-ext-enable ast
