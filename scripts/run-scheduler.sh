#!/usr/bin/env bash
#
# Wrapper invoked by the LaunchAgent every minute.
# Runs `php artisan schedule:run` in the project root and logs output.
#
# Activated via:
#   cp scripts/com.consultfest.scheduler.plist ~/Library/LaunchAgents/
#   launchctl load ~/Library/LaunchAgents/com.consultfest.scheduler.plist
#
# Deactivated via:
#   launchctl unload ~/Library/LaunchAgents/com.consultfest.scheduler.plist
#
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${PROJECT_DIR}/storage/logs"
mkdir -p "${LOG_DIR}"

cd "${PROJECT_DIR}"

# Prefer Homebrew php, fall back to system php.
if [ -x /usr/local/bin/php ]; then
    PHP_BIN=/usr/local/bin/php
elif [ -x /opt/homebrew/bin/php ]; then
    PHP_BIN=/opt/homebrew/bin/php
else
    PHP_BIN=/usr/bin/php
fi

"${PHP_BIN}" artisan schedule:run >> "${LOG_DIR}/scheduler.log" 2>&1 || true