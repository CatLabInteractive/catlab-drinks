# POS Offline Queue Resilience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The POS upload queue keeps going after a failed upload. One bad sale does not block other sales. The POS shows how many sales are not stored.

**Architecture:** A small pure module holds the rules for an upload answer. `AbstractOfflineQueue` uses them. Only one pass runs for an event at one time. A bad batch is sent one order at a time, and a bad order stays in the queue with a mark. A request can be silent, so that a background upload shows no alert. Two screens show the count.

**Tech Stack:** JavaScript (ES modules), Vue 3 through `@vue/compat`, BootstrapVue 2, localForage, axios, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-17-pos-offline-queue-resilience-design.md`

**Review:** A reviewer read the first version of this plan against the code on 2026-11-17. This version holds the findings that a second test or a read of the code supported.

## Global Constraints

- Frontend only. No file below `app/`, `database/`, or `routes/` changes.
- No library changes. `package.json` and `package-lock.json` do not change. After `npm install`, run `git checkout -- package-lock.json`.
- A queue item leaves the store only after an answer with a success status and an `items` list of the sent length.
- The timer between two passes stays 30 seconds (`30000` ms). A marked sale is tried again one time in each 10 minutes (`600000` ms).
- Kind of an upload error. `retry`: no response, a status below 400, a status of 500 or higher, and status 401, 408, 429. `rejected`: each other status from 400 to 499, also 403.
- A batch that got a status of 500 or higher in 3 passes is sent one order at a time.
- English texts, exact: `Sales that are not stored: {count}. They stay on this device and are sent again later.` and `Sales that are not stored:` and `Last reason: {reason}`.
- Dutch texts, exact: `Verkopen die niet zijn opgeslagen: {count}. Ze blijven op dit toestel en worden later opnieuw verzonden.` and `Verkopen die niet zijn opgeslagen:` and `Laatste reden: {reason}`.
- Each new text has a translation in `en.js`, `nl.js`, `fr.js`, `de.js`, and `es.js` in `resources/shared/js/i18n/`.
- The branch is `fix/pos-offline-queue-resilience`. It starts from `fix/pos-sale-order-payload` (pull request 256). Do not push to `main`.
- Before each commit, run `git branch --show-current` and make sure that the result is `fix/pos-offline-queue-resilience`.
- Do not add `.claude/`, `.superpowers/`, or `tools/walkthrough/` to a commit.

## Review Focus

- The device is offline during a pass. Expected: no item gets a mark, each item is still in the store, and the next timer is set.
- The queue holds 12 sales and sale 3 gets status 422. Expected: the other 11 are stored in the same pass, and sale 3 stays with a mark.
- One sale makes the server answer 500 each time, and other sales follow it. Expected: after 3 passes the other sales are stored and that sale has a mark.
- The server is down for 5 minutes. Expected: no sale gets a mark, and each sale is stored after the server is back.
- The sales screen was opened and closed 5 times. Expected: one request for each sale, not 5.
- The bartender logs out while only marked sales are in the queue. Expected: the logout warning shows, with the count.

Tasks 2 and 3 hold the tests for these lines.

## File Structure

| File | Responsibility |
|------|----------------|
| `resources/shared/js/services/queueUpload.js` (new) | Pure rules for an upload answer |
| `resources/shared/js/services/queueUpload.test.js` (new) | Tests for the rules |
| `resources/shared/js/services/errorReporter.js` (new) | Holds the function that reports an error to Errbit |
| `resources/shared/js/services/AbstractService.js` | `execute` takes options. The interceptor is quiet for a silent request. |
| `resources/shared/js/services/AbstractOfflineQueue.js` | The queue: pass, batch, one at a time, mark, counts, change callback |
| `resources/shared/js/services/offlineQueue.test.js` (new) | Tests for the queue with a fake store and a fake `execute` |
| `resources/shared/js/services/orderPayload.test.js` | One assertion gets the new fourth argument |
| `resources/pos/js/app.js`, `resources/manage/js/app.js` | Set the reporter with the Airbrake client |
| `resources/shared/js/components/LiveSales.vue` | The red line on the sales screen |
| `resources/pos/js/views/Settings.vue` | The counts and the last reason in the sync panel, the logout warning |
| `resources/tests/sync-logout-warning.test.js` | One source text check changes |
| `resources/shared/js/i18n/*.js` | Three new texts in five languages |

## Shapes that the tasks share

A queue item in localForage, after this work:

```js
{
	id: null,
	method: 'create',
	data: { /* the order */ },
	parameters: undefined,
	// Count of passes in sequence in which the batch of this item got a status of 500 or higher.
	serverFailures: 0,
	// Only on an item that has a mark:
	rejected: { status: 422, message: 'Could not decode resource.', at: 1794574956466 },
}
```

The stored value never holds `localStorageKey`. That field exists only on the copy in memory.

The answer of the server to a stored batch is `{ items: [ ... ] }` with one entry for each order that was sent.

---

### Task 1: Rules for an upload answer, and silent requests

**Files:**
- Create: `resources/shared/js/services/queueUpload.js`
- Create: `resources/shared/js/services/queueUpload.test.js`
- Modify: `resources/shared/js/services/AbstractService.js` (interceptor at lines 78-131, `execute` at line 141)
- Modify: `resources/tests/abstract-service-error-interceptor.test.js`

**Interfaces:**
- Produces, in `queueUpload.js`:
  - `export const RETRY_REJECTED_AFTER_MS = 600000;`
  - `export const SERVER_FAILURES_BEFORE_ONE_AT_A_TIME = 3;`
  - `export function uploadErrorKind(error)`: returns `'retry'` or `'rejected'`, by the rule in "Global Constraints". `error.response` is missing for a network error.
  - `export function isServerFailure(error)`: returns `true` for a response status of 500 or higher. Returns `false` in each other case.
  - `export function isAcceptedAnswer(body, sentCount)`: returns `true` for a `body.items` list with `sentCount` entries. Returns `false` in each other case, also for `null`, a string, and a list of a different length.
  - `export function isDueForRetry(item, now)`: an item without `rejected` is always due. For a marked item, the result is `now - item.rejected.at >= RETRY_REJECTED_AFTER_MS`.
- Produces, in `AbstractService.js`: `async execute(method, resource, data = {}, options = {})`. It passes `silent: options.silent === true` in the axios request options. The interceptor reads `error.config && error.config.silent`. For a silent request:
  - Status 422 and status 500 or higher: no `alert`, no `userNotified`, reject with the error.
  - Status 403: no `alert`, no change of `window.location`, reject with the error.
  - Status 401: no change. The page reloads.

  For a request that is not silent, nothing changes.

- [ ] **Step 1: Write the failing tests for the rules**

```js
import { describe, it, expect } from 'vitest';
import {
	uploadErrorKind, isServerFailure, isAcceptedAnswer, isDueForRetry, RETRY_REJECTED_AFTER_MS,
} from './queueUpload';

