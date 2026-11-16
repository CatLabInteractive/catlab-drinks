# Walk-through baseline, November 2026

This file records the result of the walk-through in `docs/testing/frontend-walkthrough.md` on `main`, before the interface migration.
Later pull requests run the same list and compare their result with this file.

## Run

| Item | Value |
|------|-------|
| Commit | `e917bc4` (`main`, "Merge pull request #254 from CatLabInteractive/feature/nfc-simulator") |
| Date | 2026-11-16 |
| Browser | Google Chrome `157.0.8103.38`, headless, through Playwright `1.62.0` |
| Build | Development build (`npm run dev`), so that console errors show the real function names |
| Stack | `docker compose -p catlab-drinks-walk` with `tools/walkthrough/compose.override.yml`, PHP 8.5 image, MySQL 8.0, a new database |
| Script | `node tools/walkthrough/walkthrough.mjs` with the default values of the README |
| NFC simulator | `npm run nfc-simulator` on `http://127.0.0.1:3030` |

## Summary

| Mode | Items | Pass | Fail | Not conclusive | Not run |
|------|-------|------|------|----------------|---------|
| `auto` | 104 | 79 | 25 | 0 | 0 |
| `manual` | 19 | 9 | 2 | 2 | 6 |

- The `auto` results come from one script run on a new database. That run used the script of fix round 1. It has stricter checks and two new POS items.
- I did the `manual` items with ad-hoc Playwright steps on the same stack after the script run. Their screenshots are not in the repository.
- An item that shows "not run" was not done. The notes below tell why.
- "Not conclusive" means that the step ran but did not show the expected result for a reason outside the item.

## Causes of failures

The column "Cause" in the item list points to one of these codes.

| Code | Cause | Evidence |
|------|-------|----------|
| `K` | Known error of issue 251. Each click on a `b-form-checkbox` throws `assign is not a function`. Each change of a `b-form-select` throws `el[assignKey] is not a function`. | Page errors in `report.json` of each item with `K` |
| `P1` | Failure that existed before this work. Each Blade page with `layouts/app.blade.php` loads `/js/app.js`. The build makes no such file, so the request gets 404. | `GET /js/app.js` answers 404. Chrome logs the 404 and refuses the HTML answer as a script. |
| `P2` | Failure that existed before this work. The POS cannot save a sale at the bar. The offline queue sends `menuItem: { id, name }` for each product. The server answers 422 with `Linkable resources may not contain any other attributes.`. The queue tries again later, so the 422 shows up in later POS items too. | `POST /pos-api/v1/events/1/orders` answers 422. The body sent starts with `{"items":[{"location":"Manual","status":"processed","paid":true,...,"order":{"items":[{"menuItem":{"id":2,"name":"Walk Cola"},...`. The POS shows the alert `Could not decode resource.: Linkable resources may not contain any other attributes.`. `resources/shared/js/components/LiveSales.vue` adds the `name`. Charon `v1.12.0` rejects it. |
| `P3` | Failure that existed before this work. The POS screen `Financial overview` calls an API route that the Device API does not have. | `GET /pos-api/v1/organisations/1/financial-overview` answers 404 `The route ... could not be found.` |
| `P4` | Failure that existed before this work. The top-up page throws a page error. | Page error `EVENT_TOKEN is not defined`. The page itself shows "Online top-up is not yet available." as expected. |
| `P5` | Failure that existed before this work. The QR code image of `/qr-generator/code` answers 500. | `storage/logs`: `ErrorException: Array to string conversion at app/Http/Controllers/QrGenerator.php:43` |
| `P6` | If the balance is too low, the card service logs `InsufficientFundsException: Insufficient funds.` with `console.error`. The payment modal shows the error as expected. | Console error in `nfc-card-insufficient-funds` |
| `P7` | The QR generator page showed no QR code after a tap of a card. The page uses the base NFC reader and a stored NFC server. I did not find out why no card showed. | Ad-hoc step: `#cards .card` count 0 after the tap |

## Notes for the items

