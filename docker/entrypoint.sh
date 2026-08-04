#!/bin/sh
set -e

cd /app

php artisan config:cache
php artisan route:cache
php artisan view:cache

[ -L public/storage ] || php artisan storage:link

php artisan migrate --force

exec "$@"
