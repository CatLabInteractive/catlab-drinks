# Interface migration: BootstrapVue 2 to bootstrap-vue-next, Bootstrap 5, real Vue 3

- Date: 2026-11-13
- Status: approved by the owner on 2026-11-13
- Trigger: GitHub issue 251, `i is not a function` on `/manage/events`.

## Place in the larger goal

The end goal is a modern application on the newest versions of Vue, Laravel, and the other libraries.
That goal has four projects. This document covers only the first one.

1. Interface migration (this document).
2. Build: replace Laravel Mix with Vite.
3. Library cleanup: jQuery, `popper.js`, `moment`, one test runner in place of Jest plus Vitest.
4. Laravel 13 with Passport 13.

Each project gets its own design document. Projects 2, 3, and 4 are not part of this work.

## Intent

The three frontend apps (Manage, POS, Client) use BootstrapVue 2. That library supports only Vue 2,
and its maintainers stopped work on it. The apps run it on `@vue/compat`, the Vue 3 build that imitates
Vue 2. The mismatch causes errors. Issue 251 is one: each click on a `b-form-checkbox` throws
`assign is not a function`, because BootstrapVue 2 uses a Vue 2 `model` directive that compat maps to
the Vue 3 one. The same error occurs for `b-form-select`.

This project removes the mismatch at the source. After it, the apps run on real Vue 3 with a component
library that is written for Vue 3.

## Goals

- No file imports `bootstrap-vue`. The apps use `bootstrap-vue-next` (1.2.4 or newer) and Bootstrap 5.3.
- No file uses `@vue/compat`. `vue` resolves to the real Vue 3 package in the build and in the tests.
- Every screen keeps its layout and its behavior.
- Issue 251 does not occur. A test proves it.
- `main` can go to production after each pull request.

## Non-goals

- No redesign. The look changes only where Bootstrap 5 differs from Bootstrap 4.
- No change to the build tool. Laravel Mix stays in this project.
- No removal of jQuery, `popper.js`, `moment`, or Jest. There is one exception. If a package has no
  user left after this work, the builder can remove it. An example is `popper.js`.
- No move from the Options API to the Composition API. Components keep their current style.
- No backend change.

## Anti-goals

- A change that passes build and tests but breaks a screen in the browser. This occurred in the last
  npm upgrade. Build and tests are not sufficient proof for this project.
- A wrapper layer of our own components around the library.
- A pull request that mixes this work with projects 2, 3, or 4.

## Constraints

- The Android app loads `res/pos.js` and `res/pos.css` by fixed name. The output names must not change.
- The Android app loads those two files from the server each time it starts (stated by the owner). A
  deploy of the swap therefore changes each installed POS at once. Two rules follow.
  - Before the merge of pull request 2 or 3, the branch runs on a staging instance. The owner points
    the Android app at that instance and runs the POS part of the walk-through there.
  - The owner selects the moment of the production deploy, at a time without a running event.
- The POS runs in a native wrapper with hash history. Only the owner can test in that wrapper.
- Assumption of the author, not confirmed by the owner: the shell of the Android app has no Bootstrap
  markup or styles of its own, and its build does not use `resources/pos/sass/capacitor.scss`. If the
  staging test shows otherwise, the wrapper repository needs a matching change before the merge.
- No NFC simulator exists (stated by the owner). The card screens (top-up, card payment, the Cards view,
  key approval) need a reader. Pull request 0 adds a simulator so that the builder can test these
  screens in a desktop browser. The owner still tests them one time on a real device before the merge
  of the swap.
- The three apps share 29 of the 48 `.vue` files and the Sass in `resources/shared/sass/`. Bootstrap 4
  and Bootstrap 5 styles cannot be on one page. The three apps therefore move in the same pull request.
- These pull requests change `package.json`. The matching `package-lock.json` changes are part of them.
  This is the stated exception to the rule in `CLAUDE.md`. Use `npm install`, never `npm update`.
- A build in a worktree under `.claude/worktrees/` can pass because of the `node_modules` of the main
  working copy. Make sure that each dependency change also builds in a clean copy outside the repository
  (`npm install`, then `npm run prod`), as `CLAUDE.md` describes.
- Work on feature branches and open pull requests. Do not push to `main`.

## Current state

