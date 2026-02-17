#!/bin/bash
# Generate Laravel Passport encryption keys for Heroku dynos
#
# On Heroku, the release phase runs on a separate dyno, so files generated
# by `php artisan passport:keys` during release do not persist to web dynos.
#
# This script runs on each dyno at startup and generates the key files
# in the storage directory if they don't already exist and the keys are
# not provided via environment variables.
#
# For multi-dyno deployments, set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY
# as Heroku config vars to ensure all dynos use the same keys.

if [ -n "$PASSPORT_PRIVATE_KEY" ] || [ -n "$PASSPORT_PUBLIC_KEY" ]; then
    # Keys provided via environment variables — validate both are set
    if [ -z "$PASSPORT_PRIVATE_KEY" ] || [ -z "$PASSPORT_PUBLIC_KEY" ]; then
        echo "WARNING: Only one of PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY is set." >&2
        echo "Both must be set for Passport to work correctly." >&2
    fi
else
    # No environment variables set — generate key files if they don't exist
    if [ ! -f "storage/oauth-private.key" ] || [ ! -f "storage/oauth-public.key" ]; then
        echo "Generating Passport encryption keys..."
        php artisan passport:keys --force
        if [ $? -eq 0 ]; then
            echo "Passport keys generated successfully."
        else
            echo "ERROR: Failed to generate Passport keys." >&2
        fi
    fi
fi
