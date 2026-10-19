#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

if [ -d /opt/build-seed ]; then
    rm -rf /var/www/html/public/build/*
    cp -r /opt/build-seed/. /var/www/html/public/build/
fi

exec "$@"
