# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4

##########################
# Stage 1: build (composer + npm)
##########################
FROM php:${PHP_VERSION}-fpm-alpine AS build

RUN apk add --no-cache \
        nodejs npm git unzip \
        libzip-dev icu-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev postgresql-dev sqlite-dev $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql pdo_sqlite mbstring bcmath gd zip intl exif opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN npm ci \
    && npm run build \
    && rm -rf node_modules

##########################
# Stage 2: runtime (php-fpm + nginx + supervisor)
##########################
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

RUN apk add --no-cache nginx supervisor curl libzip icu-libs oniguruma libpng freetype libjpeg-turbo postgresql-libs sqlite-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS libzip-dev icu-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev postgresql-dev sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql pdo_sqlite mbstring bcmath gd zip intl exif opcache \
    && apk del .build-deps

WORKDIR /app

COPY --from=build /app /app

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /run/nginx storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fs http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
