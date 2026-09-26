FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    linux-headers \
    openssl-dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install intl opcache pdo_mysql zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html