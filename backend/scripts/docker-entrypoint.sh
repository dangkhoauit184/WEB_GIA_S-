#!/bin/sh
set -eu

PORT="${PORT:-80}"
case "$PORT" in
  ''|*[!0-9]*) PORT=80 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
  PORT=80
fi

if [ "$PORT" != "80" ]; then
  printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
  sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

mkdir -p /var/www/educenter/backend/writable/cache \
         /var/www/educenter/backend/writable/logs \
         /var/www/educenter/backend/writable/session \
         /var/www/educenter/backend/writable/uploads
chown -R www-data:www-data /var/www/educenter/backend/writable

exec docker-php-entrypoint "$@"
