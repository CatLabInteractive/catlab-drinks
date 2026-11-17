# POS Offline Queue Resilience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The POS upload queue keeps going after a failed upload. One rejected sale does not block other sales. The POS shows how many sales are not stored.

**Architecture:** A small pure module decides the kind of an upload error and the retry rule for a rejected sale. `AbstractOfflineQueue` uses it: a rejected batch is sent one order at a time, and a rejected order stays in the queue with a mark. A request can be silent, so that a background upload shows no alert. A small reporter module sends a rejected sale one time to Errbit. Two screens show the count.

**Tech Stack:** JavaScript (ES modules), Vue 3 through `@vue/compat`, BootstrapVue 2, localForage, axios, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-17-pos-offline-queue-resilience-design.md`

## Global Constraints

- Frontend only. No file below `app/`, `database/`, or `routes/` changes.
- No library changes. `package.json` and `package-lock.json` do not change. After `npm install`, run `git checkout -- package-lock.json`.
- The code never deletes a queue item that the server did not accept.
- The timer between two passes stays 30 seconds. A marked sale is tried again one time in each 10 minutes (`600000` ms).
- Kind of an upload error: `retry` for no response, for a status of 500 or higher, and for status 401, 403, 408, 429. `rejected` for each other status from 400 to 499.
- English texts, exact: `{count} sales are not stored. They stay on this device and are sent again later.` and `Sales that are not stored:` and `Last reason: {reason}`.
- Each new text has a translation in `en.js`, `nl.js`, `fr.js`, `de.js`, and `es.js` in `resources/shared/js/i18n/`.
- The branch is `fix/pos-offline-queue-resilience`. It starts from `fix/pos-sale-order-payload` (pull request 256). Do not push to `main`.
- Before each commit, run `git branch --show-current` and make sure that the result is `fix/pos-offline-queue-resilience`.
- Do not add `.claude/`, `.superpowers/`, or `tools/walkthrough/` to a commit.

## Review Focus

- The device is offline during a pass (no response). Expected: no item gets a mark, the pass ends, and the next timer is set.
- The server answers 500 for a batch. Expected: no item gets a mark and no order is sent one at a time.
- The queue holds 12 sales and sale 3 is rejected. Expected: the other 11 are stored in the same pass, and sale 3 stays with a mark.
- A marked sale is stored at a later try. Expected: it leaves the queue and the count on the screens goes down.
- The bartender logs out while only marked sales are in the queue. Expected: the logout warning still shows, with the count.

Tasks 1, 2, and 4 hold the tests for these lines.

## File Structure

| File | Responsibility |
|------|----------------|
| `resources/shared/js/services/queueUpload.js` (new) | Pure rules: kind of an error, retry rule for a marked sale |
| `resources/shared/js/services/queueUpload.test.js` (new) | Tests for the rules |
| `resources/shared/js/services/errorReporter.js` (new) | Holds the function that reports an error to Errbit |
| `resources/shared/js/services/AbstractService.js` | `execute` takes options. The interceptor shows no alert for a silent request. |
| `resources/shared/js/services/AbstractOfflineQueue.js` | The queue: pass, batch, one at a time, mark, counts, change callback |
| `resources/shared/js/services/offlineQueue.test.js` (new) | Tests for the queue with a fake store and a fake `execute` |
| `resources/pos/js/app.js`, `resources/manage/js/app.js` | Set the reporter with the Airbrake client |
| `resources/shared/js/components/LiveSales.vue` | The red line on the sales screen |
| `resources/pos/js/views/Settings.vue` | The two counts and the last reason in the sync panel |
| `resources/shared/js/i18n/*.js` | Three new texts in five languages |

## Shapes that the tasks share

A queue item in localForage, after this work:

```js
{
	id: null,
	method: 'create',
	data: { /* the order */ },
	parameters: undefined,
	// Only on an item that the server rejected:
	rejected: { status: 422, message: 'Could not decode resource.', at: 1794574956466, reported: true },
}
```

---

### Task 1: Rules for an upload error, and silent requests

**Files:**
- Create: `resources/shared/js/services/queueUpload.js`
- Create: `resources/shared/js/services/queueUpload.test.js`
- Modify: `resources/shared/js/services/AbstractService.js` (interceptor near lines 78-131, `execute` at line 141)
- Modify: `resources/tests/abstract-service-error-interceptor.test.js`

**Interfaces:**
- Produces, in `queueUpload.js`:
  - `export const RETRY_REJECTED_AFTER_MS = 600000;`
  - `export function uploadErrorKind(error)`: returns `'retry'` or `'rejected'`. `error` is an axios error. `error.response` is missing for a network error.
  - `export function isDueForRetry(item, now)`: returns a boolean. An item without `rejected` is always due. For a marked item, the result is `now - item.rejected.at >= RETRY_REJECTED_AFTER_MS`.
- Produces, in `AbstractService.js`: `async execute(method, resource, data = {}, options = {})`. It passes `silent: options.silent === true` in the axios request options. The interceptor reads `error.config && error.config.silent`. For a silent request it shows no `alert` for 422 and for 500 or higher, sets no `userNotified`, and rejects with the error. The handling of 401 and 403 does not change.

- [ ] **Step 1: Write the failing tests for the rules**

```js
import { describe, it, expect } from 'vitest';
import { uploadErrorKind, isDueForRetry, RETRY_REJECTED_AFTER_MS } from './queueUpload';

