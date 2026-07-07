# Table Service Completion & Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the table service feature complete and safe to merge: server-side order integrity, wired patron assignment (table QR + named orders), a configurable bar prepared/delivered loop with atomic settlement, NFC dependency hardening, and structural cleanups.

**Architecture:** Validation lives in Eloquent model events (the public order endpoint bypasses Charon controller hooks). Patron assignment is wired into `PublicController@order` via the existing `PatronAssignmentService`. Settlement becomes one atomic server-side endpoint. Frontend dedupe extracts a shared OrderQueue component and status-constants module.

**Tech Stack:** Laravel + CatLab Charon (backend), Vue 3 compat + Bootstrap-Vue (frontend), PHPUnit (backend tests, MySQL test DB), Vitest (`resources/**/*.test.js`), Jest (`tests/js/*.ts`).

**Spec:** `docs/superpowers/specs/2026-07-07-table-service-completion-design.md`

## Global Constraints

- Tabs, not spaces, for indentation (PHP files in `app/` currently use 4 spaces — match whatever the file you're editing uses).
- Never run `npm update`. Never commit `package-lock.json` changes: run `git checkout -- package-lock.json` before committing if it changed.
- Composer: use `composer install --ignore-platform-reqs` if needed (PHP here is 8.5; lock file targets ~8.1/8.2). `vendor/` already exists — do not reinstall unless something is missing.
- PHPUnit: `./vendor/bin/phpunit`. Feature tests need MySQL DB `catlab_drinks_test` with user `test`/`test` (see Task 0).
- Vitest: `npx vitest run`. Jest: `npx jest`.
- Verify routes with `php artisan route:list --path=<prefix>`.
- Commit after each task. End commit messages with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- Do not touch `mix-manifest.json` in commits (build artifacts).

---

### Task 0: Test environment baseline

**Files:** none (environment only)

- [ ] **Step 1: Check the MySQL test database is reachable**

Run: `mysql -utest -ptest -h127.0.0.1 -e "SELECT 1" catlab_drinks_test 2>&1 | tail -1`

If it prints a row with `1`, skip to Step 3. If it prints `ERROR 1045` (access denied), the DB/user doesn't exist yet — go to Step 2.

- [ ] **Step 2: Ask the user to create the test database (needs sudo)**

The agent cannot run interactive sudo. Ask the user to run this in the session (the `!` prefix runs it in the conversation):

```
! sudo mysql -e "CREATE DATABASE IF NOT EXISTS catlab_drinks_test; CREATE USER IF NOT EXISTS 'test'@'localhost' IDENTIFIED BY 'test'; CREATE USER IF NOT EXISTS 'test'@'127.0.0.1' IDENTIFIED BY 'test'; GRANT ALL ON catlab_drinks_test.* TO 'test'@'localhost'; GRANT ALL ON catlab_drinks_test.* TO 'test'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

Then re-run Step 1 to confirm.

- [ ] **Step 3: Run the baseline suites**

Run: `./vendor/bin/phpunit --testsuite=Unit 2>&1 | tail -3`
Expected: `OK` (all unit tests pass — they don't need the DB).

Run: `./vendor/bin/phpunit --testsuite=Feature --filter SignedOrderUrlTest 2>&1 | tail -3`
Expected: `OK`. If this errors with a DB connection failure, Step 2 wasn't completed — stop and resolve before continuing.

Run: `npx vitest run 2>&1 | tail -5` and `npx jest 2>&1 | tail -5`
Expected: all pass. Record any pre-existing failures so they aren't attributed to this work later.

---

### Task 1: Table and Patron factories

**Files:**
- Create: `database/factories/TableFactory.php`
- Create: `database/factories/PatronFactory.php`

**Interfaces:**
- Produces: `Table::factory()`, `Patron::factory()` for use in later feature tests. Both models already use the `HasFactory` trait.

- [ ] **Step 1: Write `database/factories/TableFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'table_number' => $this->faker->unique()->numberBetween(1, 10000),
            'name' => 'Table ' . $this->faker->numberBetween(1, 100),
        ];
    }
}
```

- [ ] **Step 2: Write `database/factories/PatronFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatronFactory extends Factory
{
    protected $model = Patron::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->firstName(),
            'table_id' => null,
        ];
    }
}
```

- [ ] **Step 3: Smoke-test the factories**

Run: `php artisan tinker --execute="echo get_class(\App\Models\Table::factory()->make()); echo PHP_EOL; echo get_class(\App\Models\Patron::factory()->make());"`
Expected: `App\Models\Table` and `App\Models\Patron` printed, no exceptions.

- [ ] **Step 4: Commit**

```bash
git add database/factories/TableFactory.php database/factories/PatronFactory.php
git commit -m "Add Table and Patron model factories

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Order & Patron integrity model events

**Files:**
- Modify: `app/Models/Order.php` (boot method at lines 41-84; constants at lines 86-94)
- Modify: `app/Models/Patron.php`
- Create: `tests/Feature/OrderIntegrityTest.php`

