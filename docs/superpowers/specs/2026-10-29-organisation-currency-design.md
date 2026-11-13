# Organisation currency — design

**Date:** 2026-10-29
**Scope:** one new setting on organisations, exposed through the Management API, the
Device API and the public order API, and used wherever the three frontends or the
backend render an amount of money.

## Intent

Every amount in CatLab Drinks is labelled with a hardcoded euro sign. Nobody outside the
eurozone uses the hosted instance yet, but the project is open source and meant to be
forked: a US or UK organisation that installs it sees "€" on every price, every card
balance and every top-up page, and has no way to change it.

Money itself has no currency anywhere in the system: menu prices and order items are
plain decimals, card balances and transactions are plain integer cents, and the NFC card
payload carries no currency byte. So this is a labelling problem, not a data-model
problem. An organisation picks its currency once; everything that shows an amount uses
that code to format it; the one external payment call sends it instead of `EUR`.

## Goals

- An organisation can pick its currency in Manage and every amount shown to staff
  (Manage, POS) and to guests (public order page, card top-up pages) is labelled with it.
- Existing organisations see no change: the default is EUR and the default formatting
  for the existing locales is what people see today, modulo the Intl conventions
  described below.
- Online top-ups through Pay.nl are created in the organisation's currency.
- No hardcoded currency symbol remains in the three Vue apps or the backend views and
  translation files. A grep for `€` in `resources/` should only hit the marketing pages
  (see non-goals) and one code comment in `nfccards/models/Card.ts` that quotes a cent
  limit as a euro amount.

## Non-goals

- Per-event currencies, several currencies inside one organisation, or any conversion.
- Storing a currency on orders, order items, transactions or cards. Changing the setting
  relabels historical amounts; this is accepted and stated in the UI.
- Changing the NFC card format.
- Rewriting amounts that today appear as bare numbers without a symbol (menu tables,
  order details, card balance badge, financial overview, daily report, plain-text
  receipt). They stay bare numbers. This change swaps symbols, it does not redesign
  tables.
- The marketing pages (`resources/views/landing/*`, `resources/views/whitepaper.blade.php`,
  the `welcome.php` translation files). Their "€0.10 NFC tags" is the price of a tag on
  the European market, not the organisation's currency.

## Anti-goals

- Free-form symbol or "position" fields. The setting is a currency code; symbol,
  position and separators are derived from it and the interface language.
- Formatting code duplicated per component. One shared formatter, used everywhere.
- Blocking the guest order page or the POS when the currency cannot be fetched. Both
  fall back to the default and keep working.

## Decisions already made

### The setting

- `organisations.currency`: `string(3)`, not null, default `'EUR'`. Migration fills
  existing rows via the column default. The Eloquent model declares the same default in
  `$attributes` so a freshly constructed organisation (the `User::created` hook, the
  setup controller, the profile mirror) is `EUR` before saving.
- Value is an ISO 4217 alphabetic code: exactly three ASCII uppercase letters. The API
  rejects anything else with a 422 from `OrganisationController::beforeSaveEntity`, next
  to the organisation name guard. (Charon ships no regex or length validator, only
  exists / in-array / min / max / type, so this is the place.) No check against a list
  of "real" codes: both the browser and PHP's intl format an unknown but well-formed code
  as `XYZ 2.50`, which is harmless. Charon writes fields by property assignment, so
  `Organisation::$fillable` does not need the new column.
- Amounts always show two decimals, whatever the currency. Every stored amount in the
  system is a two-decimal quantity (prices are decimals, balances are integer cents, the
  Pay.nl amount is sent with two decimals), so the label must equal the stored number.
  Letting Intl pick the currency's own minor unit would round `150.50` to `¥151` on
  screen while the card still holds 15050 cents, and would print `KWD 150.500`. A yen
  organisation therefore sees `¥500.00`; cosmetic, and honest. Both the JS and the PHP
  formatter pin `minimumFractionDigits` and `maximumFractionDigits` to 2.
- Writeable by any member of the organisation through the existing
  `PUT /api/v1/organisations/{id}` (`OrganisationPolicy::edit`, unchanged). Profile-linked
  organisations (`profile_id` set) can change it too: the name is canonical on CatLab
  Accounts, the currency is local.
- Always changeable, also after sales exist. The settings page says so in one sentence.

### Where each app gets it

- **Manage** already loads the user's organisations at boot
  (`resources/manage/js/app.js`, `GET /api/v1/users/me`) and keeps them in
  `window.ORGANISATIONS`. Add `currency` to
  `ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition` (visible,
  writeable) and the boot code sets the formatter's currency from the selected
  organisation, next to where it sets `ORGANISATION_TEST_MODE`.