const http = (status) => ({ response: { status } });

describe('uploadErrorKind', () => {
	it('retries a network error', () => {
		expect(uploadErrorKind(new Error('Network Error'))).toBe('retry');
	});
	it.each([500, 502, 503, 401, 403, 408, 429])('retries status %i', (status) => {
		expect(uploadErrorKind(http(status))).toBe('retry');
	});
	it.each([400, 404, 409, 422])('rejects status %i', (status) => {
		expect(uploadErrorKind(http(status))).toBe('rejected');
	});
});

describe('isDueForRetry', () => {
	it('is always due for an item without a mark', () => {
		expect(isDueForRetry({ data: {} }, 0)).toBe(true);
	});
	it('is not due before 10 minutes', () => {
		expect(isDueForRetry({ rejected: { at: 1000 } }, 1000 + RETRY_REJECTED_AFTER_MS - 1)).toBe(false);
	});
	it('is due at 10 minutes', () => {
		expect(isDueForRetry({ rejected: { at: 1000 } }, 1000 + RETRY_REJECTED_AFTER_MS)).toBe(true);
	});
});
```

- [ ] **Step 2: Write the failing tests for the silent request**

Add to `resources/tests/abstract-service-error-interceptor.test.js`, with the helpers that the file already has. Read the file first.

- A request with `config.silent === true` and status 422: `alert` is not called, the promise rejects, and `error.userNotified` is not `true`.
- The same for status 500.
- A request without `silent` and status 422: `alert` is called one time. This case exists already. Keep it.
- `execute('post', '/x', {a: 1}, { silent: true })` calls the client with `silent: true`. `execute('post', '/x', {a: 1})` calls it with `silent: false`.

- [ ] **Step 3: Run the tests and make sure that they fail**

Run: `npx vitest run resources/shared/js/services/queueUpload.test.js resources/tests/abstract-service-error-interceptor.test.js`
Expected: FAIL. `./queueUpload` does not exist, and the silent cases call `alert`.

- [ ] **Step 4: Implement `queueUpload.js` and the changes in `AbstractService.js`**

Put the license header of the other service files at the top of the new file.

- [ ] **Step 5: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/services/queueUpload.js resources/shared/js/services/queueUpload.test.js resources/shared/js/services/AbstractService.js resources/tests/abstract-service-error-interceptor.test.js
git commit -m "Add the rules for an upload error and silent requests"
```

---

### Task 2: The queue keeps going and separates a rejected sale

**Files:**
- Modify: `resources/shared/js/services/AbstractOfflineQueue.js`
- Create: `resources/shared/js/services/offlineQueue.test.js`
- Create: `resources/shared/js/services/errorReporter.js`
- Modify: `resources/pos/js/app.js` (near line 66), `resources/manage/js/app.js` (near line 56)
- Modify: `resources/tests/offline-queue.test.js`. Change it only for a source text check that no longer matches.

**Interfaces:**
- Consumes: `uploadErrorKind`, `isDueForRetry` from Task 1. `execute(method, resource, data, { silent: true })` from Task 1. `getApiErrorMessage(error)` from `AbstractService.js`. `toUploadData(data)` exists already.
- Produces, in `errorReporter.js`:
  - `export function setErrorReporter(fn)`: `fn(error, params)` or `null`.
  - `export function reportError(error, params = {})`: calls the function that was set. Without one it does nothing. It never throws.
