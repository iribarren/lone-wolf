# Backend runtime: PHP 8.3-FPM + Composer + required extensions (plan.md Phase 1).
FROM php:8.3-fpm-alpine AS base

# Runtime libs must survive the build-deps cleanup (libpq for pdo_pgsql).
RUN apk add --no-cache libpq icu-libs libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Dependencies layer: install first for cache-friendly rebuilds.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist || true

# Application source.
COPY . .

RUN composer dump-autoload --optimize

EXPOSE 9000

CMD ["php-fpm"]
