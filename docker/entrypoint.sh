#!/bin/sh
set -e

# Run migrations
php /var/www/html/artisan migrate --force

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