Counts come from a search of `resources/` on 2026-11-13.

- About 390 `b-*` tags in 31 component types. Most used: `b-button` (40), `b-spinner` (38),
  `b-alert` (35), `b-modal` (32), `b-form-group` (25), `b-dropdown-item` (25), `b-container` (22),
  `b-col` (21), `b-row` (20), `b-btn` (16), `b-form-input` (14), `b-table` (9).
- No use of `$bvModal`, `$bvToast`, `v-b-*` directives, or `$root.$emit('bv::...')`.
- Modals open and close through template refs: 50 calls to `.show()` and 33 to `.hide()`.
- Tables use 37 `v-slot:cell(key)` slots and one `v-slot:row-details` slot.
- The three `app.js` files call `Vue.use(BootstrapVue)` on the compat default export and then
  `createApp(...)`. They put services on `Vue.prototype` (about 40 lines, most in `resources/pos/js/app.js`).
  `FinancialOverview.vue` reads `Vue.prototype.$organisationService`.
- Six components use the `beforeDestroy` hook.
- Sass entry files that import Bootstrap 4: `resources/sass/app.scss` (Blade pages),
  `resources/shared/sass/app.scss`, `resources/clients/sass/app.scss`. The last two also import
  `bootstrap-vue/src/index.scss`.
- About 220 uses of class names that Bootstrap 5 renamed or removed: `form-group` (78), `close` (36),
  `text-right` (21), `mr-*` and `ml-*` (about 60), `sr-only` (10), `badge-*` (10), `btn-block` (3),
  and some more. Not each hit is a Bootstrap class (`close` and `media` also occur as plain words).
- `resources/views/layouts/app.blade.php` uses `data-toggle` and `data-target` for the navbar.
- `@vue/compat` is set in `webpack.mix.js` (alias and `compatConfig`) and in `vitest.config.js` (alias).

## Approaches

Three ways were considered.

1. Direct swap on one branch. One pull request changes the library, Bootstrap, all templates, the Sass,
   and removes compat. It has the least total work. But the pull request touches almost every frontend
   file, review is hard, and `main` changes below the branch while it is open.
2. Three pull requests, with `main` ready for production after each. Chosen by the owner. A pull
   request 0 for the NFC simulator was added later, at the request of the owner.
3. Our own wrapper components between the screens and the library. This protects against the next
   library change. But it needs 31 wrappers that we must maintain, and the tag names stay almost the same.
   Rejected.

## Design

### Pull request 0: NFC simulator

The POS talks to a card reader through `RemoteNfcReader`, which connects to a socket.io service
(`nfc-socketio`). The simulator is a small Node program that takes the place of that service. It is a
development tool. It changes no production code and does not go into the frontend bundles.

- Location: `tools/nfc-simulator/`. Start it with an npm script, for example `npm run nfc-simulator`.
- Protocol: the simulator speaks the events that `RemoteNfcReader.ts` uses. It answers `hello`. It sends
  `nfc:card:connect`, `nfc:data`, and `nfc:card:disconnect`. It accepts `nfc:password` and `nfc:write`.
  The builder reads `RemoteNfcReader.ts` and the public `nfc-socketio` repository for the exact payloads.
- Cards: the simulator holds virtual cards in memory. Each card has a UID and its NDEF content. A new
  card is empty. A write from the POS replaces the content, so that the next tap returns the new balance.
- Control: a small HTTP interface on the same port lets a person or a test script do three actions:
  put a card on the reader (by UID), remove the card, and read the list of cards with their content.
  A browser walk-through with Playwright can call this interface.
- Use: set the NFC server address in the POS settings to the address of the simulator. No other change.
- Tests: one automated test starts the simulator, connects with `socket.io-client`, and does a tap,
  a write, and a second tap.
- Limit: the simulator does not imitate reader faults, a card that leaves during a write, or the
  Android reader (`AppNfcReader`). The device test by the owner covers the Android reader.

Two other forms were considered. A simulated reader class inside the POS, with a panel on the screen,
adds code and interface parts to production. Rejected. No simulator at all leaves the card screens
without a test by the builder. Rejected by the owner.

### Pull request 1: prepare, on the current stack

This pull request changes no library. BootstrapVue 2 and compat stay. The purpose is to make pull
request 2 smaller and to create the safety net.