**Interfaces:**
- Consumes: `Table::factory()`, `Patron::factory()` from Task 1.
- Produces: model-level guarantees every later task relies on:
  - Saving an `Order` whose `patron_id`/`table_id` references another event's patron/table throws `Illuminate\Validation\ValidationException` (HTTP 422 at the API boundary).
  - `payment_status` must be one of `Order::PAYMENT_STATUSES` (new constant, array of the three existing string constants).
  - Transitioning `payment_status` **to** `unpaid` requires `event->allow_unpaid_table_orders` OR `event->allow_unpaid_online_orders` (the latter covers unpaid remote online orders; the spec's rule is extended with this OR — without it, legacy unpaid online orders would be blocked).
  - `paid` bool auto-syncs: `payment_status` dirty → `paid = (payment_status === 'paid')`; legacy writes that only set `paid = true` on an `unpaid` order pull `payment_status` to `paid`.
  - Same cross-event check for `Patron.table_id`.

- [ ] **Step 1: Write the failing tests — `tests/Feature/OrderIntegrityTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tests for the model-level integrity rules on Order and Patron.
 * These rules must hold on every write path (management API, device API,
 * public order endpoint), which is why they live in model events.
 */
class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $attributes = []): Event
    {
        $organisation = Organisation::factory()->create();

        return Event::factory()->create(array_merge([
            'organisation_id' => $organisation->id,
            'allow_unpaid_table_orders' => false,
            'allow_unpaid_online_orders' => false,
        ], $attributes));
    }

    public function testOrderRejectsPatronFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $patronB = Patron::factory()->create(['event_id' => $eventB->id]);

        $order = Order::factory()->make(['event_id' => $eventA->id]);
        $order->patron_id = $patronB->id;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderAcceptsPatronFromSameEvent(): void
    {
        $event = $this->makeEvent();
        $patron = Patron::factory()->create(['event_id' => $event->id]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->patron_id = $patron->id;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'patron_id' => $patron->id,
        ]);
    }

    public function testOrderRejectsUnknownPatron(): void
    {
        $event = $this->makeEvent();

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->patron_id = 999999;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderRejectsTableFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $tableB = Table::factory()->create(['event_id' => $eventB->id]);

        $order = Order::factory()->make(['event_id' => $eventA->id]);
        $order->table_id = $tableB->id;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testOrderRejectsInvalidPaymentStatus(): void
    {
        $event = $this->makeEvent();

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = 'totally-paid-i-promise';

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testUnpaidRequiresEventSetting(): void
    {
        $event = $this->makeEvent(); // both unpaid flags false

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;

        $this->expectException(ValidationException::class);
        $order->save();
    }

    public function testUnpaidAllowedWithTableOrdersSetting(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'paid' => false,
        ]);
    }

    public function testUnpaidAllowedWithOnlineOrdersSetting(): void
    {
        $event = $this->makeEvent(['allow_unpaid_online_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);
    }

    public function testUnpaidToPaidAlwaysAllowedAndSyncsPaidBool(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        // Turn the setting off: settling must still work.
        $event->allow_unpaid_table_orders = false;
        $event->save();

        $order->refresh();
        $order->payment_status = Order::PAYMENT_STATUS_PAID;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid' => true,
        ]);
    }

    public function testVoidAlwaysAllowed(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        $order->refresh();
        $order->payment_status = Order::PAYMENT_STATUS_VOIDED;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_VOIDED,
            'paid' => false,
        ]);
    }

    public function testLegacyPaidWriteSyncsPaymentStatus(): void
    {
        $event = $this->makeEvent(['allow_unpaid_table_orders' => true]);

        $order = Order::factory()->make(['event_id' => $event->id]);
        $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
        $order->save();

        // Legacy flow: only sets `paid` (e.g. bar accepting a remote order).
        $order->refresh();
        $order->paid = true;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid' => true,
        ]);
    }

    public function testPatronRejectsTableFromOtherEvent(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $tableB = Table::factory()->create(['event_id' => $eventB->id]);

        $patron = Patron::factory()->make(['event_id' => $eventA->id]);
        $patron->table_id = $tableB->id;

        $this->expectException(ValidationException::class);
        $patron->save();
    }

    public function testPatronAcceptsTableFromSameEvent(): void
    {
        $event = $this->makeEvent();
        $table = Table::factory()->create(['event_id' => $event->id]);

        $patron = Patron::factory()->make(['event_id' => $event->id]);
        $patron->table_id = $table->id;
        $patron->save();

        $this->assertDatabaseHas('patrons', [
            'id' => $patron->id,
            'table_id' => $table->id,
        ]);
    }
}
```

Note: `Order::factory()->make([...])` sets `event_id` through the factory (factories run unguarded, so non-fillable attributes are fine).

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --testsuite=Feature --filter OrderIntegrityTest 2>&1 | tail -5`
Expected: FAILURES — the cross-event and payment-status tests fail because no validation exists yet (saves succeed where an exception is expected). `testOrderAcceptsPatronFromSameEvent` and `testPatronAcceptsTableFromSameEvent` may already pass.

- [ ] **Step 3: Implement the Order model rules**

In `app/Models/Order.php`:

Add the import at the top (after `use DB;`):

```php
use Illuminate\Validation\ValidationException;
```

Add a constant below the existing `PAYMENT_STATUS_*` constants (after line 94):

```php
const PAYMENT_STATUSES = [
    self::PAYMENT_STATUS_UNPAID,
    self::PAYMENT_STATUS_PAID,
    self::PAYMENT_STATUS_VOIDED,
];
```

In `boot()`, register a `saving` listener **before** the existing `self::created(...)` block:

```php
self::saving(function(Order $order) {
    $order->validateIntegrity();
    $order->syncPaymentFields();
});
```

Add these two methods to the class (e.g. after the `table()` relationship):

```php
/**
 * Validate patron/table ownership and payment status.
 * Lives in a model event (not a controller hook) so it runs on every
 * write path, including the public order endpoint which calls
 * saveRecursively() directly.
 * @throws ValidationException
 */
public function validateIntegrity()
{
    if (
        $this->payment_status !== null &&
        !in_array($this->payment_status, self::PAYMENT_STATUSES, true)
    ) {
        throw ValidationException::withMessages([
            'payment_status' => 'Invalid payment status.'
        ]);
    }

    if ($this->isDirty('patron_id') && $this->patron_id !== null) {
        $patron = Patron::find($this->patron_id);
        if (!$patron || (int) $patron->event_id !== (int) $this->event_id) {
            throw ValidationException::withMessages([
                'patron_id' => 'Patron does not belong to this event.'
            ]);
        }
    }

    if ($this->isDirty('table_id') && $this->table_id !== null) {
        $table = Table::find($this->table_id);
        if (!$table || (int) $table->event_id !== (int) $this->event_id) {
            throw ValidationException::withMessages([
                'table_id' => 'Table does not belong to this event.'
            ]);
        }
    }

    if (
        $this->isDirty('payment_status') &&
        $this->payment_status === self::PAYMENT_STATUS_UNPAID &&
        $this->getOriginal('payment_status') !== self::PAYMENT_STATUS_UNPAID
    ) {
        $event = $this->event;
        if (
            !$event ||
            (!$event->allow_unpaid_table_orders && !$event->allow_unpaid_online_orders)
        ) {
            throw ValidationException::withMessages([
                'payment_status' => 'Unpaid orders are not allowed for this event.'
            ]);
        }
    }
}

/**
 * Keep the legacy `paid` bool consistent with `payment_status`.
 * payment_status is canonical; legacy flows that only set `paid`
 * pull payment_status along.
 */
public function syncPaymentFields()
{
    if ($this->isDirty('payment_status')) {
        $this->paid = ($this->payment_status === self::PAYMENT_STATUS_PAID);
    } elseif (
        $this->isDirty('paid') &&
        $this->paid &&
        $this->payment_status === self::PAYMENT_STATUS_UNPAID
    ) {
        $this->payment_status = self::PAYMENT_STATUS_PAID;
    }
}
```

- [ ] **Step 4: Implement the Patron model rule**

In `app/Models/Patron.php`, add imports:

```php
use Illuminate\Validation\ValidationException;
```

Add inside the class (after the `$fillable` property):

```php
/**
 * Cross-event table assignment is never valid; enforced at model level
 * so it holds on every write path.
 */
protected static function booted()
{
    self::saving(function (Patron $patron) {
        if ($patron->isDirty('table_id') && $patron->table_id !== null) {
            $table = Table::find($patron->table_id);
            if (!$table || (int) $table->event_id !== (int) $patron->event_id) {
                throw ValidationException::withMessages([
                    'table_id' => 'Table does not belong to this event.'
                ]);
            }
        }
    });
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --testsuite=Feature --filter OrderIntegrityTest 2>&1 | tail -3`
Expected: `OK (13 tests, ...)`

- [ ] **Step 6: Run the full suites to check for regressions**

Run: `./vendor/bin/phpunit 2>&1 | tail -3`
Expected: `OK`. Watch specifically for `OrderAssignmentServiceTest` and `OrderControllerIndexTest` — they create orders and must still pass. If a pre-existing test creates an order with an invalid state that the new rules reject, fix the test's fixture (give the event the needed flag), not the rule.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Order.php app/Models/Patron.php tests/Feature/OrderIntegrityTest.php
git commit -m "Enforce order/patron integrity at model level

Cross-event patron_id/table_id references are rejected, payment_status
is enum-validated, transitions to unpaid require the event to allow
unpaid orders, and the legacy paid bool stays in sync with
payment_status.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: `table` parameter in signed order URLs

**Files:**
- Modify: `app/Services/OrderTokenSignatureService.php:41` (SIGNABLE_PARAMS)
- Modify: `app/Http/Middleware/PublicEventApiAuthentication.php:36-99`
- Modify: `app/Http/Controllers/OrderController.php:40-73` (web view)
- Modify: `resources/views/order/index.blade.php`
- Modify: `resources/clients/js/bootstrap.js` (after the ORDER_NAME block at line ~81)
- Modify: `tests/Feature/SignedOrderUrlTest.php` (add cases)

**Interfaces:**
- Produces:
  - `OrderTokenSignatureService::SIGNABLE_PARAMS === ['card', 'name', 'table']`
  - `OrderTokenSignatureService::requiresSignature(array $params): bool` — true iff `card` or `name` present (non-empty). Task 4's endpoint does not use this directly, but the middleware and web controller do.
  - HTTP header `X-Table-Number` reaches the API; `$request->header('X-Table-Number')` is validated (signature-covered when a signature is required).
  - Blade global `ORDER_TABLE_NUMBER` (int) on the client order page.

**Semantics (from the spec):** a bare `?table=12` is accepted unsigned. When `card` or `name` is present, a signature is required and must cover **all** present signable params including `table` — appending `&table=` to a signed URL invalidates it.

- [ ] **Step 1: Add failing tests to `tests/Feature/SignedOrderUrlTest.php`**

Append these test methods inside the class:

```php
    /**
     * A bare table parameter needs no signature, even for events with a secret.
     */
    public function testBareTableParamAllowedUnsigned(): void
    {
        $event = $this->createEventWithSecret();

        $response = $this->get('/order/' . $event->order_token . '?table=12');

        $response->assertStatus(200);
    }

    /**
     * When card is present, the signature must also cover the table param.
     */
    public function testTableParamMustBeCoveredBySignature(): void
    {
        $event = $this->createEventWithSecret();

        // Signature over card only — appending table must invalidate it.
        $signature = OrderTokenSignatureService::sign(
            $event->order_token_secret,
            ['card' => 'abcdef']
        );

        $response = $this->get(
            '/order/' . $event->order_token . '?card=abcdef&table=12&signature=' . $signature
        );

        $response->assertStatus(403);
    }

    /**
     * A signature covering card + table validates.
     */
    public function testTableParamSignedWithCard(): void
    {
        $event = $this->createEventWithSecret();

        $signature = OrderTokenSignatureService::sign(
            $event->order_token_secret,
            ['card' => 'abcdef', 'table' => '12']
        );

        $response = $this->get(
            '/order/' . $event->order_token . '?card=abcdef&table=12&signature=' . $signature
        );

        $response->assertStatus(200);
    }
```

- [ ] **Step 2: Run to verify failures**

Run: `./vendor/bin/phpunit --filter SignedOrderUrlTest 2>&1 | tail -5`
Expected: `testBareTableParamAllowedUnsigned` PASSES already (table isn't signable yet, so no signature demanded — fine), `testTableParamMustBeCoveredBySignature` FAILS (returns 200: the appended table is ignored today), `testTableParamSignedWithCard` FAILS (403: signature verification doesn't include table).

- [ ] **Step 3: Update `OrderTokenSignatureService`**

Replace the `SIGNABLE_PARAMS` constant (line 41) and add the new constant + method:

```php
    /**
     * Parameters that are included in signature calculation.
     * Only these parameters are signed; all others are ignored.
     */
    const SIGNABLE_PARAMS = ['card', 'name', 'table'];

    /**
     * Parameters that make a signature mandatory. `table` is signable
     * (covered by the signature when one is present) but may also be
     * passed bare: knowing a table number grants no authority.
     */
    const SIGNATURE_REQUIRED_PARAMS = ['card', 'name'];

    /**
     * Check whether the given parameters require a signature.
     *
     * @param array $params
     * @return bool
     */
    public static function requiresSignature(array $params): bool
    {
        foreach (self::SIGNATURE_REQUIRED_PARAMS as $key) {
            if (isset($params[$key]) && $params[$key] !== '' && $params[$key] !== null) {
                return true;
            }
        }
        return false;
    }
```

- [ ] **Step 4: Update the web controller `app/Http/Controllers/OrderController.php`**

Replace the validation block (lines 48-58) and the view data:

```php
        // Check if query parameters need signature validation
        $queryParams = $request->only(OrderTokenSignatureService::SIGNABLE_PARAMS);
        $secret = $event->getOrderTokenSecret();

        if ($secret && OrderTokenSignatureService::requiresSignature($queryParams)) {
            $signature = $request->query('signature');
            if (!$signature || !OrderTokenSignatureService::verify($secret, $queryParams, $signature)) {
                abort(403, 'Invalid signature.');
                return;
            }
        }

        $baseUrl = '/order/' . $event->order_token . '/';
        $token = $event->order_token;

        return view(
            'order.index',
            [
                'baseUrl' => $baseUrl,
                'token' => $token,
                'signature' => $request->query('signature', ''),
                'cardToken' => $request->query('card', ''),
                'orderName' => $request->query('name', ''),
                'tableNumber' => intval($request->query('table', 0)),
            ]
        );
```

- [ ] **Step 5: Update the API middleware `app/Http/Middleware/PublicEventApiAuthentication.php`**

Add the header constant after line 38:

```php
    const HEADER_TABLE_NUMBER = 'X-Table-Number';
```

Replace the signature validation block (lines 76-94) with:

```php
        if ($secret) {
            $params = [];
            $cardToken = $request->header(self::HEADER_CARD_TOKEN);
            $orderName = $request->header(self::HEADER_ORDER_NAME);
            $tableNumber = $request->header(self::HEADER_TABLE_NUMBER);

            if ($cardToken) {
                $params['card'] = $cardToken;
            }
            if ($orderName) {
                $params['name'] = $orderName;
            }
            if ($tableNumber) {
                $params['table'] = $tableNumber;
            }

            if (OrderTokenSignatureService::requiresSignature($params)) {
                $signature = $request->header(self::HEADER_SIGNATURE);
                if (!$signature || !OrderTokenSignatureService::verify($secret, $params, $signature)) {
                    return false;
                }
            }
        }
```

- [ ] **Step 6: Pass the table through to the client app**

In `resources/views/order/index.blade.php`, add to the footer script block after `var ORDER_NAME = @json($orderName);`:

```blade
        var ORDER_TABLE_NUMBER = @json($tableNumber);
```

In `resources/clients/js/bootstrap.js`, after the `ORDER_NAME` header block (line ~81):

```js
if (typeof(ORDER_TABLE_NUMBER) !== 'undefined' && ORDER_TABLE_NUMBER) {
    window.axios.defaults.headers.common['X-Table-Number'] = ORDER_TABLE_NUMBER;
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter SignedOrderUrlTest 2>&1 | tail -3`
Expected: `OK` — including all pre-existing signature tests (backward compatibility: signatures over card/name only still validate when no table header/param is present).

- [ ] **Step 8: Commit**

```bash
git add app/Services/OrderTokenSignatureService.php app/Http/Middleware/PublicEventApiAuthentication.php app/Http/Controllers/OrderController.php resources/views/order/index.blade.php resources/clients/js/bootstrap.js tests/Feature/SignedOrderUrlTest.php
git commit -m "Add table parameter to signed order URLs

A bare ?table= is accepted unsigned (no authority attached); when card
or name is present the signature must cover the table parameter too.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Harden the public order endpoint & wire patron assignment

**Files:**
- Modify: `app/Http/ManagementApi/V1/Controllers/PublicController.php:139-246` (`order()` method)
- Create: `tests/Feature/PublicOrderPatronAssignmentTest.php`

**Interfaces:**
- Consumes: `PatronAssignmentService::resolvePatron(Event $event, ?string $name, ?Table $table): ?Patron` and `PatronAssignmentService::findOrCreateTable(Event $event, int $tableNumber): Table` (both already exist in `app/Services/PatronAssignmentService.php`); header `X-Table-Number` validated by Task 3; integrity rules from Task 2.
- Produces: public orders never contain client-supplied `patron_id`/`table_id`/`payment_status`; patron/table assignment happens exactly per the algorithm in `.ai/table-service.md`.

- [ ] **Step 1: Write the failing tests — `tests/Feature/PublicOrderPatronAssignmentTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for POST /api/v1/public/order: field stripping and patron assignment.
 * Uses events without an order_token_secret so no signature is needed
 * (signature mechanics are covered by SignedOrderUrlTest).
 */
class PublicOrderPatronAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $organisation = Organisation::factory()->create();
        $this->event = Event::factory()->create([
            'organisation_id' => $organisation->id,
            'order_token' => 'publicordertoken123456789012',
            'order_token_secret' => null,
            'is_selling' => true,
            'allow_unpaid_online_orders' => true,
        ]);

        $this->menuItem = MenuItem::factory()->create([
            'event_id' => $this->event->id,
            'is_selling' => true,
            'price' => 2.5,
        ]);
    }

    private function orderBody(array $extra = []): array
    {
        return array_merge([
            'location' => 'Remote',
            'order' => [
                'items' => [
                    [
                        'menuItem' => ['id' => $this->menuItem->id],
                        'amount' => 1,
                    ],
                ],
            ],
        ], $extra);
    }

    private function postOrder(array $body, array $headers = [])
    {
        return $this->postJson(
            '/api/v1/public/order',
            $body,
            array_merge(['X-Event-Token' => $this->event->order_token], $headers)
        );
    }

    public function testAnonymousTableOrderCreatesPatronAndTable(): void
    {
        $response = $this->postOrder($this->orderBody(), ['X-Table-Number' => '12']);
        $response->assertStatus(200);

        $table = Table::where('event_id', $this->event->id)
            ->where('table_number', 12)->first();
        $this->assertNotNull($table, 'Table 12 should be auto-created');

        $order = Order::where('event_id', $this->event->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($table->id, $order->table_id);
        $this->assertNotNull($order->patron_id);
        $this->assertEquals(Order::PAYMENT_STATUS_UNPAID, $order->payment_status);
    }

    public function testSecondTableOrderReusesPatronWithOpenTab(): void
    {
        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $firstPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $secondPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->assertEquals($firstPatronId, $secondPatronId);
    }

    public function testSettledTableGetsFreshPatron(): void
    {
        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $firstPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        // Settle the tab.
        Order::where('patron_id', $firstPatronId)->get()->each(function (Order $order) {
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->save();
        });

        $this->postOrder($this->orderBody(), ['X-Table-Number' => '7'])->assertStatus(200);
        $secondPatronId = Order::where('event_id', $this->event->id)
            ->latest('id')->first()->patron_id;

        $this->assertNotEquals($firstPatronId, $secondPatronId);
    }

    public function testNamedOrderReusesRecentPatron(): void
    {
        $this->postOrder($this->orderBody(), ['X-Order-Name' => 'Alice'])->assertStatus(200);
        $this->postOrder($this->orderBody(), ['X-Order-Name' => 'Alice'])->assertStatus(200);

        $this->assertEquals(
            1,
            Patron::where('event_id', $this->event->id)->where('name', 'Alice')->count()
        );
    }

    public function testClientCannotInjectTableServiceFields(): void
    {
        $otherOrganisation = Organisation::factory()->create();
        $otherEvent = Event::factory()->create(['organisation_id' => $otherOrganisation->id]);
        $foreignPatron = Patron::factory()->create(['event_id' => $otherEvent->id]);
        $foreignTable = Table::factory()->create(['event_id' => $otherEvent->id]);

        $response = $this->postOrder($this->orderBody([
            'patron_id' => $foreignPatron->id,
            'table_id' => $foreignTable->id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]));
        $response->assertStatus(200);

        $order = Order::where('event_id', $this->event->id)->first();
        $this->assertNull($order->patron_id);
        $this->assertNull($order->table_id);
        // No card, unpaid online orders allowed: must be recorded unpaid,
        // regardless of what the client claimed.
        $this->assertEquals(Order::PAYMENT_STATUS_UNPAID, $order->payment_status);
        $this->assertFalse((bool) $order->paid);
    }
}
```

Note: if `MenuItem::factory()` lacks an `is_selling`/`price` field in its definition, passing them as overrides still works (factories run unguarded). Check `database/factories/MenuItemFactory.php` if setUp fails and adjust overrides to the actual column names.

- [ ] **Step 2: Run to verify failures**

Run: `./vendor/bin/phpunit --filter PublicOrderPatronAssignmentTest 2>&1 | tail -6`
Expected: FAILURES — no patrons/tables are created (assignment not wired), and the injection test fails (fields pass through / payment_status not forced).

- [ ] **Step 3: Modify `PublicController::order()`**

Add imports at the top of `app/Http/ManagementApi/V1/Controllers/PublicController.php`:

```php
use App\Services\PatronAssignmentService;
```

In `order()`, immediately after the `$entity = $this->toEntity(...)` call (line ~159-165), strip client-supplied table service fields **before** the split (replicas copy attributes):

```php
        // Never trust client-provided table service fields on the public
        // endpoint: patron/table come from PatronAssignmentService below,
        // payment_status from the actual payment outcome.
        $entity->patron_id = null;
        $entity->table_id = null;
        $entity->payment_status = null;
```

After the card payment / unpaid check block (after the `} elseif (!$event->allow_unpaid_online_orders) { ... }` block ending at line ~231), resolve the patron and table:

```php
        // Resolve patron assignment (table QR flow and named orders).
        // The X-Table-Number and X-Order-Name headers are signature-validated
        // by PublicEventApiAuthentication when the event uses signed URLs.
        $patronAssignment = new PatronAssignmentService();

        $table = null;
        $tableNumber = intval(\Request::header('X-Table-Number'));
        if ($tableNumber > 0) {
            $table = $patronAssignment->findOrCreateTable($event, $tableNumber);
        }

        $name = \Request::header('X-Order-Name');
        if (!$name) {
            // Fall back to the requester field from the order form.
            foreach ($newOrders as $newOrder) {
                if ($newOrder->requester) {
                    $name = $newOrder->requester;
                    break;
                }
            }
        }

        $patron = $patronAssignment->resolvePatron($event, $name ?: null, $table);
```

Replace the final save loop (lines ~233-240) with:

```php
        foreach ($newOrders as $entity) {

            $entity->event()->associate($event);
            $entity->status = Order::STATUS_PENDING;

            if ($patron) {
                $entity->patron()->associate($patron);
            }
            if ($table) {
                $entity->table()->associate($table);
            }

            // payment_status reflects the actual outcome, never client input.
            $entity->payment_status = $entity->paid
                ? Order::PAYMENT_STATUS_PAID
                : Order::PAYMENT_STATUS_UNPAID;

            $entity->saveRecursively();

        }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter PublicOrderPatronAssignmentTest 2>&1 | tail -3`
Expected: `OK (5 tests, ...)`

- [ ] **Step 5: Full backend suite**

Run: `./vendor/bin/phpunit 2>&1 | tail -3`
Expected: `OK`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/ManagementApi/V1/Controllers/PublicController.php tests/Feature/PublicOrderPatronAssignmentTest.php
git commit -m "Wire PatronAssignmentService into the public order endpoint

Client-supplied patron_id/table_id/payment_status are stripped; patron
and table come from the assignment algorithm (X-Table-Number /
X-Order-Name), payment_status from the actual payment outcome.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Atomic patron settlement (service + endpoint)

**Files:**
- Create: `app/Services/PatronSettlementService.php`
- Modify: `app/Http/Shared/V1/Controllers/PatronController.php`
- Create: `tests/Feature/PatronSettlementServiceTest.php`

**Interfaces:**
- Consumes: integrity/sync rules from Task 2 (`payment_status = 'paid'` auto-sets `paid = true`).
- Produces:
  - `PatronSettlementService::settle(Patron $patron, ?string $paymentType = null, int $discount = 0): \Illuminate\Support\Collection` — atomically marks all the patron's unpaid orders paid; returns the settled orders (empty collection when nothing to settle → idempotent).
  - `POST /api/v1/patrons/{id}/settle` and `POST /pos-api/v1/patrons/{id}/settle`, body `{ "payment_type": "cash", "discount": 0 }`, returning the settled orders as an order resource collection. Frontend (Task 7) calls this via `PatronService.settle(patronId, paymentType, discount)`.

- [ ] **Step 1: Write the failing tests — `tests/Feature/PatronSettlementServiceTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Patron;
use App\Services\PatronSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatronSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePatronWithOrders(int $unpaid, int $paid = 0): Patron
    {
        $organisation = Organisation::factory()->create();
        $event = Event::factory()->create([
            'organisation_id' => $organisation->id,
            'allow_unpaid_table_orders' => true,
        ]);
        $patron = Patron::factory()->create(['event_id' => $event->id]);

        for ($i = 0; $i < $unpaid; $i++) {
            $order = Order::factory()->make(['event_id' => $event->id]);
            $order->patron_id = $patron->id;
            $order->payment_status = Order::PAYMENT_STATUS_UNPAID;
            $order->save();
        }

        for ($i = 0; $i < $paid; $i++) {
            $order = Order::factory()->make(['event_id' => $event->id]);
            $order->patron_id = $patron->id;
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->save();
        }

        return $patron;
    }

    public function testSettleMarksAllUnpaidOrdersPaid(): void
    {
        $patron = $this->makePatronWithOrders(3, 1);

        $settled = (new PatronSettlementService())->settle($patron, 'cash');

        $this->assertCount(3, $settled);
        $this->assertEquals(
            0,
            $patron->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->count()
        );

        foreach ($settled as $order) {
            $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
            $this->assertEquals('cash', $order->payment_type);
            $this->assertTrue((bool) $order->paid);
        }
    }

    public function testSettleIsIdempotent(): void
    {
        $patron = $this->makePatronWithOrders(2);
        $service = new PatronSettlementService();

        $first = $service->settle($patron, 'cash');
        $second = $service->settle($patron->refresh(), 'cash');

        $this->assertCount(2, $first);
        $this->assertCount(0, $second);
    }

    public function testSettleDoesNotTouchOtherPatrons(): void
    {
        $patronA = $this->makePatronWithOrders(1);
        $patronB = $this->makePatronWithOrders(1);

        (new PatronSettlementService())->settle($patronA, 'cash');

        $this->assertEquals(
            1,
            $patronB->orders()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)->count()
        );
    }

    public function testSettleRecordsDiscount(): void
    {
        $patron = $this->makePatronWithOrders(1);

        $settled = (new PatronSettlementService())->settle($patron, 'nfc', 25);

        $this->assertEquals(25, (int) $settled->first()->discount_percentage);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/phpunit --filter PatronSettlementServiceTest 2>&1 | tail -3`
Expected: ERRORS — `Class "App\Services\PatronSettlementService" not found`.

- [ ] **Step 3: Write `app/Services/PatronSettlementService.php`**

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Patron;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atomically settle all unpaid orders of a patron.
 * Used by the settle endpoint so a tab can never end up half-paid
 * after the money was already collected.
 */
class PatronSettlementService
{
    /**
     * @param Patron $patron
     * @param string|null $paymentType e.g. 'cash', 'vouchers', 'nfc'
     * @param int $discount Discount percentage (0-100) applied by the
     *   payment (e.g. NFC card discount); recorded on the orders and
     *   applied to the item prices, mirroring the single-order NFC flow.
     * @return Collection The settled orders (empty when nothing was unpaid).
     */
    public function settle(Patron $patron, ?string $paymentType = null, int $discount = 0): Collection
    {
        $discount = max(0, min(100, $discount));

        return DB::transaction(function () use ($patron, $paymentType, $discount) {
            $orders = $patron->orders()
                ->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                /** @var Order $order */
                if ($discount > 0) {
                    $order->discount_percentage = $discount;
                    foreach ($order->order as $orderItem) {
                        $orderItem->price *= $order->getDiscountFactor();
                        $orderItem->save();
                    }
                }

                $order->payment_status = Order::PAYMENT_STATUS_PAID;
                if ($paymentType) {
                    $order->payment_type = $paymentType;
                }
                $order->save();
            }

            return $orders;
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter PatronSettlementServiceTest 2>&1 | tail -3`
Expected: `OK (4 tests, ...)`

- [ ] **Step 5: Add the endpoint to `PatronController`**

In `app/Http/Shared/V1/Controllers/PatronController.php`, add imports:

```php
use App\Http\Shared\V1\ResourceDefinitions\OrderResourceDefinition;
use App\Models\Patron;
use App\Services\PatronSettlementService;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Models\ResourceResponse;
```

(Verify the `ResourceResponse` import path by checking what `PublicController.php` imports for its `ResourceResponse` — use the same one.)

In `setRoutes()`, after `$childResource->tag('patrons');` add:

```php
        // Atomic settlement endpoint
        $childResource->post('patrons/{id}/settle', 'PatronController@settle')
            ->summary('Settle all unpaid orders for a patron')
            ->parameters()->path('id')->string()->required()
            ->returns()->statusCode(200)->many(OrderResourceDefinition::class);
```

Add the action method (after `getRelationshipKey()`):

```php
    /**
     * Atomically mark all unpaid orders of this patron as paid.
     * Idempotent: settling a patron with no unpaid orders returns an
     * empty collection.
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function settle(Request $request, $id)
    {
        /** @var Patron $patron */
        $patron = Patron::findOrFail($id);

        $this->authorizeEdit($request, $patron);

        $settled = (new PatronSettlementService())->settle(
            $patron,
            $request->input('payment_type'),
            intval($request->input('discount', 0))
        );

        $readContext = $this->getContext(Action::INDEX);
        $resources = $this->toResources($settled, $readContext, OrderResourceDefinition::class);

        return new ResourceResponse($resources, $readContext);
    }
```

If `authorizeEdit` does not exist on the trait (check with `grep -rn "function authorizeEdit" vendor/catlabinteractive/`), mirror how `TableController::bulkGenerate` authorizes (`$this->authorizeCreate($request)`) but for edit — the Charon `ChildCrudController`/`CrudController` traits provide `authorizeEdit($request, $entity)` per CLAUDE.md.

- [ ] **Step 6: Verify routes are registered on both APIs**

Run: `php artisan route:list --path=settle`
Expected: two POST routes — `api/v1/patrons/{id}/settle...` and `pos-api/v1/patrons/{id}/settle...`. (The thin `ManagementApi`/`DeviceApi` PatronController stubs inherit `setRoutes` — no stub changes needed.)

- [ ] **Step 7: Full backend suite + commit**

Run: `./vendor/bin/phpunit 2>&1 | tail -3` → `OK`.

```bash
git add app/Services/PatronSettlementService.php app/Http/Shared/V1/Controllers/PatronController.php tests/Feature/PatronSettlementServiceTest.php
git commit -m "Add atomic patron settlement service and endpoint

POST /patrons/{id}/settle marks all unpaid orders paid in one DB
transaction, replacing the client-side Promise.all of individual order
updates that could half-settle a tab.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: `bar_prepares_table_orders` event setting

**Files:**
- Create: `database/migrations/2026_07_07_100000_add_bar_prepares_table_orders_to_events.php`
- Modify: `app/Models/Event.php:42-54` (`$fillable`)
- Modify: `app/Http/ManagementApi/V1/ResourceDefinitions/EventResourceDefinition.php:73`
- Modify: `app/Http/DeviceApi/V1/ResourceDefinitions/EventResourceDefinition.php:71`
- Modify: `resources/manage/js/views/Events.vue:199-204` (event edit modal)

**Interfaces:**
- Produces: `event.bar_prepares_table_orders` (bool, default false) readable/writable via the management API, readable via the device API, editable in the Manage event modal. Tasks 7-8 read it from the event objects both apps already load.

- [ ] **Step 1: Write the migration**

`database/migrations/2026_07_07_100000_add_bar_prepares_table_orders_to_events.php` (mirrors the existing `2026_03_13_120003_add_allow_unpaid_table_orders_to_events.php` style):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('bar_prepares_table_orders')->default(false)->after('allow_unpaid_table_orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('bar_prepares_table_orders');
        });
    }
};
```

Run: `php artisan migrate`
Expected: the migration runs without error.

- [ ] **Step 2: Expose the field**

In `app/Models/Event.php`, add `'bar_prepares_table_orders',` to `$fillable` after `'allow_unpaid_table_orders',`.

In `app/Http/ManagementApi/V1/ResourceDefinitions/EventResourceDefinition.php` line 73, extend the bool field array:

```php
        $this->field([ 'payment_cash', 'payment_vouchers', 'payment_cards', 'allow_unpaid_online_orders', 'allow_unpaid_table_orders', 'bar_prepares_table_orders', 'split_orders_by_categories' ])
```

In `app/Http/DeviceApi/V1/ResourceDefinitions/EventResourceDefinition.php` line 71, same addition to its bool field array.

- [ ] **Step 3: Add the checkbox to the Manage event modal**

In `resources/manage/js/views/Events.vue`, after the `allow_unpaid_table_orders` form group (lines 199-204), add:

```html
			<b-form-group>
				<label>
					<input type="checkbox" v-model="model.bar_prepares_table_orders">
					{{ $t('Table orders are prepared by the bar (waiters only deliver)') }}
				</label>
			</b-form-group>
```

- [ ] **Step 4: Verify**

Run: `php artisan tinker --execute="\$e = new \App\Models\Event(); \$e->fill(['bar_prepares_table_orders' => true]); var_dump(\$e->bar_prepares_table_orders);"`
Expected: `bool(true)`.

Run: `./vendor/bin/phpunit 2>&1 | tail -3` → `OK` (RefreshDatabase picks up the new migration).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_07_100000_add_bar_prepares_table_orders_to_events.php app/Models/Event.php app/Http/ManagementApi/V1/ResourceDefinitions/EventResourceDefinition.php app/Http/DeviceApi/V1/ResourceDefinitions/EventResourceDefinition.php resources/manage/js/views/Events.vue
git commit -m "Add bar_prepares_table_orders event setting

Default false preserves the current waiter-does-everything flow.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Frontend status constants + service methods

**Files:**
- Create: `resources/shared/js/orderStatus.js`
- Modify: `resources/shared/js/services/OrderService.js`
- Modify: `resources/shared/js/services/PatronService.js`
- Create: `resources/tests/order-status.test.js`

**Interfaces:**
- Consumes: settle endpoint from Task 5.
- Produces (used by Tasks 8-9):
  - `ORDER_STATUS = { PENDING: 'pending', PROCESSED: 'processed', PREPARED: 'prepared', DELIVERED: 'delivered', DECLINED: 'declined' }`
  - `PAYMENT_STATUS = { UNPAID: 'unpaid', PAID: 'paid', VOIDED: 'voided' }`
  - `statusVariant(status): string`, `paymentStatusVariant(status): string` (Bootstrap variants)
  - `isBarQueueOrder(order, event): boolean`
  - `OrderService.markPrepared(orderId)`, `.markDelivered(orderId)`, `.markVoided(orderId)` (each returns the update promise)
  - `PatronService.settle(patronId, paymentType = null, discount = 0)` → `POST patrons/{id}/settle`

- [ ] **Step 1: Write the failing test — `resources/tests/order-status.test.js`**

```js
import { describe, it, expect } from 'vitest';
import {
	ORDER_STATUS,
	PAYMENT_STATUS,
	statusVariant,
	paymentStatusVariant,
	isBarQueueOrder,
} from '../shared/js/orderStatus';

describe('orderStatus constants', () => {
	it('matches the backend Order model constants', () => {
		expect(ORDER_STATUS.PENDING).toBe('pending');
		expect(ORDER_STATUS.PROCESSED).toBe('processed');
		expect(ORDER_STATUS.PREPARED).toBe('prepared');
		expect(ORDER_STATUS.DELIVERED).toBe('delivered');
		expect(ORDER_STATUS.DECLINED).toBe('declined');
		expect(PAYMENT_STATUS.UNPAID).toBe('unpaid');
		expect(PAYMENT_STATUS.PAID).toBe('paid');
		expect(PAYMENT_STATUS.VOIDED).toBe('voided');
	});
});

describe('badge variants', () => {
	it('maps statuses to bootstrap variants', () => {
		expect(statusVariant('pending')).toBe('warning');
		expect(statusVariant('prepared')).toBe('info');
		expect(statusVariant('delivered')).toBe('success');
		expect(statusVariant('declined')).toBe('danger');
		expect(statusVariant('anything-else')).toBe('secondary');
	});

	it('maps payment statuses to bootstrap variants', () => {
		expect(paymentStatusVariant('unpaid')).toBe('warning');
		expect(paymentStatusVariant('paid')).toBe('success');
		expect(paymentStatusVariant('voided')).toBe('danger');
		expect(paymentStatusVariant(null)).toBe('secondary');
	});
});

describe('isBarQueueOrder', () => {
	it('always shows non-table orders', () => {
		expect(isBarQueueOrder({ patron_id: null }, { bar_prepares_table_orders: false })).toBe(true);
		expect(isBarQueueOrder({ patron_id: null }, null)).toBe(true);
	});

	it('hides table-service orders when the bar does not prepare them', () => {
		expect(isBarQueueOrder({ patron_id: 5 }, { bar_prepares_table_orders: false })).toBe(false);
		expect(isBarQueueOrder({ patron_id: 5 }, null)).toBe(false);
	});

	it('shows table-service orders when the bar prepares them', () => {
		expect(isBarQueueOrder({ patron_id: 5 }, { bar_prepares_table_orders: true })).toBe(true);
	});
});
```

- [ ] **Step 2: Run to verify failure**

Run: `npx vitest run resources/tests/order-status.test.js 2>&1 | tail -5`
Expected: FAIL — module `../shared/js/orderStatus` not found.

- [ ] **Step 3: Write `resources/shared/js/orderStatus.js`**

```js
/*
 * Order status constants shared across the three frontend apps.
 * Values must match the constants on App\Models\Order.
 */

export const ORDER_STATUS = {
	PENDING: 'pending',
	PROCESSED: 'processed',
	PREPARED: 'prepared',
	DELIVERED: 'delivered',
	DECLINED: 'declined',
};

export const PAYMENT_STATUS = {
	UNPAID: 'unpaid',
	PAID: 'paid',
	VOIDED: 'voided',
};

export function statusVariant(status) {
	switch (status) {
		case ORDER_STATUS.PENDING: return 'warning';
		case ORDER_STATUS.PREPARED: return 'info';
		case ORDER_STATUS.DELIVERED: return 'success';
		case ORDER_STATUS.DECLINED: return 'danger';
		default: return 'secondary';
	}
}

export function paymentStatusVariant(status) {
	switch (status) {
		case PAYMENT_STATUS.UNPAID: return 'warning';
		case PAYMENT_STATUS.PAID: return 'success';
		case PAYMENT_STATUS.VOIDED: return 'danger';
		default: return 'secondary';
	}
}

/**
 * Should this order appear in the bar's remote order queue?
 * Table-service orders (patron_id set) only pass through the bar when
 * the event routes them there via bar_prepares_table_orders.
 */
export function isBarQueueOrder(order, event) {
	if (!order.patron_id) {
		return true;
	}
	return !!(event && event.bar_prepares_table_orders);
}
```

- [ ] **Step 4: Add the service methods**

In `resources/shared/js/services/OrderService.js`, add the import and methods:

```js
import {ORDER_STATUS, PAYMENT_STATUS} from "../orderStatus";
```

Inside the class, after `prepare(content)`:

```js
	markPrepared(orderId) {
		return this.update(orderId, { status: ORDER_STATUS.PREPARED });
	}

	markDelivered(orderId) {
		return this.update(orderId, { status: ORDER_STATUS.DELIVERED });
	}

	markVoided(orderId) {
		return this.update(orderId, {
			status: ORDER_STATUS.DECLINED,
			payment_status: PAYMENT_STATUS.VOIDED
		});
	}
```

In `resources/shared/js/services/PatronService.js`, add inside the class:

```js
	/**
	 * Atomically settle all unpaid orders of a patron.
	 * @param patronId
	 * @param paymentType e.g. 'cash', 'vouchers', 'nfc'
	 * @param discount Discount percentage applied by the payment (0-100)
	 */
	settle(patronId, paymentType = null, discount = 0) {
		return this.execute('post', 'patrons/' + patronId + '/settle', {
			payment_type: paymentType,
			discount: discount
		});
	}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx vitest run 2>&1 | tail -5`
Expected: all pass, including the pre-existing `table-service-services.test.js`.

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/orderStatus.js resources/shared/js/services/OrderService.js resources/shared/js/services/PatronService.js resources/tests/order-status.test.js
git commit -m "Add shared order status constants and service methods

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: Extract shared OrderQueue component, rewire waiter views, atomic settle in UI

**Files:**
- Create: `resources/shared/js/components/OrderQueue.vue`
- Modify: `resources/pos/js/components/TableService.vue`
- Modify: `resources/shared/js/views/WaiterDashboard.vue`
- Modify: `resources/shared/js/views/PatronDetail.vue:103-113`

**Interfaces:**
- Consumes: `orderStatus.js` + service methods from Task 7.
- Produces: `<order-queue :order-service="..." :allow-mark-prepared="bool" ref="...">` with a public `refresh()` method. Both waiter views use it; both settle flows call `PatronService.settle`.

- [ ] **Step 1: Create `resources/shared/js/components/OrderQueue.vue`**

```vue
<!--
  - Shared order queue for the waiter views (POS TableService component
  - and the Manage WaiterDashboard). Owns its own fetching; call
  - refresh() (e.g. when its tab becomes visible).
  -->

<template>
	<div class="mt-3">
		<b-form-group>
			<b-form-checkbox v-model="filterMyOrders" inline>
				{{ $t('My orders only') }}
			</b-form-checkbox>
			<b-form-checkbox v-model="filterPreparedOnly" inline>
				{{ $t('Prepared only') }}
			</b-form-checkbox>
		</b-form-group>

		<div class="text-center" v-if="loading">
			<b-spinner :label="$t('Loading data')" />
		</div>

		<b-table v-if="!loading" striped hover :items="filteredOrders" :fields="fields">
			<template v-slot:cell(status)="row">
				<b-badge :variant="statusVariant(row.item.status)">
					{{ row.item.status }}
				</b-badge>
			</template>

			<template v-slot:cell(payment_status)="row">
				<b-badge :variant="paymentStatusVariant(row.item.payment_status)">
					{{ row.item.payment_status }}
				</b-badge>
			</template>

			<template v-slot:cell(actions)="row">
				<b-button
					v-if="allowMarkPrepared && row.item.status === ORDER_STATUS.PENDING"
					size="sm"
					variant="info"
					@click="markPrepared(row.item)"
					class="mr-1"
				>
					{{ $t('Prepared') }}
				</b-button>
				<b-button
					v-if="row.item.status !== ORDER_STATUS.DELIVERED && row.item.status !== ORDER_STATUS.DECLINED"
					size="sm"
					variant="success"
					@click="markDelivered(row.item)"
					class="mr-1"
				>
					{{ $t('Delivered') }}
				</b-button>
				<b-button
					v-if="row.item.status !== ORDER_STATUS.DECLINED"
					size="sm"
					variant="danger"
					@click="markVoided(row.item)"
				>
					{{ $t('Void') }}
				</b-button>
			</template>
		</b-table>
	</div>
</template>

<script>
	import {ORDER_STATUS, statusVariant, paymentStatusVariant} from '../orderStatus';

	export default {

		props: {
			orderService: { type: Object, required: true },
			allowMarkPrepared: { type: Boolean, default: true },
		},

		data() {
			return {
				ORDER_STATUS,
				loading: false,
				orders: [],
				filterMyOrders: false,
				filterPreparedOnly: false,
				fields: [
					{ key: 'id', label: '#' },
					{ key: 'requester', label: this.$t('Requester') },
					{ key: 'status', label: this.$t('Status') },
					{ key: 'payment_status', label: this.$t('Payment') },
					{ key: 'date', label: this.$t('Date') },
					{ key: 'actions', label: this.$t('Actions'), class: 'text-right' }
				]
			};
		},

		computed: {
			filteredOrders() {
				let orders = this.orders.filter(o =>
					o.status === ORDER_STATUS.PENDING || o.status === ORDER_STATUS.PREPARED
				);

				if (this.filterMyOrders && window.DEVICE_ID) {
					orders = orders.filter(o => o.assigned_device_id === window.DEVICE_ID);
				}

				if (this.filterPreparedOnly) {
					orders = orders.filter(o => o.status === ORDER_STATUS.PREPARED);
				}

				return orders;
			}
		},

		methods: {
			statusVariant,
			paymentStatusVariant,

			async refresh() {
				this.loading = true;
				this.orders = (await this.orderService.index({
					status: ORDER_STATUS.PENDING + ',' + ORDER_STATUS.PREPARED
				})).items;
				this.loading = false;
			},

			async markPrepared(order) {
				await this.orderService.markPrepared(order.id);
				await this.refresh();
			},

			async markDelivered(order) {
				await this.orderService.markDelivered(order.id);
				await this.refresh();
			},

			async markVoided(order) {
				if (confirm(this.$t('Are you sure you want to void this order?'))) {
					await this.orderService.markVoided(order.id);
					await this.refresh();
				}
			}
		}
	}
</script>
```

- [ ] **Step 2: Rewire `resources/pos/js/components/TableService.vue`**

1. Replace the entire Order Queue tab content (template lines 63-122, everything inside `<b-tab :title="$t('Order Queue')">`) with:

```html
			<!-- Order Queue Tab -->
			<b-tab :title="$t('Order Queue')">
				<order-queue
					v-if="tablesLoaded"
					ref="orderQueue"
					:order-service="orderService"
					:allow-mark-prepared="!(event && event.bar_prepares_table_orders)"
				></order-queue>
			</b-tab>
```

2. In the script: import and register the component:

```js
	import OrderQueue from '../../../shared/js/components/OrderQueue.vue';
	import {PAYMENT_STATUS, statusVariant, paymentStatusVariant} from '../../../shared/js/orderStatus';
```

```js
		components: {
			'live-sales': LiveSales,
			'order-queue': OrderQueue,
		},
```

3. Delete from `data()`: `loadingOrders`, `orders`, `filterMyOrders`, `filterPreparedOnly`, `orderFields`.
4. Delete the `filteredOrders` computed.
5. Replace the `activeTab` watcher body:

```js
			activeTab(newTab) {
				if (newTab === 1 && this.$refs.orderQueue) {
					this.$refs.orderQueue.refresh();
				}
			}
```

6. Delete methods `refreshOrders`, `markPrepared`, `markDelivered`, `markVoided`, and the local `statusVariant`/`paymentStatusVariant` implementations; register the imported helpers instead:

```js
			statusVariant,
			paymentStatusVariant,
```

(they are still used by the patron-order table in the modal).

7. Replace `settleBalance()` with the atomic version:

```js
			async settleBalance() {
				const unpaidOrders = this.patronOrders.filter(o => o.payment_status === PAYMENT_STATUS.UNPAID);

				if (unpaidOrders.length === 0) return;

				try {
					let paymentData = await this.$paymentService.orders(unpaidOrders);

					// One atomic settle call; idempotent, safe to retry.
					await this.patronService.settle(
						this.selectedPatron.id,
						paymentData.paymentType || null,
						paymentData.discount || 0
					);

					this.$refs.processedModal.show();
					setTimeout(() => {
						if (this.$refs.processedModal) {
							this.$refs.processedModal.hide();
						}
					}, 2000);

					// Refresh patron data
					this.selectedPatron = await this.patronService.get(this.selectedPatron.id);
					this.patronOrders = (await this.orderService.index({ patron_id: this.selectedPatron.id })).items;
					await this.refreshPatrons();

				} catch (e) {
					// Payment was cancelled
					console.log('Payment cancelled or failed:', e);
				}
			},
```

- [ ] **Step 3: Rewire `resources/shared/js/views/WaiterDashboard.vue`**

Same surgery: replace the Order Queue tab content (template lines 87-146) with:

```html
				<!-- Order Queue Tab -->
				<b-tab :title="$t('Order Queue')">
					<order-queue
						v-if="loaded"
						ref="orderQueue"
						:order-service="orderService"
						:allow-mark-prepared="!(event && event.bar_prepares_table_orders)"
					></order-queue>
				</b-tab>
```

Script changes: import/register `OrderQueue` (path `../components/OrderQueue.vue`) and the variant helpers from `../orderStatus`; delete `loadingOrders`, `orders`, `filterMyOrders`, `filterPreparedOnly`, `orderFields` from data; delete `filteredOrders` computed and methods `refreshOrders`, `markPrepared`, `markDelivered`, `markVoided`, local `statusVariant`/`paymentStatusVariant` (this view keeps no other badge usage — check the template; the patron list uses none); update the `activeTab` watcher to call `this.$refs.orderQueue.refresh()` like in Step 2.5.

- [ ] **Step 4: Atomic settle in `resources/shared/js/views/PatronDetail.vue`**

Replace `settleBalance()` (lines 103-113) with:

```js
		async settleBalance() {
			if (confirm(this.$t('Pay all outstanding orders for this patron?'))) {
				await this.patronService.settle(this.patronId);
				await this.refresh();
			}
		},
```

Also replace its local `statusVariant`/`paymentStatusVariant` methods with the imports from `../orderStatus` (add `import {statusVariant, paymentStatusVariant} from '../orderStatus';` and register them as methods: `statusVariant,` / `paymentStatusVariant,`).

- [ ] **Step 5: Build + test**

Run: `npx vitest run 2>&1 | tail -5`
Expected: all pass. The pre-existing `table-service-views.test.js` may assert on the old inline queue markup — if it fails, update its assertions to target the new `OrderQueue` component (same behaviors, new location), not by deleting coverage.

Run: `npm run dev 2>&1 | tail -5`
Expected: webpack build succeeds with no errors. Then: `git checkout -- public/ mix-manifest.json 2>/dev/null || true` to avoid committing build artifacts (check `git status` — only source files should be staged).

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/components/OrderQueue.vue resources/pos/js/components/TableService.vue resources/shared/js/views/WaiterDashboard.vue resources/shared/js/views/PatronDetail.vue resources/tests/table-service-views.test.js
git commit -m "Extract shared OrderQueue component; use atomic settle endpoint

Deduplicates the order queue between the POS TableService component and
the WaiterDashboard, and replaces per-order Promise.all settlement with
the atomic /patrons/{id}/settle call.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: Bar queue wiring in RemoteOrders

**Files:**
- Modify: `resources/shared/js/components/RemoteOrders.vue` (refresh filter at line ~233-260; `acceptOrder` at line ~371)

**Interfaces:**
- Consumes: `isBarQueueOrder(order, event)` and `ORDER_STATUS` from Task 7; `event.bar_prepares_table_orders` from Task 6 (the component already receives the `event` prop from Headquarters.vue).
- Produces: with the setting OFF, table orders never appear at the bar; with it ON, the bar's accept action marks table orders `prepared` without demanding payment.

- [ ] **Step 1: Add the import**

In the `<script>` section of `resources/shared/js/components/RemoteOrders.vue`, add:

```js
	import {isBarQueueOrder, ORDER_STATUS} from '../orderStatus';
```

- [ ] **Step 2: Filter table orders out of the queue when the bar doesn't prepare them**

In the `refresh()` method, inside the `.filter((item) => { ... })` callback, add as the **first** check (before the assigned-orders check):

```js
					// Table-service orders only pass through the bar when
					// the event routes them here.
					if (!isBarQueueOrder(item, this.event)) {
						return false;
					}
```

- [ ] **Step 3: Bar accept marks table orders prepared, without payment**

In `acceptOrder(order)`, add immediately after `this.currentOrder = order;`:

```js
				// Table-service orders: payment is the waiter's job (settle
				// flow); the bar only prepares. Mark prepared and hand back.
				if (order.patron_id) {
					order.status = ORDER_STATUS.PREPARED;
					await this.orderService.update(order.id, order);

					this.$refs.processedModal.show();
					this.refresh();
					return;
				}
```

- [ ] **Step 4: Build + manual verification**

Run: `npm run dev 2>&1 | tail -3` → build OK. Revert build artifacts as in Task 8 Step 5.

Manual walkthrough (document the outcome in the commit/PR notes; full app run needs a POS device pairing, so at minimum verify via `npx vitest run` that nothing regressed and rely on the `isBarQueueOrder` unit tests from Task 7 for the filter logic):
1. Event with `bar_prepares_table_orders = false`: waiter creates a table order → bar's remote queue does not show it; waiter queue shows Prepared + Delivered buttons.
2. Event with `bar_prepares_table_orders = true`: table order appears at the bar as pending; bar "accept" flips it to `prepared` with no payment popup; waiter queue shows only Delivered (no Prepared button).

- [ ] **Step 5: Commit**

```bash
git add resources/shared/js/components/RemoteOrders.vue
git commit -m "Route table orders through the bar queue when the event says so

With bar_prepares_table_orders on, the bar accepts table orders as
'prepared' without demanding payment; with it off they skip the bar.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: NFC — key approval re-check + dependency audit

**Files:**
- Create: `resources/shared/js/nfccards/crypto/keyApprovalStatus.ts`
- Modify: `resources/shared/js/nfccards/CardService.ts` (fields at ~line 90; `refreshCard` at line 271)
- Modify: `resources/pos/js/app.js:286-303` (boot status block)
- Create: `tests/js/keyApprovalStatus.test.ts`

**Interfaces:**
- Produces:
  - `determineKeyApprovalStatus(hasLocalKey: boolean, hasServerKey: boolean, approvedAt: string | null): 'none' | 'pending' | 'approved' | 'revoked'` — single source of truth for the status decision, used at boot and during re-checks.
  - `CardService.recheckKeyApproval(): Promise<void>` — throttled (60s) server re-check called from `refreshCard`, so a revoked device stops signing at the next card interaction rather than the next reload.

- [ ] **Step 1: Write the failing test — `tests/js/keyApprovalStatus.test.ts`**

```ts
import { determineKeyApprovalStatus } from '../../resources/shared/js/nfccards/crypto/keyApprovalStatus';

describe('determineKeyApprovalStatus', () => {
	it('is approved when local key, server key and approval exist', () => {
		expect(determineKeyApprovalStatus(true, true, '2026-01-01T00:00:00Z')).toBe('approved');
	});

	it('is pending when the server key exists but is not approved', () => {
		expect(determineKeyApprovalStatus(true, true, null)).toBe('pending');
	});

	it('is revoked when the local key exists but the server key is gone', () => {
		expect(determineKeyApprovalStatus(true, false, null)).toBe('revoked');
	});

	it('is none without a local key', () => {
		expect(determineKeyApprovalStatus(false, false, null)).toBe('none');
		expect(determineKeyApprovalStatus(false, true, '2026-01-01T00:00:00Z')).toBe('none');
	});
});
```

Run: `npx jest keyApprovalStatus 2>&1 | tail -3`
Expected: FAIL — module not found.

- [ ] **Step 2: Write `resources/shared/js/nfccards/crypto/keyApprovalStatus.ts`**

```ts
export type KeyApprovalStatus = 'none' | 'pending' | 'approved' | 'revoked';

/**
 * Decide the device key approval status from the device state.
 * Single source of truth for the POS boot sequence and the periodic
 * re-check in CardService.
 */
export function determineKeyApprovalStatus(
	hasLocalKey: boolean,
	hasServerKey: boolean,
	approvedAt: string | null
): KeyApprovalStatus {
	if (!hasLocalKey) {
		return 'none';
	}
	if (!hasServerKey) {
		return 'revoked';
	}
	if (approvedAt) {
		return 'approved';
	}
	return 'pending';
}
```

Run: `npx jest keyApprovalStatus 2>&1 | tail -3` → PASS.

- [ ] **Step 3: Add the re-check to `CardService.ts`**

Add the import near the other crypto imports:

```ts
import {determineKeyApprovalStatus} from "./crypto/keyApprovalStatus";
```

Add a field next to `keyApprovalStatus` (~line 99):

```ts
	private lastApprovalCheck: number = 0;
```

Add the method (after `isCardOperationAllowed()`):

```ts
	/**
	 * Re-check the key approval status from the server, throttled to once
	 * per minute. Called from refreshCard so a revoked device stops
	 * signing at the next card interaction instead of at the next reload.
	 */
	public async recheckKeyApproval(): Promise<void> {
		if (Date.now() - this.lastApprovalCheck < 60000) {
			return;
		}
		this.lastApprovalCheck = Date.now();

		try {
			const response = await this.axios.get('devices/current');
			const deviceData = response.data;

			const status = determineKeyApprovalStatus(
				this.keyManager !== null && this.keyManager.isInitialized(),
				!!deviceData.public_key,
				deviceData.approved_at || null
			);

			if (status !== this.keyApprovalStatus) {
				this.setKeyApprovalStatus(status);
			}
		} catch (e) {
			// Offline or request failed: keep the current status.
		}
	}
```

In `refreshCard(card, forceWrite = false)` (line 271), add at the very start of the method body:

```ts
		if (this.hasApiConnection()) {
			await this.recheckKeyApproval();
		}
```

- [ ] **Step 4: Use the shared decision function at boot**

In `resources/pos/js/app.js`, the boot block (lines ~286-303) currently decides the status with an if/else chain. Replace the chain with:

```js
				// Determine key approval status
				const keyManager = Vue.prototype.$cardService.getKeyManager();
				const hasLocalKey = !!(keyManager && keyManager.isInitialized());
				const hasServerKey = !!window.DEVICE_PUBLIC_KEY;

				const keyStatus = determineKeyApprovalStatus(hasLocalKey, hasServerKey, window.DEVICE_APPROVED_AT || null);
				Vue.prototype.$cardService.setKeyApprovalStatus(keyStatus);

				if (keyStatus === 'approved') {
					// Load approved public keys (with offline cache fallback)
					Vue.prototype.$cardService.fetchAndCachePublicKeys(window.ORGANISATION_ID);
				}
```

Add the import near the CardService import at the top of `app.js`:

```js
import {determineKeyApprovalStatus} from '../../shared/js/nfccards/crypto/keyApprovalStatus';
```

- [ ] **Step 5: Dependency audit**

Run: `npm ls elliptic 2>&1 | head -5`
Expected: `elliptic@6.6.x` (>= 6.6.1 — the version fixing the 2024 signature-verification CVEs). `package.json` already pins `^6.6.1`; no change needed unless the installed version is older, in which case run `npm install` (never `npm update`) and revert `package-lock.json` afterwards.

Run: `npm audit 2>&1 | tail -15`
Record any high/critical findings touching `elliptic`, `crypto-js`, or `socket.io-client` in the PR description. Do not fix unrelated advisories in this branch.

- [ ] **Step 6: Test + build + commit**

Run: `npx jest 2>&1 | tail -3` → all pass.
Run: `npm run dev 2>&1 | tail -3` → build OK; revert build artifacts (`git checkout -- public/ mix-manifest.json package-lock.json 2>/dev/null || true`).

```bash
git add resources/shared/js/nfccards/crypto/keyApprovalStatus.ts resources/shared/js/nfccards/CardService.ts resources/pos/js/app.js tests/js/keyApprovalStatus.test.ts
git commit -m "Re-check NFC key approval on card refresh

A revoked device now stops signing at the next card interaction
(throttled to one server check per minute) instead of at the next
reload. Extracts the status decision into a shared, tested function.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 11: Route registration pattern fix

**Files:**
- Modify: `app/Http/ManagementApi/V1/routes.php:99`

- [ ] **Step 1: Use the thin stub**

Replace line 99:

```php
                \App\Http\Shared\V1\Controllers\OrderController::setRoutes($routes);
```

with:

```php
				\App\Http\ManagementApi\V1\Controllers\OrderController::setRoutes($routes);
```

(Also fixes the indentation to tabs, matching the surrounding lines.)

- [ ] **Step 2: Verify routes unchanged**

Run: `php artisan route:list --path=api/v1/events 2>&1 | grep -c orders`
Expected: same count as before the change (compare by running it before editing). Also run `./vendor/bin/phpunit --filter OrderControllerIndexTest 2>&1 | tail -3` → `OK`.

Note: the ManagementApi `OrderController` stub extends the Shared controller but overrides `setRoutes` `$only` defaults — check `app/Http/ManagementApi/V1/Controllers/OrderController.php` first. If its `$only` differs from the Shared default (`['index', 'view']`), the route list will change; in that case make the stub's `setRoutes` pass `['index', 'view']` explicitly so behavior is identical.

- [ ] **Step 3: Commit**

```bash
git add app/Http/ManagementApi/V1/routes.php
git commit -m "Register OrderController via its ManagementApi stub per the documented pattern

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 12: i18n keys for table service

**Files:**
- Modify: `resources/shared/js/i18n/nl.js`
- Modify: `resources/shared/js/i18n/fr.js`
- Modify: `resources/shared/js/i18n/de.js`
- Modify: `resources/shared/js/i18n/es.js`

- [ ] **Step 1: Collect the key list**

Run: `grep -hoE "\\\$t\('[^']+'" resources/pos/js/components/TableService.vue resources/shared/js/views/WaiterDashboard.vue resources/shared/js/views/PatronDetail.vue resources/shared/js/views/Tables.vue resources/shared/js/components/OrderQueue.vue resources/shared/js/components/PaymentPopup.vue resources/manage/js/views/Events.vue | sort -u`

Cross-check each key against the four i18n files; add only missing ones.

- [ ] **Step 2: Add the missing keys**

Add a `// Table service` section to each file. Baseline set (extend with anything Step 1 found that's missing):

`nl.js`:

```js
    // Table service
    'Tables': 'Tafels',
    'Order Queue': 'Bestellingen',
    'No Table': 'Geen tafel',
    'Unlinked patrons': 'Niet-gekoppelde klanten',
    'Unlinked Patrons': 'Niet-gekoppelde klanten',
    'Patrons at {table}': 'Klanten aan {table}',
    'New Patron': 'Nieuwe klant',
    'No patrons at this table.': 'Geen klanten aan deze tafel.',
    'Patron': 'Klant',
    'Unpaid': 'Onbetaald',
    'Outstanding Balance': 'Openstaand saldo',
    'Pay Outstanding Balance': 'Openstaand saldo betalen',
    'Pay all outstanding orders for this patron?': 'Alle openstaande bestellingen van deze klant betalen?',
    'Orders': 'Bestellingen',
    'Items': 'Items',
    'No orders for this patron.': 'Geen bestellingen voor deze klant.',
    'New Order': 'Nieuwe bestelling',
    'Back to patron list': 'Terug naar klantenlijst',
    'My orders only': 'Alleen mijn bestellingen',
    'Prepared only': 'Alleen bereid',
    'Prepared': 'Bereid',
    'Delivered': 'Geleverd',
    'Void': 'Annuleren',
    'Are you sure you want to void this order?': 'Ben je zeker dat je deze bestelling wil annuleren?',
    'Pay later': 'Later betalen',
    'Payment processed successfully.': 'Betaling succesvol verwerkt.',
    'Table Service': 'Tafelbediening',
    'Allow unpaid table orders (waiter can open a tab)': 'Onbetaalde tafelbestellingen toestaan (ober kan een rekening openen)',
    'Table orders are prepared by the bar (waiters only deliver)': 'Tafelbestellingen worden door de bar bereid (obers leveren enkel)',
```

`fr.js`:

```js
    // Table service
    'Tables': 'Tables',
    'Order Queue': 'Commandes',
    'No Table': 'Sans table',
    'Unlinked patrons': 'Clients non liés',
    'Unlinked Patrons': 'Clients non liés',
    'Patrons at {table}': 'Clients à {table}',
    'New Patron': 'Nouveau client',
    'No patrons at this table.': 'Aucun client à cette table.',
    'Patron': 'Client',
    'Unpaid': 'Impayé',
    'Outstanding Balance': 'Solde impayé',
    'Pay Outstanding Balance': 'Payer le solde impayé',
    'Pay all outstanding orders for this patron?': 'Payer toutes les commandes impayées de ce client ?',
    'Orders': 'Commandes',
    'Items': 'Articles',
    'No orders for this patron.': 'Aucune commande pour ce client.',
    'New Order': 'Nouvelle commande',
    'Back to patron list': 'Retour à la liste des clients',
    'My orders only': 'Mes commandes uniquement',
    'Prepared only': 'Préparées uniquement',
    'Prepared': 'Préparée',
    'Delivered': 'Livrée',
    'Void': 'Annuler',
    'Are you sure you want to void this order?': 'Voulez-vous vraiment annuler cette commande ?',
    'Pay later': 'Payer plus tard',
    'Payment processed successfully.': 'Paiement traité avec succès.',
    'Table Service': 'Service à table',
    'Allow unpaid table orders (waiter can open a tab)': 'Autoriser les commandes impayées (le serveur peut ouvrir une ardoise)',
    'Table orders are prepared by the bar (waiters only deliver)': 'Les commandes de table sont préparées par le bar (les serveurs livrent uniquement)',
```

`de.js`:

```js
    // Table service
    'Tables': 'Tische',
    'Order Queue': 'Bestellungen',
    'No Table': 'Kein Tisch',
    'Unlinked patrons': 'Nicht zugeordnete Gäste',
    'Unlinked Patrons': 'Nicht zugeordnete Gäste',
    'Patrons at {table}': 'Gäste an {table}',
    'New Patron': 'Neuer Gast',
    'No patrons at this table.': 'Keine Gäste an diesem Tisch.',
    'Patron': 'Gast',
    'Unpaid': 'Unbezahlt',
    'Outstanding Balance': 'Offener Betrag',
    'Pay Outstanding Balance': 'Offenen Betrag bezahlen',
    'Pay all outstanding orders for this patron?': 'Alle offenen Bestellungen dieses Gastes bezahlen?',
    'Orders': 'Bestellungen',
    'Items': 'Artikel',
    'No orders for this patron.': 'Keine Bestellungen für diesen Gast.',
    'New Order': 'Neue Bestellung',
    'Back to patron list': 'Zurück zur Gästeliste',
    'My orders only': 'Nur meine Bestellungen',
    'Prepared only': 'Nur zubereitete',
    'Prepared': 'Zubereitet',
    'Delivered': 'Geliefert',
    'Void': 'Stornieren',
    'Are you sure you want to void this order?': 'Diese Bestellung wirklich stornieren?',
    'Pay later': 'Später bezahlen',
    'Payment processed successfully.': 'Zahlung erfolgreich verarbeitet.',
    'Table Service': 'Tischservice',
    'Allow unpaid table orders (waiter can open a tab)': 'Unbezahlte Tischbestellungen erlauben (Kellner kann anschreiben)',
    'Table orders are prepared by the bar (waiters only deliver)': 'Tischbestellungen werden von der Bar zubereitet (Kellner liefern nur)',
```

`es.js`:

```js
    // Table service
    'Tables': 'Mesas',
    'Order Queue': 'Pedidos',
    'No Table': 'Sin mesa',
    'Unlinked patrons': 'Clientes sin mesa',
    'Unlinked Patrons': 'Clientes sin mesa',
    'Patrons at {table}': 'Clientes en {table}',
    'New Patron': 'Nuevo cliente',
    'No patrons at this table.': 'No hay clientes en esta mesa.',
    'Patron': 'Cliente',
    'Unpaid': 'Impagado',
    'Outstanding Balance': 'Saldo pendiente',
    'Pay Outstanding Balance': 'Pagar saldo pendiente',
    'Pay all outstanding orders for this patron?': '¿Pagar todos los pedidos pendientes de este cliente?',
    'Orders': 'Pedidos',
    'Items': 'Artículos',
    'No orders for this patron.': 'No hay pedidos para este cliente.',
    'New Order': 'Nuevo pedido',
    'Back to patron list': 'Volver a la lista de clientes',
    'My orders only': 'Solo mis pedidos',
    'Prepared only': 'Solo preparados',
    'Prepared': 'Preparado',
    'Delivered': 'Entregado',
    'Void': 'Anular',
    'Are you sure you want to void this order?': '¿Seguro que quieres anular este pedido?',
    'Pay later': 'Pagar después',
    'Payment processed successfully.': 'Pago procesado correctamente.',
    'Table Service': 'Servicio de mesa',
    'Allow unpaid table orders (waiter can open a tab)': 'Permitir pedidos de mesa impagados (el camarero puede abrir una cuenta)',
    'Table orders are prepared by the bar (waiters only deliver)': 'Los pedidos de mesa los prepara la barra (los camareros solo entregan)',
```

Before adding any key, grep the target file for it — some (e.g. `'Tables'`, `'Orders'`, `'Unpaid'`) may already exist; never duplicate a key.

- [ ] **Step 3: Build + commit**

Run: `npm run dev 2>&1 | tail -3` → OK (catches syntax errors in the i18n files). Revert build artifacts.

```bash
git add resources/shared/js/i18n/nl.js resources/shared/js/i18n/fr.js resources/shared/js/i18n/de.js resources/shared/js/i18n/es.js
git commit -m "Add table service translations (nl, fr, de, es)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 13: Documentation updates + final verification

**Files:**
- Modify: `CLAUDE.md` (Building & Testing section)
- Modify: `.ai/table-service.md`
- Modify: `.ai/signed-order-urls.md`

- [ ] **Step 1: Update `CLAUDE.md`**

In the Backend section, replace:

```
No automated test suite exists currently. Manual testing is required.
```

with:

```
PHPUnit test suite: `./vendor/bin/phpunit` (Unit + Feature; Feature tests need a MySQL
database `catlab_drinks_test`, user `test`/`test` — see phpunit.xml).
```

- [ ] **Step 2: Update `.ai/table-service.md`**

- In the *Patron Assignment Algorithm* section, add at the top: patron assignment is invoked by `PublicController@order` for public/remote orders; the table number arrives via the `X-Table-Number` header (signature-covered when signed URLs are in use), the name via `X-Order-Name` or the order's `requester` field.
- In *API Endpoints*, add: `POST /patrons/{id}/settle` (both APIs) — atomically settles all unpaid orders; body `{payment_type, discount}`; idempotent.
- In *Updated Order Fields*, note: on the public endpoint `patron_id`/`table_id`/`payment_status` are server-forced; on authenticated APIs cross-event references are rejected (model-level validation) and `payment_status` transitions to `unpaid` require the event to allow unpaid orders.
- In *Event Settings*, add the `bar_prepares_table_orders` row: bool, default false; when true, table orders enter the bar's remote queue as `pending`, the bar marks them `prepared` (no payment demanded), waiters only deliver; when false, table orders skip the bar queue and waiters mark prepared/delivered themselves.
- In *Component Architecture*, add `OrderQueue.vue` (`shared/js/components/`) and mention `orderStatus.js` as the constants module; note settle flows call `PatronService.settle()`.

- [ ] **Step 3: Update `.ai/signed-order-urls.md`**

- Signable parameters: `card`, `name`, `table`. Signature is **required** only when `card` or `name` is present; a bare `table` parameter is accepted unsigned (no authority attached). When a signature is present it must cover all present signable params, `table` included (sort order: `card`, `name`, `table`).
- Add `X-Table-Number` to the HTTP headers table.
- Add `table` to the URL format example: `https://drinks.catlab.eu/order/{public_token}?table=12` (unsigned) and `...?card=player1&table=12&signature={hex}` (signed).

- [ ] **Step 4: Final full verification**

```bash
./vendor/bin/phpunit 2>&1 | tail -3          # OK
npx vitest run 2>&1 | tail -3                # OK
npx jest 2>&1 | tail -3                      # OK
npm run production 2>&1 | tail -3            # build OK
git checkout -- public/ mix-manifest.json package-lock.json 2>/dev/null || true
php artisan route:list --path=settle | head  # both settle routes present
```

- [ ] **Step 5: Commit**

```bash
git add CLAUDE.md .ai/table-service.md .ai/signed-order-urls.md
git commit -m "Update docs for table service completion

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
