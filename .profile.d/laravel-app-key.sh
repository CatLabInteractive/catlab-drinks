#!/bin/bash
# Fix APP_KEY format for Laravel on Heroku
# This script ensures APP_KEY is in the correct format for Laravel's encrypter

# Only process if APP_KEY is set and doesn't have the correct format
if [ -n "$APP_KEY" ] && [[ ! "$APP_KEY" =~ ^base64: ]]; then
    # Heroku's generator creates a secret that isn't in Laravel's expected format.
    # We convert it to the correct format by:
    # 1. Hashing the secret to get exactly 32 bytes (required for AES-256-CBC)
    # 2. Base64-encoding the result
    # 3. Adding the 'base64:' prefix
    # This ensures the same secret always produces the same key (idempotent)
    APP_KEY="base64:$(php -r "echo base64_encode(hash('sha256', getenv('APP_KEY'), true));")"
    export APP_KEY
fi
