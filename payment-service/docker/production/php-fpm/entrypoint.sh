#!/bin/sh
set -e

# Initialise storage volume on first run
if [ ! -d /var/www/storage/framework/sessions ]; then
    cp -rp /var/www/storage-init/* /var/www/storage/
fi

# Cache configuration & routes
php /var/www/artisan config:cache
php /var/www/artisan route:cache
php /var/www/artisan view:cache

# Run migrations
php /var/www/artisan migrate --force

exec "$@"
