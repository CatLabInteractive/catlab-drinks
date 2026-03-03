# Deployment — Heroku & Dokku

## Overview

The app supports three deployment targets that coexist in the same repo without conflict:

| Target | Build method | Web process |
|--------|-------------|-------------|
| **Heroku** | Buildpacks (`heroku/nodejs` + `heroku/php`) | `heroku-php-apache2 public/` |
| **DigitalOcean App Platform** | `Dockerfile` | `apache2-foreground` (via `run_command`) |
| **Dokku** | `Dockerfile` | `apache2-foreground` (via image entrypoint) |

---

## Key Files

| File | Purpose |
|------|---------|
| `Procfile` | Web process + release task for both Heroku and Dokku |
| `app.json` | Heroku "Deploy to Heroku" button config (stack, buildpacks, env vars, addons) |
| `.do/deploy.template.yaml` | DigitalOcean "Deploy to DigitalOcean" button config |
| `Dockerfile` | Dokku + DigitalOcean build (uses `thecodingmachine/php:8.1-v5-slim-apache`) |
| `heroku.yml` | **Deleted** — not used; would conflict with buildpack stack |

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

- `"buildpacks"` — declares `heroku/nodejs` first, then `heroku/php`. Order matters (see below). This also prevents Heroku from auto-detecting the `Dockerfile` and switching to the container stack.
- No `"stack"` property needed — the `buildpacks` array is sufficient to prevent Docker auto-detection, and omitting `stack` lets Heroku use its current default stack.
- `"scripts": { "postdeploy": ... }` — **not used** (unsupported on container stack; migrations run via `Procfile release:` instead).
- The `buildpacks` array in `app.json` only takes effect when creating a new app via the button or `heroku create`. For existing apps, buildpacks must be set manually via CLI.

## DigitalOcean App Platform

Config lives in `.do/deploy.template.yaml` — this is the filename DigitalOcean specifically looks for when the "Deploy to DigitalOcean" button is clicked. It uses `git.repo_clone_url` (public HTTPS clone URL) and has a `spec:` root wrapper, which differs from the `doctl` CLI app format.

- Uses the **Dockerfile** to build (same as Dokku)
- `run_command` overrides the container's default CMD: runs `php artisan migrate --force && apache2-foreground`, so migrations happen automatically on every deploy
- **No inline database** — DigitalOcean's app spec `databases` field only supports dev-tier PostgreSQL; MySQL requires a managed cluster. Users must create a [Managed MySQL cluster](https://cloud.digitalocean.com/databases) separately and provide `DATABASE_URL` as a secret
- `DATABASE_URL` is parsed in `config/database.php` (same parser as Heroku's `JAWSDB_URL`)
- `APP_KEY` must be set manually as a secret during the deploy wizard — DigitalOcean has no equivalent of Heroku's `generator: secret`

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
| `APP_DEBUG` | `false` | Must be explicit — security risk if left as `true` |

`TRUSTED_PROXIES` defaults to `*` in `TrustProxies.php` and does not need to be set.
| `LOG_CHANNEL` | `errorlog` | Sends logs to `heroku logs` |

JawsDB MySQL addon is provisioned automatically via `app.json` `addons`. Laravel's `DB_*` variables must be parsed from `JAWSDB_URL` or set manually after provisioning.

