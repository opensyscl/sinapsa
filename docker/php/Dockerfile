FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl icu-dev libzip-dev oniguruma-dev libpng-dev jpeg-dev freetype-dev \
    postgresql-dev libpq-dev \
    autoconf g++ make linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql intl zip bcmath opcache gd pcntl exif sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make linux-headers

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/bash -D www
USER www

EXPOSE 9000
CMD ["php-fpm"]
