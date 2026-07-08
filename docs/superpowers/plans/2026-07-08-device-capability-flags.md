# Device Capability Flags (sales / topup) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin-controlled `allow_sales` / `allow_topup` flags per POS device, so bar devices can be locked out of card topup/reset while topup stations can be locked out of sales — with server-side audit flagging of unauthorized topup transactions.

**Architecture:** Two boolean columns on `devices`, writeable only through the Management API (Device API exposes them read-only). The POS reads the flags at boot from `GET /pos-api/v1/devices/current` and gates navigation with a Vue Router guard. Because card writes happen offline with the device signing key, the server does not reject rogue topups — it marks them `unauthorized` at upload time (`merge-transactions` endpoint) for admin review.

**Tech Stack:** Laravel 8-style backend with CatLab Charon REST framework, Vue 3 (@vue/compat) + Bootstrap-Vue frontends, Laravel Mix build, Vitest for JS route/guard tests.

**Spec:** `docs/superpowers/specs/2026-07-08-device-capability-flags-design.md`

## Global Constraints

- Never run `npm update`; use `npm install` only. Never commit `package-lock.json` changes — run `git checkout -- package-lock.json` before committing.
- PHP deps: lock file requires PHP ~8.1/~8.2; use `composer install --ignore-platform-reqs` if needed (vendor/ is already installed).
- No PHP test suite exists — backend tasks are verified manually via `php artisan migrate`, `php artisan route:list`, and `php artisan tinker`. The local MySQL comes from `docker-compose.yml`; run `docker compose up -d` if the DB is not running.
- JS tests: `npx vitest run` (route/view tests), `npx jest` (NFC tests). Both must pass before every commit that touches JS.
- All user-facing strings go through `$t('...')` and must be added to **all five** locale files: `resources/shared/js/i18n/{en,nl,fr,de,es}.js` (identical key sets; keys are the English strings).
- Tabs for indentation in PHP and Vue files (match existing files).
- Both new device flags default to `true` — existing devices must keep working unchanged after migration.
- End every commit message with:
  ```
  Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01RQjdQhQM3q7MZCWCv3AdmD
  ```
  (The plan's `git commit -m` commands omit this for brevity — always append it.)

---

### Task 1: Device capability columns + model

**Files:**
- Create: `database/migrations/2026_07_08_100000_add_capability_flags_to_devices.php`
- Modify: `app/Models/Device.php:24-39` (fillable/casts), `app/Models/Device.php:78-88` (updated hook)

**Interfaces:**
- Produces: `Device::$allow_sales` (bool, default true), `Device::$allow_topup` (bool, default true) — used by Tasks 3, 4, 5.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_07_08_100000_add_capability_flags_to_devices.php`:

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
		Schema::table('devices', function (Blueprint $table) {
			$table->boolean('allow_sales')->default(true)->after('allow_live_orders');
			$table->boolean('allow_topup')->default(true)->after('allow_sales');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('devices', function (Blueprint $table) {
			$table->dropColumn('allow_sales');
			$table->dropColumn('allow_topup');
		});
	}
};
```

- [ ] **Step 2: Update the Device model**

In `app/Models/Device.php`, extend `$fillable` and `$casts`:

```php
	protected $fillable = [
		'uid',
		'name',
		'secret_key',
		'category_filter_id',
		'allow_remote_orders',
		'allow_live_orders',
		'allow_sales',
		'allow_topup',
	];

	protected $casts = [
		'last_ping' => 'datetime',
		'last_activity' => 'datetime',
		'allow_remote_orders' => 'boolean',
		'allow_live_orders' => 'boolean',
		'allow_sales' => 'boolean',
		'allow_topup' => 'boolean',
		'approved_at' => 'datetime',
	];
```

In the `static::updated` hook (currently around line 78), extend the reassignment condition so switching sales off reassigns that device's pending remote orders:

```php
			// If settings affecting order assignment changed, re-evaluate assignments
			$needsReassignment = $device->wasChanged('category_filter_id')
				|| ($device->wasChanged('allow_remote_orders') && !$device->allow_remote_orders)
				|| ($device->wasChanged('allow_sales') && !$device->allow_sales);
```

- [ ] **Step 3: Run the migration and verify**

```bash
docker compose up -d   # only if DB not already running
php artisan migrate
```
Expected: `2026_07_08_100000_add_capability_flags_to_devices ... DONE`

Verify defaults with tinker:

```bash
php artisan tinker --execute="var_dump(\App\Models\Device::query()->first()?->allow_sales, \App\Models\Device::query()->first()?->allow_topup);"
```
Expected: `bool(true)` twice (or `NULL NULL` if the table is empty — then just check the columns exist: `php artisan tinker --execute="var_dump(\Illuminate\Support\Facades\Schema::hasColumns('devices', ['allow_sales','allow_topup']));"` → `bool(true)`).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_08_100000_add_capability_flags_to_devices.php app/Models/Device.php
git commit -m "Add allow_sales/allow_topup capability flags to devices"
```

---

### Task 2: Transaction audit columns + model

**Files:**
- Create: `database/migrations/2026_07_08_100001_add_upload_audit_to_card_transactions.php`
- Modify: `app/Models/Transaction.php` (casts + relationship)

**Interfaces:**
- Produces: `Transaction::$uploaded_by_device_id` (nullable FK), `Transaction::$unauthorized` (bool, default false), `Transaction::uploadedByDevice()` relation — used by Tasks 3 and 4.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_07_08_100001_add_upload_audit_to_card_transactions.php`:

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
		Schema::table('card_transactions', function (Blueprint $table) {
			$table->unsignedBigInteger('uploaded_by_device_id')->nullable();
			$table->foreign('uploaded_by_device_id')->references('id')->on('devices')->nullOnDelete();
			$table->boolean('unauthorized')->default(false);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('card_transactions', function (Blueprint $table) {
			$table->dropForeign(['uploaded_by_device_id']);
			$table->dropColumn('uploaded_by_device_id');
			$table->dropColumn('unauthorized');
		});
	}
};
```

- [ ] **Step 2: Update the Transaction model**

In `app/Models/Transaction.php`, add a `$casts` property (the model currently has none) right after `$fillable`, and add the relationship method after the existing `topup()` method:

```php
    protected $casts = [
        'unauthorized' => 'boolean',
    ];
```

```php
    /**
     * The device that uploaded this transaction to the server.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function uploadedByDevice()
    {
        return $this->belongsTo(Device::class, 'uploaded_by_device_id');
    }
```

Add `use App\Models\Device;` only if the model isn't already in the same namespace — it is (`App\Models`), so no import is needed.

- [ ] **Step 3: Run the migration and verify**

```bash
php artisan migrate
php artisan tinker --execute="var_dump(\Illuminate\Support\Facades\Schema::hasColumns('card_transactions', ['uploaded_by_device_id','unauthorized']));"
```
Expected: `bool(true)`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_08_100001_add_upload_audit_to_card_transactions.php app/Models/Transaction.php
git commit -m "Add upload audit columns to card_transactions"
```

---

### Task 3: API exposure — Management writeable, Device API read-only

**Files:**
- Modify: `app/Http/ManagementApi/V1/ResourceDefinitions/DeviceResourceDefinition.php:97-105`
- Modify: `app/Http/DeviceApi/V1/ResourceDefinitions/DeviceResourceDefinition.php:66-74`
- Modify: `app/Http/DeviceApi/V1/ResourceDefinitions/TransactionResourceDefinition.php:112-118`

**Interfaces:**
- Consumes: model fields from Tasks 1–2.
- Produces: `allow_sales`/`allow_topup` in Management API device resources (writeable) and in `GET /pos-api/v1/devices/current` (read-only); `unauthorized` field visible on transaction resources (both APIs share this definition). Task 6 writes these fields; Task 7 reads them; Task 9 reads `unauthorized`.

**Security note:** In the Device API definition the new fields get NO `writeable()` call — Charon fields are non-writeable by default, which is exactly what makes `PUT /pos-api/v1/devices/current` ignore them.

- [ ] **Step 1: Management API — add writeable fields**

In `app/Http/ManagementApi/V1/ResourceDefinitions/DeviceResourceDefinition.php`, after the `allow_live_orders` field block (line ~102), add:

```php
        $this->field('allow_sales')
            ->bool()
            ->visible(true)
            ->writeable(true, true);

        $this->field('allow_topup')
            ->bool()
            ->visible(true)
            ->writeable(true, true);
```

- [ ] **Step 2: Device API — add read-only fields**

In `app/Http/DeviceApi/V1/ResourceDefinitions/DeviceResourceDefinition.php`, after the `allow_live_orders` field block (line ~71), add:

```php
		$this->field('allow_sales')
			->bool()
			->visible(true)
			->writeable(false);

		$this->field('allow_topup')
			->bool()
			->visible(true)
			->writeable(false);
```

- [ ] **Step 3: Expose `unauthorized` on transactions**

In `app/Http/DeviceApi/V1/ResourceDefinitions/TransactionResourceDefinition.php` (shared by both APIs via the Shared TransactionController), after the `client_date` field block (line ~110), add:

```php
        $this->field('unauthorized')
            ->bool()
            ->visible(true);
```

- [ ] **Step 4: Verify routes still register**

```bash
php artisan route:list --path=pos-api | head -30
php artisan route:list --path=api/v1/devices
```
Expected: routes list without errors (a Charon definition typo throws during route registration).

- [ ] **Step 5: Commit**

```bash
git add app/Http/ManagementApi/V1/ResourceDefinitions/DeviceResourceDefinition.php app/Http/DeviceApi/V1/ResourceDefinitions/DeviceResourceDefinition.php app/Http/DeviceApi/V1/ResourceDefinitions/TransactionResourceDefinition.php
git commit -m "Expose capability flags via APIs (admin-writeable, device read-only)"
```

---

### Task 4: Flag unauthorized topups at upload (TransactionMerger)

**Files:**
- Modify: `app/Tools/TransactionMerger.php`
- Modify: `app/Http/DeviceApi/V1/Controllers/TransactionController.php:64-73`

**Interfaces:**
- Consumes: `Device::$allow_topup`, `Transaction::$uploaded_by_device_id`, `Transaction::$unauthorized` (Tasks 1–2).
- Produces: `TransactionMerger::__construct(Organisation $organisation, ?Device $uploadingDevice = null)` — new optional second parameter. All other callers keep working (search confirmed the only instantiation is in the Device API TransactionController).

**Background (why here):** All typed transactions (topup/reset/sale/refund) reach the server exclusively through `POST /pos-api/v1/organisations/{id}/merge-transactions`, and each device only uploads transactions it performed itself (they come from its local pending queue). Second-hand card observations arrive via the separate card-data endpoint as `unknown` placeholders, so flagging by uploader cannot false-positive on legitimate topups observed by bar devices.

- [ ] **Step 1: Add the uploading device to TransactionMerger**

In `app/Tools/TransactionMerger.php`:

Add import below the existing ones (line ~28):

```php
use App\Models\Device;
```

Replace the constructor:

```php
    /**
     * @var Device|null
     */
    private $uploadingDevice;

    public function __construct(Organisation $organisation, ?Device $uploadingDevice = null)
    {
        $this->organisation = $organisation;
        $this->uploadingDevice = $uploadingDevice;
    }
```

In `mergeTransaction()`, after `$transaction->mergeFromTransaction($entity);` and **before** the `if (!$transaction->exists && ...)` balance block, add:

```php
            if ($this->uploadingDevice) {
                if (!$transaction->uploaded_by_device_id) {
                    $transaction->uploaded_by_device_id = $this->uploadingDevice->id;
                }

                // Devices without topup permission cannot legitimately produce
                // balance-adding transactions; keep them but mark for review.
                if (
                    !$this->uploadingDevice->allow_topup &&
                    in_array($entity->transaction_type, [
                        Transaction::TYPE_TOPUP,
                        Transaction::TYPE_RESET,
                        Transaction::TYPE_REFUND,
                    ])
                ) {
                    $transaction->unauthorized = true;
                }
            }
```

- [ ] **Step 2: Pass the authenticated device from the controller**

In `app/Http/DeviceApi/V1/Controllers/TransactionController.php`, `mergeTransactions()` (line ~73), replace:

```php
        $transactionMerger = new TransactionMerger($organisation);
```

with:

```php
        $uploadingDevice = $request->user() instanceof \App\Models\Device ? $request->user() : null;
        $transactionMerger = new TransactionMerger($organisation, $uploadingDevice);
```

- [ ] **Step 3: Verify**

```bash
php artisan route:list --path=pos-api > /dev/null && echo OK
grep -rn "new TransactionMerger" app/
```
Expected: `OK`; the grep shows only the Device API TransactionController instantiation (with the new argument).

Logic check via tinker (uses first organisation + a fabricated device; skip if DB is empty):

```bash
php artisan tinker --execute="
\$org = \App\Models\Organisation::first();
\$device = new \App\Models\Device(['allow_topup' => false]);
\$device->id = 999999;
\$m = new \App\Tools\TransactionMerger(\$org, \$device);
echo 'constructed ok';
"
```
Expected: `constructed ok`

- [ ] **Step 4: Commit**

```bash
git add app/Tools/TransactionMerger.php app/Http/DeviceApi/V1/Controllers/TransactionController.php
git commit -m "Flag topup/reset/refund uploads from non-topup devices as unauthorized"
```

---

### Task 5: Exclude sales-disabled devices from order assignment

**Files:**
- Modify: `app/Services/OrderAssignmentService.php:92-103` (reevaluate check), `app/Services/OrderAssignmentService.php:183-186` (findBestDevice query)

**Interfaces:**
- Consumes: `Device::$allow_sales` (Task 1).

- [ ] **Step 1: Update `findBestDevice()`**

In `app/Services/OrderAssignmentService.php` (line ~183), add the `allow_sales` condition to the online-devices query:

```php
		// Get all online devices for this organisation that accept remote orders
		$onlineDevices = Device::where('organisation_id', $event->organisation_id)
			->where('last_ping', '>', $cutoff)
			->where('allow_remote_orders', true)
			->where('allow_sales', true)
			->get();
```

- [ ] **Step 2: Update `reevaluateAssignments()`**

In the same file (line ~93), extend the changed-device check:

```php
					// If this order belongs to the device that just changed its settings,
					// check if the device still accepts this order
					if ($changedDevice && $device->id === $changedDevice->id) {
						// Device disabled remote orders or sales — must reassign
						if (!$device->allow_remote_orders || !$device->allow_sales) {
							// Fall through to reassign
						} else {
```

- [ ] **Step 3: Verify**

```bash
php artisan tinker --execute="new \App\Services\OrderAssignmentService(); echo 'ok';"
```
Expected: `ok`

- [ ] **Step 4: Commit**

```bash
git add app/Services/OrderAssignmentService.php
git commit -m "Exclude sales-disabled devices from remote order assignment"
```

---

### Task 6: Manage UI — edit capabilities + list badges

**Files:**
- Modify: `resources/manage/js/views/Devices.vue` (edit modal ~line 185-198, fields array ~line 260-290, list cell templates ~line 47, `saveEdit()` ~line 375)
- Modify: `resources/shared/js/i18n/en.js`, `nl.js`, `fr.js`, `de.js`, `es.js`

**Interfaces:**
- Consumes: Management API `allow_sales` / `allow_topup` (Task 3).

- [ ] **Step 1: Add checkboxes to the edit-device modal**

In `resources/manage/js/views/Devices.vue`, inside the edit modal (after the Device Name `b-form-group`, before `<template #modal-footer>`), add:

```html
		<b-form-group :description="$t('This device can process orders and sales.')">
			<b-form-checkbox v-model="editModel.allow_sales">
				{{ $t('Allow sales') }}
			</b-form-checkbox>
		</b-form-group>

		<b-form-group :description="$t('This device can topup, reset and manage NFC cards.')">
			<b-form-checkbox v-model="editModel.allow_topup">
				{{ $t('Allow card topups') }}
			</b-form-checkbox>
		</b-form-group>
```

- [ ] **Step 2: Send the fields in `saveEdit()`**

Replace the `saveEdit` method body:

```js
			async saveEdit() {
				await this.service.update(this.editModel.id, {
					name: this.editModel.name,
					allow_sales: !!this.editModel.allow_sales,
					allow_topup: !!this.editModel.allow_topup
				});
				this.resetEditForm();
				this.refreshDevices();
			},
```

- [ ] **Step 3: Add a capabilities column**

In the `fields` array in `data()`, insert after the `status` entry:

```js
					{
						key: 'capabilities',
						label: this.$t('Capabilities'),
					},
```

In the template, next to the other `cell(...)` slots (e.g. after the `cell(status)` template), add:

```html
					<template v-slot:cell(capabilities)="row">
						<span v-if="row.item.allow_sales !== false" class="badge badge-info mr-1">{{ $t('Sales') }}</span>
						<span v-if="row.item.allow_topup !== false" class="badge badge-success">{{ $t('Topup') }}</span>
						<span v-if="row.item.allow_sales === false && row.item.allow_topup === false" class="badge badge-danger">{{ $t('Disabled') }}</span>
					</template>
```

- [ ] **Step 4: Add i18n keys**

Add to **all five** files `resources/shared/js/i18n/{en,nl,fr,de,es}.js`, in a new `// Device capabilities` section before the closing `}`:

en.js:
```js
    // Device capabilities
    'Allow sales': 'Allow sales',
    'Allow card topups': 'Allow card topups',
    'This device can process orders and sales.': 'This device can process orders and sales.',
    'This device can topup, reset and manage NFC cards.': 'This device can topup, reset and manage NFC cards.',
    'Capabilities': 'Capabilities',
    'Sales': 'Sales',
    'Disabled': 'Disabled',
```

nl.js:
```js
    // Device capabilities
    'Allow sales': 'Verkoop toestaan',
    'Allow card topups': 'Kaarten opwaarderen toestaan',
    'This device can process orders and sales.': 'Dit apparaat kan bestellingen en verkopen verwerken.',
    'This device can topup, reset and manage NFC cards.': 'Dit apparaat kan NFC-kaarten opwaarderen, resetten en beheren.',
    'Capabilities': 'Functies',
    'Sales': 'Verkoop',
    'Disabled': 'Uitgeschakeld',
```

fr.js:
```js
    // Device capabilities
    'Allow sales': 'Autoriser les ventes',
    'Allow card topups': 'Autoriser le rechargement des cartes',
    'This device can process orders and sales.': 'Cet appareil peut traiter les commandes et les ventes.',
    'This device can topup, reset and manage NFC cards.': 'Cet appareil peut recharger, réinitialiser et gérer les cartes NFC.',
    'Capabilities': 'Fonctions',
    'Sales': 'Ventes',
    'Disabled': 'Désactivé',
```

de.js:
```js
    // Device capabilities
    'Allow sales': 'Verkäufe erlauben',
    'Allow card topups': 'Kartenaufladung erlauben',
    'This device can process orders and sales.': 'Dieses Gerät kann Bestellungen und Verkäufe verarbeiten.',
    'This device can topup, reset and manage NFC cards.': 'Dieses Gerät kann NFC-Karten aufladen, zurücksetzen und verwalten.',
    'Capabilities': 'Funktionen',
    'Sales': 'Verkauf',
    'Disabled': 'Deaktiviert',
```

es.js:
```js
    // Device capabilities
    'Allow sales': 'Permitir ventas',
    'Allow card topups': 'Permitir recargas de tarjetas',
    'This device can process orders and sales.': 'Este dispositivo puede procesar pedidos y ventas.',
    'This device can topup, reset and manage NFC cards.': 'Este dispositivo puede recargar, restablecer y gestionar tarjetas NFC.',
    'Capabilities': 'Capacidades',
    'Sales': 'Ventas',
    'Disabled': 'Desactivado',
```

Note: `'Topup'` already exists in all five files — do not add it again.

- [ ] **Step 5: Run JS tests and build**

```bash
npx vitest run
npm run dev
```
Expected: vitest passes; build succeeds.

- [ ] **Step 6: Commit**

```bash
git checkout -- package-lock.json 2>/dev/null || true
git add resources/manage/js/views/Devices.vue resources/shared/js/i18n/
git commit -m "Manage UI: edit device capability flags, show capability badges"
```

---

### Task 7: POS capability guard (TDD) + boot wiring + disabled screen

**Files:**
- Create: `resources/pos/js/deviceCapabilities.js`
- Create: `resources/pos/js/views/Disabled.vue`
- Test: `resources/tests/device-capabilities.test.js`
- Modify: `resources/pos/js/app.js` (imports ~line 1-90, routes ~line 119-208, guard after router creation ~line 209, boot flags ~line 239-245)
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js`

**Interfaces:**
- Consumes: `allow_sales` / `allow_topup` on the `devices/current` response (Task 3).
- Produces:
  - `getDeviceCapabilities(): { allowSales: boolean, allowTopup: boolean }` (reads `window.DEVICE_ALLOW_SALES` / `window.DEVICE_ALLOW_TOPUP`, missing ⇒ `true`)
  - `resolveCapabilityRedirect(routeName: string|null, capabilities): string|null` — returns a route **name** to redirect to, or `null` to allow. Task 8 reads the same window flags.

- [ ] **Step 1: Write the failing tests**

Create `resources/tests/device-capabilities.test.js`:

```js
/**
 * Tests for POS device capability gating (allow_sales / allow_topup).
 */
import { describe, it, expect, afterEach } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { getDeviceCapabilities, resolveCapabilityRedirect } from '../pos/js/deviceCapabilities';

const BOTH = { allowSales: true, allowTopup: true };
const SALES_ONLY = { allowSales: true, allowTopup: false };
const TOPUP_ONLY = { allowSales: false, allowTopup: true };
const NONE = { allowSales: false, allowTopup: false };

describe('resolveCapabilityRedirect', () => {

	it('allows everything when both capabilities are enabled', () => {
		['home', 'events', 'hq', 'cards', 'transactions', 'settings'].forEach((name) => {
			expect(resolveCapabilityRedirect(name, BOTH)).toBeNull();
		});
	});

	it('redirects card routes to events on sales-only devices', () => {
		expect(resolveCapabilityRedirect('cards', SALES_ONLY)).toBe('events');
		expect(resolveCapabilityRedirect('transactions', SALES_ONLY)).toBe('events');
		expect(resolveCapabilityRedirect('testTransactions', SALES_ONLY)).toBe('events');
	});

	it('keeps sales routes accessible on sales-only devices', () => {
		expect(resolveCapabilityRedirect('events', SALES_ONLY)).toBeNull();
		expect(resolveCapabilityRedirect('hq', SALES_ONLY)).toBeNull();
	});

	it('redirects sales routes to cards on topup-only devices', () => {
		['home', 'events', 'menu', 'hq', 'sales', 'summary', 'summary-names', 'attendees', 'checkIn'].forEach((name) => {
			expect(resolveCapabilityRedirect(name, TOPUP_ONLY)).toBe('cards');
		});
	});

	it('keeps card routes accessible on topup-only devices', () => {
		expect(resolveCapabilityRedirect('cards', TOPUP_ONLY)).toBeNull();
		expect(resolveCapabilityRedirect('transactions', TOPUP_ONLY)).toBeNull();
	});

	it('redirects everything gated to the disabled screen when nothing is allowed', () => {
		expect(resolveCapabilityRedirect('events', NONE)).toBe('disabled');
		expect(resolveCapabilityRedirect('cards', NONE)).toBe('disabled');
	});

	it('always allows settings and the disabled screen itself', () => {
		expect(resolveCapabilityRedirect('settings', NONE)).toBeNull();
		expect(resolveCapabilityRedirect('disabled', NONE)).toBeNull();
	});

	it('bounces away from the disabled screen when capabilities exist', () => {
		expect(resolveCapabilityRedirect('disabled', BOTH)).toBe('events');
		expect(resolveCapabilityRedirect('disabled', TOPUP_ONLY)).toBe('cards');
		expect(resolveCapabilityRedirect('disabled', SALES_ONLY)).toBe('events');
	});
});

describe('getDeviceCapabilities', () => {

	afterEach(() => {
		delete globalThis.window;
	});

	it('defaults to fully enabled when flags are missing', () => {
		globalThis.window = {};
		expect(getDeviceCapabilities()).toEqual({ allowSales: true, allowTopup: true });
	});

	it('reads explicit false flags', () => {
		globalThis.window = { DEVICE_ALLOW_SALES: false, DEVICE_ALLOW_TOPUP: false };
		expect(getDeviceCapabilities()).toEqual({ allowSales: false, allowTopup: false });
	});
});

describe('POS app wiring', () => {
	const source = readFileSync(resolve(__dirname, '..', 'pos', 'js', 'app.js'), 'utf-8');

	it('sets window capability flags from device data', () => {
		expect(source).toContain('window.DEVICE_ALLOW_SALES');
		expect(source).toContain('window.DEVICE_ALLOW_TOPUP');
	});

	it('registers the capability guard', () => {
		expect(source).toContain('resolveCapabilityRedirect');
		expect(source).toContain('router.beforeEach');
	});

	it('has the disabled route', () => {
		expect(source).toContain("name: 'disabled'");
	});
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
npx vitest run resources/tests/device-capabilities.test.js
```
Expected: FAIL — cannot resolve `../pos/js/deviceCapabilities`.

- [ ] **Step 3: Implement the capability module**

Create `resources/pos/js/deviceCapabilities.js`:

```js
/*
 * Device capability gating for the POS app.
 *
 * allow_sales / allow_topup are admin-controlled flags loaded from
 * GET /pos-api/v1/devices/current at boot. Missing flags (old cached
 * responses) are treated as enabled so existing devices keep working.
 */

// Route names that require the sales capability.
const SALES_ROUTES = [
	'home',
	'events',
	'menu',
	'hq',
	'sales',
	'summary',
	'summary-names',
	'attendees',
	'checkIn',
];

// Route names that require the topup capability.
const TOPUP_ROUTES = [
	'cards',
	'transactions',
	'testTransactions',
];

export function getDeviceCapabilities() {
	return {
		allowSales: window.DEVICE_ALLOW_SALES !== false,
		allowTopup: window.DEVICE_ALLOW_TOPUP !== false,
	};
}

/**
 * Decide whether navigation to a route is allowed.
 * Returns the route name to redirect to, or null when allowed.
 */
export function resolveCapabilityRedirect(routeName, capabilities) {
	const { allowSales, allowTopup } = capabilities;

	if (!allowSales && SALES_ROUTES.indexOf(routeName) !== -1) {
		return allowTopup ? 'cards' : 'disabled';
	}

	if (!allowTopup && TOPUP_ROUTES.indexOf(routeName) !== -1) {
		return allowSales ? 'events' : 'disabled';
	}

	if (routeName === 'disabled' && (allowSales || allowTopup)) {
		// Device regained a capability; leave the dead-end screen.
		return allowSales ? 'events' : 'cards';
	}

	return null;
}
```

- [ ] **Step 4: Create the disabled screen**

Create `resources/pos/js/views/Disabled.vue`:

```html
<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
	<b-container fluid>
		<b-alert variant="warning" :show="true" class="mt-3 text-center">
			<p class="mb-1"><strong>{{ $t('This device has no functions enabled.') }}</strong></p>
			<p class="mb-0">{{ $t('Please contact your administrator to enable sales or topups for this device.') }}</p>
		</b-alert>
	</b-container>
</template>

<script>
	export default {};
</script>
```

- [ ] **Step 5: Wire into `resources/pos/js/app.js`**

Add imports near the other view imports at the top of the file:

```js
import Disabled from './views/Disabled.vue';
import { getDeviceCapabilities, resolveCapabilityRedirect } from './deviceCapabilities';
```

Add the route to the `routes` array (after the `financialOverview` entry):

```js
			{
				path: '/disabled',
				name: 'disabled',
				component: Disabled
			}
```

Immediately after `const router = createRouter({...});`, register the guard:

```js
	router.beforeEach((to, from, next) => {
		const redirect = resolveCapabilityRedirect(to.name, getDeviceCapabilities());
		if (redirect) {
			next({ name: redirect });
		} else {
			next();
		}
	});
```

In the boot block where `window.DEVICE_NAME` etc. are set (after `window.DEVICE_PUBLIC_KEY = ...`), add:

```js
				window.DEVICE_ALLOW_SALES = deviceData.allow_sales !== false;
				window.DEVICE_ALLOW_TOPUP = deviceData.allow_topup !== false;
```

(This runs before `app.mount('#app')`, so the guard always sees resolved flags.)

- [ ] **Step 6: Add i18n keys**

Add to the `// Device capabilities` section (created in Task 6; create it if executing out of order) of all five locale files:

en.js:
```js
    'This device has no functions enabled.': 'This device has no functions enabled.',
    'Please contact your administrator to enable sales or topups for this device.': 'Please contact your administrator to enable sales or topups for this device.',
```

nl.js:
```js
    'This device has no functions enabled.': 'Dit apparaat heeft geen functies ingeschakeld.',
    'Please contact your administrator to enable sales or topups for this device.': 'Neem contact op met je beheerder om verkoop of opwaarderen voor dit apparaat in te schakelen.',
```

fr.js:
```js
    'This device has no functions enabled.': 'Cet appareil n\'a aucune fonction activée.',
    'Please contact your administrator to enable sales or topups for this device.': 'Veuillez contacter votre administrateur pour activer les ventes ou les rechargements pour cet appareil.',
```

de.js:
```js
    'This device has no functions enabled.': 'Für dieses Gerät sind keine Funktionen aktiviert.',
    'Please contact your administrator to enable sales or topups for this device.': 'Bitte kontaktiere deinen Administrator, um Verkäufe oder Aufladungen für dieses Gerät zu aktivieren.',
```

es.js:
```js
    'This device has no functions enabled.': 'Este dispositivo no tiene funciones habilitadas.',
    'Please contact your administrator to enable sales or topups for this device.': 'Ponte en contacto con tu administrador para habilitar ventas o recargas en este dispositivo.',
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
npx vitest run
```
Expected: all tests PASS, including `device-capabilities.test.js`.

- [ ] **Step 8: Commit**

```bash
git checkout -- package-lock.json 2>/dev/null || true
git add resources/pos/js/deviceCapabilities.js resources/pos/js/views/Disabled.vue resources/pos/js/app.js resources/tests/device-capabilities.test.js resources/shared/js/i18n/
git commit -m "POS: gate routes by device capability flags"
```

---

### Task 8: POS nav + settings gating

**Files:**
- Modify: `resources/pos/js/views/App.vue` (nav ~line 44-56, data ~line 190-200)
- Modify: `resources/pos/js/views/Settings.vue` (order toggles ~line 55-73, data ~line 264-286)

**Interfaces:**
- Consumes: `window.DEVICE_ALLOW_SALES` / `window.DEVICE_ALLOW_TOPUP` (set in Task 7 before the app mounts).

- [ ] **Step 1: Gate the navbar items in `App.vue`**

In `resources/pos/js/views/App.vue`, change the two nav items:

```html
					<b-nav-item :to="{ name: 'events' }"  v-if="!kioskMode && allowSales">{{ $t('Events') }}</b-nav-item>
```

```html
						<b-nav-item :to="{ name: 'cards' }" v-if="allowTopup">{{ $t('Cards') }}</b-nav-item>
```

In the component's `data()` (where `kioskMode: false` is defined, ~line 197), add:

```js
				allowSales: window.DEVICE_ALLOW_SALES !== false,
				allowTopup: window.DEVICE_ALLOW_TOPUP !== false,
```

- [ ] **Step 2: Hide the order toggles on sales-disabled devices in `Settings.vue`**

In `resources/pos/js/views/Settings.vue`, wrap the two order checkboxes (the `allow_live_orders` and `allow_remote_orders` `b-form-group` blocks) in a template guard:

```html
						<template v-if="allowSales">
							<b-form-group
								id="allow_live_orders"
								:description="$t('This terminal can process orders at the bar')"
							>
								<label>
									<input type="checkbox" v-model="allowLiveOrders"></input>
									{{ $t('Allow live orders at this terminal') }}<br />
								</label>
							</b-form-group>

							<b-form-group
								id="allow_remote_orders"
								:description="$t('This terminal can process orders from tables')"
							>
								<label>
									<input type="checkbox" v-model="allowRemoteOrders"></input>
									{{ $t('Allow remote orders at this terminal') }}<br />
								</label>
							</b-form-group>
						</template>
```

In `data()`, add:

```js
					allowSales: window.DEVICE_ALLOW_SALES !== false,
```

- [ ] **Step 3: Run tests and build**

```bash
npx vitest run
npm run dev
```
Expected: tests pass, build succeeds.

- [ ] **Step 4: Commit**

```bash
git checkout -- package-lock.json 2>/dev/null || true
git add resources/pos/js/views/App.vue resources/pos/js/views/Settings.vue
git commit -m "POS: hide sales/topup UI according to device capabilities"
```

---

### Task 9: Unauthorized badge in transaction overviews

**Files:**
- Modify: `resources/shared/js/nfccards/models/Transaction.ts` (~line 28)
- Modify: `resources/shared/js/nfccards/store/TransactionStore.ts:341-378` (`mapTransactions`)
- Modify: `resources/shared/js/components/TransactionsTable.vue:37-42`
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js`

**Interfaces:**
- Consumes: `unauthorized` field on transaction API resources (Task 3; included automatically because the store requests `fields=*`).
- Produces: `Transaction.unauthorized: boolean` on the client model.

- [ ] **Step 1: Add the property to the client Transaction model**

In `resources/shared/js/nfccards/models/Transaction.ts`, after `public uploaded = false;`:

```ts
    public unauthorized = false;
```

- [ ] **Step 2: Map the API field**

In `resources/shared/js/nfccards/store/TransactionStore.ts`, in `mapTransactions()`, after `transaction.uploaded = true;`:

```ts
                transaction.unauthorized = !!item.unauthorized;
```

- [ ] **Step 3: Show the badge in the transactions table**

In `resources/shared/js/components/TransactionsTable.vue`, replace the `cell(order)` template:

```html
            <template v-slot:cell(order)="row">
                <span v-if="row.item.order">
                    <a href="javascript:void(0)" class="btn btn-sm btn-info" v-on:click="showOrder(row.item.order)">Order #{{row.item.order.id}}</a>
                </span>
                <span v-else>{{ row.item.type }}</span>
                <span v-if="row.item.unauthorized" class="badge badge-danger ml-1" :title="$t('This transaction was uploaded by a device that is not allowed to topup cards.')">{{ $t('Unauthorized') }}</span>
            </template>
```

- [ ] **Step 4: Add i18n keys**

Add to the `// Device capabilities` section of all five locale files:

en.js:
```js
    'Unauthorized': 'Unauthorized',
    'This transaction was uploaded by a device that is not allowed to topup cards.': 'This transaction was uploaded by a device that is not allowed to topup cards.',
```

nl.js:
```js
    'Unauthorized': 'Niet toegestaan',
    'This transaction was uploaded by a device that is not allowed to topup cards.': 'Deze transactie werd geüpload door een apparaat dat geen kaarten mag opwaarderen.',
```

fr.js:
```js
    'Unauthorized': 'Non autorisé',
    'This transaction was uploaded by a device that is not allowed to topup cards.': 'Cette transaction a été envoyée par un appareil qui n\'est pas autorisé à recharger les cartes.',
```

de.js:
```js
    'Unauthorized': 'Nicht autorisiert',
    'This transaction was uploaded by a device that is not allowed to topup cards.': 'Diese Transaktion wurde von einem Gerät hochgeladen, das keine Karten aufladen darf.',
```

es.js:
```js
    'Unauthorized': 'No autorizado',
    'This transaction was uploaded by a device that is not allowed to topup cards.': 'Esta transacción fue subida por un dispositivo que no puede recargar tarjetas.',
```

- [ ] **Step 5: Run tests**

```bash
npx vitest run
npx jest
```
Expected: all pass (jest covers the NFC model code we touched).

- [ ] **Step 6: Commit**

```bash
git checkout -- package-lock.json 2>/dev/null || true
git add resources/shared/js/nfccards/models/Transaction.ts resources/shared/js/nfccards/store/TransactionStore.ts resources/shared/js/components/TransactionsTable.vue resources/shared/js/i18n/
git commit -m "Show unauthorized badge on flagged transactions"
```

---

### Task 10: Final verification & production build

**Files:** none new — verification only.

- [ ] **Step 1: Full JS test suites**

```bash
npx vitest run
npx jest
```
Expected: all pass.

- [ ] **Step 2: Production build**

```bash
npm run production
git checkout -- package-lock.json 2>/dev/null || true
git status
```
Expected: build succeeds; only `public/res/` build artifacts changed (check whether this repo commits build output — if `git status` shows `public/res/` untracked/ignored, leave it; do NOT commit `package-lock.json`).

- [ ] **Step 3: Backend smoke checks**

```bash
php artisan migrate:status | tail -5
php artisan route:list --path=pos-api > /dev/null && echo POS-API-OK
php artisan route:list --path=api/v1 > /dev/null && echo MGMT-API-OK
```
Expected: both new migrations `Ran`; `POS-API-OK`, `MGMT-API-OK`.

- [ ] **Step 4: Manual end-to-end checklist (requires running app + paired device; report what was and wasn't checked)**

1. Manage → Devices → Edit: uncheck "Allow card topups" on a device, save. Reload list → device shows only "Sales" badge.
2. On that POS device (reload): "Cards" nav item gone; navigating to `/cards` directly redirects to events.
3. `PUT /pos-api/v1/devices/current` with `{"allow_topup": true}` using the device token → response still shows `allow_topup: false` (read-only field ignored).
4. Uncheck "Allow sales" too → POS lands on the disabled screen; Settings still reachable.
5. Re-enable both → POS back to normal.
6. (If NFC hardware available) With `allow_topup` off, force a topup via the API/an unpatched client → transaction appears with "Unauthorized" badge in Manage → Transactions.

- [ ] **Step 5: Final commit (if verification produced changes)**

```bash
git status
```
If clean apart from ignored build output: done. Otherwise commit fixes with a descriptive message.
