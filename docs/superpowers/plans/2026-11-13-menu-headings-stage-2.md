# Menu Headings, Stage 2, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A product can have a heading. The order screens group products under headings. Manage edits headings, and the import handles headings and categories per row.

**Architecture:** Headings are a new child resource of an event (`menu_headings`), separate from categories. Categories keep routing orders and do not change. Grouping, change tracking, and name matching are pure JavaScript modules with Vitest tests. The Vue files render state and call those modules.

**Tech Stack:** Laravel, CatLab Charon, PHPUnit, Vue 3 compat with Bootstrap-Vue, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-12-menu-import-and-ordering-design.md`, section "Stage 2: headings on the order screens". Read that section before you start. Stage 1 is on the branch `feature/menu-import-and-ordering` (pull request 245), and this branch is stacked on it.

## Global Constraints

- Branch: `feature/menu-headings`. Run `git branch --show-current` before each commit. Never commit on `main`. Do not push.
- Read all files in `.ai/` and the root `CLAUDE.md` before the first change. The section "Menu sequence and import" in `CLAUDE.md` describes stage 1.
- Do not change the POS category filter (`RemoteOrders.vue`, `devices.category_filter_id`), the split of orders (`Order::splitByCategories()`), or `OrderAssignmentService`.
- Categories get no position and no new behavior, except the event check in Task 1.
- No new npm package. Drag and drop uses native HTML5 drag events, as the menu table does.
- Never commit `package-lock.json`. Run `git checkout -- package-lock.json` after `npm install` or `npm run dev`.
- Do not change `resources/shared/js/components/Spreadsheet.vue`, `resources/shared/js/views/Attendees.vue`, or `resources/pos/js/views/Menu.vue`.
- All interface text goes through `$t()`. Add each new key to `en.js`, `nl.js`, `fr.js`, `de.js`, and `es.js` in `resources/shared/js/i18n/`, with a real translation. The key is the English text. The Dutch word for "Heading" is "Rubriek".
- API errors use the common format. Throw `ResourceValidationException` or call `abort()`.
- Nothing reaches the server before the user presses Save. The two exceptions are the creation of a new category and the creation of a new heading, which are immediate.
- Save sends `name` and `price` plus only the fields that changed. `is_selling` is sent only if the user changed it.
- A device can read headings. A device cannot create, change, delete, or reorder a heading, and cannot change the heading of a product.
- PHP tests run through docker. JavaScript tests: `npx vitest run <path>` and `npx jest`.

## Review Focus

1. A user links a product to a heading or a category of a different event or a different organisation. The server answers 422 and writes nothing. Task 1.
2. A heading row on the client order page reaches the order that the customer sends, or changes the total. It must not. Task 6.
3. A POS device that works offline has a cached menu without `heading`. The sales screen must still show all products. Task 3 (`groupMenuItems` accepts items without a `heading` key) and Task 6.
4. Two rows of an import name the same new heading with different letter case (`Bieren`, `bieren`). One heading is created. Task 3 and Task 5.
5. A create request for a heading fails in the middle of "Add". The dialog stays open, no rows are added, and a second "Add" does not create the headings that already exist again. Task 5.

## File Map

| File | Status | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_11_13_100000_create_menu_headings_table.php` | new | Table and `menu_items.heading_id` |
| `app/Models/MenuHeading.php` | new | Model, default position, clears products on delete |
| `app/Models/Concerns/AssignsNextPosition.php` | new | Shared "next position per event" hook for `MenuItem` and `MenuHeading` |
| `app/Models/MenuItem.php`, `app/Models/Event.php` | change | `heading()` relation, `menuHeadings()` relation |
| `app/Policies/MenuHeadingPolicy.php` | new | Users write, devices read |
| `app/Http/Shared/V1/Controllers/MenuHeadingController.php` and thin classes in both API namespaces | new | Charon child resource |
| `app/Http/ManagementApi/V1/ResourceDefinitions/MenuHeadingResourceDefinition.php` | new | `id`, `name`, `position` |
| both `MenuItemResourceDefinition.php` | change | `heading` relationship |
| `app/Http/Shared/V1/Controllers/MenuController.php` | change | Event check for heading and category |
| `app/Services/SequenceWriter.php` | new | Shared sequence write for products and headings |
| `tests/Feature/MenuHeadingTest.php` | new | Backend tests |
| `resources/shared/js/menu/groupMenuItems.js` | new | Group products by heading |
| `resources/shared/js/services/MenuHeadingService.js` | new | API calls for headings |
| `resources/manage/js/menu/menuChanges.js` | change | `heading_id` on rows |
| `resources/manage/js/menu/headingChanges.js` | new | Heading rows, plan, save runner |
| `resources/manage/js/menu/menuImport.js` | change | Column types, name matching, copy sheet |
| `resources/manage/js/views/Menu.vue` | change | Heading column, Headings section, Save |
| `resources/manage/js/components/MenuImportModal.vue` | change | Heading selector, per-row names, create at Add |
| `resources/shared/js/components/LiveSales.vue`, `resources/clients/js/views/Order.vue` | change | Titles on the order screens |
| `CLAUDE.md` | change | Headings note |