const http = (status) => ({ response: { status } });

describe('uploadErrorKind', () => {
	it('retries a network error', () => {
		expect(uploadErrorKind(new Error('Network Error'))).toBe('retry');
	});
	it.each([302, 500, 502, 503, 401, 408, 429])('retries status %i', (status) => {
		expect(uploadErrorKind(http(status))).toBe('retry');
	});
	it.each([400, 403, 404, 409, 413, 422])('rejects status %i', (status) => {
		expect(uploadErrorKind(http(status))).toBe('rejected');
	});
});

describe('isServerFailure', () => {
	it('is true for 500 and higher only', () => {
		expect(isServerFailure(http(500))).toBe(true);
		expect(isServerFailure(http(503))).toBe(true);
		expect(isServerFailure(http(422))).toBe(false);
		expect(isServerFailure(new Error('Network Error'))).toBe(false);
	});
});

describe('isAcceptedAnswer', () => {
	it('accepts a list of the sent length', () => {
		expect(isAcceptedAnswer({ items: [{}, {}] }, 2)).toBe(true);
	});
	it.each([[null], ['<html>login</html>'], [{}], [{ items: [{}] }], [{ items: 'x' }]])('does not accept %j', (body) => {
		expect(isAcceptedAnswer(body, 2)).toBe(false);
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

Read `resources/tests/abstract-service-error-interceptor.test.js` first. Add these cases with its helpers:

- `config.silent === true` and status 422: `alert` is not called, the promise rejects, and `error.userNotified` is not `true`.
- `config.silent === true` and status 500: `alert` is not called and the promise rejects.
- `config.silent === true` and status 403: `alert` is not called, `window.location` does not change, and the promise rejects.
- No `silent` and status 422: `alert` is called one time. If this case exists already, keep it.
- `execute('post', '/x', { a: 1 }, { silent: true })` calls the client with `silent: true`. `execute('post', '/x', { a: 1 })` calls it with `silent: false`.

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
git commit -m "Add the rules for an upload answer and silent requests"
```

---

### Task 2: The queue keeps going and separates a bad sale

**Files:**
- Modify: `resources/shared/js/services/AbstractOfflineQueue.js`
- Create: `resources/shared/js/services/offlineQueue.test.js`
- Create: `resources/shared/js/services/errorReporter.js`
- Modify: `resources/shared/js/services/orderPayload.test.js` (the assertion near line 76)
- Modify: `resources/pos/js/app.js` (near line 66), `resources/manage/js/app.js` (near line 56)
- Modify: `resources/tests/offline-queue.test.js`. Change it only for a source text check that no longer matches.

**Background, from the current code:**
- `destroy()` clears the timer and then calls `uploadQueue()`, which sets a new timer at its end. A destroyed queue object therefore uploads for ever. `LiveSales.vue` and `SalesSummary.vue` call `destroy()`.
- `getPendingActions()` writes `localStorageKey` on the value that it reads.
- The real localForage returns a new copy of the stored value for each read.

**Interfaces:**
- Consumes: the four functions and two constants of Task 1. `execute(method, resource, data, { silent: true })` of Task 1. `getApiErrorMessage(error)` of `AbstractService.js`. `toUploadData(data)` exists already.
- Produces, in `errorReporter.js`:
  - `export function setErrorReporter(fn)`: `fn(error, params)` or `null`.
  - `export function reportError(error, params = {})`: calls the function that was set. Without one it does nothing. It never throws, also for a function that throws.
- Produces, in `AbstractOfflineQueue.js`:
  - One pass at a time. A set at module level holds each queue prefix that has a pass in flight. All queue objects and `uploadAllPending` share it.
  - `async runPass(options = {})` with `options.ignoreRetryRule` (default `false`) and `options.setTimer` (default `true`). It resolves with `{ skipped: boolean, retryError: Error | null }` and never rejects.
    - If the prefix has a pass in flight, it does no upload and resolves with `skipped: true`.
    - If `setTimer` is `true` and the object is not destroyed, it clears the timer of the object and sets a new one of 30 seconds. It does this in each case, also for a skipped pass and after an error.
    - It sets `this.uploading` to `true` for the time of the pass and to `false` at its end, in each case.
    - At the end of a pass that was not skipped: if `this.onQueueChange` is a function, it calls it.
  - `async uploadQueue()`: calls `runPass()` and resolves with nothing.
  - The order of work in a pass:
    1. Read the items of the prefix. Sort them by key.
    2. Take the items without a mark in batches of ten. If one item of a batch has `serverFailures >= SERVER_FAILURES_BEFORE_ONE_AT_A_TIME`, call `uploadEach(batch)`. If not, call `uploadBatch(batch)`.
    3. If `uploadBatch` throws an error of kind `rejected`, call `uploadEach(batch)` and go on with the next batch.
    4. If `uploadBatch` throws an error with `isServerFailure`, add 1 to `serverFailures` of each item of that batch in the store. Then end the pass with that error as `retryError`.
    5. If `uploadBatch` or `uploadEach` throws a different error of kind `retry`, end the pass with that error as `retryError`.
    6. After the batches, call `uploadEach` for the marked items. Without `ignoreRetryRule`, take only the items for which `isDueForRetry(item, Date.now())` is `true`.
  - `async uploadBatch(items)`: one silent request with all items. If the answer passes `isAcceptedAnswer(body, items.length)`, it removes the items from the store. If not, it throws an `Error` without a `response`. On a request error it throws that error and removes nothing.
  - `async uploadEach(items)`: one silent request for each item, with the same body shape as a batch of one.
    - Accepted answer: remove the item.
    - Error of kind `rejected`: call `markRejected(item, error)`.
    - Error with `isServerFailure`: remember the item and the error, and go on.
    - Each other error, and an answer that is not accepted: throw. The pass ends.
    - After the loop: if at least one item of this call was removed, call `markRejected` for each remembered item. If no item was removed and at least one item is remembered, throw the last remembered error.
  - `async markRejected(item, error)`: reads the value again from the store with `getItem(item.localStorageKey)`. If the value is gone, it does nothing. If not, it writes the value back with `rejected: { status, message, at: Date.now() }` and without `localStorageKey`. `message` is `getApiErrorMessage(error)`. If that gives nothing, `message` is `error.message`. If the stored value had no `rejected` before, it reports. See "Report" below.
  - Report: `reportError(error, { queue: this.localForagePrefix, status, message })`. A set at module level holds each combination of queue prefix, status, and message that was reported. A combination is reported one time for a page load.
  - `destroy()`: sets `this.destroyed = true` and `this.onQueueChange = null`, clears the timer, removes the offline binding, and calls `runPass({ setTimer: false })` one time.
  - `async getQueueCounts()`: returns `{ pending: number, rejected: number, lastReason: string | null }` for the items of this prefix. `lastReason` is the `message` of the marked item with the highest `at`.
  - `static async getAllQueueCounts()`: the same shape, over each queue item in the store.
  - `static async getAllPendingCount()`: stays. It returns `pending + rejected`.
  - `static async uploadAllPending()`: for each event prefix in the store, it makes an `OrderService` and calls `runPass({ ignoreRetryRule: true, setTimer: false })`. If a pass was skipped, it waits 500 ms and tries one more time. After all events: if there is a `retryError`, it throws the first one. A marked sale does not make it throw.
  - `this.onQueueChange`: `null` or a function without arguments.
- Produces, in the two `app.js` files: directly after `new AirbrakeClient(AIRBRAKE_CONFIG)`, the call `setErrorReporter((error, params) => airbrake.notify({ error, params }))`.

- [ ] **Step 1: Write the fake store and the failing tests**

`offlineQueue.test.js` uses `vi.resetModules()` and `vi.doMock('localforage', ...)` before each test, as `orderPayload.test.js` does. The fake store is a `Map` with these rules:

- `setItem(key, value, callback)` stores `structuredClone(value)`. If there is a callback, it calls the callback. It returns a promise.
- `getItem(key)` resolves with a `structuredClone` of the value, or `null`.
- `iterate(fn)` calls `fn(structuredClone(value), key)` for each entry and returns a promise.
- `removeItem(key)` deletes the key and returns a promise.

The tests stub `CATLAB_DRINKS_CONFIG` and `window.axios` as `orderPayload.test.js` does. They use `vi.useFakeTimers()`. `service.execute` is a `vi.fn()`. Seed the store with keys `event_3_<n>`. The helper `ok(n)` resolves `{ items: [ n entries ] }`. The helper `http(status)` rejects with `{ response: { status, data: { error: { message: 'm' + status } } } }`.

Write one test for each line. Each test reads the result from the store, not from an object in memory.

1. `execute` rejects with a network error: no item has a mark, each item is still in the store, `service.uploading` is `false`, and after 30 seconds `execute` is called again.
2. `execute` rejects with status 503 for a batch: no item has a mark, each item has `serverFailures === 1`, and `execute` was called one time.
3. Twelve items. `execute` rejects with 422 for each body that holds the order with `uid: 'bad'`, and resolves for each other body. After one pass the store holds one item. That item has `rejected.status === 422` and `rejected.message === 'm422'`. The stored value has no `localStorageKey`.
4. The store of test 3, 9 minutes later: a pass sends no request for the marked item. 10 minutes later: a pass sends one request for it.
5. A marked item that is due, and `execute` resolves with an accepted answer: the item is gone.
6. Three items. `execute` rejects with 500 for each body that holds `uid: 'bad'` and resolves for each other body. After 3 passes the store holds the 3 items, each with `serverFailures === 3`. After pass 4 the store holds one item, with `rejected.status === 500`.
7. Three items, and `execute` rejects with 500 for each request. After 5 passes no item has a mark and each item is still in the store.
8. `execute` resolves with the string `'<html>login</html>'`: no item is removed and no item has a mark.
9. Two queue objects for the prefix `event_3_`, and `execute` is a promise that the test resolves later. `runPass()` on both: `execute` was called one time, and the second call resolved with `skipped: true`.
10. `markRejected` for an item whose key was removed from the store: the store stays empty.
11. `destroy()`: after it, no timer is pending (`vi.getTimerCount() === 0`) and `onQueueChange` is `null`.
12. Two different items that get 422 with the same message: the reporter was called one time. A marked item that gets 422 again: the reporter is not called again.
13. Each call of `execute` in these tests has `{ silent: true }` as its fourth argument.
14. `getQueueCounts()` and `getAllQueueCounts()`. For event 3, the store has two items without a mark and two marked items. The marked items have `at` 5 and 9 and the messages `'a'` and `'b'`. For event 4, the store has one marked item. The instance for event 3 gives `{ pending: 2, rejected: 2, lastReason: 'b' }`. `getAllQueueCounts()` gives `rejected: 3`. `getAllPendingCount()` gives 5.
15. `onQueueChange` is called one time at the end of a pass. This also holds for a pass that ended on an error. It is not called for a skipped pass.
16. `uploadAllPending()` sends a marked item that is not due. If that item is rejected again, it resolves. If `execute` rejects with a network error, it rejects.
17. `reportError` without a reporter does nothing. A reporter that throws does not make `reportError` throw.

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `npx vitest run resources/shared/js/services/offlineQueue.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement the changes**

Keep `startPeriodicUpload`, `addQueueItem`, `getPendingActions`, `isQueueItem`, and `toUploadData` as they are, except where the interfaces above need a change.

- [ ] **Step 4: Change the old assertion in `orderPayload.test.js`**

The assertion near line 76 expects three arguments. Add `{ silent: true }` as the fourth argument. The mock of `execute` in that test must resolve with `{ items: [{}] }`.

- [ ] **Step 5: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS. A source text check in `resources/tests/offline-queue.test.js` can fail because a line that it quotes changed. In that case, change the check to the new line. Do not delete a check.

- [ ] **Step 6: Commit**

```bash
git add resources/shared/js/services resources/pos/js/app.js resources/manage/js/app.js resources/tests/offline-queue.test.js
git commit -m "Keep the upload queue alive and separate a bad sale"
```

---

### Task 3: Show the count on the sales screen and in the POS settings

**Files:**
- Modify: `resources/shared/js/components/LiveSales.vue` (template near line 79, script near lines 116, 154, and 201)
- Modify: `resources/pos/js/views/Settings.vue` (template lines 166-188, script near lines 283, 323, 329-352, and 373-376)
- Modify: `resources/tests/sync-logout-warning.test.js` (the check near line 88)
- Modify: `resources/shared/js/i18n/en.js`, `nl.js`, `fr.js`, `de.js`, `es.js`
- Create: `resources/tests/queue-status-views.test.js`

**Interfaces:**
- Consumes: `getQueueCounts()`, `AbstractOfflineQueue.getAllQueueCounts()`, `uploadAllPending()`, and `onQueueChange` of Task 2.
- Produces, in `LiveSales.vue`:
  - Data `rejectedCount: 0`.
  - Method `async refreshRejectedCount()`: reads `this.orderService.getQueueCounts()` and sets `rejectedCount` to `rejected`. Without an order service it sets 0.
  - After the component makes the `OrderService` for an event, it sets `this.orderService.onQueueChange = () => this.refreshRejectedCount()` and calls `refreshRejectedCount()`.
  - Template, below the alert for `warning`: `<b-alert variant="danger" class="queue-rejected" :show="rejectedCount > 0">`. Its text is `$t('Sales that are not stored: {count}. They stay on this device and are sent again later.', { count: rejectedCount })`. Use `:show`. A `b-alert` without `show` renders nothing.
- Produces, in `Settings.vue`:
  - Data `rejectedQueueCount: 0` and `rejectedQueueReason: null`.
  - `refreshPendingTransactionCount()` reads `AbstractOfflineQueue.getAllQueueCounts()`. It sets `pendingQueueCount` to `pending`, `rejectedQueueCount` to `rejected`, and `rejectedQueueReason` to `lastReason`.
  - Template, below the line "Pending queue items": a line with `$t('Sales that are not stored:')` and the count. For a count above 0, the count has the class `text-danger`. If there is a reason, add a line with `$t('Last reason: {reason}', { reason: rejectedQueueReason })`.
  - Logout: the condition `hasPending` is `pendingTransactionCount > 0 || pendingQueueCount > 0 || rejectedQueueCount > 0`. The count in the warning text is the sum of the three.
  - `syncNow`: after the sync it calls `refreshPendingTransactionCount()`. If `rejectedQueueCount === 0`, it sets `syncSuccess` to `true`. If not, `syncSuccess` stays `false`.
- The builder writes the French, German, and Spanish texts.

- [ ] **Step 1: Write the failing tests**

`queue-status-views.test.js` imports the script block of both components. `vitest.config.js` has a plugin for that. See `resources/tests/events-views.test.js` for the pattern. Mock `AbstractOfflineQueue` with `vi.mock`. Call a method with `call` on an object that holds the data fields.

1. `Settings.refreshPendingTransactionCount`: `getAllQueueCounts` resolves `{ pending: 2, rejected: 1, lastReason: 'x' }`. Expected: `pendingQueueCount` is 2, `rejectedQueueCount` is 1, and `rejectedQueueReason` is `'x'`.
2. `Settings.logout` with `pendingTransactionCount: 0`, `pendingQueueCount: 0`, `rejectedQueueCount: 1`, and a stub of `window.confirm` that returns `false`. Expected: `confirm` was called one time, and its text holds `1`. Read the method first, so that the test gives it the fields that it needs.
3. `Settings.syncNow` with `uploadAllPending` resolved and `getAllQueueCounts` resolving `rejected: 1`: `syncSuccess` is `false`. With `rejected: 0`: `syncSuccess` is `true`.
4. `LiveSales.refreshRejectedCount` with an order service whose `getQueueCounts` resolves `{ pending: 0, rejected: 2, lastReason: null }`: `rejectedCount` is 2.
5. Source text checks, in the style of `resources/tests/offline-support.test.js`: `LiveSales.vue` holds `class="queue-rejected"` and `:show="rejectedCount > 0"` and the English text. `Settings.vue` holds `Sales that are not stored:` and `Last reason: {reason}`.
6. Each of the five language files holds the three English keys.

Change the check near line 88 of `resources/tests/sync-logout-warning.test.js` to `AbstractOfflineQueue.getAllQueueCounts()`.

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `npx vitest run resources/tests/queue-status-views.test.js resources/tests/sync-logout-warning.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement the two views and the translations**

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS. This includes `resources/tests/i18n-completeness.test.js`.

- [ ] **Step 5: Commit**

```bash
git add resources/shared/js/components/LiveSales.vue resources/pos/js/views/Settings.vue resources/shared/js/i18n resources/tests/queue-status-views.test.js resources/tests/sync-logout-warning.test.js
git commit -m "Show the sales that are not stored on the sales screen and in the POS settings"
```

---

### Task 4: Proof in the browser, and the documentation

**Files:**
- Modify: `CLAUDE.md` (a short section "POS upload queue" after "401 Handling" in "Device Management")

- [ ] **Step 1: Run the automated tests and the builds**

Run: `npm test` and `npx jest`
Expected: both pass.

Copy the tracked files to a directory outside the repository with `git archive HEAD | tar -x -C <directory>`. There, run `npm install` and `npm run prod`.
Expected: both end without an error.

Run `npm run prod` in the repository also. The proof run needs that build.

Run: `git diff main --stat -- package.json package-lock.json app database routes`
Expected: no output.

- [ ] **Step 2: Proof run**

The walk-through tools are on the branch `feature/interface-migration-prepare`. Copy them to a scratch directory outside the repository with `git archive feature/interface-migration-prepare tools/walkthrough docs/testing | tar -x -C <directory>`. Read the README in that copy. Run each `docker compose` command from the repository root, with `-f <directory>/tools/walkthrough/compose.override.yml`. Use only the compose project `catlab-drinks-walk`. Never load a public host. Do not use the default compose project.

Write a Playwright script in the scratch directory. Reuse the pairing code of the copied tools. Listen for the `dialog` event of each page and count the dialogs. Do these steps on the sales screen of one event:

1. Make one normal cash sale. Wait for one pass (at most 35 seconds).
2. Add one bad queue item to the store of the page. The store is IndexedDB, database `localforage`, object store `keyvaluepairs`. The key is `event_<id>_<milliseconds>`. The value has the shape of "Shapes that the tasks share". Its order has a `uid` and no `location`. The server answers 422 for it. Make sure that the next upload request gets status 422.
3. Wait for one pass. Make a second normal cash sale. Wait for one pass.
4. Set the browser context offline. Make a third cash sale. Wait 40 seconds. Set the context online. Wait at most 40 seconds. Do not open the sales screen again.

Expected:
- The count of dialogs is 0.
- The database holds the three cash sales with `location = 'Manual'`.
- The sales screen shows the element with the class `queue-rejected` and the count 1.
- The POS settings show "Pending queue items" 0, "Sales that are not stored" 1, and a reason.
- In the 60 seconds after its mark, the bad sale is sent zero times. The unit test of Task 2 holds the proof for the 10 minute rule.

Record the evidence: the dialog count, the database rows, the request log for the orders endpoint, and two screenshots. Tear the stack down with `down -v` and the project name.

- [ ] **Step 3: Write the `CLAUDE.md` section**

Write five to eight lines. Cover these points:

- The queue file and the two kinds of an upload error.
- The mark on a bad item, the 10 minute rule, and the rule for a status of 500.
- One pass at a time for an event.
- The silent flag.
- The two screens and the Errbit report.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "Document the POS upload queue"
```

- [ ] **Step 5: Stop before the push**

Do not push and do not open the pull request. Report the results. The owner decides about the push.
