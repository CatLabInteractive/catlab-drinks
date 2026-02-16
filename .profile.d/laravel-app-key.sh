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
    # Capture both stdout and stderr to handle PHP errors
    TRANSFORM_OUTPUT=$(php -r "echo base64_encode(hash('sha256', getenv('APP_KEY'), true));" 2>&1)
    TRANSFORM_EXIT=$?
    
    # Validate the transformed key format and length
    # Base64-encoded 32 bytes always produces exactly 44 characters total (including one '=' padding)
    # Math: 32 bytes = 256 bits. Base64 encodes in 3-byte (24-bit) chunks to 4 chars each.
    #       32 bytes = 10 complete chunks (30 bytes) + 2 remaining bytes
    #       10 chunks * 4 chars = 40 chars
    #       2 remaining bytes (16 bits) = 3 base64 chars + 1 padding '='
    #       Total: 40 + 3 + 1 = 44 characters (always)
    if [ $TRANSFORM_EXIT -eq 0 ] && [[ "$TRANSFORM_OUTPUT" =~ ^[A-Za-z0-9+/]{43}=$ ]]; then
        APP_KEY="base64:${TRANSFORM_OUTPUT}"
        export APP_KEY
    else
        echo "ERROR: Failed to transform APP_KEY to Laravel format." >&2
        if [ $TRANSFORM_EXIT -ne 0 ]; then
            echo "PHP execution failed with exit code $TRANSFORM_EXIT" >&2
            echo "Error: $TRANSFORM_OUTPUT" >&2
        else
            echo "Transformation produced invalid output" >&2
            echo "Expected: 44 characters total (43 base64 chars + one '=' padding) from SHA-256 hash" >&2
        fi
        echo "PHP must be available and functional to transform Heroku's generated secret." >&2
        echo "The APP_KEY will remain in incorrect format and Laravel will fail to start." >&2
        # Note: Keeping the original key allows Laravel to show a clearer error message
        # about the incorrect format, which is more helpful for debugging
    fi
fi
