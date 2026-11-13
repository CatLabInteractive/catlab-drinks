# Organisation Currency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an organisation pick its currency once and have every amount in Manage, POS, the guest order page and the card top-up pages labelled with it, with Pay.nl top-ups created in that currency.

**Architecture:** One `currency` column on `organisations` (ISO 4217 code, default `EUR`) exposed read/write on the Management API organisation resource, read-only on the Device API organisation resource embedded in the device payload, and as an inline `EVENT_CURRENCY` global on the guest order page shell. One shared JS module built on `Intl.NumberFormat` replaces every hardcoded `€` in the three Vue apps; one PHP helper built on `NumberFormatter` does the same for the Blade top-up pages. Nothing stored changes meaning; amounts always render with two decimals.

**Tech Stack:** Laravel 12 / PHP 8.5 (`ext-intl`), CatLab Charon resource definitions, Vue 3 compat + Bootstrap-Vue, vue-i18n, Vitest (jsdom), PHPUnit via `docker compose`.

**Spec:** `docs/superpowers/specs/2026-10-29-organisation-currency-design.md`

## Global Constraints

- Default currency is `EUR`; existing organisations and freshly created ones must read `EUR` without any data fix.
- A currency is valid only when it matches `/^[A-Z]{3}$/`; anything else is a 422 from the API and ignored by the JS setter.
- Amounts always show exactly two fraction digits, in JS and PHP, whatever the currency (`¥500.00`, never `¥500`).
- The display locale is the user's interface language (`getLocale()` from `resources/shared/js/i18n/index.js`, `app()->getLocale()` in PHP), never the organisation's.
- Every new or renamed `$t()` key gets an entry in all five locale files `resources/shared/js/i18n/{en,nl,fr,de,es}.js`; `resources/tests/i18n-completeness.test.js` enforces it.
- Spots that show a bare number today (menu tables, `OrderDetails.vue`, `NfcCardBalance.vue`, `FinancialOverview.vue`, `reports/daily.blade.php`, `ReceiptPrinter.ts`, `VisibleAmount.ts`) stay bare. Marketing pages and `welcome.php` translations stay untouched.
- Never run `npm update`; after `npm install` run `git checkout -- package-lock.json` before committing.
- PHP tests run in docker: `docker compose --profile test run --rm -e PHP_EXTENSION_BCMATH=1 -e PHP_EXTENSION_GD=1 -e PHP_EXTENSION_INTL=1 phpunit <args>` (called `$PHPUNIT` below). Composer in the same container: replace `phpunit <args>` with `--entrypoint composer phpunit <args>`.
- Intl output uses U+00A0 between symbol and number for some locales; tests normalise with `.replace(/ /g, ' ')` (JS) / `str_replace("\u{a0}", ' ', ...)` (PHP) before comparing.
- Every commit message ends with these two trailers:
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01WpWbfA53pyJSMJ5KCVrNbt
  ```
- Run `git branch --show-current` before each commit; it must print `feature/organisation-currency`.

## Review Focus

1. A code sent in lowercase or with whitespace (`usd`, ` USD`) must be rejected with 422, not silently normalised. Pinned in Task 1.
2. A POS that boots offline from a device payload cached before this change has no `organisation.currency`; `setCurrency(undefined)` must leave the formatter on `EUR`. Pinned in Task 5.
3. A browser without `Intl.supportedValuesOf` must still show a usable currency picker that contains the organisation's current code. Pinned in Task 5.
4. Prices arrive from the API as numeric strings in places; `formatMoney('2.5')` must format like a number, and `null`/`NaN` must render as an empty string rather than `NaN`. Pinned in Task 5.
5. A zero-decimal currency (JPY) must keep two decimals in both the JS and PHP formatter so the label equals the stored amount. Pinned in Tasks 3 and 5.

---

### Task 1: Currency column, model default and Management API field

**Files:**
- Create: `database/migrations/2026_10_29_130000_add_currency_to_organisations.php`
- Modify: `app/Models/Organisation.php:38-40`
- Modify: `app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php:46-52`
- Modify: `app/Http/ManagementApi/V1/Controllers/OrganisationController.php:78-88`
- Test: `tests/Feature/OrganisationCurrencyTest.php`

**Interfaces:**
- Produces: `organisations.currency` (string(3), default `'EUR'`); `Organisation::isValidCurrencyCode(?string $code): bool`; `currency` field on `GET /api/v1/users/me` (`organisations.items.*.currency`) and on `GET|PUT /api/v1/organisations/{id}`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/OrganisationCurrencyTest.php` (namespace `Tests\Feature`, `use RefreshDatabase`, copy the `makeUser()` helper from `tests/Feature/OrganisationNameGuardTest.php`):

