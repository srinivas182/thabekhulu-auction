# PHP 8.4 runtime for the app, Horizon and scheduler containers.
FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype \
        nodejs npm git unzip ghostscript \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
        libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip bcmath pcntl gd opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app

WORKDIR /var/www/html
USER app
