#!/usr/bin/env sh
set -e

APP_PORT="$(printf '%s' "${PORT:-10000}" | sed 's/[^0-9].*$//')"
if [ -z "$APP_PORT" ]; then
    APP_PORT=10000
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php -S 0.0.0.0:"$APP_PORT" -t public railway/router.php
