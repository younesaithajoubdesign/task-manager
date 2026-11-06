#!/usr/bin/env bash
# Daily copy of the database, keeping the last 30 (SPEC addendum §11).
# Cron: 0 3 * * * /srv/app/deploy/backup.sh
# sqlite3 .backup instead of cp: the copy is consistent even if a write
# happens at that very moment.
set -euo pipefail

mkdir -p /srv/backups
sqlite3 /srv/app/api/database/database.sqlite ".backup '/srv/backups/db-$(date +%F).sqlite'"
find /srv/backups -name 'db-*.sqlite' -mtime +30 -delete
