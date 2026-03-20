#!/bin/sh
set -e

# Wait for database
echo "Waiting for database connection..."
while ! nc -z "${DB_HOST:-db}" "${DB_PORT:-3306}"; do
  sleep 1
done
echo "Database is up!"

# Run migrations if needed
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running migrations..."
  php /app/artisan migrate --force
fi

# Clear cache
php /app/artisan config:cache
php /app/artisan route:cache
php /app/artisan view:cache

echo "Application is ready!"

# Execute the main command
exec "$@"
