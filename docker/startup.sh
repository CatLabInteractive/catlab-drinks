#!/bin/bash
# Docker startup script for CatLab Drinks
# This script runs on container startup to initialize the application.
# It is designed to be idempotent — safe to run on every container start.

set -e

echo "==> CatLab Drinks: Running startup script..."

# --- .env file ---
if [ ! -f .env ]; then
    echo "==> No .env file found, copying from .env.example..."
    cp .env.example .env
fi

# --- Composer dependencies ---
if [ ! -d vendor ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction
fi

# --- Application key ---
# Generate an application key if one is not set
APP_KEY_VALUE=$(grep '^APP_KEY=' .env | cut -d '=' -f2-)
if [ -z "$APP_KEY_VALUE" ]; then
    echo "==> Generating application key..."
    php artisan key:generate --force
fi

# --- Wait for MySQL and run migrations ---
echo "==> Waiting for MySQL and running migrations..."
MAX_TRIES=30
COUNT=0
until php artisan migrate --force 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ $COUNT -eq $MAX_TRIES ]; then
        echo "WARNING: Could not run migrations after $MAX_TRIES attempts."
        break
    fi
    echo "    Waiting for database... ($COUNT/$MAX_TRIES)"
    sleep 2
done

# --- Passport keys ---
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    echo "==> Generating Passport encryption keys..."
    php artisan passport:keys --force
fi

# --- NPM dependencies and frontend build ---
if [ ! -d node_modules ]; then
    echo "==> Installing NPM dependencies..."
    npm install
fi

if [ ! -d public/res ]; then
    echo "==> Building frontend assets..."
    npm run prod
fi

echo "==> CatLab Drinks: Startup complete!"
