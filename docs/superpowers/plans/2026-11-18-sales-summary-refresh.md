# Sales Summary Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The sales summary tabs reload on their own and show the load time and the age of the data. If the data is more than 2 minutes old, the tabs show a warning.

**Architecture:** Summary requests are "background" requests: they skip the POS offline cache, show no `alert()`, and time out. A pure scheduling module (`autoRefresh.js`) decides the moment of each load and owns the "in flight" state. A small child component (`SummaryFreshness.vue`) owns the 1-second clock, so the big table does not render again every second. `SalesSummary.vue` loads the data and passes the load time to the child. The server loads the menu items, with their category and heading, in one query each.

**Tech Stack:** Vue 3 with `@vue/compat`, Bootstrap-Vue, vue-i18n, axios, Vitest (jsdom), Laravel, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-18-sales-summary-refresh-design.md`

## Global Constraints

- Refresh interval: 30 seconds on the items tab, 60 seconds on the names tab.
- Stale after: 2 minutes (120000 ms) since the last successful load.
- Background request timeout: 20000 ms.
- No polling while `document.hidden` is true. Refresh at once on `visibilitychange` to visible.
- One request at a time. A tick during a request is skipped. `AutoRefresh` is the only owner of this state.
- A failed load keeps the old rows and does not change the load time. A response from the offline cache counts as a failure.
- Timestamp: "Updated at {date time} ({age})". Date and time in the app locale (`getLocale()` from `resources/shared/js/i18n/index.js`). Age in seconds below one minute, whole minutes after that.
- In print: the timestamp and the alert show, the age and the "Refresh now" button do not (`d-print-none`). The alert is readable without background colours (bold text, border).
- Add every new UI string to all five files in `resources/shared/js/i18n/` (en, nl, fr, de, es).
- Work on a feature branch (`feature/sales-summary-refresh`), never on `main`. Do not commit `package-lock.json`.

## Review Focus

- POS without network: the cache interceptor must not serve the summary from cache. Test in Task 1 (`background GET is not served from the cache`).
- Server outage (500): no `alert()` on each tick. Test in Task 1 (`background request does not alert on 500`).
- A request that never answers: it ends after 20 s, so later ticks and "Refresh now" work again. Test in Task 1 (`background request sets a timeout`) and Task 2 (`skips a tick while a load runs`).
- The user leaves the page while the first request runs: no scheduler starts after `unmounted`. Covered in Task 4 Step 1, checked in Task 4 Step 6.
- First load fails: the page shows the alert, not "Updated at" and not an endless spinner. Test in Task 3 (`isStale(null, now)` is true), checked in Task 4 Step 6.

---

### Task 1: Background requests

**Files:**
- Modify: `resources/shared/js/services/AbstractService.js` (`execute()` and the response interceptor)
- Modify: `resources/shared/js/services/ApiCacheService.js` (`installCacheInterceptors()`)
- Modify: `resources/shared/js/services/OrderService.js` (`summary()`, `summaryNames()`)
- Test: `resources/shared/js/services/backgroundRequests.test.js` (create)

**Interfaces:**
- Produces: `execute(method, resource, data = {}, options = {})`. With `options.background === true`, the axios config gets `background: true` and `timeout: 20000`.
- Produces: `OrderService.summary(parameters = {}, options = {})` and `OrderService.summaryNames(parameters = {}, options = {})`, which pass `options` to `execute()`.
- Behaviour: if `config.background` is true, the cache interceptor does not cache and does not fall back to the cache. If `config.background` is true, the error interceptor does not call `alert()` for 5xx. 401 and 403 handling stays the same.

- [ ] **Step 1: Write the failing tests** with a stubbed `window.alert` and an axios adapter stub (or `vi.spyOn` on the client):
  - `background request sets a timeout`: the request config has `timeout: 20000` and `background: true`.
  - `background request does not alert on 500`: the promise rejects and `alert` is not called.
  - `normal request still alerts on 500`: `alert` is called once.
  - `background GET is not served from the cache`: with `installCacheInterceptors` and a cached body for the URL, a network error rejects.
  - `background GET is not cached`: a successful response does not call `cacheResponse`.
- [ ] **Step 2: Run** `npx vitest run resources/shared/js/services/backgroundRequests.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement** the behaviour in Interfaces.
- [ ] **Step 4: Run** the same command and `npx vitest run`. Expected: all pass.
- [ ] **Step 5: Commit** `git commit -m "Add quiet background requests that skip the offline cache"`

