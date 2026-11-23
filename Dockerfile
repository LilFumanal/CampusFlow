# ---------- Stage 1 : dépendances Composer ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --ignore-platform-reqs

# ---------- Stage 2 : image finale ----------
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

ENV APP_ENV=prod

# Variables factices UNIQUEMENT pour le build (jamais utilisées en runtime)
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && APP_SECRET=build-only \
    DATABASE_URL="postgresql://x:x@127.0.0.1:5432/x?serverVersion=16" \
    php bin/console importmap:install \
    && APP_SECRET=build-only \
    DATABASE_URL="postgresql://x:x@127.0.0.1:5432/x?serverVersion=16" \
    php bin/console asset-map:compile \
    && mkdir -p var/cache var/log var/share \
    && chown -R www-data:www-data var

USER www-data
EXPOSE 9000
CMD ["php-fpm"]