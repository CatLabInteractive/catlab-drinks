#!/bin/bash
# Generate Laravel Passport encryption keys for Heroku dynos
#
# On Heroku, the release phase runs on a separate dyno, so files generated
# by `php artisan passport:keys` during release do not persist to web dynos.
#
# This script generates an RSA key pair via PHP and exports the keys as
# environment variables so Laravel Passport can use them without key files.
#
# IMPORTANT: Keys generated this way are random and will change on each deploy,
# invalidating existing OAuth tokens. To keep tokens valid across deploys, set
# PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY as persistent Heroku config vars:
#
#   php artisan passport:keys
#   heroku config:set PASSPORT_PRIVATE_KEY="$(cat storage/oauth-private.key)"
#   heroku config:set PASSPORT_PUBLIC_KEY="$(cat storage/oauth-public.key)"

if [ -n "$PASSPORT_PRIVATE_KEY" ] || [ -n "$PASSPORT_PUBLIC_KEY" ]; then
    # Keys provided via environment variables — validate both are set
    if [ -z "$PASSPORT_PRIVATE_KEY" ] || [ -z "$PASSPORT_PUBLIC_KEY" ]; then
        echo "WARNING: Only one of PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY is set." >&2
        echo "Both must be set for Passport to work correctly." >&2
    fi
else
    # No environment variables set — generate a key pair and export as env vars
    echo "No PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY config vars found."
    echo "Generating temporary Passport keys for this dyno..."
    echo "NOTE: These keys will change on each deploy, invalidating OAuth tokens."
    echo "For persistent keys, set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY as Heroku config vars."

    KEYS_OUTPUT=$(php -r '
        $key = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        if (!$key) { fwrite(STDERR, "openssl_pkey_new failed: " . openssl_error_string() . "\n"); exit(1); }
        openssl_pkey_export($key, $privKey);
        $pubKey = openssl_pkey_get_details($key)["key"];
        echo base64_encode($privKey) . "\n" . base64_encode($pubKey);
    ' 2>&1)

    if [ $? -eq 0 ]; then
        PASSPORT_PRIVATE_KEY=$(echo "$KEYS_OUTPUT" | head -1 | base64 -d)
        PASSPORT_PUBLIC_KEY=$(echo "$KEYS_OUTPUT" | tail -1 | base64 -d)
        export PASSPORT_PRIVATE_KEY
        export PASSPORT_PUBLIC_KEY
        echo "Passport keys generated and exported as environment variables."
    else
        echo "ERROR: Failed to generate Passport keys." >&2
        echo "$KEYS_OUTPUT" >&2
    fi
fi