---

### Task 1: Headings in the backend

**Files:** see the File Map rows for the migration, the two models, the trait, the policy, the controllers, the resource definitions, `MenuController.php`, both `routes.php` files, `app/Providers/AuthServiceProvider.php`, a `MenuHeadingFactory`, and `tests/Feature/MenuHeadingTest.php`.

**Interfaces:**
- Produces: `GET|POST /api/v1/events/{id}/headings`, `GET|PUT|DELETE /api/v1/headings/{id}`. `GET /pos-api/v1/events/{id}/headings` and `GET /pos-api/v1/headings/{id}`. Resource: `{ id, name, position }`, sorted by `position`, then `id`.
- Produces: menu items in both APIs and in the public menu carry `heading: { id, name, position } | null`. The Management API accepts `heading: { id }` and `heading: null` on create and update.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/MenuHeadingTest.php`. Reuse the setup style of `tests/Feature/MenuSequenceTest.php`.

```php
testUserCanCreateAHeadingAndItGoesLast()            // POST {name:'Bieren'} then {name:'Frisdrank'}: positions 1 and 2, index returns both in that sequence
testHeadingNameIsRequired()                         // POST {} gives 422 in the common error format
testUserCanRenameAHeading()                         // PUT {name:'Bier'} gives 200 and stores the name
testHeadingIndexIsSortedByPositionThenId()          // positions 2,1,1 by forceFill: sequence is id2,id3,id1 by (position,id)
testHeadingsDeniedForOtherOrganisation()            // index 403, create 403, rename 403, delete 403
testDeviceCanReadHeadings()                         // pos-api index 200 with the names
testDeviceCannotWriteHeadings()                     // pos-api POST, PUT, DELETE: 403, 404 or 405, and nothing changes
testMenuItemCarriesItsHeading()                     // management index, device index, public menu: items.0.heading.name and .position
testMenuItemWithoutHeadingHasNullHeading()
testUserCanSetAndClearTheHeadingOfAProduct()        // PUT {name,price,heading:{id}} sets heading_id; PUT {name,price,heading:null} clears it; other fields unchanged
testUpdateWithoutHeadingKeyKeepsTheHeading()        // PUT {name,price} leaves heading_id unchanged
testBulkCreateAcceptsAHeading()                     // X-Bulk-Request with heading:{id} on one item
testHeadingOfADifferentEventIsRejected()            // PUT and POST with a heading of another event of the same organisation: 422, heading_id unchanged, no product created
testHeadingOfADifferentOrganisationIsRejected()     // 422
testCategoryOfADifferentEventIsRejected()           // PUT and POST with category:{id} of another event: 422
testDeviceCannotChangeTheHeadingOfAProduct()        // pos-api PUT {heading:{id}, is_selling:false}: 200, heading_id unchanged, is_selling false
testDeletingAHeadingClearsItsProducts()             // DELETE heading: 2 products keep existing, heading_id null, heading soft deleted
testNewHeadingPositionCountsSoftDeletedHeadings()   // delete the last heading, create one: its position is higher than the deleted one's
```

- [ ] **Step 2: Run** `phpunit --filter MenuHeadingTest`. Expected: FAIL, the table and the routes do not exist.

- [ ] **Step 3: Migration.** `menu_headings`: `increments id`, unsigned `event_id` with a foreign key to `events`, `string name`, `unsignedInteger position default 0`, timestamps, soft deletes, index on `['event_id', 'position']` plus a single-column index on `event_id` (so that the composite index never backs the foreign key alone; see the stage 1 migration `down()` for the reason). `menu_items.heading_id`: unsigned, nullable, after `category_id`, foreign key to `menu_headings`. `down()` drops the foreign key, the column, and the table. Run `migrate:rollback --step=1` and `migrate` twice in the docker test database.

- [ ] **Step 4: Models.** Move the `creating` hook of `MenuItem` into the trait `App\Models\Concerns\AssignsNextPosition` (same behavior: next position per `event_id`, soft-deleted rows count) and use it in `MenuItem` and `MenuHeading`. `MenuHeading`: `SoftDeletes`, `HasFactory`, `event()`, `items()`, and a `deleting` hook that sets `heading_id` to null on its products. `MenuItem::heading()`, `Event::menuHeadings()`.

- [ ] **Step 5: API.** Follow the shared controller pattern of `CategoryController` (full class in `Shared`, thin classes in both API namespaces). Management routes: `index, view, store, edit, destroy`. Device routes: `index, view`. `MenuHeadingResourceDefinition` in the ManagementApi namespace: identifier, `name` (required, writeable), `position` (number, sortable, writeable), `defaultOrder('position')`. `MenuHeadingPolicy`: `index` and `view` pass `$allowDevices = true`, `create`, `edit`, `destroy` use the default. Register it in `AuthServiceProvider`. Add the `heading` relationship to both `MenuItemResourceDefinition` classes (`one()`, `visible(true)`, `expanded()`, and `linkable()` in the Management API only).

- [ ] **Step 6: Event check.** In `App\Http\Shared\V1\Controllers\MenuController::beforeSaveEntity()`, after the trait call: if the product has a `heading_id` or a `category_id` whose row belongs to a different `event_id`, throw `ResourceValidationException` with one message. Soft-deleted or missing rows are also rejected.

- [ ] **Step 7: Run** `phpunit --filter "MenuHeadingTest|MenuSequenceTest"`, then the full PHP suite. Expected: PASS.

- [ ] **Step 8: Commit.** Message: "Add menu headings as a resource of an event".

---

### Task 2: Heading sequence endpoint

**Files:** `app/Services/SequenceWriter.php` (new), `app/Http/ManagementApi/V1/Controllers/MenuController.php`, `app/Http/ManagementApi/V1/Controllers/MenuHeadingController.php`, `tests/Feature/MenuHeadingTest.php`.

**Interfaces:**
- Produces: `PUT /api/v1/events/{id}/headings/sequence` with body `{ "items": [ { "id": 3 }, { "id": 1 } ] }`, response `{ "success": true }`. Same rules as `PUT /api/v1/events/{id}/menu/sequence`.
- Produces: `App\Services\SequenceWriter::write(\Illuminate\Database\Eloquent\Relations\HasMany $relation, mixed $items): void`. It validates and writes, and throws `ResourceValidationException`.

- [ ] **Step 1: Write the failing tests.** Mirror the sequence tests of `MenuSequenceTest` for headings: stores the sequence, unlisted headings go after the listed ones, rejects IDs of a different event, duplicate IDs, a deleted heading, and a body without `items` (422, nothing written, common error format), 403 for a different organisation, and no route for a device.

- [ ] **Step 2: Run.** Expected: FAIL with 404 or 405.

- [ ] **Step 3: Extract and reuse.** Move the validation and the transaction of `MenuController::sequence()` into `SequenceWriter::write()`. `MenuController::sequence()` and the new `MenuHeadingController::sequence()` both authorize (`create` ability on the parent event) and call it. Register the heading route in the Management API class only.

- [ ] **Step 4: Run** `phpunit --filter "MenuHeadingTest|MenuSequenceTest"`, then the full suite. Expected: PASS, with `MenuSequenceTest` unchanged.

- [ ] **Step 5: Commit.** Message: "Add an endpoint that stores the heading sequence".

---

### Task 3: Pure modules for headings

**Files:** `resources/shared/js/menu/groupMenuItems.js` (+ test), `resources/shared/js/services/MenuHeadingService.js`, `resources/manage/js/menu/menuChanges.js` (+ test), `resources/manage/js/menu/headingChanges.js` (+ test), `resources/manage/js/menu/menuImport.js` (+ test).

**Interfaces (all produced here, consumed by Tasks 4 to 6):**

- `groupMenuItems(items: Item[]): Array<{ heading: { id, name, position } | null, items: Item[] }>`.
  The first group has `heading: null` and holds the products without a heading. It is left out if it is empty. The other groups follow in `heading.position`, then `heading.id` order. Products keep their incoming sequence inside a group. An item without a `heading` key counts as no heading. The function does not mutate its argument. It returns the same item objects, not copies.
- `MenuHeadingService(eventId)` extends `AbstractService`: `indexUrl = 'events/' + eventId + '/headings'`, `entityUrl = 'headings'`, `mapToServerData(data)` returns `{ name: data.name }`, and `saveSequence(ids: number[]): Promise<void>` sends `PUT events/{eventId}/headings/sequence` with `{ items: ids.map(id => ({ id })) }`.
- `menuChanges.js`: Row gets `heading_id: number|null`. `rowFromItem` reads `item.heading`. `newRow` defaults it to null. A changed heading adds `heading: { id }` or `heading: null` to `changes`. A create carries `heading`. `rowFromImport(importRow, categoryId, headingId)` gets a third argument (default null). All existing tests keep passing, with `heading: null` added to the fixture items and to the expected create data.
- `headingChanges.js`:
  - `headingRowsFrom(headings): HeadingRow[]` with `HeadingRow = { key, id, name }`.
  - `headingStatuses(originals, rows): { [key]: 'clean'|'changed'|'moved' }` ('moved' by the same longest-run rule as `rowStatuses`; export and reuse the helper from `menuChanges.js`, do not copy it).
  - `validateHeadingRows(rows): number[]` returns the keys of rows with an empty name.
  - `hasHeadingChanges(originals, rows): boolean`.
  - `runHeadingSave(service, originals, rows): Promise<{ originals, rows, error }>`. It never throws and does not mutate its arguments. It sends one `service.update(id, { name })` per renamed heading, then one `service.saveSequence(ids)` if the sequence changed. After each request that succeeds, it writes the result into the copies, so that a second run sends only what is left.
- `menuImport.js`:
  - `COLUMN_TYPES` becomes `['name', 'description', 'price', 'vat_percentage', 'category', 'heading', 'ignore']`. `guessColumnTypes` never returns `category` or `heading`.
  - `buildImportRows` adds `category: string` and `heading: string` to each row (trimmed text, empty text without such a column). They never cause an error.
  - `matchByName(name: string, list: Array<{ id, name }>): { id, name } | null` compares without regard to letter case and outer spaces.
  - `namesToCreate(importRows, field: 'category'|'heading', existing: Array<{ id, name }>, preferredOrder: string[] = []): string[]`. It returns each name that has no match, once (first spelling wins), names of `preferredOrder` first in that order, then the others in order of first appearance.
  - `sheetFromItems(items)` adds a `category` column and a `heading` column with the names. A column of `description`, `vat_percentage`, `category`, or `heading` that is empty for all items is left out. `name` and `price` always stay. An empty list still gives the four stage 1 types.
  - `headingOrderFromItems(items): string[]` returns the distinct heading names of the items in `heading.position`, then `heading.id` order.

- [ ] **Step 1: Write the failing tests.** One `it` per rule above. Required cases, with exact values:

```js
// groupMenuItems
const beer = { id: 2, name: 'Bieren', position: 2 }, soda = { id: 5, name: 'Frisdrank', position: 1 };
const items = [ { id: 1, name: 'Pils', heading: beer }, { id: 2, name: 'Chips' }, { id: 3, name: 'Cola', heading: soda },
                { id: 4, name: 'Duvel', heading: beer }, { id: 5, name: 'Water', heading: null } ];