- Produces, in `AbstractOfflineQueue`:
  - `uploadQueue()`: one pass. It always clears `this.uploading` and always sets the 30 second timer at the end, also after an error. It never rejects.
  - `uploadBatch(items)`: sends one request with `{ silent: true }`. On success it removes the items from localForage. On an error it throws the error and removes nothing.
  - `uploadEach(items)`: sends each item in a request of its own, silent. A stored item is removed. A rejected item gets its mark through `markRejected`. An error of kind `retry` stops the loop and is thrown.
  - `markRejected(item, error)`: writes `rejected: { status, message, at: Date.now(), reported }` on the stored item, under the same localForage key. `message` comes from `getApiErrorMessage(error)`. On the first mark it calls `reportError(error, { queue: this.localForagePrefix, status, message })` and sets `reported: true`. On a later mark it keeps `reported: true` and does not report again.
  - The pass order in `uploadQueue`: first the items without a mark, in batches of ten. If a batch throws an error of kind `rejected`, call `uploadEach` for that batch and go on with the next batch. If a batch throws an error of kind `retry`, end the pass. After the batches, call `uploadEach` for the marked items that are due (`isDueForRetry(item, Date.now())`).
  - `static async getAllQueueCounts()`: returns `{ pending: number, rejected: number, lastReason: string | null }` over all queue items in localForage. `lastReason` is the `message` of the marked item with the highest `at`.
  - `static async getAllPendingCount()`: stays. It returns `pending + rejected`.
  - `static async uploadAllPending()`: it keeps its current signature. For each event it first sends the items without a mark, in batches, with the same rules. Then it sends each marked item. It does not apply the 10 minute rule.
  - `this.onQueueChange`: `null` or a function. The queue calls it without arguments at the end of each pass.
- Produces, in the two `app.js` files: directly after `new AirbrakeClient(AIRBRAKE_CONFIG)`, the call `setErrorReporter((error, params) => airbrake.notify({ error, params }))`.

- [ ] **Step 1: Write the failing tests**

`offlineQueue.test.js` uses `vi.doMock('localforage', ...)` with a fake store that is a `Map`, with `setItem`, `removeItem`, and `iterate`. It stubs `CATLAB_DRINKS_CONFIG` and `window.axios` as `orderPayload.test.js` does. It uses `vi.useFakeTimers()`. `service.execute` is a `vi.fn()` that the test controls. Seed the store with keys `event_3_<n>`.

Write one test for each line:

