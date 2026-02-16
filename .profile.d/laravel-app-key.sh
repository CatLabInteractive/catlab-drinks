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
    TRANSFORMED_KEY=$(php -r "echo base64_encode(hash('sha256', getenv('APP_KEY'), true));" 2>/dev/null)
    
    if [ $? -eq 0 ] && [ -n "$TRANSFORMED_KEY" ]; then
        APP_KEY="base64:${TRANSFORMED_KEY}"
        export APP_KEY
    else
        echo "ERROR: Failed to transform APP_KEY to Laravel format." >&2
        echo "PHP must be available to transform Heroku's generated secret." >&2
        echo "APP_KEY will remain in incorrect format: ${APP_KEY:0:20}..." >&2
        # Note: Keeping the original key allows Laravel to show a clearer error message
        # about the incorrect format, which is more helpful for debugging
    fi
fi
