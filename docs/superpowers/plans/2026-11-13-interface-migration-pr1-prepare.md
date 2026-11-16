# Prepare Step Implementation Plan (interface migration, pull request 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the later library swap smaller and create the browser safety net, without a change to a library.

**Architecture:** BootstrapVue 2 and `@vue/compat` stay. This plan first writes a walk-through list with a script and records a baseline on `main`. Then it removes three groups of Vue 2 patterns that also work on the current stack. One test file guards each group.

**Tech Stack:** Vue 3 through `@vue/compat`, BootstrapVue 2, Vitest, Playwright, the NFC simulator from pull request 0. Playwright runs through `npx` and is not added to `package.json`.

**Spec:** `docs/superpowers/specs/2026-11-13-vue3-interface-migration-design.md`, sections "Pull request 1" and "Verification".

## Global Constraints

- No library changes. `package.json` and `package-lock.json` do not change in this pull request. After `npm install`, run `git checkout -- package-lock.json`.
- `Vue.use(BootstrapVue)` stays in the three `app.js` files.
- Every screen keeps its layout and its behavior.
- This plan starts after pull request 0 is merged. The walk-through needs the NFC simulator.
- Work on the branch `feature/interface-migration-prepare`, made from `main`. Open a pull request. Do not push to `main`.
- Before each commit, run `git branch --show-current` and make sure that the result is `feature/interface-migration-prepare`.
- Commit messages end with the line `Co-Authored-By: Claude Fable 5.2 <noreply@anthropic.com>`.
- The baseline on `main` shows the console error of issue 251 (`assign is not a function`). It is a known failure in this pull request.

## Review Focus

- A component reads a service through `this.$cardService` (or a different service) after the move. Expected: the same object as before, in Manage and in POS.
- The POS shows the pairing screen (no stored token). Expected: the screen loads without a service and without an error.
- A screen with a timer (sales summary, sales history, remote orders, POS settings, devices) is left. Expected: the timer stops, as before the hook rename.
- The Manage app is used with a slow first request. Expected: no component reads a service before the app mounts.
- A `b-form-group` that was a `b-form-fieldset` shows its label and description as before.

The walk-through in Task 1 and Task 5 covers these lines. Task 2 adds the test for the first line.

## File Structure

| File | Responsibility |
|------|----------------|
| `docs/testing/frontend-walkthrough.md` | The list: each app, route, modal, dropdown, form, and table, with action and expected result |
| `docs/testing/baseline-2026-11-main.md` | The recorded result of the first run on `main` |
| `tools/walkthrough/walkthrough.mjs` | Playwright script for the list items marked `auto` |
| `tools/walkthrough/README.md` | How to start the stack and the script |
| `resources/tests/legacy-patterns.test.js` | Guards: no `Vue.prototype`, no `beforeDestroy`, no alias tags |
| `resources/tests/offline-support.test.js` | One assertion changes with the service move |
| `resources/{manage,pos,clients}/js/app.js` | Services move to `app.config.globalProperties` |
| `resources/{manage,pos,clients}/js/bootstrap.js` | The unused Popper block goes away |

---

### Task 1: Walk-through list, script, and baseline

**Files:**
- Create: `docs/testing/frontend-walkthrough.md`
- Create: `tools/walkthrough/walkthrough.mjs`
- Create: `tools/walkthrough/README.md`
- Create: `docs/testing/baseline-2026-11-main.md`
- Modify: `.gitignore` (add `tools/walkthrough/output/`)

**Interfaces:**
- Produces: the list file. Later pull requests run it again and compare with the baseline.
- Produces: `node tools/walkthrough/walkthrough.mjs`. It reads `WALK_BASE_URL` (default `http://localhost:8080`), `WALK_USER`, `WALK_PASSWORD`, and `WALK_NFC_SIM` (default `http://127.0.0.1:3030`). It writes `tools/walkthrough/output/<item-id>.png` and `tools/walkthrough/output/report.json`. The report is an array of `{ id, status: 'pass' | 'fail', consoleErrors: string[], note: string }`. If an item fails, the exit code is 1.

- [ ] **Step 1: Collect the routes and the interactive parts**

Read the `routes` arrays in `resources/manage/js/app.js`, `resources/pos/js/app.js`, and `resources/clients/js/app.js`. For each route component, list each `b-modal`, `b-dropdown`, `b-form`, and `b-table` in its template and in the components that it uses. Add the Blade pages: login, register, setup, and the QR generator.

- [ ] **Step 2: Write `docs/testing/frontend-walkthrough.md`**

One section for each of: Blade pages, Manage, POS, POS with the NFC simulator, Client, and "Owner on a device". Each item is one table row:

| Column | Content |
|--------|---------|
| ID | Stable, for example `manage-events-create` |
| Where | Route and the part of the screen |
| Action | What to do, in exact steps |
| Expected | What the screen shows afterward |
| Mode | `auto` (the script does it) or `manual` |

The list must cover at least the flows that the spec names in "Verification". The section "Owner on a device" repeats the POS and card items as numbered steps for the Android app.

- [ ] **Step 3: Write the script and its README**