| Code | Note |
|------|------|
| `M1` | The link of `Open POS on this device` is `https://drinks.catlab.eu/connect?data=...`. The connect URL in Manage always points to the public instance. |
| `M2` | The badge `Offline` showed while the browser was offline and went away afterwards. The console showed network errors (`net::ERR_INTERNET_DISCONNECTED`, `AxiosError: Network Error`). They are expected while offline. |
| `M3` | The order page of a closed event showed the message of the server instead of the table. The API answered 423 on purpose, and Chrome logs that status as a console error. |
| `N1` | Not run. An active Pay.nl gateway sends the browser to a public host. |
| `N2` | Not conclusive. I stopped the database and opened `/`. `.env` has `APP_DEBUG=true`, so the debug page showed instead of `errors/database.blade.php`. The walk-through must not change `.env`. |
| `N3` | Not run. The banner needs `PRODUCTION_ORGANISATION_IDS` in the environment of the web server, and the walk-through must not change `.env`. The script records that the banner was not shown, which is right for an instance without that variable. |
| `N4` | Not run. The headless browser has no camera. |
| `N5` | Not run. The warning needs more scanned cards than the free limit of a device. |
| `N6` | Not run. Round robin needs a second paired POS. |
| `N7` | Not conclusive. `Start test` ran and the log showed `NoCardFoundException: No card found.`. The button `Stop test` did not show within 15 seconds. |
| `N8` | Not run. A signed order URL needs a card and a signature made outside the browser. |

Other observations:

- `nfc-card-first-tap` passes, but the first tap of a blank card shows `Corrupt card, contact support`. A new organisation has `min_nfc_version` 1. The script removes the card and taps it again, as the NFC simulator README describes.
- `pos-sale-cash` passes on screen, but its order is one of the orders that `P2` rejects. The 422 shows up in the next POS item, `pos-sale-vouchers`.
- `manage-transactions` fails because no card transaction is linked to an order. The card payment order is rejected by `P2`.
- `blade-register-closed` expects the 403 status of `/register`. `pos-unpaired` expects the 401 of the deleted device. The script moves those two console errors to the note.
- `manage-events-list` notes that the testing mode banner is not shown.
- In `pos-remote-orders-filters` the list followed the filter. The order with `Walk Fries` went away with the `Bar` filter and came back with `Kitchen`. The item fails only on the console errors `K` and `P2`.
- `blade-home` shows the welcome page, because `HomeController@index` renders the welcome view. The view `home.blade.php` is not used.

## Console errors and the items that showed them

| Console error | Items |
|---------------|-------|
| `pageerror: assign is not a function` (`K`) | `manage-events-edit`, `manage-devices-edit`, `pos-disabled-device`, `pos-remote-orders-filters` |
| `pageerror: el[assignKey] is not a function` (`K`) | `manage-settings-currency`, `manage-settings-gateway-add` |
| `Failed to load resource: ... 404 (Not Found) (/js/app.js)` and `Refused to execute script from '.../js/app.js'` (`P1`) | `blade-setup`, `blade-register-closed`, `blade-login-wrong-password`, `manage-logout`, `blade-login` (and `blade-daily-report` in the ad-hoc step) |
| `Failed to load resource: ... 422 (Unprocessable Content) (/pos-api/v1/events/1/orders)` (`P2`) | `pos-sale-vouchers`, `pos-sale-cancel-payment`, `pos-settings-live-remote`, `pos-settings-sync`, `pos-settings-logout-cancel`, `pos-remote-orders-accept`, `pos-remote-orders-decline`, `pos-remote-orders-filters`, `nfc-card-payment`, `nfc-card-insufficient-funds`, `pos-summary` |
| `pageerror: Request failed with status code 422` (`P2`) | The same items as the 422 line, except `pos-settings-sync` and `pos-settings-logout-cancel` |
| `Sync failed: AxiosError: Request failed with status code 422` (`P2`) | `pos-settings-sync` |
| `InsufficientFundsException: Insufficient funds.` (`P6`) | `nfc-card-insufficient-funds` |
| `Failed to load resource: ... 404 (Not Found) (/pos-api/v1/organisations/1/financial-overview)` and `pageerror: Request failed with status code 404` (`P3`) | `pos-financial-overview` |
| `pageerror: EVENT_TOKEN is not defined` (`P4`) | `blade-topup-not-available` |

