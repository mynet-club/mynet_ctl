#!/bin/sh
# Release build: PHP dependencies, app key for tests, then Vite assets.
set -eu

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --force >/dev/null
fi
npm ci
npm run build
