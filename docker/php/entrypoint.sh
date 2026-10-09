#!/bin/sh
set -e

export COMPOSER_ALLOW_SUPERUSER=1

if [ ! -f /app/vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --no-scripts
fi

mkdir -p /app/var
chmod -R 777 /app/var || true

if [ -f /app/bin/console ]; then
  php /app/bin/console cache:clear --no-interaction || true
  php /app/bin/console doctrine:database:create --if-not-exists --no-interaction || true
  php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
fi

exec "$@"
