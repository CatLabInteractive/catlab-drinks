# Menu Import and Sequence, Stage 1, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Products have a sequence. The Manage menu page is an editable table with one Save button. The user can paste a menu into an import sheet.

**Architecture:** A `position` column on `menu_items` drives all three menu reads through the Charon default order. All parsing, change tracking, and save logic live in pure JavaScript modules with Vitest tests. Two Vue components (the menu page and the import dialog) only render state and call those modules.

**Tech Stack:** Laravel, CatLab Charon, PHPUnit, Vue 3 compat with Bootstrap-Vue, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-12-menu-import-and-ordering-design.md`. Read it before you start. This plan covers stage 1 only. Stage 2 (category headings) gets its own plan after stage 1 is merged.

## Global Constraints

- Branch: `feature/menu-import-and-ordering`. Run `git branch --show-current` before each commit. Never commit on `main`.
- Read all files in `.ai/` and the root `CLAUDE.md` before the first change.
- No new npm package. Drag and drop uses native HTML5 drag events.
- Never commit `package-lock.json`. Run `git checkout -- package-lock.json` after `npm install`.
- Mouse and keyboard on a laptop are the target. No touch drag and no phone layout.
- Do not change `resources/shared/js/components/Spreadsheet.vue` or `resources/shared/js/views/Attendees.vue`.
- Do not change `resources/pos/js/views/Menu.vue`. A device can only change `is_selling`.
- All interface text goes through `$t()`. Add each new key to `en.js`, `nl.js`, `fr.js`, `de.js`, and `es.js` in `resources/shared/js/i18n/`. The key is the English text.
- API errors use the common format. Throw `ResourceValidationException` or call `abort()`. Do not build a different error shape.
- Nothing reaches the server before the user presses Save. The one exception is the creation of a new category, which stays immediate.
- Save sends only the fields that changed.
- An import never changes or removes existing products.
- PHP tests: `vendor/bin/phpunit --filter <Class>`. If the host has no MySQL test database, see the project memory notes on the docker PHPUnit runner.
- JavaScript tests: `npx vitest run <path>`.
- End each commit message with the attribution lines of the session.

## Review Focus

1. A POS device sets a product to "not selling" while the menu table is open. The user then changes the price of that product and saves. The product must stay "not selling". Task 2 (partial update test) and Task 5 (`buildSavePlan` sends only changed fields).
2. The user pastes an Excel cell that contains a line break or a quote character. The row must stay one row. Task 3.
3. A price with a thousands mark, a negative number, or text such as `25cl`. `1.250,00` reads as 1250. `-2` and `25cl` do not read as a price. Task 3.
4. A Save fails after some requests succeeded. A second Save must not create the same products again. Task 6.
5. The sequence request contains the same ID twice, or an ID of a product that was deleted a moment ago. The server answers 422 and writes nothing. Task 2.

## File Map

| File | Status | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_11_13_000000_add_position_to_menu_items.php` | new | Column and backfill |
| `app/Models/MenuItem.php` | change | Default position on create |
| `app/Http/ManagementApi/V1/ResourceDefinitions/MenuItemResourceDefinition.php` | change | `position` field, writeable, default order |
| `app/Http/DeviceApi/V1/ResourceDefinitions/MenuItemResourceDefinition.php` | change | `position` field, read only, default order |
| `app/Http/ManagementApi/V1/Controllers/MenuController.php` | change | Sequence endpoint |
| `tests/Feature/MenuSequenceTest.php` | new | Backend tests |
| `resources/manage/js/menu/priceParser.js` | new | Read a price from text |
| `resources/manage/js/menu/clipboardTable.js` | new | Clipboard text to rows and cells |
| `resources/manage/js/menu/menuImport.js` | new | Smart split, column guess, import rows |
| `resources/manage/js/menu/menuChanges.js` | new | Row state, validation, save plan |
| `resources/manage/js/menu/menuSave.js` | new | Run a save plan against the service |
| `resources/shared/js/services/MenuService.js` | change | Partial update, bulk create, sequence |
| `resources/manage/js/views/Menu.vue` | rewrite | Editable menu table |
| `resources/manage/js/components/MenuImportModal.vue` | new | Import sheet dialog |
| `resources/shared/js/i18n/*.js` | change | New text |
| `CLAUDE.md` | change | Short note on menu sequence |

Each new `.js` module has a test file next to it with the suffix `.test.js`.

---

### Task 1: Position column and sorted menu reads

**Files:**
- Create: `database/migrations/2026_11_13_000000_add_position_to_menu_items.php`
- Create: `tests/Feature/MenuSequenceTest.php`
- Modify: `app/Models/MenuItem.php`
- Modify: `app/Http/ManagementApi/V1/ResourceDefinitions/MenuItemResourceDefinition.php`
- Modify: `app/Http/DeviceApi/V1/ResourceDefinitions/MenuItemResourceDefinition.php`

**Interfaces:**
- Produces: `menu_items.position` (unsigned integer, not null, default 0). JSON field `position` on menu items in both APIs and in the public menu. All menu reads return items sorted by `position`, then `id`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MenuSequenceTest.php`. Copy the `setUp()` and the `createDeviceToken()` helper from `tests/Feature/AccessScopingTest.php`. Add a helper `makeItems(Event $event, array $names): array` that creates one `MenuItem` per name through the factory with `category_id => null` and returns the models. Make the event with `is_selling => true`, `allow_unpaid_online_orders => true`, and `order_token_secret => null`.

```php
public function testNewProductsGetTheNextPosition(): void
{
    [$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
    $this->assertEquals([1, 2, 3], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position]);
}

public function testPositionIsCountedPerEvent(): void
{
    $this->makeItems($this->event, ['A', 'B']);
    [$x] = $this->makeItems($this->otherEventOfSameOrganisation, ['X']);
    $this->assertEquals(1, $x->fresh()->position);
}

