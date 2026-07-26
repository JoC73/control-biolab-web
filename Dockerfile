FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libsqlite3-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV SESSION_DRIVER=file
ENV CACHE_STORE=file
ENV QUEUE_CONNECTION=sync

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