expect(groupMenuItems(items).map(g => [g.heading && g.heading.name, g.items.map(i => i.name)])).toEqual([
  [null, ['Chips', 'Water']], ['Frisdrank', ['Cola']], ['Bieren', ['Pils', 'Duvel']] ]);
expect(groupMenuItems([{ id: 1, name: 'Pils' }])).toEqual([{ heading: null, items: [{ id: 1, name: 'Pils' }] }]);   // no titles
expect(groupMenuItems([{ id: 1, name: 'Pils', heading: beer }])[0].heading.name).toBe('Bieren');                   // no empty first group
expect(groupMenuItems([])).toEqual([]);
// equal positions fall back to the heading id; the returned items are the same objects (toBe)

// menuChanges
// heading change on Pils: updates toEqual [{ id: 1, changes: { name: 'Pils', price: 2.5, heading: { id: 4 } } }]
// heading cleared: changes { name, price, heading: null }
// untouched row: no `heading` key in changes
// rowFromImport(row, 7, 4) has category_id 7 and heading_id 4; create data carries heading { id: 4 }

// headingChanges, originals [{id:1,name:'Bieren',position:1},{id:2,name:'Frisdrank',position:2},{id:3,name:'Snacks',position:3}]
// rename 2 and move 3 to the top: calls ['update 2 {"name":"Fris"}', 'saveSequence 3,1,2'], error null, hasHeadingChanges false after
// untouched: no calls. Rename only: no saveSequence call.
// update of 2 rejects: error set, a second run with the result sends 'update 2' and then the sequence, and nothing twice
// validateHeadingRows marks a row whose name is '  '