- **POS** receives the organisation embedded in `GET /pos-api/v1/devices/current`
  (`DeviceApi/V1/ResourceDefinitions/OrganisationResourceDefinition`, today only `id` and
  `name`). Add `currency` there, read-only. The device payload is cached for offline
  boot, so the currency is available offline for free. The separate organisation fetch
  that runs only when NFC is configured is not the right place: it does not run on
  devices without a reader.
- **Guest order page** (`resources/clients/`). The SPA only calls `public/menu.json`,
  but the Blade shell that serves it, `OrderController::view` rendering
  `resources/views/order/index.blade.php`, already resolves the event from the order
  token and emits inline globals (`EVENT_TOKEN`, `ORDER_SIGNATURE`, `ORDER_CARD_TOKEN`,
  `ORDER_NAME`) that `resources/clients/js/bootstrap.js` depends on. Add one more:
  `var EVENT_CURRENCY = @json($event->organisation->currency);`. The client boot reads
  it with the same `typeof` guard as the others and calls `setCurrency`; when it is
  missing the formatter keeps its EUR default. No new API endpoint, no extra request, no
  race with the menu call.

### Formatting in the browser

- One shared module, e.g. `resources/shared/js/utils/money.js`, holding the current
  currency (module state, default `EUR`, `setCurrency(code)` / `getCurrency()`) and
  exposing `formatMoney(amount)` built on `Intl.NumberFormat` with
  `{ style: 'currency', currency }` and the active interface locale from
  `resources/shared/js/i18n/index.js` (`getLocale()`). The locale is the user's language,
  not the organisation's: a Dutch volunteer in a US organisation sees `US$ 2,50`, an
  English one sees `$2.50`. This was discussed and accepted.
- `formatMoney` always passes `minimumFractionDigits: 2, maximumFractionDigits: 2` (see
  "The setting"), returns `''` for `null` / `undefined` / `NaN`, and when the browser
  throws on the code (RangeError) it falls back to `CODE 2.50`.
- A `currencySymbol()` helper (from `formatToParts`, part type `currency`) for the one
  place that needs a symbol on its own: the voucher-value input prefix in
  `resources/manage/js/components/EventForm.vue`.
- A `listCurrencies(locale)` helper returning `[{ code, name }]` sorted by name, built
  from `Intl.supportedValuesOf('currency')` and `Intl.DisplayNames(locale,
  { type: 'currency' })`. Where `supportedValuesOf` is missing (older Safari), fall back
  to a short built-in list (EUR, USD, GBP, CHF, SEK, NOK, DKK, PLN, CZK, CAD, AUD) so the
  picker still works.
- Both Manage and POS already define `app.config.globalProperties.$filters` with a
  `formatDate`; add `money` there (and create the same object in the client app) so
  templates write `$filters.money(x)`. Shared components are compiled into both apps,
  so both apps must define it.

### Every hardcoded euro and what replaces it

Frontend (`€` becomes `$filters.money(...)` unless stated otherwise):

- `resources/shared/js/components/SalesSummary.vue` — six cells (price, net, VAT, total,
  and the two footer totals).
- `resources/shared/js/components/LiveSales.vue` — two uses of the translation key
  `'Total: {amount} items = €{price}'`. The key becomes
  `'Total: {amount} items = {price}'` with `price` pre-formatted; the old key is removed
  from all five locale files and the new one added.
- `resources/shared/js/components/Card.vue` — the top-up amount tiles and the confirm
  dialog key `'Are you sure you want to topup for €{amount}?'`, which becomes
  `'Are you sure you want to topup for {amount}?'` with `amount` pre-formatted. Same
  five-locale treatment.
- `resources/shared/js/components/PaymentPopup.vue` — `cashAmount = '€' + amount`.
- `resources/shared/js/services/PaymentService.js` — drop `currencySymbol` and make
  `visualiseCurrency` delegate to the shared formatter (it is what the remote-orders
  screens use).
- `resources/manage/js/components/EventForm.vue` — `prepend="€"` on the voucher input
  becomes the current currency symbol. Placeholder `2,50` stays.
- `resources/clients/js/views/Order.vue` — the price under each menu item.

Backend:

- `app/Http/Controllers/TopupController::getParameters` — `'currency' => 'EUR'` becomes
  the card's organisation currency.
