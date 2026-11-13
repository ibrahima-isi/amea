#!/bin/sh
# Sole scheduler, enabled explicitly after disabling the former host cron.
set -eu
cd /opt/amea
next_pending=0
next_trash=0
while :; do
    php scripts/run-shared-jobs.php mail || echo "Mail worker failed; retrying next minute" >&2
    now=$(date +%s)
    if [ "$now" -ge "$next_pending" ]; then
        php scripts/run-shared-jobs.php pending || echo "Storage cleanup failed; durable work remains queued" >&2
        next_pending=$((now + 3600))
    fi
    if [ "${AMEA_TRASH_BACKUP_CONFIRMED:-0}" = 1 ] && [ "$now" -ge "$next_trash" ]; then
        php scripts/run-shared-jobs.php trash || echo "Trash purge failed; durable work remains queued" >&2
        next_trash=$((now + 86400))
    fi
    sleep 60 &
    wait $!
done