1. A pass in which `execute` rejects with a network error: no item has a mark, `service.uploading` is `false`, and after 30 seconds `execute` is called again.
2. A pass in which `execute` rejects with status 500: no item has a mark, and `execute` was called one time.
3. Twelve items, and `execute` rejects with 422 for each body that holds the order with `uid: 'bad'`: after one pass the store holds one item, that item has `rejected.status === 422` and `rejected.reported === true`, and the other 11 keys are gone.
4. The same store after that pass, with the clock 9 minutes later: a pass sends no request for the marked item. With the clock 10 minutes later: a pass sends one request for it.
5. A marked item that is due, and `execute` resolves: the item is gone from the store.
6. A marked item is rejected two more times: the reporter function was called one time in total.
7. Each call of `execute` in these tests has `{ silent: true }` as its fourth argument.
8. `getAllQueueCounts()` on a store with two items without a mark and two marked items (`at` 5 and 9, messages `'a'` and `'b'`): `{ pending: 2, rejected: 2, lastReason: 'b' }`. `getAllPendingCount()` returns 4.
9. `onQueueChange` is called one time at the end of a pass. This also holds for a pass that ended on an error.
10. `uploadAllPending()` sends a marked item that is not due.
11. `reportError` without a reporter does nothing and does not throw. A reporter that throws does not make `reportError` throw.

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `npx vitest run resources/shared/js/services/offlineQueue.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement the changes**

Keep `startPeriodicUpload`, `destroy`, `addQueueItem`, `getPendingActions`, and `isQueueItem` as they are, except where the interfaces above need a change.

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS. If a source text check in `resources/tests/offline-queue.test.js` fails because a line that it quotes moved, change that check to the new line. Do not delete a check.

- [ ] **Step 5: Commit**

```bash
git add resources/shared/js/services resources/pos/js/app.js resources/manage/js/app.js resources/tests/offline-queue.test.js
git commit -m "Keep the upload queue alive and separate a rejected sale"
```

---

### Task 3: Show the count on the sales screen and in the POS settings

**Files:**
- Modify: `resources/shared/js/components/LiveSales.vue` (template near line 79, script near lines 154 and 201)
- Modify: `resources/pos/js/views/Settings.vue` (template lines 166-175, script near lines 283 and 323, logout warning near line 373)
- Modify: `resources/shared/js/i18n/en.js`, `nl.js`, `fr.js`, `de.js`, `es.js`
- Create: `resources/tests/queue-status-views.test.js`

**Interfaces:**
- Consumes: `AbstractOfflineQueue.getAllQueueCounts()`, `getAllPendingCount()`, and `onQueueChange` from Task 2.
- Produces, in `LiveSales.vue`: data `rejectedCount: 0`. A method `refreshRejectedCount()` reads `getAllQueueCounts()` and sets `rejectedCount` to `rejected`. The component calls it after it creates the `OrderService` for an event, and it sets `this.orderService.onQueueChange = () => this.refreshRejectedCount()`. Template: a `b-alert` with `variant="danger"` and the condition `rejectedCount > 0`. Its text is `$t('{count} sales are not stored. They stay on this device and are sent again later.', { count: rejectedCount })`. Give it the class `queue-rejected`.
- Produces, in `Settings.vue`: data `rejectedQueueCount: 0` and `rejectedQueueReason: null`. `refreshPendingTransactionCount()` reads `getAllQueueCounts()` and sets `pendingQueueCount` to `pending`, and the two new fields. Template: below the line "Pending queue items", add a line with `$t('Sales that are not stored:')` and the count. For a count above 0, the count has the class `text-danger`. If there is a reason, add a line `$t('Last reason: {reason}', { reason: rejectedQueueReason })`. The logout warning uses `pendingTransactionCount + pendingQueueCount + rejectedQueueCount`.
- Dutch texts, exact: `{count} verkopen zijn niet opgeslagen. Ze blijven op dit toestel en worden later opnieuw verzonden.` and `Verkopen die niet zijn opgeslagen:` and `Laatste reden: {reason}`. The builder writes the French, German, and Spanish texts.

- [ ] **Step 1: Write the failing tests**

`queue-status-views.test.js` imports the script block of both components. `vitest.config.js` has a plugin for that. See `resources/tests/events-views.test.js` for the pattern. Mock `AbstractOfflineQueue` with `vi.mock`.

1. `Settings`: `getAllQueueCounts` resolves `{ pending: 2, rejected: 1, lastReason: 'x' }`. Call `refreshPendingTransactionCount` with a `this` that has the data fields. Expected: `pendingQueueCount` is 2, `rejectedQueueCount` is 1, and `rejectedQueueReason` is `'x'`.
2. `LiveSales`: `refreshRejectedCount` sets `rejectedCount` to 1 for the same counts.
3. Source text checks, in the style of `resources/tests/offline-support.test.js`: `LiveSales.vue` holds the English sales screen text and the class `queue-rejected`. `Settings.vue` holds `Sales that are not stored:` and `Last reason: {reason}`, and its logout warning holds `rejectedQueueCount`.
4. Each of the five language files holds the three English keys. The file `resources/tests/i18n-completeness.test.js` also covers this. Run it.

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `npx vitest run resources/tests/queue-status-views.test.js resources/tests/i18n-completeness.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement the two views and the translations**

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/shared/js/components/LiveSales.vue resources/pos/js/views/Settings.vue resources/shared/js/i18n resources/tests/queue-status-views.test.js
git commit -m "Show the sales that are not stored on the sales screen and in the POS settings"
```

---

### Task 4: Proof in the browser, and the pull request

**Files:**
- Modify: `CLAUDE.md` (a short section "POS upload queue" after "401 Handling" in "Device Management")

- [ ] **Step 1: Run the automated tests and the build**

Run: `npm test`, `npx jest`, and `npm run prod`
Expected: all pass.

Run: `git diff main --stat -- package.json package-lock.json app database routes`
Expected: no output.

- [ ] **Step 2: Proof run**

Use the walk-through stack. Its tools are on the branch `feature/interface-migration-prepare`. Copy them to a directory outside the repository with `git archive feature/interface-migration-prepare tools/walkthrough docs/testing | tar -x -C <directory>` and follow the README in that copy. Use only the compose project `catlab-drinks-walk`. Never load a public host.

Pair a POS, open the sales screen of an event, and do these steps with a Playwright script in the scratch directory:

1. Make one normal cash sale.
2. Put a second sale in the queue that the server will reject. One way: add a queue item through `localforage` in the page with a product id that does not exist.
3. Wait for one pass (at most 35 seconds).
4. Make a third normal cash sale and wait for one pass.

Expected:
- No browser dialog opened during the run. Listen for the `dialog` event of the page.
- The database holds the first and the third sale.
- The sales screen shows the line with the class `queue-rejected` and the count 1.
- The POS settings show "Pending queue items" 0, "Sales that are not stored" 1, and a reason.
- The requests to the orders endpoint after the first rejection do not repeat for the rejected sale within the next 60 seconds.

Record the evidence: the dialog count, the database rows, and two screenshots. Tear the stack down.

- [ ] **Step 3: Write the `CLAUDE.md` section**

Write five to eight lines. Cover these points:

- The queue file and the two kinds of an error.
- The mark on a rejected item and the 10 minute rule.
- The silent flag.
- The two screens and the Errbit report.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "Document the POS upload queue"
```

- [ ] **Step 5: Stop before the push**

Do not push and do not open the pull request. Report the results. The owner decides about the push.