public function testManagementIndexIsSortedByPosition(): void
{
    [$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
    $c->forceFill(['position' => 1])->save(); $a->forceFill(['position' => 2])->save(); $b->forceFill(['position' => 3])->save();
    Passport::actingAs($this->user);
    $response = $this->getJson('/api/v1/events/' . $this->event->id . '/menu?records=1000')->assertStatus(200);
    $this->assertEquals(['C', 'A', 'B'], array_column($response->json('items'), 'name'));
    $this->assertEquals(1, $response->json('items.0.position'));
}

public function testDeviceIndexIsSortedByPosition(): void   // same data, GET /pos-api/v1/events/{id}/menu with the device bearer token, expect ['C','A','B']

public function testPublicMenuIsSortedByPosition(): void    // same data, GET /api/v1/public/menu.json?records=1000 with header X-Event-Token, expect ['C','A','B']

public function testEqualPositionsFallBackToId(): void      // set all three positions to 0, expect ['A','B','C']

public function testDeviceCannotWritePosition(): void
{
    [$a] = $this->makeItems($this->event, ['A']);
    $this->withHeader('Authorization', 'Bearer ' . $this->createDeviceToken($this->organisation))
        ->putJson('/pos-api/v1/menuitems/' . $a->id, ['position' => 9, 'is_selling' => false])
        ->assertStatus(200);
    $this->assertEquals(1, $a->fresh()->position);
    $this->assertFalse((bool) $a->fresh()->is_selling);
}

public function testUserCanWritePositionOnCreate(): void
{
    Passport::actingAs($this->user);
    $response = $this->postJson('/api/v1/events/' . $this->event->id . '/menu', ['name' => 'Z', 'price' => 2, 'position' => 7]);
    $response->assertSuccessful();
    $this->assertEquals(7, MenuItem::find($response->json('id'))->position);
}
```

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `vendor/bin/phpunit --filter MenuSequenceTest`
Expected: FAIL. The column `position` does not exist.

- [ ] **Step 3: Write the migration**

Add `position` as `unsignedInteger`, default 0, after `category_id`, with an index on `['event_id', 'position']`. In the same `up()`, fill existing rows: for each `event_id`, number the rows 1, 2, 3 in `id` order. Include rows that are soft deleted. `down()` drops the index and the column.

- [ ] **Step 4: Set the default position in `MenuItem`**

Add a `creating` handler in `booted()`. If `position` is empty (null or 0), set it to the highest `position` of the same `event_id` plus 1. Count soft-deleted rows too (`withTrashed()`).

- [ ] **Step 5: Add the field to both resource definitions**

Management API: `$this->field('position')->number()->visible(true)->sortable()->writeable(true, true);`
Device API: the same without `writeable`.
In both constructors, add `$this->defaultOrder('position');`. Charon reads this in `PaginationProcessor` and adds the identifier as the last sort key. If the fallback test fails, also mark the identifier as `->sortable()`.

- [ ] **Step 6: Run the tests and make sure that they pass**

Run: `vendor/bin/phpunit --filter MenuSequenceTest`
Expected: PASS, 8 tests.

- [ ] **Step 7: Run the full PHP suite and commit**

Run: `vendor/bin/phpunit`
Expected: PASS.

```bash
git add database/migrations app/Models/MenuItem.php app/Http tests/Feature/MenuSequenceTest.php
git commit -m "Add a position to menu items and sort menu reads by it"
```

---

### Task 2: Sequence endpoint and API behavior that the frontend relies on

**Files:**
- Modify: `app/Http/ManagementApi/V1/Controllers/MenuController.php`
- Modify: `tests/Feature/MenuSequenceTest.php`

**Interfaces:**
- Produces: `PUT /api/v1/events/{parentId}/menu/sequence` with body `{ "items": [ { "id": 5 }, { "id": 2 } ] }`. Response 200 `{ "success": true }`. Users of the organisation only.
- Produces (existing behavior, pinned by tests): `PUT /api/v1/menuitems/{id}` with a subset of fields changes only those fields. `POST /api/v1/events/{id}/menu` with header `X-Bulk-Request: 1` and body `{ "items": [...] }` creates all items and returns `{ "items": [...] }` with an `id` for each, in request order.

- [ ] **Step 1: Write the failing tests**

Add to `MenuSequenceTest`:

```php
public function testSequenceEndpointStoresTheSequence(): void
{
    [$a, $b, $c] = $this->makeItems($this->event, ['A', 'B', 'C']);
    Passport::actingAs($this->user);
    $this->putJson($this->sequenceUrl(), ['items' => [['id' => $c->id], ['id' => $a->id], ['id' => $b->id]]])
        ->assertStatus(200)->assertJson(['success' => true]);
    $this->assertEquals([2, 3, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position]);
}

public function testProductsMissingFromTheListGoAfterTheListedOnes(): void
{
    [$a, $b, $c, $d] = $this->makeItems($this->event, ['A', 'B', 'C', 'D']);
    Passport::actingAs($this->user);
    $this->putJson($this->sequenceUrl(), ['items' => [['id' => $d->id], ['id' => $b->id]]])->assertStatus(200);
    // listed: D=1, B=2. Not listed keep their relative sequence: A=3, C=4.
    $this->assertEquals([3, 2, 4, 1], [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position, $d->fresh()->position]);
}

public function testSequenceRejectsIdsOfADifferentEvent(): void      // one ID of an item of another event of the same organisation: 422, no position changed
public function testSequenceRejectsDuplicateIds(): void              // [a, a, b]: 422, no position changed
public function testSequenceRejectsADeletedProduct(): void           // soft delete b, send [a, b]: 422
public function testSequenceRejectsABodyWithoutItems(): void         // {}: 422. {"items": "x"}: 422
public function testSequenceDeniedForOtherOrganisation(): void       // event of otherOrganisation: 403
public function testSequenceDeniedForDevices(): void                 // PUT /pos-api/v1/events/{id}/menu/sequence with device token: 404 or 405 (route not registered)
public function testSequenceErrorUsesTheCommonErrorFormat(): void    // 422 body has error.status and error.message

public function testPartialUpdateKeepsOtherFields(): void
{
    $category = Category::factory()->create(['event_id' => $this->event->id]);
    $item = MenuItem::factory()->create([
        'event_id' => $this->event->id, 'category_id' => $category->id,
        'name' => 'Pils', 'description' => '25cl', 'price' => 2.5, 'vat_percentage' => 21, 'is_selling' => false,
    ]);
    Passport::actingAs($this->user);
    $this->putJson('/api/v1/menuitems/' . $item->id, ['price' => 3])->assertStatus(200);
    $item->refresh();
    $this->assertEquals(3, $item->price);
    $this->assertEquals('Pils', $item->name);
    $this->assertEquals('25cl', $item->description);
    $this->assertEquals(21, $item->vat_percentage);
    $this->assertEquals($category->id, $item->category_id);
    $this->assertFalse((bool) $item->is_selling);
}

public function testCategoryCanBeClearedWithNull(): void             // PUT {"category": null}: category_id becomes null, name unchanged

public function testBulkCreateReturnsIdsInRequestOrder(): void
{
    Passport::actingAs($this->user);
    $response = $this->withHeader('X-Bulk-Request', '1')->postJson('/api/v1/events/' . $this->event->id . '/menu', [
        'items' => [
            ['name' => 'One', 'price' => 1, 'vat_percentage' => 21],
            ['name' => 'Two', 'price' => 2, 'description' => 'second'],
        ],
    ]);
    $response->assertSuccessful();
    $this->assertEquals(['One', 'Two'], array_column($response->json('items'), 'name'));
    $this->assertNotNull($response->json('items.0.id'));
    $this->assertEquals(21, MenuItem::find($response->json('items.0.id'))->vat_percentage);
    $this->assertTrue((bool) MenuItem::find($response->json('items.1.id'))->is_selling);
}
```

- [ ] **Step 2: Run the tests and note which ones fail**

Run: `vendor/bin/phpunit --filter MenuSequenceTest`
Expected: the sequence tests FAIL with 404 or 405. The last three tests describe existing behavior and are expected to PASS.

If `testPartialUpdateKeepsOtherFields`, `testCategoryCanBeClearedWithNull`, or `testBulkCreateReturnsIdsInRequestOrder` fails, stop. Report the failing assertion to the owner. Tasks 5 to 7 depend on this behavior, and the fallback changes their design.

- [ ] **Step 3: Implement the endpoint**

In `App\Http\ManagementApi\V1\Controllers\MenuController::setRoutes()`, keep the result of `parent::setRoutes()` and add `->put('events/{parentId}/menu/sequence', 'MenuController@sequence')` with a summary, in the same way as `AttendeeController` registers `attendees/import`.

Add `public function sequence(Request $request)`:
1. `$event = $this->getParent($request);` then `$this->authorize('create', [MenuItem::class, $event]);`. That policy method accepts users only.
2. Read `items`. It must be a non-empty array in which each entry has an integer `id`. IDs must be unique. Each ID must be a product of this event that is not deleted. On each violation, throw `ResourceValidationException` with one message, as `AttendeeController::import()` does.
3. In one `DB::transaction`, write `position` 1..k for the listed IDs in list order. Then write k+1.. for the other products of the event (soft-deleted rows included), in their current `position`, `id` order.
4. Return `Response::json(['success' => true])`.

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `vendor/bin/phpunit --filter MenuSequenceTest`
Expected: PASS.

- [ ] **Step 5: Make sure that the route exists only in the Management API**

Run: `php artisan route:list --path=menu/sequence`
Expected: one route, `PUT api/v1/events/{parentId}/menu/sequence`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/ManagementApi/V1/Controllers/MenuController.php tests/Feature/MenuSequenceTest.php
git commit -m "Add an endpoint that stores the menu sequence"
```

---

### Task 3: Price reader and clipboard parser

**Files:**
- Create: `resources/manage/js/menu/priceParser.js`, `resources/manage/js/menu/priceParser.test.js`
- Create: `resources/manage/js/menu/clipboardTable.js`, `resources/manage/js/menu/clipboardTable.test.js`

**Interfaces:**
- Produces: `parsePrice(text: string|number|null): number|null`. Returns a number that is 0 or higher, rounded to 2 decimals, or `null`.
- Produces: `parseNumber(text): number|null`. The same number reading without currency marks, for the VAT column. Accepts a `%` mark at the end.
- Produces: `parseClipboardTable(text: string): string[][]`. All rows have the same number of cells.

- [ ] **Step 1: Write the failing tests for `parsePrice`**

```js
it.each([
  ['2,50', 2.5], ['2.5', 2.5], ['€ 4,50', 4.5], ['4,50 €', 4.5], ['€4.50', 4.5],
  ['5 euro', 5], ['5 EURO', 5], ['EUR 3', 3], ['3,-', 3], ['3.-', 3], ['0', 0], ['  2,50  ', 2.5],
  ['1.250,00', 1250], ['1,250.00', 1250], ['12', 12], [2.5, 2.5], ['2,505', 2.51],
])('reads %s as %s', (input, expected) => { expect(parsePrice(input)).toBe(expected); });

it.each(['', '   ', 'Koffie', '25cl', '33 cl', '-2', '2,50 per stuk', '?', null, undefined, '1,2,3'])(
  'does not read %s as a price', (input) => { expect(parsePrice(input)).toBeNull(); });
```

`parseNumber` tests: `'21'` gives 21, `'21%'` gives 21, `'6,5 %'` gives 6.5, `'€ 2'` gives null, `''` gives null.

- [ ] **Step 2: Run and make sure that they fail**

Run: `npx vitest run resources/manage/js/menu/priceParser.test.js`
Expected: FAIL, module not found.

- [ ] **Step 3: Implement `priceParser.js`**

Rule for the decimal mark: if the text has both `,` and `.`, the last one is the decimal mark and the other one is removed. If the text has one mark, that mark is always the decimal mark. `1.250` reads as 1.25. Only the two-mark form has a thousands mark. Accepted currency marks: `€`, `eur`, `euro`, in any letter case, before or after the number. A trailing `,-` or `.-` means zero cents. All other leftover text gives `null`.

- [ ] **Step 4: Write the failing tests for `parseClipboardTable`**

```js
it('splits tabs and lines', () => {
  expect(parseClipboardTable('Pils\t2,50\nDuvel\t4,50')).toEqual([['Pils', '2,50'], ['Duvel', '4,50']]);
});
it('accepts Windows line ends', () => {
  expect(parseClipboardTable('a\t1\r\nb\t2\r\n')).toEqual([['a', '1'], ['b', '2']]);
});
it('keeps a quoted cell with a line break as one cell', () => {
  expect(parseClipboardTable('"Duvel\n33cl"\t4,50\nCola\t2')).toEqual([['Duvel 33cl', '4,50'], ['Cola', '2']]);
});
it('reads doubled quotes inside a quoted cell', () => {
  expect(parseClipboardTable('"Wijn ""rood"""\t4')).toEqual([['Wijn "rood"', '4']]);
});
it('keeps a quote that is not at the start of a cell', () => {
  expect(parseClipboardTable('Pizza 12"\t9')).toEqual([['Pizza 12"', '9']]);
});
it('drops empty lines and trims cells', () => {
  expect(parseClipboardTable('\n  Pils \t 2 \n\n\t\nCola\t2\n')).toEqual([['Pils', '2'], ['Cola', '2']]);
});
it('pads short rows', () => {
  expect(parseClipboardTable('a\tb\tc\nd')).toEqual([['a', 'b', 'c'], ['d', '', '']]);
});
it('returns one column for plain lines', () => {
  expect(parseClipboardTable('Pils 2,50\nCola 2')).toEqual([['Pils 2,50'], ['Cola 2']]);
});
it('returns an empty list for empty text', () => {
  expect(parseClipboardTable('')).toEqual([]);
  expect(parseClipboardTable(null)).toEqual([]);
});
```

- [ ] **Step 5: Implement `clipboardTable.js`**

Scan character by character. If the first character of a cell is `"`, the cell is quoted. No other cell is quoted. Inside a quoted cell, `""` is one quote and a line break becomes one space. A row in which all cells are empty is dropped.

- [ ] **Step 6: Run both test files and make sure that they pass**

Run: `npx vitest run resources/manage/js/menu`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/manage/js/menu
git commit -m "Add a price reader and a clipboard table parser for the menu import"
```

---

### Task 4: Import logic

**Files:**
- Create: `resources/manage/js/menu/menuImport.js`, `resources/manage/js/menu/menuImport.test.js`

**Interfaces:**
- Consumes: `parsePrice`, `parseNumber` from `./priceParser`.
- Produces: `COLUMN_TYPES = ['name', 'description', 'price', 'vat_percentage', 'ignore']`.
- Produces: `smartSplitLine(line: string): [string, string]`. Returns name and price text. If the line has no price at its end, the price text is `''`.
- Produces: `prepareSheet(rows: string[][]): string[][]`. If all rows have one column, it applies `smartSplitLine` to each row. Otherwise it returns the rows unchanged.
- Produces: `guessColumnTypes(rows: string[][]): string[]`. One type per column.
- Produces: `setColumnType(types: string[], index: number, type: string): string[]`. Returns a new list. If a different column had that type, that column becomes `'ignore'`.
- Produces: `buildImportRows(rows: string[][], types: string[]): ImportRow[]` with `ImportRow = { name: string, description: string, price: number|null, vat_percentage: number|null, errors: Array<'name'|'price'> }`.
- Produces: `canImport(types: string[], importRows: ImportRow[]): boolean`. It returns true only with all three of these: `name` and `price` are both assigned, at least one row exists, and no row has errors.

- [ ] **Step 1: Write the failing tests**

```js
describe('smartSplitLine', () => {
  it.each([
    ['Pils 25cl ..... 2,50', ['Pils 25cl', '2,50']],
    ['Pils 25cl…… 2,50', ['Pils 25cl', '2,50']],
    ['Duvel - € 4,50', ['Duvel', '€ 4,50']],
    ['Duvel – 4,50 €', ['Duvel', '4,50 €']],
    ['Cola: 2,5', ['Cola', '2,5']],
    ['Croque monsieur - ham en kaas 5 euro', ['Croque monsieur - ham en kaas', '5 euro']],
    ['7up 2,50', ['7up', '2,50']],
    ['Wijn (rood) 3,-', ['Wijn (rood)', '3,-']],
    ['Koffie', ['Koffie', '']],
    ['Pils 25cl', ['Pils 25cl', '']],
    ['Pils 33 cl', ['Pils 33 cl', '']],
    ['BIEREN', ['BIEREN', '']],
    ['2,50', ['', '2,50']],
  ])('splits %s', (line, expected) => { expect(smartSplitLine(line)).toEqual(expected); });
});

describe('prepareSheet', () => {
  it('splits a one-column paste', () => {
    expect(prepareSheet([['Pils 2,50'], ['Koffie']])).toEqual([['Pils', '2,50'], ['Koffie', '']]);
  });
  it('leaves a table unchanged', () => {
    const rows = [['Pils', '2,50'], ['Cola', '2']];
    expect(prepareSheet(rows)).toEqual(rows);
  });
});

describe('guessColumnTypes', () => {
  it('finds name and price', () => {
    expect(guessColumnTypes([['Pils', '2,50'], ['Cola', '2']])).toEqual(['name', 'price']);
  });
  it('finds name, description and price, and ignores the rest', () => {
    expect(guessColumnTypes([
      ['Pils', '25cl', 'vat', '2,50'], ['Duvel', '33cl fles', 'fles', '€ 4,50'], ['Koffie', '', '', ''],
    ])).toEqual(['name', 'description', 'ignore', 'price']);
  });
  it('uses the last price-like column as the price', () => {
    expect(guessColumnTypes([['Pils', '21', '2,50'], ['Cola', '21', '2']])).toEqual(['name', 'ignore', 'price']);
  });
  it('accepts a price column before the name', () => {
    expect(guessColumnTypes([['2,50', 'Pils'], ['2', 'Cola']])).toEqual(['price', 'name']);
  });
  it('tolerates a header row and a missing price', () => {
    expect(guessColumnTypes([['Product', 'Prijs'], ['Pils', '2,50'], ['Cola', '2'], ['Koffie', '']])).toEqual(['name', 'price']);
  });
  it('returns an empty list for no rows', () => { expect(guessColumnTypes([])).toEqual([]); });
});

describe('setColumnType', () => {
  it('moves a type away from its previous column', () => {
    expect(setColumnType(['name', 'price', 'ignore'], 2, 'price')).toEqual(['name', 'ignore', 'price']);
  });
  it('allows ignore on several columns', () => {
    expect(setColumnType(['name', 'price', 'ignore'], 0, 'ignore')).toEqual(['ignore', 'price', 'ignore']);
  });
});

describe('buildImportRows', () => {
  it('builds rows and marks errors', () => {
    expect(buildImportRows(
      [['Pils', '25cl', '2,50', '21%'], ['Koffie', '', '', ''], ['', '', '3', ''], ['Thee', '', 'gratis', '']],
      ['name', 'description', 'price', 'vat_percentage'],
    )).toEqual([
      { name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, errors: [] },
      { name: 'Koffie', description: '', price: null, vat_percentage: null, errors: ['price'] },
      { name: '', description: '', price: 3, vat_percentage: null, errors: ['name'] },
      { name: 'Thee', description: '', price: null, vat_percentage: null, errors: ['price'] },
    ]);
  });
});

describe('canImport', () => {
  it('needs a name column and a price column', () => {
    const rows = buildImportRows([['Pils', '2']], ['name', 'ignore']);
    expect(canImport(['name', 'ignore'], rows)).toBe(false);
  });
  it('is false while a row has errors', () => {
    const types = ['name', 'price'];
    expect(canImport(types, buildImportRows([['Pils', '2'], ['Koffie', '']], types))).toBe(false);
  });
  it('is false without rows', () => { expect(canImport(['name', 'price'], [])).toBe(false); });
  it('is true for clean rows', () => {
    const types = ['name', 'price'];
    expect(canImport(types, buildImportRows([['Pils', '2']], types))).toBe(true);
  });
});
```

- [ ] **Step 2: Run and make sure that they fail**

Run: `npx vitest run resources/manage/js/menu/menuImport.test.js`
Expected: FAIL, module not found.

- [ ] **Step 3: Implement `menuImport.js`**

`smartSplitLine`: try the longest tail of the line, up to 3 space-separated tokens, for which `parsePrice` gives a number. The tail must start at a token boundary. Then clean the end of the remaining name. Remove dot leaders (`.` or `…` repeated), dashes (`-`, `–`, `—`), colons, and spaces.

`guessColumnTypes`: count the non-empty cells of a column. If `parsePrice` gives a number for more than half of them, the column is price-like. The last price-like column is `price`. The first column that is not price-like and has a non-empty cell is `name`. The second such column is `description`. All others are `ignore`.

- [ ] **Step 4: Run and make sure that they pass**

Run: `npx vitest run resources/manage/js/menu`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/menu
git commit -m "Add smart split, column guess and row building for the menu import"
```

---

### Task 5: Change tracking for the menu table

**Files:**
- Create: `resources/manage/js/menu/menuChanges.js`, `resources/manage/js/menu/menuChanges.test.js`

**Interfaces:**
- Consumes: `parsePrice`, `parseNumber` from `./priceParser`.
- Item: the API shape `{ id, name, description, price, vat_percentage, is_selling, position, category: {id, name}|null }`.
- Row: `{ key: number, id: number|null, name: string, description: string, price: string, vat_percentage: string, category_id: number|null, is_selling: boolean, deleted: boolean }`. `price` and `vat_percentage` are the text of the input cell.
- Produces: `rowFromItem(item: Item): Row`, `rowsFromItems(items: Item[]): Row[]`, `newRow(values?: Partial<Row>): Row` (unique `key`, `id: null`, `is_selling: true`).
- Produces: `rowFromImport(importRow, categoryId: number|null): Row`.
- Produces: `isBlank(row: Row): boolean`. True for a new row with no name, no description, and no price text.
- Produces: `rowStatus(row: Row, originals: Item[], index: number): 'clean'|'new'|'changed'|'moved'|'deleted'`. `index` is the index among rows that are not blank and not deleted new rows. `changed` wins over `moved`.
- Produces: `validateRows(rows: Row[]): Array<{ key: number, fields: Array<'name'|'price'|'vat_percentage'> }>`. Blank rows and deleted rows are skipped.
- Produces: `moveRow(rows: Row[], from: number, to: number): Row[]`. Returns a new list.
- Produces: `buildSavePlan(originals: Item[], rows: Row[]): SavePlan` with
  `SavePlan = { updates: Array<{ id: number, changes: object }>, creates: Array<{ key: number, data: object }>, deletes: number[], sequenceKeys: number[], sequenceChanged: boolean }`.
  `sequenceKeys` holds the `key` of each row that remains after Save, in table sequence.
- Produces: `hasChanges(originals: Item[], rows: Row[]): boolean`.

- [ ] **Step 1: Write the failing tests**

Use this fixture:

```js
const items = [
  { id: 1, name: 'Pils', description: null, price: 2.5, vat_percentage: 21, is_selling: true, position: 1, category: { id: 7, name: 'Drinks' } },
  { id: 2, name: 'Cola', description: 'fles', price: 2, vat_percentage: null, is_selling: false, position: 2, category: null },
  { id: 3, name: 'Croque', description: null, price: 5, vat_percentage: 6, is_selling: true, position: 3, category: null },
];
```

Tests, each with the assertion shown:

```js
it('gives an empty plan for untouched rows', () => {
  const plan = buildSavePlan(items, rowsFromItems(items));
  expect(plan).toMatchObject({ updates: [], creates: [], deletes: [], sequenceChanged: false });
  expect(hasChanges(items, rowsFromItems(items))).toBe(false);
});
it('shows null values as empty text', () => {
  expect(rowFromItem(items[1])).toMatchObject({ description: 'fles', vat_percentage: '', category_id: null, price: '2.00' });
});
it('sends only the changed field', () => {
  const rows = rowsFromItems(items); rows[0].price = '3';
  expect(buildSavePlan(items, rows).updates).toEqual([{ id: 1, changes: { price: 3 } }]);
});
it('does not send is_selling for a row with a different change', () => {
  const rows = rowsFromItems(items); rows[1].name = 'Cola zero';
  expect(buildSavePlan(items, rows).updates).toEqual([{ id: 2, changes: { name: 'Cola zero' } }]);
});
it('treats 2,50 and 2.5 as the same price', () => {
  const rows = rowsFromItems(items); rows[0].price = '2,50';
  expect(buildSavePlan(items, rows).updates).toEqual([]);
});
it('sends a category change as an object and a cleared category as null', () => {
  const rows = rowsFromItems(items); rows[0].category_id = null; rows[1].category_id = 7;
  expect(buildSavePlan(items, rows).updates).toEqual([
    { id: 1, changes: { category: null } }, { id: 2, changes: { category: { id: 7 } } },
  ]);
});
it('sends a cleared VAT and a cleared description as null', () => {
  const rows = rowsFromItems(items); rows[0].vat_percentage = ''; rows[1].description = '';
  expect(buildSavePlan(items, rows).updates).toEqual([
    { id: 1, changes: { vat_percentage: null } }, { id: 2, changes: { description: null } },
  ]);
});
it('creates new rows with all fields and ignores blank rows', () => {
  const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2' }), newRow()];
  const plan = buildSavePlan(items, rows);
  expect(plan.creates).toEqual([{ key: rows[3].key, data: {
    name: 'Thee', description: null, price: 2, vat_percentage: null, is_selling: true, category: null } }]);
  expect(plan.sequenceKeys).toEqual([rows[0].key, rows[1].key, rows[2].key, rows[3].key]);
  expect(plan.sequenceChanged).toBe(true);
});
it('deletes existing rows and drops deleted new rows', () => {
  const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2', deleted: true })];
  rows[1].deleted = true;
  const plan = buildSavePlan(items, rows);
  expect(plan.deletes).toEqual([2]);
  expect(plan.creates).toEqual([]);
  expect(plan.sequenceKeys).toEqual([rows[0].key, rows[2].key]);
  expect(plan.sequenceChanged).toBe(false);
});
it('does not update a row that is deleted', () => {
  const rows = rowsFromItems(items); rows[1].name = 'x'; rows[1].deleted = true;
  expect(buildSavePlan(items, rows).updates).toEqual([]);
});
it('flags a moved row', () => {
  const rows = moveRow(rowsFromItems(items), 2, 0);
  const plan = buildSavePlan(items, rows);
  expect(plan.sequenceChanged).toBe(true);
  expect(plan.updates).toEqual([]);
  expect(rowStatus(rows[0], items, 0)).toBe('moved');
  expect(hasChanges(items, rows)).toBe(true);
});
it('reports the status of each row', () => {
  const rows = [...rowsFromItems(items), newRow({ name: 'Thee', price: '2' })];
  rows[0].price = '3'; rows[1].deleted = true;
  expect(rows.map((row, i) => rowStatus(row, items, i))).toEqual(['changed', 'deleted', 'clean', 'new']);
});
it('validates name, price and VAT', () => {
  const rows = rowsFromItems(items);
  rows[0].name = ' '; rows[1].price = 'abc'; rows[2].vat_percentage = 'x';
  rows.push(newRow({ name: 'Thee' }), newRow());
  expect(validateRows(rows)).toEqual([
    { key: rows[0].key, fields: ['name'] }, { key: rows[1].key, fields: ['price'] },
    { key: rows[2].key, fields: ['vat_percentage'] }, { key: rows[3].key, fields: ['price'] },
  ]);
});
it('accepts a price of 0', () => {
  const rows = rowsFromItems(items); rows[0].price = '0';
  expect(validateRows(rows)).toEqual([]);
});
it('skips deleted rows in validation', () => {
  const rows = rowsFromItems(items); rows[0].name = ''; rows[0].deleted = true;
  expect(validateRows(rows)).toEqual([]);
});
it('builds a row from an import row', () => {
  expect(rowFromImport({ name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, errors: [] }, 7))
    .toMatchObject({ id: null, name: 'Pils', description: '25cl', price: '2.50', vat_percentage: '21', category_id: 7, is_selling: true, deleted: false });
});
```

- [ ] **Step 2: Run and make sure that they fail**

Run: `npx vitest run resources/manage/js/menu/menuChanges.test.js`
Expected: FAIL, module not found.

- [ ] **Step 3: Implement `menuChanges.js`**

Compare values in normal form: text trimmed with empty text as `null`, numbers through `parsePrice` and `parseNumber`, category as an ID or `null`. For `sequenceChanged`, take the IDs of the remaining existing rows in table sequence. Take the IDs of the remaining originals in `position` sequence. If the two lists differ, or if the plan has creates, `sequenceChanged` is true.

- [ ] **Step 4: Run and make sure that they pass**

Run: `npx vitest run resources/manage/js/menu`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/menu
git commit -m "Add change tracking for the editable menu table"
```

---

### Task 6: Service methods and save runner

**Files:**
- Modify: `resources/shared/js/services/MenuService.js`
- Create: `resources/manage/js/menu/menuSave.js`, `resources/manage/js/menu/menuSave.test.js`

**Interfaces:**
- Consumes: `buildSavePlan` and the Row and Item shapes from Task 5. The API behavior from Task 2.
- Produces on `MenuService` (the constructor already receives the event ID, store it as `this.eventId`):
  - `updateFields(id: number, changes: object): Promise<Item>`. `PUT menuitems/{id}` with `changes` as the body, without `mapToServerData`.
  - `bulkCreate(dataList: object[]): Promise<Item[]>`. `POST events/{eventId}/menu` with body `{ items: dataList }` and header `X-Bulk-Request: 1`. Returns `response.items`. Follow `EventService.bulkCreateAttendees()`.
  - `saveSequence(ids: number[]): Promise<void>`. `PUT events/{eventId}/menu/sequence` with body `{ items: ids.map(id => ({ id })) }`.
  - `mapToServerData()` also sends `vat_percentage`.
- Produces: `runSave(service, originals: Item[], rows: Row[]): Promise<{ originals: Item[], rows: Row[], error: any|null }>`. It never throws. It returns new lists that describe what the server holds now and what is still unsaved.

- [ ] **Step 1: Write the failing tests for `runSave`**

Use a fake service object that records calls in a list and returns items. `updateFields` returns the original merged with the changes. `bulkCreate` returns the data with IDs 100, 101 and so on.

```js
it('runs updates, creates, deletes, then the sequence', async () => {
  // rows: change price of 1, delete 2, add 'Thee' at the end
  // expect calls: ['updateFields 1', 'bulkCreate 1', 'delete 2', 'saveSequence 1,3,100']
  // expect result.error toBeNull, hasChanges(result.originals, result.rows) toBe(false)
  // expect result.rows to have no row with id 2 and the 'Thee' row to have id 100
});
it('does not call the sequence endpoint if the sequence is unchanged', async () => {
  // rows: change price of 1 only. expect calls: ['updateFields 1']
});
it('makes no calls for an untouched table', async () => { /* expect calls: [] */ });
it('keeps what succeeded after a failure and does not send it again', async () => {
  // rows: change 1 and 3, add 'Thee'. The fake rejects updateFields for id 3 on the first run.
  // first run: result.error is the rejection. calls: ['updateFields 1', 'updateFields 3']
  // buildSavePlan(result.originals, result.rows).updates has only id 3. creates still has 'Thee'.
  // second run with result.originals and result.rows, fake no longer rejects:
  // calls: ['updateFields 3', 'bulkCreate 1', 'saveSequence 1,2,3,100']. error null.
});
it('does not create products twice if the sequence request fails', async () => {
  // rows: add 'Thee'. The fake rejects saveSequence on the first run.
  // first run: error set. result.rows 'Thee' row has id 100. buildSavePlan(...).creates is [].
  // second run: calls: ['saveSequence 1,2,3,100']
});
it('does not mutate its arguments', async () => { /* originals and rows deep-equal their copies after the run */ });
```

- [ ] **Step 2: Run and make sure that they fail**

Run: `npx vitest run resources/manage/js/menu/menuSave.test.js`
Expected: FAIL, module not found.

- [ ] **Step 3: Implement `runSave`**

Work on copies. Build the plan once. Run the steps in this order, one request at a time: each update, the bulk create, each delete (`service.delete(id)`), the sequence. After each request that succeeds, write its result into the copies: replace the original with the returned item, set `id` on created rows and add the returned items to the originals, remove deleted rows and originals. After the sequence request succeeds, set `position` on the originals to match the table. On the first rejection, stop and return the copies with `error`. For the second run to send the sequence, a created row must compare as "sequence changed". Give the returned items of created rows `position: null` in the originals. They keep that value until the sequence request succeeds. Make sure that `buildSavePlan` treats an original with `position: null` as out of sequence. Add that case to `menuChanges.test.js`.

- [ ] **Step 4: Add the service methods**

Edit `MenuService.js` as described under Interfaces.

- [ ] **Step 5: Run and make sure that all menu tests pass**

Run: `npx vitest run resources/manage/js/menu`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/services/MenuService.js resources/manage/js/menu
git commit -m "Add menu service methods and a save runner that survives partial failure"
```

---

### Task 7: Editable menu table

**Files:**
- Rewrite: `resources/manage/js/views/Menu.vue`
- Modify: `resources/shared/js/i18n/en.js`, `nl.js`, `fr.js`, `de.js`, `es.js`

**Interfaces:**
- Consumes: everything from `menuChanges.js`, `runSave` from `menuSave.js`, `MenuService`, `CategoryService`, `getApiErrorMessage` from `AbstractService.js`.
- Produces: a method `appendRows(rows: Row[])` on the view, which Task 8 calls. It inserts the rows before the trailing blank row.

This task has no component test. Its logic is in the tested modules. Step 6 is the check.

- [ ] **Step 1: Replace the template**

Keep the license header and the page title. Remove the popup form (`b-modal ref="editFormModal"`) and the green plus button. Above the table, show a bar with four parts. "Save changes" has `variant="success"` and is disabled during a save and for a table without changes. "Reset" discards changes. "Import menu" is wired in Task 8. A status text shows "Unsaved changes", "Saving", or "All changes saved".

The table is a plain `<table class="table table-sm menu-table">`, not `b-table`. One `<tr>` per row, keyed by `row.key`. Columns in this sequence: drag handle (`⠿`), Product name, Description, Price, VAT %, Category, Status, Delete. Use `<input type="text">` for the first four value cells, with `inputmode="decimal"` on Price and VAT. Category is a `<select>` with an empty option, one option per category, and "+ Create new category". Status is the existing green or red button, and it only flips `row.is_selling`. Delete flips `row.deleted`. A deleted row shows an undo button and has its inputs disabled.

Row classes: `table-success` for status `new`, `table-warning` for `changed` and `moved`, `table-danger` for `deleted`. A cell that fails validation gets the class `is-invalid` after the user pressed Save once.

- [ ] **Step 2: State and loading**

Data: `originals` (items from `service.index()`), `rows`, `categories`, `saving`, `showErrors`, `errorMessage`. `refresh()` loads both lists, sets `rows = rowsFromItems(originals)`, and adds one blank `newRow()`. A watcher makes sure that the last row is always blank: if the user types in it, a new blank row is added. Computed: `dirty` from `hasChanges`, `invalid` from `validateRows`, and the status per row from `rowStatus`.

- [ ] **Step 3: Save, Reset, and the leave warning**

`save()`: set `showErrors = true`. If `validateRows` returns entries, stop. Otherwise call `runSave(this.service, this.originals, this.rows)`. Take `originals` and `rows` from the result and add the blank row again. If `result.error` is set, set `errorMessage` from `getApiErrorMessage(result.error)`. The fallback text is `Some changes were not saved. Press Save to try again.` Show `errorMessage` in a `b-alert variant="danger"`. `AbstractService` already shows an alert for status 422 and 500, and that stays.

`reset()`: if there are changes, ask the user `Discard all unsaved changes?` first. Then call `refresh()`.

Leave warning: add a `beforeunload` listener in `mounted()` and remove it in `beforeUnmount()`. If `dirty` is true, the listener sets `event.returnValue`. Add `beforeRouteLeave` and `beforeRouteUpdate` guards. If `dirty` is true, the guards ask `You have unsaved changes. Leave this page?`

Category "Create new category": keep the existing prompt and the immediate `categoriesService.create()`. Set `row.category_id` to the new ID. If the user cancels the prompt, restore the previous value of the selector.

- [ ] **Step 4: Drag and keyboard**

Drag: the handle cell has `draggable="true"`. On `dragstart`, store the row index and call `event.dataTransfer.setDragImage` with the `<tr>`. On `dragover` of a `<tr>`, call `preventDefault()` and mark the row as the drop target. The mark is a 2px border, at the top or the bottom, on the side of the row middle where the pointer is. On `drop`, call `moveRow`. The trailing blank row is not draggable and always stays last.

Keyboard: Enter and ArrowDown move the focus to the same column of the next row. ArrowUp moves to the previous row. Alt+ArrowUp and Alt+ArrowDown move the current row one place and keep the focus in the same cell. Tab keeps the browser default.

- [ ] **Step 5: Add the text to all five language files**

New keys:

```
Save changes
Unsaved changes
All changes saved
Import menu
Description
Drag to change the sequence
Undo delete
Discard all unsaved changes?
You have unsaved changes. Leave this page?
Some changes were not saved. Press Save to try again.
Type to add a product…
```

First search each file for a key that exists already and reuse it. Remove no existing keys.

- [ ] **Step 6: Build and check in the browser**

Run: `npm run dev`
Expected: build succeeds.

Start the web stack (see the project memory note on the docker web stack). Open an event menu in Manage and make sure that each of these works:
1. Change a price, see the row marked, press Save, reload: the price is stored.
2. Type a VAT value, save, reload: the VAT value is stored.
3. Type in the last row, save: the product exists and is last.
4. Drag the last row to the top, save, reload: the sequence is stored.
5. Delete a row, undo, delete again, save: the product is gone.
6. Clear a name, press Save: no request goes out and the cell is marked.
7. Change a price and close the tab: the browser warns.
8. Open `/pos/` on the same event: the products show in the new sequence.

- [ ] **Step 7: Run all tests and commit**

Run: `npx vitest run && npx jest`
Expected: PASS.

```bash
git checkout -- package-lock.json
git add resources/manage/js/views/Menu.vue resources/shared/js/i18n
git commit -m "Replace the menu popup form with an editable table"
```

---

### Task 8: Import sheet dialog

**Files:**
- Create: `resources/manage/js/components/MenuImportModal.vue`
- Modify: `resources/manage/js/views/Menu.vue`
- Modify: the five files in `resources/shared/js/i18n/`

**Interfaces:**
- Consumes: `parseClipboardTable`, `prepareSheet`, `guessColumnTypes`, `setColumnType`, `buildImportRows`, `canImport`, `COLUMN_TYPES`, `rowFromImport`, and `appendRows` of the menu view.
- Produces: component `menu-import-modal` with prop `categories: Array<{id, name}>`, method `show()`, and event `import` with payload `Row[]`.

- [ ] **Step 1: Build the component**

A `b-modal` with `size="xl"` and the title "Import menu". Data: `cells: string[][]`, `types: string[]`, `categoryId: number|null`. Each `show()` resets the state to one empty row with two columns of types `name` and `price`.

Body: a short hint ("Paste a table from an email, Word, or Excel into the first cell."), then a table. The header row has one `<select>` per column with the options from `COLUMN_TYPES`, labelled 'Product name', 'Description', 'Price', 'VAT %', 'Ignore'. A change calls `setColumnType`. Each body cell is an `<input type="text">` bound to `cells[r][c]`. Each row ends with a delete button. Cells of an `ignore` column are greyed out.

Paste: handle `@paste` on the inputs. Read `event.clipboardData.getData('text/plain')` and run `parseClipboardTable`. If the result has more than one row or more than one column, call `preventDefault()`. If the sheet is empty (all cells empty), replace it: `cells = prepareSheet(parsed)` and `types = guessColumnTypes(cells)`. Otherwise write the parsed block into the sheet from the focused cell, and add rows and columns of type `ignore` as needed. A paste of one cell keeps the browser default.

Computed `importRows = buildImportRows(cells, types)`. A row with errors gets the class `table-warning`, and the cell that causes the error gets `is-invalid`. Rows in which all cells are empty are left out of `importRows` and are never marked.

Footer, from left to right:
- A category selector with the label "Category for all products". The default is empty. It lists existing categories only.
- A "Delete marked rows" button. Show it only while a row has errors.
- "Cancel".
- The main button "Add {count} products". It is disabled until `canImport` returns true.

The main button emits `import` with `importRows.map(row => rowFromImport(row, categoryId))` and hides the dialog.

- [ ] **Step 2: Wire it into the menu page**

Render `<menu-import-modal ref="importModal" :categories="categories" @import="appendRows" />`. The "Import menu" button calls `this.$refs.importModal.show()`. Implement `appendRows(rows)`.

- [ ] **Step 3: Add the text to all five language files**

New keys. Reuse a key that exists already.

```
Paste a table from an email, Word, or Excel into the first cell.
Ignore
VAT %
Category for all products
Delete marked rows
Add {count} products
Rows without a name or a readable price are marked. Fix or delete them.
```

Show the last text above the sheet while a row has errors.

- [ ] **Step 4: Build and check in the browser**

Run: `npm run dev`

Make sure that each of these works:
1. Copy a two-column range from a spreadsheet and paste: the sheet fills, the headers read "Product name" and "Price", and the products land in the menu table as green rows after "Add".
2. Paste a four-column range: the guess gives name, description, ignore, price. Change a header and see the marks update.
3. Paste these plain lines: `Pils 25cl ..... 2,50`, `Duvel - € 4,50`, `BIEREN`, `Koffie`. Two rows are clean and two are marked. "Add" is disabled. "Delete marked rows" enables it.
4. Select a category for all, add, press Save on the menu page, reload: the products exist, in pasted sequence, after the products that existed, with that category and the status "selling".
5. Paste a table copied from a Word document and from an email.
6. Open the Attendees page and paste a list there: it behaves as before.

- [ ] **Step 5: Run all tests and commit**

Run: `npx vitest run && npx jest && vendor/bin/phpunit`
Expected: PASS.

```bash
git checkout -- package-lock.json
git add resources/manage/js resources/shared/js/i18n
git commit -m "Add a paste import sheet to the menu page"
```

---

### Task 9: Documentation and pull request

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Document the menu sequence**

Add a short section "Menu sequence and import" to `CLAUDE.md`. State these four facts:
- `menu_items.position` and the default order in both resource definitions.
- The sequence endpoint and who can call it.
- The rule that Save in Manage sends only changed fields, and the reason.
- The location of the pure modules in `resources/manage/js/menu/`.

- [ ] **Step 2: Commit, push, and open the pull request**

```bash
git branch --show-current
git add CLAUDE.md
git commit -m "Document the menu sequence and the menu import"
git push -u origin feature/menu-import-and-ordering
gh pr create --base main --title "Menu import, menu sequence and editable menu table (stage 1)"
```

The pull request text names the spec and lists the browser checks of Tasks 7 and 8 with their results. It also states that stage 2 follows in a second pull request. Ask the owner before you push.
