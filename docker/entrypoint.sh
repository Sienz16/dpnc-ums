#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is missing. Set APP_KEY in deployment environment variables." >&2
    exit 1
fi

ROLE="${CONTAINER_ROLE:-web}"

case "${ROLE}" in
    web)
        php artisan migrate --force --isolated --ansi
        php artisan optimize:clear --ansi
        php artisan optimize --ansi
        php-fpm -D
        exec nginx -g "daemon off;"
        ;;
    worker)
        php artisan optimize --ansi
        exec php artisan queue:work --verbose --no-interaction --tries=3 --timeout=90
        ;;
    scheduler)
        php artisan optimize --ansi
        exec php artisan schedule:work --no-interaction
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: ${ROLE}" >&2
        exit 1
        ;;
esac
