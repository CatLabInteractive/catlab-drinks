# Opt-out usage and error reporting

Self-hosted instances report back to CatLab. Both switches are **on by default**;
the owner sets them to `false` to opt out.

| Env var | Effect |
|---------|--------|
| `USAGE_REPORTING=false` | Stops the daily anonymous heartbeat to CatLab's Matomo |
| `ERROR_REPORTING=false` | Stops errors going to CatLab's Errbit/Airbrake project (the instance's own `AIRBRAKE_*` project also replaces CatLab's) |
| `USAGE_REPORTING_MATOMO_URL` / `USAGE_REPORTING_MATOMO_SITE_ID` | Override the Matomo destination |

- `Telemetry::isOn()` resolves a switch. Unset means on, except when `APP_ENV` is
  `local` or `testing` (`Telemetry::QUIET_ENVIRONMENTS`), so development machines and
  test runs do not report. An explicit `true` turns it on there too.
- CatLab's default destinations are constants in `App\Support\Telemetry`.
  While they are `null`, nothing is sent and no notice is shown.

## Footer notice
Because reporting is opt-out, the owner must be able to see it. `Telemetry::notice()`
returns `{ usage, errors }`: what the instance reports to CatLab right now.
- `resources/views/blocks/telemetry-notice.blade.php` renders it in the footer of
  `layouts/app.blade.php` (login, register, setup, home).
- `client/manage.blade.php` passes it as `CATLAB_DRINKS_CONFIG.TELEMETRY`; the footer of
  Manage's `App.vue` renders it (translated in `resources/shared/js/i18n/`).
- POS and the client order pages show no notice: their users are not the owner.
- The notice links to the readme section that explains how to opt out.

## Usage heartbeat
- `App\Services\UsageReporter::collect()` is the single source of what is sent:
  random instance UUID (hashed into Matomo's visitor id), commit, PHP/Laravel
  version, DB driver, hosting (heroku/docker/other), SSO yes/no, and counts
  (organisations, users, devices, events, cards, orders in 30 days, events with
  orders in 30 days). Never names, emails, menu items, amounts or URLs.
  Update the readme table when changing it.
- Sent as one Matomo bulk tracking request: a `heartbeat` pageview plus one
  event per value (`instance` category: value in event name; `usage` category:
  value in event value). Events only, so the Matomo site needs no custom dimensions.
- Triggers: `ReportUsage` terminable middleware on the `web`/`api` groups (Heroku
  and Dokku run no scheduler) and `drinks:report-usage` scheduled daily.
  A file-cache marker limits the DB check to once an hour per process;
  `UsageReporter::claim()` does an atomic conditional UPDATE on
  `instance_properties.usage_last_reported_at` so concurrent requests/dynos send once.
- `php artisan drinks:report-usage --dry-run` prints the payload.
- `instance_properties` is a generic key/value table for instance-wide state
  (the filesystem is ephemeral on Heroku/Dokku).

## Error reporting
- `Telemetry::airbrakeProject()` (read from `config/airbrake.php` and
  `config/logging.php`) picks the instance's own `AIRBRAKE_*` project first,
  then CatLab's unless `ERROR_REPORTING` is off. When CatLab's project is used, a
  key blocklist strips passwords, tokens, secrets, emails and card data.
- The same config feeds the frontend Airbrake client via `blocks/airbrake.blade.php`.
- Errbit can open a GitHub issue per error group, so no GitHub token lives in
  self-hosted installs.