## Requests to public hosts

The request guard aborted every request to a host that is not `localhost` or `127.0.0.1`.
In the script run, `hosts.json` lists these hosts: `localhost:8098` (1188 requests), `127.0.0.1:3030` (104 requests), and `fonts.googleapis.com` (134 requests, all aborted).
No item has a "blocked request to public host" note. The ad-hoc steps showed the same three hosts only.

## Result of each item

The items are in the sequence of the list.

| ID | Mode | Result | Cause |
|----|------|--------|-------|
| `blade-setup` | auto | fail | `P1` |
| `blade-getting-started` | auto | pass |  |
| `blade-register-closed` | auto | fail | `P1` |
| `blade-login-wrong-password` | auto | fail | `P1` |
| `blade-welcome` | auto | pass |  |
| `blade-topup-not-available` | auto | fail | `P4` |
| `blade-qr-generator` | auto | fail | `P5` |
| `blade-qr-generator-cards` | manual | fail (ad hoc) | `P7` |
| `blade-landing-pages` | auto | pass |  |
| `blade-docs` | auto | pass |  |
| `blade-home` | auto | pass |  |
| `blade-daily-report` | manual | fail (ad hoc) | `P1` |
| `blade-topup-form` | manual | not run | `N1` |
| `blade-database-error` | manual | not conclusive (ad hoc) | `N2` |
| `manage-logout` | auto | fail | `P1` |
| `blade-login` | auto | fail | `P1` |
| `manage-navbar-settings-dropdown` | auto | pass |  |
| `manage-language-toggle` | auto | pass |  |
| `manage-navbar-mobile` | auto | pass |  |
| `manage-org-switch` | manual | pass (ad hoc) |  |
| `manage-test-mode-banner` | manual | not run | `N3` |
| `manage-events-list` | auto | pass |  |
| `manage-events-create` | auto | pass |  |
| `manage-events-wizard-back-cancel` | auto | pass |  |
| `manage-events-toggle-open` | auto | pass |  |
| `manage-events-edit` | auto | fail | `K` |
| `manage-events-order-token` | auto | pass |  |
| `manage-events-actions-links` | auto | pass |  |
| `manage-events-client-order-form-tab` | manual | pass (ad hoc) |  |
| `manage-events-delete` | auto | pass |  |
| `manage-menu-add-products` | auto | pass |  |
| `manage-menu-selling-delete-undo` | auto | pass |  |
| `manage-menu-reset` | auto | pass |  |
| `manage-menu-leave-unsaved` | auto | pass |  |
| `manage-menu-reorder-drag` | auto | pass |  |
| `manage-menu-keyboard` | manual | pass (ad hoc) |  |
| `manage-menu-headings-rename` | auto | pass |  |
| `manage-menu-headings-reorder` | manual | pass (ad hoc) |  |
| `manage-menu-import-paste` | auto | pass |  |
| `manage-menu-import-cancel` | auto | pass |  |
| `manage-menu-import-copy-event` | auto | pass |  |
| `manage-menu-import-columns` | manual | pass (ad hoc) |  |
| `manage-attendees-edit` | auto | pass |  |
| `manage-attendees-paste-dialog` | auto | pass |  |
| `manage-attendees-delete-all` | manual | pass (ad hoc) |  |
| `manage-summary` | auto | pass |  |
| `manage-summary-names` | auto | pass |  |
| `manage-summary-detail-modal` | auto | pass |  |
| `manage-sales-history` | auto | pass |  |
| `manage-transactions` | auto | fail | `P2` |
| `manage-card-details-topup` | auto | pass |  |
| `manage-financial-overview` | auto | pass |  |
| `manage-cards` | auto | pass |  |
| `manage-test-transactions` | auto | pass |  |
| `manage-hello` | auto | pass |  |
| `manage-settings-currency` | auto | fail | `K` |
| `manage-settings-gateway-add` | auto | fail | `K` |
| `manage-settings-gateway-edit-delete` | auto | pass |  |
| `manage-devices-list` | auto | pass |  |
| `manage-devices-pair-cancel` | auto | pass |  |
| `manage-devices-pair` | auto | pass |  |
| `manage-devices-open-pos-button` | manual | pass (ad hoc) | `M1` |
| `manage-devices-approve-key` | auto | pass |  |
| `manage-devices-signed-cards` | auto | pass |  |
| `manage-devices-license` | auto | pass |  |
| `manage-devices-buy-license-link` | auto | pass |  |
| `manage-devices-edit` | auto | fail | `K` |
| `manage-devices-revoke-key` | auto | pass |  |
| `manage-devices-delete` | auto | pass |  |
| `manage-public-keys-empty` | auto | pass |  |
| `manage-public-keys-list` | auto | pass |  |
| `manage-public-keys-approve` | auto | pass |  |
| `pos-pair-qr-scanner` | manual | not run | `N4` |
| `pos-language-toggle` | auto | pass |  |
| `pos-offline-badge` | manual | pass (ad hoc) | `M2` |
| `pos-license-warning` | manual | not run | `N5` |
| `pos-events` | auto | pass |  |
| `pos-menu` | auto | pass |  |
| `pos-hq` | auto | pass |  |
| `pos-sale-cash` | auto | pass |  |
| `pos-sale-vouchers` | auto | fail | `P2` |
| `pos-sale-cancel-payment` | auto | fail | `P2` |
| `pos-remote-orders-accept` | auto | fail | `P2` |
| `pos-remote-orders-decline` | auto | fail | `P2` |
| `pos-remote-orders-filters` | auto | fail | `K, P2` |
| `pos-remote-orders-round-robin` | manual | not run | `N6` |
| `pos-settings-live-remote` | auto | fail | `P2` |
| `pos-settings-sync` | auto | fail | `P2` |
| `pos-settings-logout-cancel` | auto | fail | `P2` |
| `pos-disabled-device` | auto | fail | `K` |
| `pos-unpaired` | auto | pass |  |
| `pos-authenticate-manual-entry` | auto | pass |  |
| `pos-summary` | auto | fail | `P2` |
| `pos-sales-history` | auto | pass |  |
| `pos-transactions` | auto | pass |  |
| `pos-financial-overview` | auto | fail | `P3` |
| `pos-test-transactions` | auto | pass |  |
| `pos-hello` | auto | pass |  |
| `pos-attendees` | auto | pass |  |
| `nfc-settings-connect` | auto | pass |  |
| `nfc-key-generate` | auto | pass |  |
| `nfc-reader-ready` | auto | pass |  |
| `nfc-card-first-tap` | auto | pass |  |
| `nfc-card-topup-default` | auto | pass |  |
| `nfc-card-topup-custom` | auto | pass |  |
| `nfc-card-remove-retap` | auto | pass |  |
| `nfc-card-aliases` | auto | pass |  |
| `nfc-card-discount` | auto | pass |  |
| `nfc-card-payment` | auto | fail | `P2` |
| `nfc-card-insufficient-funds` | auto | fail | `P2, P6` |
| `nfc-checkin` | auto | pass |  |
| `nfc-cards-transactions-link` | auto | pass |  |
| `nfc-card-reset` | auto | pass |  |
| `nfc-card-rebuild` | auto | pass |  |
| `nfc-key-revoked-regenerate` | auto | pass |  |
| `nfc-test-transactions` | manual | not conclusive (ad hoc) | `N7` |
| `client-order-load` | auto | pass |  |
| `client-order-empty` | auto | pass |  |
| `client-order-place` | auto | pass |  |
| `client-order-place-second` | auto | pass |  |
| `client-language-toggle` | auto | pass |  |
| `client-order-signed-url` | manual | not run | `N8` |
| `client-order-closed-event` | manual | pass (ad hoc) | `M3` |
