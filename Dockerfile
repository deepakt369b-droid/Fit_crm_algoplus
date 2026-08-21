# syntax=docker/dockerfile:1

# ---- Build stage: composer + npm (npm run build also invokes
# `php artisan filament:assets`, so PHP has to be present in this
# stage too — it can't be split into a separate node-only stage) ----
FROM php:8.2-fpm-alpine AS build

RUN apk add --no-cache \
        nodejs npm git unzip \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl zip bcmath pdo_mysql opcache mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

RUN npm ci && npm run build && rm -rf node_modules

# ---- Runtime stage ----
FROM php:8.2-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx supervisor \
        libpng libjpeg-turbo freetype libzip icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl zip bcmath pdo_mysql opcache mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=build /app /var/www/html

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-fitcrm.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /var/www/html/scripts/coolify-deploy.sh

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/app/public storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