Use Playwright through `npx playwright` with the installed Chrome (`channel: 'chrome'`). Do not add a package. One function for each `auto` item, named by its ID. The script records each `console` message of type `error` and each `pageerror` for the item that is running. For the card items, the script calls the HTTP interface of the NFC simulator. The README gives four commands: start the stack with `docker compose`, make a development build, start the simulator, and run the script.

- [ ] **Step 4: Run the baseline on `main`**

Start the stack from a build of `main`. Run the script. Do each `manual` item by hand.
Expected: each item passes, with one known exception: items that click a `b-form-checkbox` or change a `b-form-select` record the console error `assign is not a function`.

If a different item fails on `main`, do not repair it here. Record it in the baseline as a failure that existed before this work, and tell the owner.

- [ ] **Step 5: Write `docs/testing/baseline-2026-11-main.md`**

The file has these parts:

- The commit of `main`, the date, and the browser version.
- One line for each item, with pass or fail.
- The list of console errors, with the items that showed them.

- [ ] **Step 6: Commit**

```bash
git add docs/testing tools/walkthrough .gitignore
git commit -m "Add the frontend walk-through list, its script, and the baseline"
```

---

### Task 2: Move the services from `Vue.prototype` to the app

**Files:**
- Create: `resources/tests/legacy-patterns.test.js`
- Modify: `resources/tests/offline-support.test.js:109-110`
- Modify: `resources/manage/js/app.js` (lines near 172-210)
- Modify: `resources/pos/js/app.js` (lines near 113 and 239-369)
- Modify: `resources/shared/js/views/FinancialOverview.vue:75`

**Interfaces:**
- Produces: in `manage/js/app.js` and `pos/js/app.js`, a local object `const services = {}`. Boot code writes `services.$settingService`, `services.$organisationService`, `services.$cardService`, `services.$offlineManager`, `services.$kioskModeService`, and `services.$paymentService`. After `createApp(...)`, the code calls `Object.assign(app.config.globalProperties, services)`. Components keep `this.$cardService` and so on.
- `resources/clients/js/app.js` has no service on `Vue.prototype`. It keeps `Vue.use(VueCookies)` until pull request 3.

- [ ] **Step 1: Write the failing test**

Create `resources/tests/legacy-patterns.test.js`. Read the sources with `fs.readFileSync`, as `offline-support.test.js` does.

```js
import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

/** All .vue and .js files below resources/, without test files. */
function sourceFiles() { /* recursive walk, returns paths relative to root */ }

describe('Vue 2 patterns that pull request 1 removed', () => {
	it('no source file uses Vue.prototype', () => {
		const hits = sourceFiles().filter((f) => /Vue\.prototype\.\$/.test(read(f)));
		expect(hits).toEqual([]);
	});

	it('manage and pos attach the services to the app', () => {
		for (const file of ['manage/js/app.js', 'pos/js/app.js']) {
			expect(read(file)).toContain('Object.assign(app.config.globalProperties, services)');
		}
	});
});
```

The comment in `resources/shared/js/services/OfflineManager.js:27` names `Vue.prototype.$offlineManager`. Change that comment to `app.config.globalProperties.$offlineManager`, so that the first test can pass.

- [ ] **Step 2: Run the test and make sure that it fails**

Run: `npx vitest run resources/tests/legacy-patterns.test.js`
Expected: FAIL. The first test lists `manage/js/app.js`, `pos/js/app.js`, `shared/js/views/FinancialOverview.vue`, and `shared/js/services/OfflineManager.js`.

- [ ] **Step 3: Change the two `app.js` files**

Replace each `Vue.prototype.$name` by `services.$name`. Declare `const services = {};` before the first use. In `pos/js/app.js`, the early app for the `Authenticate` screen gets no services. Add the `Object.assign` line directly after each `createApp({ components: {App}, ... })` call that mounts the main app.

- [ ] **Step 4: Change `FinancialOverview.vue` and the old assertion**

In `FinancialOverview.vue:75`, use `this.$organisationService`. If `this` is not the component at that line, keep a reference to the component in a local variable first. If nothing else in that file uses the `Vue` import, remove the import.

In `offline-support.test.js:110`, the expected source line becomes `services.$offlineManager = offlineManager`. Change the test name on line 109 to match.

- [ ] **Step 5: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS.

- [ ] **Step 6: Make sure that the apps still get their services**

Run: `npm run dev`, then `node tools/walkthrough/walkthrough.mjs`
Expected: the report equals the baseline. Look at the sales summary in Manage, the financial overview, and one POS sale. These screens read `$cardService`, `$organisationService`, and `$paymentService`.

- [ ] **Step 7: Commit**

```bash
git add resources/tests resources/manage/js/app.js resources/pos/js/app.js resources/shared/js/views/FinancialOverview.vue resources/shared/js/services/OfflineManager.js
git commit -m "Attach the services to the app instead of Vue.prototype"
```

---

### Task 3: Rename the `beforeDestroy` hook

