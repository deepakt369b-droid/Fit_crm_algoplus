#!/bin/sh
set -e

# CONTAINER_ROLE selects what this container instance does with the same
# image: "web" (default) serves HTTP and is the only role that runs
# migrations/caches; "queue" and "scheduler" just run their worker command
# directly, so three replicas of one image never race each other to
# migrate concurrently.
ROLE="${CONTAINER_ROLE:-web}"

case "$ROLE" in
    web)
        /var/www/html/scripts/coolify-deploy.sh
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
