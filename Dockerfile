# Railway build for Consultfest.
#
# Why a Dockerfile instead of Nixpacks: Nixpacks' Laravel preset merges its
# own PHP (8.3) into PATH even when we override [phases.setup] with php84,
# so composer install keeps failing with "your php version (8.3.33) does not
# satisfy that requirement". A Dockerfile is explicit — the PHP version you
# see in the FROM line is the PHP version composer runs against.
#
# PHP 8.4-cli-bookworm matches the local dev environment (PHP 8.4.23 via
# Laravel Herd) and satisfies composer.lock's symfony/* v8.1 requirements.

FROM php:8.4-cli-bookworm

# System deps. Most are required by PHP extensions we install below.
#   git             → composer needs it for some package metadata
#   curl, zip, unzip → utility tools (composer install, archive handling)
#   libpng/libonig/libxml/libzip-dev → headers for gd/mbstring/zip
#   libpq-dev       → headers + client library for pdo_pgsql (libpq-fe.h, libpq.so)
#   nodejs + npm    → frontend build (Tailwind v4 via Vite)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        zip \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libpq-dev \
        nodejs \
        npm \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions Laravel + Postgres need:
#   pdo_pgsql — Postgres driver (we run Postgres in production)
#   mbstring  — string handling (Laravel core)
#   bcmath    — arbitrary precision math
#   gd        — image handling (avatars, attachments)
#   zip       — ZipArchive (used by some packages)
#   intl      — locale-aware sorting (Laravel uses it in some comparisons)
RUN docker-php-ext-install pdo pdo_pgsql mbstring bcmath gd zip intl

# Composer (official image is the standard way to install the latest).
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Build-ID marker. If you see this in the build log, Railway is reading THIS Dockerfile.
# If you don't see it, Railway is building from a cached/stale Dockerfile.
RUN echo "BUILD-MARKER: e9e025c-explicit-copy-$(date +%s)"

# Install PHP deps in their own layer so editing app code doesn't bust the cache.
# We split the COPYs (instead of one combined COPY) because Debug-Time: BuildKit
# and Railway's builder were silently producing an empty bootstrap/ directory with
# the combined form. Separating each source makes the COPY deterministic and lets
# us `ls` each one to verify what's actually in the image.
#
# Composer's post-install script runs `php artisan package:discover`, which needs
# artisan and bootstrap/app.php to exist already — so we copy them before
# composer install.
COPY composer.json composer.lock ./
COPY artisan ./
COPY bootstrap/app.php ./bootstrap/app.php
COPY bootstrap/providers.php ./bootstrap/providers.php
# bootstrap/cache/ is required by Laravel at runtime (package:discover writes
# services.php and packages.php into it). The dir is empty in git (only its
# inner .gitignore is tracked), so we create it here.
RUN mkdir -p /app/bootstrap/cache && chmod 775 /app/bootstrap/cache

# storage/framework/{views,cache,sessions} are also gitignored-empty in this
# repo. Laravel's View Compiler needs storage/framework/views to exist BEFORE
# `php artisan package:discover` runs (Livewire v4 boots something that touches
# the view compiler). Same story for sessions/cache — created lazily at runtime
# but the dirs must exist. Logs dir is for Laravel's logger.
RUN mkdir -p \
        /app/storage/framework/views \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/logs \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Debug: confirm what's actually in the image before composer runs.
RUN ls -la /app/ && echo "---BOOTSTRAP---" && ls -la /app/bootstrap/

RUN composer install --no-interaction --optimize-autoloader --prefer-dist

# Then pnpm deps (the project uses pnpm — see pnpm-lock.yaml v9.0).
# corepack is bundled with Node.js ≥16, so no extra install needed.
RUN corepack enable && corepack prepare pnpm@9 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

# Finally the rest of the app code.
COPY . .

# Build frontend assets (Tailwind v4 / Vite → public/build).
RUN pnpm run build

# Railway sets $PORT dynamically. Default to 8000 for parity with artisan serve.
EXPOSE 8000

# On every container start: migrate, warm caches, serve.
# migrate --force is required because the env is non-interactive.
# config:cache / route:cache / view:cache always rebuild — env vars may have changed.
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
