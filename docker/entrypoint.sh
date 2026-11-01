#!/bin/sh
set -e

cd /app

php artisan config:cache
php artisan route:cache
php artisan view:cache

[ -L public/storage ] || php artisan storage:link

php artisan migrate --force

# Idempotent: gives existing companies the permissions added by new releases.
php artisan companies:sync-roles

exec "$@"
