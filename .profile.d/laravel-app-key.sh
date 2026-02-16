#!/bin/bash
# Fix APP_KEY format for Laravel on Heroku
# This script ensures APP_KEY is in the correct format for Laravel's encrypter

# Only process if APP_KEY is set and doesn't have the correct format
if [ -n "$APP_KEY" ] && [[ ! "$APP_KEY" =~ ^base64: ]]; then
    # Heroku's generator creates a cryptographically secure random secret that isn't
    # in Laravel's expected format. We convert it to the correct format by:
    # 1. Hashing the secret with SHA-256 to get exactly 32 bytes (required for AES-256-CBC)
    # 2. Base64-encoding the result
    # 3. Adding the 'base64:' prefix
    #
    # Security note: SHA-256 is appropriate here (not a KDF like PBKDF2) because:
    # - The input is already a cryptographically secure random string from Heroku
    # - We're not deriving a key from a password (which would need salt & iterations)
    # - We're transforming high-entropy input to high-entropy output
    # - The transformation is deterministic, ensuring consistency across dynos
    # - An attacker with access to the Heroku secret already has all environment variables
    
    # Transform the APP_KEY with error handling
    TRANSFORMED_KEY=$(php -r "echo base64_encode(hash('sha256', getenv('APP_KEY'), true));" 2>&1)
    
    if [ $? -eq 0 ] && [ -n "$TRANSFORMED_KEY" ]; then
        APP_KEY="base64:${TRANSFORMED_KEY}"
        export APP_KEY
    else
        echo "ERROR: Failed to transform APP_KEY. Please check PHP is available." >&2
        echo "Original error: $TRANSFORMED_KEY" >&2
        # Don't exit - let the app try to start and fail with a clearer Laravel error
    fi
fi
