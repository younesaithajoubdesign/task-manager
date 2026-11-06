#!/usr/bin/env bash
# Put the latest commit of main online. Run on the server: /srv/app/deploy/deploy.sh
set -euo pipefail

cd /srv/app
/srv/app/deploy/backup.sh          # a copy of the data before any migration
git pull --ff-only

cd /srv/app/api
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize

cd /srv/app/web
npm ci
npm run build

sudo systemctl reload php8.5-fpm
echo "Deployed $(git -C /srv/app rev-parse --short HEAD)"