### Task 2: Scheduling module

**Files:**
- Create: `resources/shared/js/utils/autoRefresh.js`
- Test: `resources/shared/js/utils/autoRefresh.test.js`

**Interfaces:**
- Produces: `export class AutoRefresh { constructor(load: () => Promise<any>, { intervalMs: number, doc?: Document, onChange?: (running: boolean) => void }); start(): void; stop(): void; refresh(): Promise<void>; running: boolean }`. `start()` calls `refresh()` once, sets the interval, and adds the `visibilitychange` listener to `doc` (default `document`). If `doc.hidden` is true, a tick does nothing. Otherwise it calls `refresh()`. `refresh()` returns at once while a load runs. It catches errors from `load`, because the caller handles its own errors. At the start of a load, `onChange` gets `true`. At the end, it gets `false`. After `stop()`, `refresh()` does nothing.

- [ ] **Step 1: Write the failing tests** with `vi.useFakeTimers()` and a fake doc (`Object.assign(new EventTarget(), { hidden: false })`):
  - `loads on start and on each tick`: intervalMs 30000, advance 60000 → load called 3 times.
  - `does not load while hidden`: set `hidden = true`, advance 90000 → load called 1 time (the start).
  - `refreshes when the document becomes visible`: hidden, advance, then `hidden = false` and dispatch `visibilitychange` → one extra call at once.
  - `skips a tick while a load runs`: load returns a promise that never resolves, advance 90000 → load called 1 time. A direct `refresh()` call also does not call load.
  - `reports running through onChange`: calls are `[true, false]` for one load.
  - `keeps running after a failed load`: load rejects, advance 30000 → load called 2 times, no unhandled rejection.
  - `stop removes the timer and the listener`: after `stop()`, advance, dispatch `visibilitychange`, and call `refresh()` → no new calls.
- [ ] **Step 2: Run** `npx vitest run resources/shared/js/utils/autoRefresh.test.js`. Expected: FAIL, module not found.
- [ ] **Step 3: Implement `AutoRefresh`** in `autoRefresh.js` as described in Interfaces.
- [ ] **Step 4: Run** the same command. Expected: 7 passed.
- [ ] **Step 5: Commit** `git commit -m "Add a scheduler that refreshes only while the page is visible"`

### Task 3: Freshness component

**Files:**
- Create: `resources/shared/js/components/SummaryFreshness.vue`
- Create: `resources/shared/js/utils/dataAge.js`
- Test: `resources/shared/js/utils/dataAge.test.js`
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js` (next to `'Orderer names'`)

**Interfaces:**
- Produces: `export const STALE_AFTER_MS = 120000`, `export function isStale(loadedAt: number|null, now: number): boolean`, `export function describeAge(ms: number): { unit: 's'|'min', value: number }`, `export function formatLoadTime(loadedAt: number, locale: string): string` in `dataAge.js`. `formatLoadTime` uses `Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'medium' })`.
- Produces: `<summary-freshness :loaded-at="number|null" :refreshing="boolean" @refresh>`. It keeps its own `now`, updated every 1000 ms by its own interval, cleared in `unmounted`.

- [ ] **Step 1: Write the failing tests** in `dataAge.test.js`:
  - `isStale(null, 1000)` → `true`. `isStale(0, 120000)` → `false`. `isStale(0, 120001)` → `true`.
  - `describeAge(0)` → `{ unit: 's', value: 0 }`. `describeAge(59999)` → `{ unit: 's', value: 59 }`. `describeAge(60000)` → `{ unit: 'min', value: 1 }`. `describeAge(-500)` → `{ unit: 's', value: 0 }` (clock skew).
  - `formatLoadTime(Date.UTC(2026, 10, 13, 21, 31, 53), 'nl')` contains `13` and `:31:53`.
- [ ] **Step 2: Run** `npx vitest run resources/shared/js/utils/dataAge.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement** `dataAge.js`.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Build `SummaryFreshness.vue`.** If `loadedAt` is set, the component shows a muted small line with "Updated at {time}". The age follows in a `d-print-none` span. If `isStale(loadedAt, now)` is true, the component shows a `b-alert` with variant `danger`. The alert has bold text and a border. It shows the text "These figures are possibly out of date. Refresh the page before you use them to settle payments.", and a `d-print-none` button "Refresh now". The button emits `refresh` and is disabled while `refreshing`. Strings with vue-i18n named arguments: `'Updated at {time}'`, `'{n} s ago'`, `'{n} min ago'`, the alert text, `'Refresh now'`. Dutch: `'Bijgewerkt om {time}'`, `'{n} s geleden'`, `'{n} min geleden'`, `'Deze cijfers zijn mogelijk niet meer actueel. Vernieuw de pagina voor je ze gebruikt om af te rekenen.'`, `'Nu vernieuwen'`.
- [ ] **Step 6: Commit** `git commit -m "Show the age of the sales summary and warn when it is old"`

