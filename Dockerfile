# syntax=docker/dockerfile:1

# ---- Build stage: composer + npm (npm run build also invokes
# `php artisan filament:assets`, so PHP has to be present in this
# stage too — it can't be split into a separate node-only stage) ----
FROM php:8.2-fpm-alpine AS build

# $PHPIZE_DEPS (gcc, make, autoconf, etc.) has to be installed before
# ANY of docker-php-ext-install/pecl below — php:*-alpine strips the C
# toolchain out of the base image after building it, it isn't just
# missing for pecl specifically. pecl install redis in particular has
# no alternative to compiling from source (unlike the docker-php-ext-
# install extensions below, it isn't bundled with PHP). -j1 (not
# -j"$(nproc)") caps compilation to one core so it doesn't spike memory
# usage past what a small deploy VPS has available.
RUN apk add --no-cache \
        nodejs npm git unzip $PHPIZE_DEPS \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 gd intl zip bcmath pdo_mysql opcache mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Cached independently of application source: npm ci (network-bound,
# the slowest single step in this build) only re-runs when
# package.json/package-lock.json actually change, instead of on every
# deploy regardless of what changed — COPY . . below invalidates every
# layer after it on any code change, so anything before it that
# shouldn't re-run on every commit has to be copied and installed
# ahead of that line, same reasoning as the composer layer above it.
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev
RUN npm run build && rm -rf node_modules

# ---- Runtime stage ----
FROM php:8.2-fpm-alpine AS runtime

# Same $PHPIZE_DEPS/-j1 reasoning as the build stage above — this
# runtime image is a separate php:*-alpine base with its own stripped
# toolchain, folded into the .build-deps virtual package here so
# `apk del .build-deps` removes it again afterward and it doesn't
# bloat the final image.
RUN apk add --no-cache \
        nginx supervisor \
        libpng libjpeg-turbo freetype libzip icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 gd intl zip bcmath pdo_mysql opcache mbstring \
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
