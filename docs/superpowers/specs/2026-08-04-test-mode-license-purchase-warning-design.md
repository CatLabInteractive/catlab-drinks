# Test-mode warning before license purchase

**Date:** 2026-08-04
**Status:** Approved

## Background

On a shared instance, organisations not listed in `PRODUCTION_ORGANISATION_IDS` run in
testing mode (`Organisation::getTestModeAttribute()`); the Manage SPA receives this as
`window.ORGANISATION_TEST_MODE` at boot (false on private instances, whose whitelist is
empty). The Manage Devices view's "Buy License" action
(`resources/manage/js/views/Devices.vue:103`) is a plain `href` that navigates directly
to the accounts license shop (`buyLicenseUrl()`, line ~420).

## Requirement

When the current organisation is in testing mode, clicking "Buy License" shows a warning
modal before navigating to the purchase page. Production-mode organisations keep the
current direct navigation. Purchasing stays allowed either way — this is a warning, not
a block. No backend changes.

## Design

All changes in `resources/manage/js/views/Devices.vue` (+ i18n files):

- The "Buy License" dropdown item becomes `@click="buyLicense(row.item)"` (drop the
  `:href`).
- `buyLicense(device)`: if `!window.ORGANISATION_TEST_MODE` → `window.location.href =
  this.buyLicenseUrl(device)` (same-tab, as today). Else stow the device in
  `buyLicenseDevice` and show the warning modal (`ref="buyLicenseWarningModal"`).
- New `b-modal` titled `$t('Testing mode')` with body:
  - `$t('Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.')`
  - a link to `https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance`
    labelled with the existing key `$t('How to set up your own instance')` (target
    `_blank`, `rel="noopener"`).
  - `$t('Are you sure you want to continue to the license purchase?')`
  - Footer: cancel button (`$t('Cancel')`, existing key) hides the modal;
    `$t('Continue to purchase')` navigates to `buyLicenseUrl(buyLicenseDevice)`.
- New i18n keys (added to all five locale files, which enumerate every key):
  `'Testing mode'`, the warning sentence, the confirmation sentence,
  `'Continue to purchase'`. `'How to set up your own instance'` and `'Cancel'` exist.

## Testing

No new JS unit tests: this is view-glue in a component with no component-test harness,
and extracting a one-line boolean check into a module for testability is not worth it.
Verification = `npm run dev` build + existing suites stay green (vitest, jest, phpunit
untouched).

## Out of scope

- Backend enforcement or purchase blocking.
- POS app, private instances, the Enter License flow (manual paste stays untouched).