### Task 4: Wire it into the sales summary

**Files:**
- Modify: `resources/shared/js/components/SalesSummary.vue`

**Interfaces:**
- Consumes: `OrderService.summary/summaryNames(parameters, { background: true })` (Task 1), `AutoRefresh` (Task 2), `<summary-freshness>` (Task 3).

- [ ] **Step 1: Change the data and lifecycle.** Add `loadedAt: null`, `refreshing: false` and `firstLoadDone: false`. The `eventId` watcher stops an old `AutoRefresh`, creates the services, and then creates and starts a new `AutoRefresh` at once. For `summaryType === 'names'`, `intervalMs` is 60000. Otherwise it is 30000. `onChange` is `running => this.refreshing = running`. The event fetch (`eventService.get`) runs next to it, not before it, and its failure does not stop polling. A `destroyed` flag, set in `unmounted`, keeps a late watcher from starting a scheduler. Remove the commented-out interval and `beforeDestroy`. In `unmounted`, call `stop()`.
- [ ] **Step 2: Change `refresh()`.** Fetch with `{ background: true }`. Build the rows in local variables: the grouping, `this.$t('Unknown / manual')` and `calculateRanks` all run before the assignment. Then assign `summary`, `groupedItems` (frozen with `Object.freeze`, rows included) and `loadedAt = Date.now()` together. On an error, change nothing. Set `firstLoadDone = true` in `finally`. Show the spinner only while `!firstLoadDone` (replaces the `loaded` flag, which is set before the request today).
- [ ] **Step 3: Rows.** Give the outer `template v-for` a `:key`: `productGroup.menuItem.id` on the items tab, `rootIndex` on the names tab (the server can return two rows with an empty name). Give the inner row `:key="index"`. Replace the per-row `b-btn` with a plain `<button type="button" class="btn btn-secondary btn-sm">`.
- [ ] **Step 4: Place `<summary-freshness>`** directly under the `h2`, with `@refresh="autoRefresh.refresh()"`.
- [ ] **Step 5: Run** `npx vitest run` and `npm run dev`. Expected: all tests pass and the build has no errors.
- [ ] **Step 6: Check in the browser** (POS and Manage, both summary tabs): "Updated at" shows and the age counts up. With the network blocked in DevTools for 2 minutes, the red alert shows, the old rows stay, and no `alert()` opens. After unblock, "Refresh now" clears the alert. Leave the page during the first load and wait: no requests follow. Print preview shows the timestamp and the alert, not the age and not the button.
- [ ] **Step 7: Commit** `git commit -m "Refresh the sales summary on its own"`

### Task 5: Fewer queries for the menu items

**Files:**
- Modify: `app/Http/Shared/V1/Controllers/OrderSummaryController.php` (`getSummary()`, after `$items = $groupedQuery->get();`)
- Test: `tests/Feature/OrderSummaryTest.php` (create, authenticate with `Passport::actingAs` as in `tests/Feature/MenuSequenceTest.php`)

- [ ] **Step 1: Write the failing test** `testSummaryLoadsMenuItemsInFewQueries`: an event with 5 menu items, each with its own category and heading, and one processed order with one item of each. Call `GET /api/v1/events/{id}/ordersummary` with `DB::enableQueryLog()`. Assert status 200, 5 rows, and a total of the 5 prices. Assert exactly 1 logged query each for `menu_items`, `categories` and `menu_headings`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter OrderSummaryTest` (via the docker runner, see memory). Expected: FAIL.
- [ ] **Step 3: Implement** `$items->load(['menuItem.category', 'menuItem.heading']);` after the `get()`.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Load the menu items of the sales summary in a fixed number of queries"`
