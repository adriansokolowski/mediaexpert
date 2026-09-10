#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    echo '==> Installing PHP dependencies'
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    echo '==> Creating .env from .env.example'
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo '==> Generating application key'
    php artisan key:generate --force
fi

echo '==> Running migrations and seeders'
php artisan migrate --force --seed

exec "$@"
