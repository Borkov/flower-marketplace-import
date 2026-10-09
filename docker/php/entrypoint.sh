#!/bin/sh
set -e

if [ ! -f /app/vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

if [ -f /app/bin/console ]; then
  php /app/bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
  php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null || true
fi

exec "$@"
