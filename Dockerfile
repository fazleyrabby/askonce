FROM dunglas/frankenphp:php8.4 AS dependencies
RUN apt-get update && apt-get install -y --no-install-recommends postgresql-client && rm -rf /var/lib/apt/lists/*
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl redis
RUN install-php-extensions pdo_sqlite
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM node:24-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY --from=dependencies /app/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
COPY vite.config.js ./
RUN npm run build

FROM dependencies AS app
COPY . .
RUN mkdir -p storage/app/private storage/app/backups storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
ENV SERVER_NAME=:8080
EXPOSE 8080
