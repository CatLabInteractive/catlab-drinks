#!/bin/bash
# Fix APP_KEY format for Laravel on Heroku
# This script ensures APP_KEY is in the correct format for Laravel's encrypter

# Only process if APP_KEY is set and doesn't have the correct format
if [ -n "$APP_KEY" ] && [[ ! "$APP_KEY" =~ ^base64: ]]; then
    # Heroku's generator creates a secret that isn't in Laravel's expected format.
    # We convert it to the correct format by:
    # 1. Hashing the secret with SHA-256 to get exactly 32 bytes (required for AES-256-CBC)
    # 2. Base64-encoding the result
    # 3. Adding the 'base64:' prefix
    #
    # Security note: SHA-256 is a one-way cryptographic hash function that preserves
    # the entropy of the input while ensuring a fixed 32-byte output. The transformation
    # is deterministic (same input = same output), which ensures consistency across dynos.
    # Since the original Heroku secret is stored in environment variables (which an attacker
    # with access to the app would already have), this transformation doesn't introduce
    # any additional vulnerability.
    APP_KEY="base64:$(php -r "echo base64_encode(hash('sha256', getenv('APP_KEY'), true));")"
    export APP_KEY
fi