- Add a small money formatter for Blade, e.g. `App\Support\Money::format(float $amount,
  string $currency, ?string $locale = null)`: uses `NumberFormatter` (`ext-intl`, present
  in the Dokku image) with the current app locale and `FRACTION_DIGITS` pinned to 2;
  when the class does not exist, falls back to `CODE 2.50`. Add `ext-intl` to
  `composer.json` `require` so a Heroku build enables it (this touches `composer.lock`
  only in its platform section; verify with `composer validate` and
  `composer update --lock`). The test environments do not have it yet: add `intl` to the
  `extensions` list in `.github/workflows/tests.yml` and `PHP_EXTENSION_INTL: "1"` to the
  `phpunit` service in `docker-compose.yml`, otherwise `composer install` refuses the
  platform requirement there.
- `resources/views/topup/topupForm.blade.php` — `€ {{ $balance }}` becomes the formatted
  balance; the controller passes already-formatted strings for `balance` and `minTopup`
  (the `max` / `min` attributes of the number input keep the raw numbers).
- `resources/lang/{en,nl,fr,de,es}/topup.php` — `choose_amount` loses its literal euro
  in all five languages and receives a pre-formatted `:min`. `payment_success` already
  takes `:amount`; the controller passes it formatted.
- `resources/views/topup/status.blade.php` — `$topup->amount` formatted via the helper.

Untouched on purpose: `resources/views/reports/daily.blade.php`,
`resources/shared/js/utils/ReceiptPrinter.ts`, `resources/shared/js/nfccards/tools/VisibleAmount.ts`,
`OrderDetails.vue`, `NfcCardBalance.vue`, `FinancialOverview.vue`, both `Menu.vue`
tables. All show bare numbers today and keep doing so.

### The settings screen

In `resources/manage/js/views/OrganisationSettings.vue`, above "Payment Gateways":

- Heading "Currency".
- One `b-form-select` listing `listCurrencies(locale)` as "Euro (EUR)", "US Dollar
  (USD)", …, preselected to the organisation's current code. If the current code is not
  in the list (older browser fallback), it is appended so the select never shows blank.
- A muted sentence: "Used to label prices, balances and top-ups. Changing it relabels
  past sales too; no amounts are converted."
- A Save button that calls `OrganisationService.update(id, { currency })`, updates the
  entry in `window.ORGANISATIONS`, calls `setCurrency`, and shows the usual spinner /
  success state used elsewhere on the page. No page reload needed.
- All new strings go through `$t()` with entries in the five locale files
  (`resources/tests/i18n-completeness.test.js` enforces this).

### Documentation

- `CLAUDE.md`, "Common Patterns": one bullet saying amounts are formatted through the
  shared money helper and the organisation's currency; never hardcode a symbol.
- `.ai/` gets no new file; this spec is the reference.

## Testing

PHP (`tests/Feature/OrganisationCurrencyTest.php`, `RefreshDatabase`):

- A new organisation has `currency = 'EUR'` and `/api/v1/users/me` exposes it.
- A member can `PUT /api/v1/organisations/{id}` with `{ currency: 'USD' }`; the response
  and the database reflect it.
- `usd`, `EURO`, `E€`, `''` are rejected with 422 and the stored value is unchanged.
- A profile-linked organisation can change its currency (the name guard does not
  interfere).
- `GET /pos-api/v1/devices/current` (device token) includes `organisation.currency`.
- `GET /order/{token}` for an event whose organisation uses USD renders the
  `EVENT_CURRENCY` global with `"USD"`.
- The Pay.nl purchase parameters use the organisation's currency (make `getParameters`
  reachable from a test, or assert through a fake gateway, builder's choice).
- `App\Support\Money::format` for EUR/nl, USD/en, JPY (two decimals kept), and the
  no-intl fallback.

JavaScript (`vitest`, `resources/tests/`):

- `money.test.js`: `formatMoney` for EUR with `nl` and `en`, USD with `en`, JPY (keeps
  two decimals), `null`, `NaN`, and an invalid code; `currencySymbol`; `listCurrencies`
  returns sorted `{ code, name }` pairs and includes the current code.
- The existing `i18n-completeness` test stays green with the renamed keys.
- A source-level test in the style of `offline-support.test.js` asserting the Manage and
  POS boot files call `setCurrency` from the organisation payload, and that no `.vue`
  file under `resources/` still contains a literal `€`.

## Left to the builder

- Exact names and placement of the JS module and the PHP helper.
- Whether `$filters.money` or a mixin is the nicer way to reach the formatter from
  templates, as long as it is one shared implementation.
- The visual treatment of the Save button and feedback on the settings page, matching
  what the page already does for payment gateways.
