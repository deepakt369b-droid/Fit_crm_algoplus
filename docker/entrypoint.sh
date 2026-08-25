#!/bin/sh
set -e

# CONTAINER_ROLE selects what this container instance does with the same
# image: "web" (default) serves HTTP and is the only role that runs
# migrations/caches; "queue" and "scheduler" just run their worker command
# directly, so three replicas of one image never race each other to
# migrate concurrently.
ROLE="${CONTAINER_ROLE:-web}"

# php-fpm workers run as www-data; make sure everything they must write
# (sessions/logs/cache, and the sqlite db file plus its directory for
# journal/WAL sidecars) is writable by them BEFORE anything else runs.
# This also covers Coolify persistent volumes mounted over these paths,
# which can come up owned by root on first attach. Runs cheaply even when
# ownership is already correct.
fix_ownership() {
    mkdir -p database \
        storage/framework/cache storage/framework/sessions \
        storage/framework/testing storage/framework/views \
        storage/app/public storage/logs bootstrap/cache
    touch database/database.sqlite
    chown -R www-data:www-data database storage bootstrap/cache
}

case "$ROLE" in
    web)
        fix_ownership
        /var/www/html/scripts/coolify-deploy.sh
        # coolify-deploy.sh above runs as root (migrations, caches) and
        # can leave root-owned artifacts behind — cached config/routes in
        # bootstrap/cache, sqlite -wal/-shm sidecars in database/ — that
        # www-data then cannot write to. Normalize once more before the
        # web server takes over.
        fix_ownership
        exec "$@"
        ;;
    queue)
        exec php artisan queue:work --tries=3 --max-time=3600
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: $ROLE" >&2
        exit 1
        ;;
esac