**Files:**
- Modify: `resources/tests/legacy-patterns.test.js`
- Modify: `resources/shared/js/components/SalesSummary.vue:131`
- Modify: `resources/shared/js/components/SalesHistory.vue:74`
- Modify: `resources/shared/js/components/RemoteOrderStatus.vue:56`
- Modify: `resources/shared/js/components/RemoteOrders.vue:188`
- Modify: `resources/pos/js/views/Settings.vue:260`
- Modify: `resources/manage/js/views/Devices.vue:269`

- [ ] **Step 1: Write the failing test**

Add to the `describe` block:

```js
it('no component uses the beforeDestroy or destroyed hook', () => {
	const hits = sourceFiles().filter((f) => /\b(beforeDestroy|destroyed)\s*\(/.test(read(f)));
	expect(hits).toEqual([]);
});
```

- [ ] **Step 2: Run the test and make sure that it fails**

Run: `npx vitest run resources/tests/legacy-patterns.test.js`
Expected: FAIL with the six files.

- [ ] **Step 3: Rename `beforeDestroy` to `beforeUnmount` in the six files**

Change only the hook name. The body stays.

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS.

- [ ] **Step 5: Make sure that the timers stop**

Run a development build. Open each of the six screens, then go to a different route. In the browser network panel, the repeated requests of the screen that you left must stop.
Expected: no request from the old screen after 30 seconds.

- [ ] **Step 6: Commit**

```bash
git add resources
git commit -m "Rename beforeDestroy to beforeUnmount"
```

---

### Task 4: Canonical tag names and the unused Popper block

**Files:**
- Modify: `resources/tests/legacy-patterns.test.js`
- Modify: the seven files that use `<b-btn` or `<b-form-fieldset`: `shared/js/components/SalesSummary.vue`, `shared/js/components/Spreadsheet.vue`, `clients/js/views/Order.vue`, `manage/js/views/OrganisationSettings.vue`, `pos/js/views/Settings.vue`, `pos/js/views/App.vue`, `manage/js/views/Devices.vue`
- Modify: `resources/manage/js/bootstrap.js`, `resources/pos/js/bootstrap.js`, `resources/clients/js/bootstrap.js`

- [ ] **Step 1: Write the failing test**

```js
it('no template uses a BootstrapVue alias tag', () => {
	const hits = sourceFiles().filter((f) => /<\/?b-(btn|form-fieldset)\b/.test(read(f)));
	expect(hits).toEqual([]);
});

it('no bootstrap.js loads Popper', () => {
	for (const app of ['manage', 'pos', 'clients']) {
		expect(read(app + '/js/bootstrap.js')).not.toContain('popper');
	}
});
```

- [ ] **Step 2: Run the test and make sure that it fails**

Run: `npx vitest run resources/tests/legacy-patterns.test.js`
Expected: FAIL with the seven template files and the three `bootstrap.js` files.

- [ ] **Step 3: Replace the tags**

`<b-btn` and `</b-btn>` become `<b-button` and `</b-button>`. `<b-form-fieldset` and `</b-form-fieldset>` become `<b-form-group` and `</b-form-group>`. Props and content stay.

- [ ] **Step 4: Remove the Popper block from the three `bootstrap.js` files**

Remove the `try { window.Popper = require('popper.js').default; } catch (e) {}` block and the comment above it that names jQuery and the Bootstrap jQuery plugin. Before the removal, run `grep -rn "window.Popper\|Popper\." resources` and make sure that the only hits are the three blocks.

- [ ] **Step 5: Run the tests and make sure that they pass**

Run: `npm test`
Expected: PASS.

- [ ] **Step 6: Make sure that dropdowns still open**

Run a development build. Open the Actions dropdown on the Manage events page and the navbar dropdown in POS.
Expected: both menus open at the right place. BootstrapVue 2 imports Popper by itself.

- [ ] **Step 7: Commit**

```bash
git add resources
git commit -m "Use canonical BootstrapVue tag names and remove the unused Popper block"
```

---

### Task 5: Full verification and the pull request

**Files:**
- Modify: `CLAUDE.md` (in "Building & Testing", add a short section "Frontend walk-through" that points to `docs/testing/frontend-walkthrough.md` and `tools/walkthrough/README.md`)

- [ ] **Step 1: Run the automated tests**

Run: `npm test` and `npx jest`
Expected: both PASS.

- [ ] **Step 2: Run the production build in a clean copy**

Copy the tracked files to a directory outside the repository (`git archive HEAD | tar -x -C <directory>`). There, run `npm install` and `npm run prod`.
Expected: the build ends without an error and writes `public/res/pos.js`, `public/res/pos.css`, and `public/res/manage.js`.

- [ ] **Step 3: Run the full walk-through**

Start the stack from a production build of the branch. Run the script. Do each `manual` item.
Expected: each item has the same result as in `docs/testing/baseline-2026-11-main.md`. The only console error is the known `assign is not a function`.

- [ ] **Step 4: Make sure that no library changed**

Run: `git diff main --stat -- package.json package-lock.json`
Expected: no output.

- [ ] **Step 5: Commit the `CLAUDE.md` section and open the pull request**

```bash
git add CLAUDE.md
git commit -m "Document the frontend walk-through"
```

Push the branch and open a pull request to `main`. The description lists the four changes and states the result of Step 3. It ends with the line `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
