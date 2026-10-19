# Opt-in usage and error reporting

Self-hosted instances can opt in to report back to CatLab. Both switches are
**off by default**; nothing leaves the instance unless the owner sets them.

| Env var | Effect |
|---------|--------|
| `USAGE_REPORTING=true` | Daily anonymous heartbeat to CatLab's Matomo |
| `ERROR_REPORTING=true` | Errors go to CatLab's Errbit/Airbrake project (unless the instance sets its own `AIRBRAKE_*`) |
| `USAGE_REPORTING_MATOMO_URL` / `USAGE_REPORTING_MATOMO_SITE_ID` | Override the Matomo destination |

CatLab's default destinations are constants in `App\Support\Telemetry`.
While they are `null`, opting in is a silent no-op.

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
  then CatLab's when `ERROR_REPORTING` is on. When CatLab's project is used, a
  key blocklist strips passwords, tokens, secrets, emails and card data.
- The same config feeds the frontend Airbrake client via `blocks/airbrake.blade.php`.
- Errbit can open a GitHub issue per error group, so no GitHub token lives in
  self-hosted installs.
