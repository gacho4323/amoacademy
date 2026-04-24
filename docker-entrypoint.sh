#!/bin/bash
set -e

timeout=60
attempt=0

echo "Checking MySQL readiness..."
while ! mysqladmin ping -h"mysql" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent; do
    attempt=$((attempt + 1))
    if [ $attempt -ge $timeout ]; then
        echo "ERROR: MySQL not ready after $timeout seconds. Aborting."
        exit 1
    fi
    echo "Waiting for MySQL... (attempt $attempt/$timeout)"
    sleep 2
done

echo "Syncing storage/app/upc from image to volume..."
mkdir -p /var/www/html/storage/app/upc
mkdir -p /var/www/html/storage_default/app/upc
rm -rf /var/www/html/storage/app/upc/*
cp -r /var/www/html/storage_default/app/upc/* /var/www/html/storage/app/upc/

if [ ! -L public/storage ]; then
    echo "Creating public/storage symlink..."
    php artisan storage:link
fi

echo "Clearing and optimizing Laravel cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan cache:clear
php artisan optimize

echo "Dumping Composer autoload..."
composer dump-autoload --optimize

if [ "$APP_ENV" != "stage" ] && [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

if [ "$RUN_QUEUE" = "true" ]; then
    echo "Starting Laravel queue worker..."
    exec php artisan queue:work --verbose --tries=3 --timeout=90
else
    echo "Starting PHP-FPM..."
    exec "$@"
fi
