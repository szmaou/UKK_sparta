#!/bin/sh
set -eu

# Ensure upload and session directories exist and are writable by php-fpm worker.
mkdir -p /var/www/html/public/uploads/alat /var/lib/php/sessions
chown -R nobody:nobody /var/www/html/public/uploads/alat /var/lib/php/sessions

# Start php-fpm and nginx in foreground-friendly mode.
php-fpm85 -D
exec nginx -g 'daemon off;'
