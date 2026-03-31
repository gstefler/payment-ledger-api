#!/bin/sh
set -e

# Initialise storage volume on first run
if [ ! -d /var/www/storage/framework/sessions ]; then
    cp -rp /var/www/storage-init/* /var/www/storage/
fi

exec "$@"
