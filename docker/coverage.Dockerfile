ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# pcov is a coverage-only driver — much faster than Xdebug for line/branch
# coverage and Infection's mutation runs, and it builds like any other PECL
# extension (mirrors docker/phan.Dockerfile).
RUN pecl install pcov \
    && docker-php-ext-enable pcov
