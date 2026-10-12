FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && git config --global --add safe.directory '*'

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/backend