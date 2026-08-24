#!/bin/sh
set -e

# Run once, by the 'web' container only (see docker/entrypoint.sh) —
# never by the queue/scheduler containers sharing the same image, so
# they don't race the web container to run migrations concurrently.

php artisan storage:link || true
php artisan migrate --force
if [ "${APP_ENV:-production}" = "testing" ]; then
	php artisan db:seed --class=UserSeeder --force
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