// menuImport
// matchByName(' bieren ', [{ id: 1, name: 'Bieren' }]).id is 1; no match gives null; '' gives null
// namesToCreate(rows with headings ['Bieren','bieren','Snacks','','Frisdrank'], 'heading', [{id:1,name:'Snacks'}], ['Frisdrank'])
//   toEqual ['Frisdrank', 'Bieren']
// sheetFromItems of two items with category 'Drinks' and headings 'Bieren' / none:
//   types ['name','description','price','vat_percentage','category','heading'] if description and VAT have a value somewhere,
//   and without the columns that are empty for all items
// round trip: buildImportRows(sheet.cells, sheet.types) gives category 'Drinks' and heading 'Bieren' / ''
// headingOrderFromItems returns names by position, each once
```

- [ ] **Step 2: Run** `npx vitest run resources/manage/js/menu resources/shared/js/menu`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `npx vitest run` and `npx jest`. Expected: PASS.
- [ ] **Step 5: Commit.** Message: "Add pure modules for menu headings".

---

### Task 4: Headings in the Manage menu page

**Files:** `resources/manage/js/views/Menu.vue`, the five i18n files.

**Interfaces:**
- Consumes: Task 3 modules, `MenuHeadingService`.
- Produces: a method `createHeading(name): Promise<{ id, name, position }>` on the view (creates at once, adds the heading to the list and to the Headings section, rejects on failure), passed to the import dialog as the prop `create-heading` in Task 5. The view also passes `:headings="headings"`.

- [ ] **Step 1: Heading column.** After "Category", add a "Heading" column with a selector: empty option, one option per heading in sequence, and "+ Create new heading". It works as the category selector does: a prompt for the name (`Enter the name of the new heading:`), immediate creation through `createHeading`, restore of the previous value on cancel or failure. Keep the keyboard movement between cells working with the extra column.

- [ ] **Step 2: Headings section.** Below the menu table, a section with the title "Headings" and the hint `Headings group products on the order screens. Categories send orders to a location, for example the bar or the kitchen.` If the event has no heading, show `No headings yet. Create one in the "Heading" column of a product.` Otherwise one row per heading: a drag handle and a text input for the name. Drag and Alt with the arrow keys move a row, as in the menu table. Row classes from `headingStatuses`: `table-warning` for 'changed' and 'moved'. An empty name gets `is-invalid` after the first Save attempt.

- [ ] **Step 3: Save, Reset, and loading.** `refresh()` also loads the headings. `dirty` is true if the menu rows or the heading rows changed. `save()` stops if `validateRows` or `validateHeadingRows` reports something. It runs `runSave` first and `runHeadingSave` second, also if the first one returned an error, and shows the first error. After a rename succeeded, the selectors in the menu table show the new name. Reset, the leave warning, and the event-switch guards cover the heading rows too. All heading inputs are disabled during a save or a load.

- [ ] **Step 4: Text.** New keys: `Heading`, `Headings`, `+ Create new heading`, `Enter the name of the new heading:`, the hint, and the empty text.

- [ ] **Step 5: Browser check** on the isolated stack (see the web stack notes). One PASS or FAIL line each:
  1. Create a heading from a product row. It shows in the selector of all rows and in the Headings section.
  2. Set headings on three products, press Save, reload: the API returns the headings on the products.
  3. Rename a heading and drag it to the top, press Save, reload: `GET events/{id}/headings` returns the new name and sequence.
  4. Clear a heading name, press Save: no request goes out and the input is marked.
  5. Change a heading name and leave the page: the question appears.
  6. A stage 1 flow still works: change a price, Save, reload.

- [ ] **Step 6: Run** `npx vitest run && npx jest`, then commit. Message: "Edit menu headings on the Manage menu page".

---

### Task 5: Headings and categories in the import

**Files:** `resources/manage/js/components/MenuImportModal.vue`, `resources/manage/js/views/Menu.vue` (props only), the five i18n files.

**Interfaces:**
- Consumes: `matchByName`, `namesToCreate`, `headingOrderFromItems`, the new column types, `rowFromImport(importRow, categoryId, headingId)`, and the props `headings`, `create-heading`, `categories`, `create-category`.

- [ ] **Step 1: Selector.** Next to "Category for all products", add "Heading for all products" with the headings and "+ Create new heading". Same behavior as the category selector (prompt, immediate creation, restore on cancel or failure). Move the shared logic of the two selectors into one method. Do not copy it.

- [ ] **Step 2: Column types.** The header selectors offer "Category" and "Heading". Cells of those columns are free text.

- [ ] **Step 3: Add.** "Add N products" becomes asynchronous:
  1. Compute `namesToCreate` for categories and for headings (preferred order for headings: the heading order of the copied event, empty for a paste).
  2. Create them one at a time through `createCategory` and `createHeading`. Disable the buttons meanwhile.
  3. If a request fails: stop, show the API error message in the dialog, keep the sheet, add no rows. What was created stays, and a second "Add" finds it by name.
  4. Resolve each row: a non-empty name gives the matched ID, an empty cell gives the "for all" selection.
  5. Emit `import` with the rows and close.

- [ ] **Step 4: Copy.** The copy from a different event uses the new `sheetFromItems`, so the sheet shows the category and heading names of the source. Remember `headingOrderFromItems(menu.items)` for Step 3. A paste after a copy clears that memory only if it replaces the sheet.

- [ ] **Step 5: Text.** New keys: `Heading for all products`, and reuse `Category`, `Heading`.

- [ ] **Step 6: Browser check**, one PASS or FAIL line each:
  1. Paste two lines, select a heading for all, Add, Save, reload: both products have that heading.
  2. Paste a three-column table with a third column of heading names (`Bieren`, `bieren`, `Frisdrank`), set its type to "Heading", Add: two headings are created, and the rows carry them after Save.
  3. Copy from an event whose products have categories and headings: the sheet shows both name columns. After Add and Save, the current event has categories and headings with those names, the headings are in the sequence of the source, and the source event is unchanged (no request to it other than GET).
  4. Copy again into the same event: no second category or heading with the same name is created.
  5. A stage 1 flow still works: plain lines with a marked row, "Delete marked rows", Add.

- [ ] **Step 7: Run** `npx vitest run && npx jest`, then commit. Message: "Import headings and categories per row, and copy them from an event".

---

### Task 6: Titles on the order screens

**Files:** `resources/shared/js/components/LiveSales.vue`, `resources/clients/js/views/Order.vue`, `resources/shared/sass/app.scss` or the stylesheet that each screen uses, i18n only if a new text is needed.

**Interfaces:**
- Consumes: `groupMenuItems` from `resources/shared/js/menu/groupMenuItems.js`.

- [ ] **Step 1: POS sales screen.** Render the tiles per group. A group with a heading gets a title above its tiles. `increaseOrder(product, index, event)` and `decreaseOrder` use the index in the flat `items` list (`this.items.splice(index, 1, product)`). Keep the flat list as the state and pass the correct flat index, or change both handlers so that they no longer need an index. The summary list, the totals, and the confirm flow must behave as before.

- [ ] **Step 2: Client order page.** The page uses a `b-table` over `this.items`, with a totals row at the end, and many methods loop over `this.items`. Show a title row before the first product of each heading. A title row has no amount and no buttons. Make sure that title rows are excluded wherever products are counted, summed, stored for recovery, or sent in the order. Prefer a computed list for display that is built from the product list, so that `this.items` keeps holding products (and the totals row) only.

- [ ] **Step 3: No titles without headings.** If no visible product has a heading, both screens render exactly the same elements as before this task.

- [ ] **Step 4: Check.** `npm run dev`. Client order page in the browser on the isolated stack (the event needs `is_selling` and `allow_unpaid_online_orders`): products without a heading first, titles in heading sequence, an order of two products from two headings posts exactly two order items and the right total, and an event without headings shows no title rows. POS sales screen: the test stack has no paired POS device. Render the built POS stylesheet with the tile markup and titles on a static test page in headless Chrome and take a screenshot, and state in the report that the real POS screen was not exercised.

- [ ] **Step 5: Run** `npx vitest run && npx jest`, then commit. Message: "Group products under headings on the order screens".

---

### Task 7: Documentation

**Files:** `CLAUDE.md`.

- [ ] **Step 1:** Extend the section "Menu sequence and import" (rename it to "Menu sequence, headings and import"): the `menu_headings` resource and who can write it, the difference between a heading and a category, the event check, `SequenceWriter`, `groupMenuItems` and where it is used, and the per-row names in the import. Add `MenuHeading` to the "Key Models" table and `MenuHeadingController` to the list of shared controllers.
- [ ] **Step 2:** Commit. Message: "Document menu headings". Do not push.