- Write the walk-through list (see "Verification"). Run it once on `main` and record the result. That
  record is the baseline for the later runs.
- Move the services from `Vue.prototype` to `app.config.globalProperties`. Some boot code uses a service
  before the app exists. There, hold the service in a local variable and attach it to the new app.
  `FinancialOverview.vue` uses `this.$organisationService`.
- Rename `beforeDestroy` to `beforeUnmount` in the six components.
- Replace the BootstrapVue aliases with the canonical names, which BootstrapVue 2 also accepts:
  `b-btn` to `b-button` (16), `b-form-fieldset` to `b-form-group` (5).
- If there is dead code that refers to jQuery plugins of Bootstrap, remove it.

`Vue.use(BootstrapVue)` stays in this pull request. BootstrapVue 2 needs the Vue 2 style constructor.

### Pull request 2: the swap

First step: a spike. Convert only the Manage events page on a scratch branch and discard the branch.
The spike answers two questions.

- Does `bootstrap-vue-next` operate correctly while `vue` is aliased to `@vue/compat`? If it does not,
  pull requests 2 and 3 become one pull request. This is the planned fallback and needs no new approval.
- What is the real change for each component type? The answers go into the implementation plan.

Then the swap, for the three apps together:

- Packages: remove `bootstrap-vue`. Add `bootstrap-vue-next` and the peer packages that it needs.
  Set `bootstrap` to `^5.3`. Remove the `bootstrap` entry from `overrides`, which exists only for Bootstrap 4
  and jQuery 4.
- Registration: one shared module, `resources/shared/js/ui/bootstrap.js`, exports a function that takes the
  app, installs the plugin of the library, and registers by name each component that the apps use. The three
  `app.js` files and `qrGenerator.js` (if it needs it) call that function. `bootstrap-vue-next` does not
  register components globally by itself. Its resolver is for build plugins, and the build tool changes in
  project 2. An explicit list works with Mix now and with Vite later. (Call of the author.)
- Sass: the three entry files import Bootstrap 5 and the styles of `bootstrap-vue-next`. Adapt the
  variables in `resources/sass/_variables.scss` to their Bootstrap 5 names where they changed.
- Class names: apply the Bootstrap 5 names in `.vue`, `.blade.php`, and `.scss` files. Look at each hit.
  Do not replace by a blind search. The known renames:

  | Bootstrap 4 | Bootstrap 5 |
  |-------------|-------------|
  | `ml-*`, `mr-*` | `ms-*`, `me-*` |
  | `text-right`, `text-left` | `text-end`, `text-start` |
  | `float-right` | `float-end` |
  | `sr-only` | `visually-hidden` |
  | `font-weight-bold` | `fw-bold` |
  | `badge-danger` and the other badge colors | `text-bg-danger` and so on |
  | `btn-block` | a `d-grid` parent, or `w-100` |
  | `form-group` | a margin utility such as `mb-3` |
  | `close` | `btn-close` |
- Blade pages: `data-toggle` and `data-target` become `data-bs-toggle` and `data-bs-target`. Make sure
  that the Blade layout loads the Bootstrap 5 script that these attributes need, or replace the navbar
  toggle with a few lines of plain script. The builder decides.
- Components. The known changes, from the migration notes of the library:

  | Component | Change |
  |-----------|--------|
  | `b-modal` | `hide-footer` to `no-footer`, `hide-header` to `no-header`, `hide-header-close` to `no-header-close`. `.show()` and `.hide()` on a template ref stay. The `#modal-footer` slot is `#footer`. |
  | `b-alert` | Visibility is the `v-model`. A bound `:show="x"` becomes `:model-value="x"`. `@dismissed` becomes `@closed`. |
  | `b-dropdown` | `right` becomes `placement="bottom-end"`. Menus use floating-ui, not Popper. |
  | `b-button` | `block` is gone. Use `d-grid` on the parent or `w-100`. |
  | `b-form` | `inline` is gone. Use Bootstrap 5 grid or flex classes. |
  | Form inputs | The model prop is `model-value` and the event is `update:model-value`. `@input` and `@change` are now the native events, so their argument is an `Event`, not the value. `v-model` in a template stays. |
  | `b-form-select` | `options` as an object is not supported. Use an array. |
  | `b-form-group` | `label-sr-only` becomes `label-visually-hidden`. |
  | `b-navbar` | `type` is deprecated. `ml-auto` on `b-navbar-nav` becomes `ms-auto`. |
  | `b-collapse` | Visibility is the `v-model`. The `$root` toggle events are gone. |
  | `b-table` | Sorting props and events changed. `v-slot:cell(key)` stays. Make sure that `row-details` and `toggleDetails` still operate. |
  | `b-badge` | No hover style on a linked badge. |

  The notes say that they are not complete. The spike and the walk-through find the rest.
