#!/bin/bash
# Pre-start hook for the Railway Laravel container.
#
# Runs migrations against the DB pointed to by $DB_URL (Postgres on Railway,
# SQLite in dev). --force is required because deploys are non-interactive.
#
# Then rebuilds all cached config/routes/views so they pick up the latest
# env vars and code. `optimize:clear` runs first to wipe stale caches from
# a previous deploy — otherwise config:cache can resurrect old values if the
# file already exists.
#
# This script is wired into the container CMD via Dockerfile. Exit non-zero
# (set -e) so a failed migration halts the deploy instead of booting the
# app against an unmigrated DB.

set -e

php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
