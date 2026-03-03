# Deployment — Heroku & Dokku

## Overview

The app supports two deployment targets that coexist in the same repo without conflict:

| Target | Build method | Web process |
|--------|-------------|-------------|
| **Heroku** | Buildpacks (`heroku/nodejs` + `heroku/php`) | `heroku-php-apache2 public/` |
| **Dokku** | `Dockerfile` (`thecodingmachine/php:8.1-v5-slim-apache`) | `apache2-foreground` (via image entrypoint) |

---

## Key Files

| File | Purpose |
|------|---------|
| `Procfile` | Web process + release task — works for both Heroku and Dokku |
| `app.json` | Heroku "Deploy to Heroku" button config (stack, buildpacks, env vars, addons) |
| `Dockerfile` | Dokku build only — Heroku ignores this when using buildpacks |
| `heroku.yml` | **Does not exist / must not be present** — would switch Heroku back to container stack |

---

## Procfile

```
web: bash -c 'if command -v heroku-php-apache2 &> /dev/null; then heroku-php-apache2 public/; else apache2-foreground; fi'
release: php artisan migrate --force
```

- On **Heroku** (buildpacks): `heroku-php-apache2` is installed by the PHP buildpack → used as the web server.
- On **Dokku** (Dockerfile): `heroku-php-apache2` does not exist in the image → falls back to `apache2-foreground`, which is the correct command for the `thecodingmachine/php` base image.
- The `release:` process runs `php artisan migrate --force` on every deploy. Both Heroku and Dokku support this.

---

## app.json (Heroku "Deploy to Heroku" button)

- `"stack": "heroku-22"` — ensures Heroku uses buildpacks, not Docker. **Do not set this to `"container"`.**
- `"buildpacks"` — declares `heroku/nodejs` first, then `heroku/php`. Order matters (see below).
- `"scripts": { "postdeploy": ... }` — **not used** (unsupported on container stack; migrations run via `Procfile release:` instead).
- The `buildpacks` array in `app.json` only takes effect when creating a new app via the button or `heroku create`. For existing apps, buildpacks must be set manually via CLI.

---

## Heroku Buildpack Order

**Node.js must come before PHP.** This ensures `npm run prod` (triggered via `heroku-postbuild` in `package.json`) compiles all frontend assets before PHP's composer install finalises the build.

For new apps the `app.json` `buildpacks` array handles this automatically. For existing apps:

```bash
heroku buildpacks:add --index 1 heroku/nodejs
# Result: 1. heroku/nodejs  2. heroku/php
```

The `heroku-postbuild` script in `package.json` runs `npm run prod`. All build tools (webpack, laravel-mix, etc.) are in `devDependencies` — the `NPM_CONFIG_PRODUCTION=false` env var (set in `app.json`) ensures they are installed during the build.

---

## Dockerfile (Dokku only)

The `Dockerfile` uses `thecodingmachine/php:8.1-v5-slim-apache` which:
- Has `ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]` and `CMD ["apache2-foreground"]`
- Handles environment setup internally via its entrypoint script
- Does **not** need `sudo` privileges — Dokku does not set `no_new_privileges`

Heroku ignores the `Dockerfile` entirely when the stack is `heroku-22` (buildpacks).

---

## Why Not Docker on Heroku?

Heroku's container stack (`"stack": "container"` + `heroku.yml`) was attempted but abandoned due to:
1. `no_new_privileges` security flag blocking `sudo` inside `thecodingmachine/php`'s startup scripts
2. Apache MPM conflicts (`mpm_event` vs `mpm_prefork`) when switching to `php:8.1-apache`
3. Buildpacks handle PHP + Node.js natively with zero configuration overhead

---

## Environment Variables

Key env vars configured in `app.json` for Heroku deployments:

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_KEY` | (generated) | Auto-generated secret |
| `APP_ENV` | `production` | |
| `NPM_CONFIG_PRODUCTION` | `false` | Allows devDependencies install during build |
| `TRUSTED_PROXIES` | `*` | Required behind Heroku's routing layer |
| `LOG_CHANNEL` | `errorlog` | Sends logs to `heroku logs` |

JawsDB MySQL addon is provisioned automatically via `app.json` `addons`. Laravel's `DB_*` variables must be parsed from `JAWSDB_URL` or set manually after provisioning.

