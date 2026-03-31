#!/bin/sh
set -e

php /var/www/artisan migrate --force

exec "$@"