```php
public function testNewOrganisationDefaultsToEuro(): void
{
    $user = $this->makeUser();
    $organisation = $user->organisations()->first();
    $this->assertSame('EUR', $organisation->currency);

    Passport::actingAs($user);
    $this->getJson('/api/v1/users/me')
        ->assertStatus(200)
        ->assertJsonPath('organisations.items.0.currency', 'EUR');
}

public function testMemberCanChangeCurrency(): void
{
    $user = $this->makeUser();
    $organisation = $user->organisations()->first();

    Passport::actingAs($user);
    $this->putJson('/api/v1/organisations/' . $organisation->id, ['currency' => 'USD'])
        ->assertStatus(200)
        ->assertJsonPath('currency', 'USD');
    $this->assertSame('USD', $organisation->fresh()->currency);
}

public function testInvalidCurrencyCodesAreRejected(): void
{
    $user = $this->makeUser();
    $organisation = $user->organisations()->first();
    Passport::actingAs($user);

    foreach (['usd', 'EURO', 'E€', '', ' USD'] as $code) {
        $this->putJson('/api/v1/organisations/' . $organisation->id, ['currency' => $code])
            ->assertStatus(422);
        $this->assertSame('EUR', $organisation->fresh()->currency, "Code '{$code}' was stored");
    }
}

public function testLinkedOrganisationCanStillChangeCurrency(): void
{
    $user = $this->makeUser();
    $organisation = $user->organisations()->first();
    $organisation->profile_id = 501;
    $organisation->save();

    Passport::actingAs($user);
    $this->putJson('/api/v1/organisations/' . $organisation->id, ['currency' => 'GBP'])
        ->assertStatus(200);
    $this->assertSame('GBP', $organisation->fresh()->currency);
    $this->assertSame('Test User', $organisation->fresh()->name);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `$PHPUNIT --filter OrganisationCurrencyTest`
Expected: FAIL (`currency` attribute is null / unknown column).

- [ ] **Step 3: Add the migration**

`database/migrations/2026_10_29_130000_add_currency_to_organisations.php`, modelled on `2026_03_03_103500_add_min_nfc_version_to_organisations.php`: `$table->string('currency', 3)->default('EUR')->after('name');` in `up()`, `dropColumn('currency')` in `down()`. No data update needed; the default fills existing rows.

- [ ] **Step 4: Add the model default and validator**

In `app/Models/Organisation.php` add `protected $attributes = ['currency' => 'EUR'];` next to `$fillable`, and:

```php
public static function isValidCurrencyCode(?string $code): bool
```
returning true only for `preg_match('/^[A-Z]{3}$/', $code) === 1`.

- [ ] **Step 5: Expose the field and guard it**

In `OrganisationResourceDefinition::__construct` add after `name`:
```php
$this->field('currency')->string()->visible(true)->writeable(true, true);
```
In `OrganisationController::beforeSaveEntity`, after the name guard:
```php
if ($entity->isDirty('currency') && !Organisation::isValidCurrencyCode($entity->currency)) {
    abort(422, 'Currency must be a three-letter ISO 4217 code, e.g. EUR.');
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `$PHPUNIT --filter OrganisationCurrencyTest`
Expected: 4 tests PASS. Also run `$PHPUNIT --filter 'OrganisationNameGuardTest|OrganisationTestModeTest'` — still PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_10_29_130000_add_currency_to_organisations.php app/Models/Organisation.php app/Http/ManagementApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php app/Http/ManagementApi/V1/Controllers/OrganisationController.php tests/Feature/OrganisationCurrencyTest.php
git commit -m "Add a currency setting to organisations"
```

---

### Task 2: Currency for the POS payload and the guest order page shell

**Files:**
- Modify: `app/Http/DeviceApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php:17-25`
- Modify: `app/Http/Controllers/OrderController.php:63-72`
- Modify: `resources/views/order/index.blade.php:12-18`
- Test: `tests/Feature/OrganisationCurrencyTest.php`

**Interfaces:**
- Produces: `organisation.currency` in `GET /pos-api/v1/devices/current`; a global `var EVENT_CURRENCY = "<code>";` in the HTML of `GET /order/{token}` (consumed by Task 6).

- [ ] **Step 1: Write the failing tests**

Append to `OrganisationCurrencyTest` (device token setup copied from `tests/Feature/DeviceControllerTest.php:28-50`):

```php
public function testDeviceApiExposesOrganisationCurrency(): void
{
    $organisation = Organisation::factory()->create(['currency' => 'USD']);
    $device = Device::factory()->create(['organisation_id' => $organisation->id]);
    $user = $this->makeUser();
    $organisation->users()->attach($user->id);
    $token = new DeviceAccessToken([
        'device_id' => $device->id,
        'access_token' => 'test-access-token-' . Str::random(8),
        'expires_at' => now()->addHour(),
    ]);
    $token->created_by = $user->id;
    $token->save();

    $this->withHeader('Authorization', 'Bearer ' . $token->access_token)
        ->getJson('/pos-api/v1/devices/current')
        ->assertStatus(200)
        ->assertJsonPath('organisation.currency', 'USD');
}

public function testOrderPageEmitsEventCurrency(): void
{
    $organisation = Organisation::factory()->create(['currency' => 'USD']);
    $event = Event::factory()->create(['organisation_id' => $organisation->id]);

    $this->get('/order/' . $event->order_token)
        ->assertStatus(200)
        ->assertSee('var EVENT_CURRENCY = "USD";', false);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `$PHPUNIT --filter 'OrganisationCurrencyTest::testDeviceApiExposesOrganisationCurrency|OrganisationCurrencyTest::testOrderPageEmitsEventCurrency'`
Expected: FAIL (missing JSON path; missing string).

- [ ] **Step 3: Add the Device API field**

In `app/Http/DeviceApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php` add after `name`:
```php
$this->field('currency')->string()->visible(true)->writeable(false);
```

- [ ] **Step 4: Emit the global on the order page**

In `OrderController::view` add `'currency' => $event->organisation->currency,` to the view data. In `resources/views/order/index.blade.php` add, after the `ORDER_NAME` line:
```blade
var EVENT_CURRENCY = @json($currency);
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `$PHPUNIT --filter 'OrganisationCurrencyTest|DeviceControllerTest|SignedOrderUrlTest'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/DeviceApi/V1/ResourceDefinitions/OrganisationResourceDefinition.php app/Http/Controllers/OrderController.php resources/views/order/index.blade.php tests/Feature/OrganisationCurrencyTest.php
git commit -m "Expose the organisation currency to POS devices and the order page"
```

---

### Task 3: PHP money formatter and the intl requirement

**Files:**
- Create: `app/Support/Money.php`
- Modify: `composer.json:12-15`, `composer.lock` (platform section only)
- Modify: `.github/workflows/tests.yml:41`
- Modify: `docker-compose.yml:66-69`
- Test: `tests/Unit/MoneyTest.php`

**Interfaces:**
- Produces: `App\Support\Money::format(float $amount, string $currency, ?string $locale = null): string` (locale defaults to `app()->getLocale()`); `App\Support\Money::fallbackFormat(float $amount, string $currency): string` returning `"<CODE> 2.50"`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/MoneyTest.php` extending `Tests\TestCase` (the app must be booted for `app()->getLocale()`):

```php
private function normalise(string $value): string
{
    return str_replace("\u{a0}", ' ', $value);
}

public function testFormatsEuroInDutch(): void
{
    $this->assertSame('€ 2,50', $this->normalise(Money::format(2.5, 'EUR', 'nl')));
}

public function testFormatsDollarInEnglish(): void
{
    $this->assertSame('$2.50', Money::format(2.5, 'USD', 'en'));
}

public function testKeepsTwoDecimalsForZeroDecimalCurrencies(): void
{
    $this->assertSame('¥500.00', $this->normalise(Money::format(500, 'JPY', 'en')));
    $this->assertSame('¥150.50', $this->normalise(Money::format(150.5, 'JPY', 'en')));
}

public function testUsesTheAppLocaleByDefault(): void
{
    app()->setLocale('nl');
    $this->assertSame('€ 2,50', $this->normalise(Money::format(2.5, 'EUR')));
}

public function testFallbackFormatWithoutIntl(): void
{
    $this->assertSame('USD 2.50', Money::fallbackFormat(2.5, 'USD'));
    $this->assertSame('JPY 500.00', Money::fallbackFormat(500, 'JPY'));
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `$PHPUNIT --filter MoneyTest`
Expected: FAIL with class `App\Support\Money` not found.

- [ ] **Step 3: Implement `Money`**

`app/Support/Money.php` (same licence header style as `app/Support/InstanceSettings.php`):

- `format()`: when `!class_exists(\NumberFormatter::class)` return `self::fallbackFormat()`. Otherwise `new \NumberFormatter($locale ?? app()->getLocale(), \NumberFormatter::CURRENCY)`, `setAttribute(MIN_FRACTION_DIGITS, 2)`, `setAttribute(MAX_FRACTION_DIGITS, 2)`, return `formatCurrency($amount, $currency)`. If the JPY test shows ICU ignoring the digits for `formatCurrency`, set the currency with `setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency)` and call `format($amount)` instead.
- `fallbackFormat()`: `$currency . ' ' . number_format($amount, 2, '.', '')`.

- [ ] **Step 4: Require and enable `ext-intl`**

- `composer.json`: add `"ext-intl": "*",` after `"ext-json": "*",`.
- Run `docker compose --profile test run --rm -e PHP_EXTENSION_BCMATH=1 -e PHP_EXTENSION_GD=1 -e PHP_EXTENSION_INTL=1 --entrypoint composer phpunit update --lock` then `... --entrypoint composer phpunit validate`. Expected: `composer.json is valid`; `git diff composer.lock` shows only the `platform` / `content-hash` lines.
- `.github/workflows/tests.yml:41`: `extensions: mbstring, json, openssl, intl`.
- `docker-compose.yml` `phpunit` service `environment`: add `PHP_EXTENSION_INTL: "1"`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `$PHPUNIT --filter MoneyTest`
Expected: 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Money.php composer.json composer.lock .github/workflows/tests.yml docker-compose.yml tests/Unit/MoneyTest.php
git commit -m "Add a Money formatter backed by ext-intl"
```

---

### Task 4: Top-up pages and Pay.nl in the organisation's currency

**Files:**
- Modify: `app/Http/Controllers/TopupController.php:76-98, 165-190, 230-246`
- Modify: `resources/views/topup/topupForm.blade.php:9-12, 27`
- Modify: `resources/views/topup/status.blade.php:20`
- Modify: `resources/lang/{en,nl,fr,de,es}/topup.php:8`
- Test: `tests/Feature/TopupCurrencyTest.php`

**Interfaces:**
- Consumes: `Money::format()` from Task 3; `organisations.currency` from Task 1.
- Produces: view variables `balance` (formatted string), `minTopupFormatted` (formatted string), `minTopup` / `maxTopup` (raw numbers), `amountFormatted` on the status view.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TopupCurrencyTest.php` (`Tests\Feature`, `use RefreshDatabase`):

```php
public function testPurchaseParametersUseOrganisationCurrency(): void
{
    $organisation = new Organisation(['name' => 'Test org']);
    $organisation->currency = 'USD';
    $card = new Card();
    $card->uid = 'abc123';
    $card->setRelation('organisation', $organisation);
    $topup = new Topup(['amount' => 12.5]);
    $topup->uid = 'topup-uid';

    $method = new \ReflectionMethod(TopupController::class, 'getParameters');
    $params = $method->invoke(new TopupController(), $card, $topup);

    $this->assertSame('USD', $params['currency']);
    $this->assertSame('12.50', $params['amount']);
}

public function testTopupFormShowsAmountsInOrganisationCurrency(): void
{
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $organisation = Organisation::factory()->create(['currency' => 'USD']);

    $gateway = new OrganisationPaymentGateway(['gateway' => OrganisationPaymentGateway::GATEWAY_PAYNL, 'is_testing' => true, 'is_active' => true]);
    $gateway->organisation_id = $organisation->id;
    $gateway->credentials = ['apiToken' => 'token', 'apiSecret' => 'secret', 'serviceId' => 'SL-1234-5678'];
    $gateway->save();

    $card = new Card();
    $card->organisation_id = $organisation->id;
    $card->uid = 'cardabc';
    $card->order_token = 'ordertoken123';
    $card->transaction_count = 0;
    $card->balance = 0;
    $card->save();

    $response = $this->get('/topup/cardabc');
    $response->assertStatus(200);
    $response->assertSee('$0.00');
    $response->assertSee('$10.00');
    $response->assertDontSee('€');
}
```
If `Card::save()` needs further not-null columns, set them in the test; do not change the model.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `$PHPUNIT --filter TopupCurrencyTest`
Expected: FAIL (`currency` is `EUR`; page shows `€`).

- [ ] **Step 3: Change the controller**

In `TopupController`:
- `getParameters()`: `'currency' => $card->organisation->currency`.
- `topupForm()`: pass `'minTopup' => $this->minTopup`, `'maxTopup' => $this->maxTopup` (raw), `'minTopupFormatted' => Money::format((float)$this->minTopup, $card->organisation->currency)`, `'balance' => Money::format($card->getBalance() / 100, $card->organisation->currency)`.
- `status()`: pass `'amountFormatted' => Money::format((float)$topup->amount, $card->organisation->currency)` to the `topup/status` view.

- [ ] **Step 4: Change the views and translations**

- `topupForm.blade.php`: `€ {{ $balance }}` → `{{ $balance }}`; `__('topup.choose_amount', ['min' => $minTopup])` → `['min' => $minTopupFormatted]`; the `min`/`max` input attributes keep `$minTopup` / `$maxTopup`.
- `status.blade.php`: `['amount' => $topup->amount]` → `['amount' => $amountFormatted]`.
- `choose_amount` in the five `topup.php` files:
  - en: `Choose the desired amount (starting from :min).`
  - nl: `Kies het gewenste bedrag (vanaf :min).`
  - fr: `Choisissez le montant souhaité (à partir de :min).`
  - de: `Wählen Sie den gewünschten Betrag (ab :min).`
  - es: `Elija el monto deseado (a partir de :min).`

- [ ] **Step 5: Run the tests to verify they pass**

Run: `$PHPUNIT --filter TopupCurrencyTest`
Expected: 2 tests PASS. Then `grep -rn '€' resources/lang/*/topup.php resources/views/topup` prints nothing.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TopupController.php resources/views/topup resources/lang tests/Feature/TopupCurrencyTest.php
git commit -m "Format top-up pages and Pay.nl payments in the organisation currency"
```

---

### Task 5: Shared money module for the frontends

**Files:**
- Create: `resources/shared/js/utils/money.js`
- Test: `resources/tests/money.test.js`

**Interfaces:**
- Consumes: `getLocale()` from `resources/shared/js/i18n/index.js`.
- Produces (all named exports of `resources/shared/js/utils/money.js`):
  - `DEFAULT_CURRENCY = 'EUR'`, `FALLBACK_CURRENCIES = ['EUR','USD','GBP','CHF','SEK','NOK','DKK','PLN','CZK','CAD','AUD']`
  - `isCurrencyCode(value: any): boolean` — `/^[A-Z]{3}$/` on strings only
  - `setCurrency(code: any): string` — stores `code` when `isCurrencyCode(code)`, otherwise leaves the current value; returns the current value
  - `getCurrency(): string`
  - `formatMoney(amount: number|string|null|undefined, currency = getCurrency(), locale = getLocale()): string`
  - `currencySymbol(currency = getCurrency(), locale = getLocale()): string`
  - `listCurrencies(locale = getLocale()): Array<{code: string, name: string}>` sorted by `name`
  - `currencyOptions(current: string, locale = getLocale()): Array<{value: string, text: string}>` — `text` is `"<name> (<code>)"`; `current` is appended when the list lacks it

- [ ] **Step 1: Write the failing tests**

Create `resources/tests/money.test.js` (vitest; `afterEach(() => setCurrency('EUR'))`; helper `const n = (s) => s.replace(/ /g, ' ')`):

```js
it('formats euro the Dutch way and the English way', () => {
    expect(n(formatMoney(2.5, 'EUR', 'nl'))).toBe('€ 2,50');
    expect(formatMoney(2.5, 'EUR', 'en')).toBe('€2.50');
});
it('formats dollars', () => { expect(formatMoney(2.5, 'USD', 'en')).toBe('$2.50'); });
it('keeps two decimals for zero-decimal currencies', () => {
    expect(n(formatMoney(500, 'JPY', 'en'))).toBe('¥500.00');
    expect(n(formatMoney(150.5, 'JPY', 'en'))).toBe('¥150.50');
});
it('accepts numeric strings and rejects non-numbers', () => {
    expect(formatMoney('2.5', 'USD', 'en')).toBe('$2.50');
    expect(formatMoney(null)).toBe('');
    expect(formatMoney(undefined)).toBe('');
    expect(formatMoney(NaN)).toBe('');
});
it('falls back to CODE amount for a malformed code', () => {
    expect(formatMoney(2.5, 'EU', 'en')).toBe('EU 2.50');
});
it('uses the module currency by default', () => {
    setCurrency('USD');
    expect(formatMoney(1, undefined, 'en')).toBe('$1.00');
});
it('ignores invalid codes in setCurrency', () => {
    expect(setCurrency('usd')).toBe('EUR');
    expect(setCurrency(undefined)).toBe('EUR');
    expect(setCurrency(null)).toBe('EUR');
    expect(setCurrency('')).toBe('EUR');
    expect(setCurrency('GBP')).toBe('GBP');
    expect(getCurrency()).toBe('GBP');
});
it('returns the symbol alone', () => {
    expect(currencySymbol('EUR', 'en')).toBe('€');
    expect(currencySymbol('USD', 'nl')).toBe('US$');
    expect(currencySymbol('XYZ', 'en')).toBe('XYZ');
});
it('lists currencies by name', () => {
    const list = listCurrencies('en');
    expect(list).toContainEqual({ code: 'EUR', name: 'Euro' });
    expect(list).toContainEqual({ code: 'USD', name: 'US Dollar' });
    const names = list.map((c) => c.name);
    expect(names).toEqual([...names].sort((a, b) => a.localeCompare(b, 'en')));
});
it('falls back to a short list without Intl.supportedValuesOf and keeps the current code', () => {
    const original = Intl.supportedValuesOf;
    Intl.supportedValuesOf = undefined;
    try {
        expect(listCurrencies('en').map((c) => c.code)).toEqual(expect.arrayContaining(FALLBACK_CURRENCIES));
        expect(listCurrencies('en')).toHaveLength(FALLBACK_CURRENCIES.length);
        const options = currencyOptions('HUF', 'en');
        expect(options).toContainEqual({ value: 'HUF', text: 'Hungarian Forint (HUF)' });
        expect(options).toContainEqual({ value: 'EUR', text: 'Euro (EUR)' });
    } finally {
        Intl.supportedValuesOf = original;
    }
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/tests/money.test.js`
Expected: FAIL, cannot resolve `../shared/js/utils/money`.

- [ ] **Step 3: Implement the module**

`resources/shared/js/utils/money.js` with the exports above. `formatMoney`: coerce with `Number(amount)`; return `''` when `amount` is `null`/`undefined` or the result is not finite; build `new Intl.NumberFormat(locale, { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 })` inside `try`, on `RangeError` return `` `${currency} ${value.toFixed(2)}` ``. `currencySymbol`: `formatToParts(0)` and the part with `type === 'currency'`, falling back to the code. `listCurrencies`: codes from `Intl.supportedValuesOf('currency')` when it is a function, else `FALLBACK_CURRENCIES`; names from `new Intl.DisplayNames([locale], { type: 'currency' }).of(code)`, falling back to the code; sort with `localeCompare(…, locale)`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/tests/money.test.js`
Expected: 10 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/shared/js/utils/money.js resources/tests/money.test.js
git commit -m "Add a shared money formatter for the frontends"
```

---

### Task 6: Wire the formatter into the Manage, POS and guest app boots

**Files:**
- Modify: `resources/manage/js/app.js:11-15, 176-186, 222-228`
- Modify: `resources/pos/js/app.js:11-18, 255, 376-382`
- Modify: `resources/clients/js/app.js:26-33, 74-82`
- Test: `resources/tests/currency-display.test.js`

**Interfaces:**
- Consumes: `setCurrency`, `formatMoney` from Task 5; `organisation.currency` on `/users/me` (Task 1) and `/devices/current` (Task 2); `EVENT_CURRENCY` global (Task 2).
- Produces: `this.$filters.money(value)` available in every component of all three apps.

- [ ] **Step 1: Write the failing test**

Create `resources/tests/currency-display.test.js` in the style of `resources/tests/offline-support.test.js`:

```js
describe('currency wiring at boot', () => {
    it('Manage sets the currency from the selected organisation', () => {
        const source = readFile('manage/js/app.js');
        expect(source).toContain("import { setCurrency, formatMoney } from '../../shared/js/utils/money';");
        expect(source).toContain('setCurrency(organisation ? organisation.currency : null);');
        expect(source).toContain('money: formatMoney');
    });
    it('POS sets the currency from the device payload', () => {
        const source = readFile('pos/js/app.js');
        expect(source).toContain('setCurrency(deviceData.organisation.currency);');
        expect(source).toContain('money: formatMoney');
    });
    it('the guest app sets the currency from the page global', () => {
        const source = readFile('clients/js/app.js');
        expect(source).toContain("if (typeof(EVENT_CURRENCY) !== 'undefined') {");
        expect(source).toContain('setCurrency(EVENT_CURRENCY);');
        expect(source).toContain('money: formatMoney');
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/tests/currency-display.test.js`
Expected: FAIL on every `toContain`.

- [ ] **Step 3: Wire the three apps**

- Manage `app.js`: import; after `window.ORGANISATION_TEST_MODE = …` add `setCurrency(organisation ? organisation.currency : null);`; extend `$filters` with `money: formatMoney`.
- POS `app.js`: import; after `window.ORGANISATION_ID = deviceData.organisation.id;` add `setCurrency(deviceData.organisation.currency);`; extend `$filters` with `money: formatMoney`.
- Client `app.js`: import; before `app.use(router)` add the guarded `setCurrency(EVENT_CURRENCY)` block and `app.config.globalProperties.$filters = { money: formatMoney };`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/tests/currency-display.test.js resources/tests/offline-support.test.js resources/tests/app-routes.test.js`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/app.js resources/pos/js/app.js resources/clients/js/app.js resources/tests/currency-display.test.js
git commit -m "Set the organisation currency when the apps boot"
```

---

### Task 7: Replace the hardcoded euro in shared components

**Files:**
- Modify: `resources/shared/js/components/SalesSummary.vue:70-74, 86-88`
- Modify: `resources/shared/js/components/LiveSales.vue:66, 92`
- Modify: `resources/shared/js/components/Card.vue:43, 115`
- Modify: `resources/shared/js/components/PaymentPopup.vue:72`
- Modify: `resources/shared/js/services/PaymentService.js:37, 270-274`
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js:34, 57`
- Test: `resources/tests/currency-display.test.js`, `resources/tests/i18n-completeness.test.js`

**Interfaces:**
- Consumes: `$filters.money` (Task 6), `formatMoney` (Task 5).
- Produces: renamed keys `'Total: {amount} items = {price}'` and `'Are you sure you want to topup for {amount}?'`; `PaymentService.visualiseCurrency(price)` now returns `formatMoney(price)` and `PaymentService.currencySymbol` no longer exists.

- [ ] **Step 1: Write the failing test**

Append to `resources/tests/currency-display.test.js`:

```js
describe('shared components use the money filter', () => {
    it('no shared component or service hardcodes the euro sign', () => {
        for (const file of ['components/SalesSummary.vue', 'components/LiveSales.vue', 'components/Card.vue', 'components/PaymentPopup.vue', 'services/PaymentService.js']) {
            expect(readFile('shared/js/' + file), file).not.toContain('€');
        }
    });
    it('the locale files carry the renamed keys and not the old ones', () => {
        for (const locale of ['en', 'nl', 'fr', 'de', 'es']) {
            const source = readFile('shared/js/i18n/' + locale + '.js');
            expect(source).toContain("'Total: {amount} items = {price}'");
            expect(source).toContain("'Are you sure you want to topup for {amount}?'");
            expect(source).not.toContain('€');
        }
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/tests/currency-display.test.js`
Expected: FAIL on the `€` assertions.

- [ ] **Step 3: Replace the euro in templates and scripts**

- `SalesSummary.vue`: each `€{{x.toFixed(2)}}` → `{{ $filters.money(x) }}` (six cells; the VAT percentage cell is untouched).
- `LiveSales.vue` both spots: `$t('Total: {amount} items = {price}', { amount: totals.amount, price: $filters.money(totals.price) })`.
- `Card.vue`: tile `{{ $filters.money(amount) }}`; confirm text `$t('Are you sure you want to topup for {amount}?', { amount: $filters.money(topupAmount) })`.
- `PaymentPopup.vue`: import `formatMoney` from `../utils/money`; `this.cashAmount = formatMoney(this.amount);`.
- `PaymentService.js`: import `formatMoney` from `../utils/money`; delete `this.currencySymbol = '€';`; `visualiseCurrency(price) { return formatMoney(price); }` with the comment updated.

- [ ] **Step 4: Rename the two keys in all five locale files**

| locale | `Total: {amount} items = {price}` | `Are you sure you want to topup for {amount}?` |
|---|---|---|
| en | `Total: {amount} items = {price}` | `Are you sure you want to topup for {amount}?` |
| nl | `Totaal: {amount} items = {price}` | `Weet je zeker dat je wilt opwaarderen voor {amount}?` |
| fr | `Total : {amount} articles = {price}` | `Êtes-vous sûr de vouloir recharger pour {amount} ?` |
| de | `Gesamt: {amount} Artikel = {price}` | `Sind Sie sicher, dass Sie für {amount} aufladen möchten?` |
| es | `Total: {amount} artículos = {price}` | `¿Está seguro de que desea recargar por {amount}?` |

Remove the old `€` keys.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npx vitest run resources/tests/currency-display.test.js resources/tests/i18n-completeness.test.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/components/SalesSummary.vue resources/shared/js/components/LiveSales.vue resources/shared/js/components/Card.vue resources/shared/js/components/PaymentPopup.vue resources/shared/js/services/PaymentService.js resources/shared/js/i18n resources/tests/currency-display.test.js
git commit -m "Format shared components with the organisation currency"
```

---

### Task 8: Replace the euro in the event form and the guest order page

**Files:**
- Modify: `resources/manage/js/components/EventForm.vue:76, 167, 198-208`
- Modify: `resources/clients/js/views/Order.vue:55`
- Test: `resources/tests/currency-display.test.js`, `resources/tests/event-form.test.js`

**Interfaces:**
- Consumes: `currencySymbol` (Task 5), `$filters.money` (Task 6).

- [ ] **Step 1: Write the failing test**

Append to `resources/tests/currency-display.test.js` a scan over every `.vue` and `.js` file under `resources/{manage,pos,clients,shared}/js` (copy the directory walk from `resources/tests/i18n-completeness.test.js` into this file as `sourceFiles()`, with the extension regex limited to `\.(vue|js)$` so `Card.ts` and its euro comment stay out, and still skipping `*.test.js`):

```js
it('no Vue or JS source under resources/ contains a literal euro sign', () => {
    const offenders = sourceFiles().filter((file) => readFileSync(file, 'utf-8').includes('€'));
    expect(offenders).toEqual([]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/tests/currency-display.test.js`
Expected: FAIL listing `EventForm.vue` and `Order.vue`.

- [ ] **Step 3: Replace the two remaining spots**

- `EventForm.vue`: `import { currencySymbol as moneySymbol } from '../../../shared/js/utils/money';`, computed `voucherCurrencySymbol() { return moneySymbol(); }`, template `<b-input-group :prepend="voucherCurrencySymbol" class="event-form__voucher-value">`.
- `Order.vue`: `<span class="price">{{ $filters.money(row.item.price) }}</span>`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/tests/currency-display.test.js resources/tests/event-form.test.js resources/tests/order-view.test.js`
Expected: PASS. If `event-form.test.js` mounts the component without `$filters`, that is fine: the form uses `moneySymbol()` directly, not the filter.

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/components/EventForm.vue resources/clients/js/views/Order.vue resources/tests/currency-display.test.js
git commit -m "Use the organisation currency in the event form and the order page"
```

---

### Task 9: Currency picker on Organisation Settings

**Files:**
- Modify: `resources/manage/js/views/OrganisationSettings.vue:36-40, 143-170, 185-250`
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js` (new keys)
- Modify: `CLAUDE.md:330-338`
- Test: `resources/tests/organisation-settings-currency.test.js`, `resources/tests/i18n-completeness.test.js`

**Interfaces:**
- Consumes: `currencyOptions`, `setCurrency`, `getCurrency` (Task 5); `getLocale()` from i18n; `this.$organisationService.update(id, data)` (`resources/shared/js/services/AbstractService.js:151`), which issues `PUT /api/v1/organisations/{id}` (Task 1).

- [ ] **Step 1: Write the failing test**

Create `resources/tests/organisation-settings-currency.test.js` (source-level, same `readFile` helper):

```js
const source = readFile('manage/js/views/OrganisationSettings.vue');
it('renders a currency select built from currencyOptions', () => {
    expect(source).toContain("$t('Currency')");
    expect(source).toContain('currencyOptions(');
    expect(source).toContain('<b-form-select id="organisation-currency" v-model="currency" :options="currencyOptions"');
});
it('saves through the organisation service and updates the running app', () => {
    expect(source).toContain('this.$organisationService.update(window.ORGANISATION_ID, { currency: this.currency })');
    expect(source).toContain('setCurrency(this.currency)');
    expect(source).toContain('window.ORGANISATIONS');
});
it('explains that past amounts are relabelled', () => {
    expect(source).toContain("$t('Used to label prices, balances and top-ups. Changing it relabels past sales too; no amounts are converted.')");
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/tests/organisation-settings-currency.test.js`
Expected: FAIL.

- [ ] **Step 3: Build the section**

In `OrganisationSettings.vue`, inside `v-if="loaded"` above the "Payment Gateways" heading:

```html
<h2>{{ $t('Currency') }}</h2>
<p class="text-muted">{{ $t('Used to label prices, balances and top-ups. Changing it relabels past sales too; no amounts are converted.') }}</p>
<b-form inline @submit.prevent="saveCurrency" class="mb-4">
    <b-form-select id="organisation-currency" v-model="currency" :options="currencyOptions" class="mr-2" />
    <b-btn variant="success" type="submit" :disabled="savingCurrency || currency === savedCurrency">
        <b-spinner small v-if="savingCurrency" />
        <span class="mr-1" v-if="!savingCurrency">💾</span>
        {{ $t('Save') }}
    </b-btn>
    <b-alert v-if="currencySaved" variant="none" :show="2" class="mb-0 ml-2">{{ $t('Saved') }}</b-alert>
</b-form>
```

Script: import `{ currencyOptions, setCurrency, DEFAULT_CURRENCY }` from `../../../shared/js/utils/money` and `{ getLocale }` from `../../../shared/js/i18n/index`. Data: `currency` and `savedCurrency` from `currentOrganisation.currency || DEFAULT_CURRENCY`, `savingCurrency: false`, `currencySaved: false`, `currencyOptions: currencyOptions(currentOrganisation ? currentOrganisation.currency || DEFAULT_CURRENCY : DEFAULT_CURRENCY, getLocale())`.

```js
async saveCurrency()
```
sets `savingCurrency`, awaits `this.$organisationService.update(window.ORGANISATION_ID, { currency: this.currency })`, then updates the matching entry in `window.ORGANISATIONS`, calls `setCurrency(this.currency)`, sets `savedCurrency = this.currency` and `currencySaved = true`; `finally` clears `savingCurrency`. Errors are already alerted by the service's interceptor; no extra message.

- [ ] **Step 4: Add the two new keys to the five locale files**

| locale | `Currency` | description |
|---|---|---|
| en | `Currency` | `Used to label prices, balances and top-ups. Changing it relabels past sales too; no amounts are converted.` |
| nl | `Valuta` | `Wordt gebruikt om prijzen, saldo's en opwaarderingen te labelen. Wijzigen hernoemt ook eerdere verkopen; er worden geen bedragen omgerekend.` |
| fr | `Devise` | `Sert à libeller les prix, les soldes et les rechargements. Un changement s'applique aussi aux ventes passées ; aucun montant n'est converti.` |
| de | `Währung` | `Wird zur Kennzeichnung von Preisen, Guthaben und Aufladungen verwendet. Eine Änderung gilt auch für frühere Verkäufe; es werden keine Beträge umgerechnet.` |
| es | `Moneda` | `Se usa para etiquetar precios, saldos y recargas. Cambiarla también afecta a las ventas pasadas; no se convierte ningún importe.` |

Escape the apostrophes for single-quoted JS strings.

- [ ] **Step 5: Document the pattern**

Add to `CLAUDE.md` "Common Patterns":
`- Amounts of money are formatted with the shared helper (`resources/shared/js/utils/money.js` in the frontends via `$filters.money()`, `App\Support\Money::format()` in PHP) using the organisation's `currency`; never hardcode a currency symbol.`

- [ ] **Step 6: Run the tests to verify they pass**

Run: `npx vitest run resources/tests/organisation-settings-currency.test.js resources/tests/i18n-completeness.test.js`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/manage/js/views/OrganisationSettings.vue resources/shared/js/i18n CLAUDE.md resources/tests/organisation-settings-currency.test.js
git commit -m "Let organisations pick their currency in Manage"
```

---

### Task 10: Full verification

**Files:** none modified unless a check fails.

- [ ] **Step 1: Full PHP suite**

Run: `$PHPUNIT`
Expected: all tests PASS (the suite needs the `mysql-test` service; the `test` profile starts it).

- [ ] **Step 2: Full JS suite and production build**

Run: `npx vitest run` then `npx jest` then `npm run dev`.
Expected: all PASS; build completes without errors; `git status --short` shows no change to `package-lock.json` (if it does, `git checkout -- package-lock.json`).

- [ ] **Step 3: Grep for leftovers**

Run: `grep -rn '€' resources app --include='*.vue' --include='*.js' --include='*.ts' --include='*.php' | grep -v -E 'resources/views/landing|whitepaper|welcome.php|Card.ts'`
Expected: no output.

- [ ] **Step 4: Manual smoke test (optional, if the docker web stack is up)**

In Manage, open Organisation Settings, switch to USD, save: the voucher prefix in the new-event form shows `$`, the sales summary shows `$`. On the guest page of an event of that organisation prices show `$`. Switch back to EUR.

- [ ] **Step 5: Hand off**

Use superpowers:finishing-a-development-branch to open the pull request against `main`.