- Test for issue 251: a Vitest test mounts `BFormCheckbox` and `BFormSelect` from the new library, sends a
  `change` event, and expects no error and the new value. The same test against BootstrapVue 2 fails with
  `assign is not a function` (measured on 2026-11-13).
- Close issue 251 with this pull request.

### Pull request 3: remove compat

If the fallback merged this work into pull request 2, skip this pull request.

- Remove the `vue` and `@vue/composition-api` aliases and the `compatConfig` block from `webpack.mix.js`.
  Remove the alias from `vitest.config.js`. Remove `@vue/compat` from `package.json`.
- Replace the default import of `Vue` in the `app.js` files. Plugins such as `vue-cookies` go through
  `app.use(...)`.
- Repair each Vue 2 behavior that compat still supplied. Search for at least these patterns:
  - `$listeners`, `$scopedSlots`, `$children`
  - `$set`, `$delete`
  - `$on`, `$off`, `$once`
  - `filters`, `.native`, `.sync`, `slot-scope`
  - a `v-model` on our own components that relies on `value` and `input`
  - an attribute that relies on `false` to stay in the DOM
- Run the build in development mode and read the browser console on each walk-through screen. A Vue
  warning is a defect in this pull request.

## Verification

Each pull request must pass all of these before it merges.

- `npm test` and `npx jest` pass.
- `npm run prod` passes in a clean copy outside the repository.
- The walk-through passes in a real browser. The list is a new file, `docs/testing/frontend-walkthrough.md`,
  written in pull request 1. The list has one section for each app. Each section names each route.
  For each route it names each modal, dropdown, form, and table. Each entry gives the action to do and
  the result to expect. The browser console must show no error during the walk-through.
  The list covers at least these flows:
  - Blade pages: login and setup.
  - Manage: create an event with the wizard, edit an event, the order token modal.
  - Manage: menu edit and menu import.
  - Manage: devices and pairing, public keys.
  - Manage: organisation settings and the organisation switch.
  - POS: pairing, sales with an order, settings.
  - POS with the NFC simulator: top-up, card payment, the Cards view, key approval.
  - Shared: sales summary and sales history.
  - Client: one order from start to end.
- For pull request 2 and 3: the owner runs the POS part and the card section of the list in the Android
  app, against the staging instance, before the merge.
- For pull request 2: screenshots of the main screens before and after, side by side in the pull request,
  so that the owner can accept the Bootstrap 5 look.
- The look starts from stock Bootstrap 5. The builder does not restore the Bootstrap 4 look in advance.
  The owner reviews the screenshots one time before the merge. If the owner rejects a difference, the
  builder adds a Sass override for that difference only. (Call of the author.)
- The baseline run on `main` shows the console errors of issue 251. They are known failures for pull
  request 1 and must be gone after pull request 2.

## Risks

- `bootstrap-vue-next` under compat can fail. The fallback (merge pull requests 2 and 3) covers it.
- The migration notes of the library are not complete. The spike and the walk-through cover it.
- An old Android WebView on a POS device can lack something that Bootstrap 5 or floating-ui needs. Vue 3
  already runs there, so the risk is small. The Android test by the owner covers it.
- The Bootstrap 5 look differs in small ways (form controls, close buttons, link underlines, spacing).
  The screenshots let the owner accept or reject each difference.

## Left to the builder

- The exact plugin call and the list of peer packages for `bootstrap-vue-next`, read from the
  documentation of the installed version.
- The order of work inside each pull request.
- How to run the walk-through (Playwright or the Chrome extension) and where to keep the screenshots.
- The replacement for each Bootstrap 4 class, case by case.
- The Blade navbar toggle: Bootstrap 5 script or plain script.
- Whether a package without users after this work is removed here or in project 3.
