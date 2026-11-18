# Sales Summary Sorting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The user sorts both sales summary tables by clicking a column header, and the names tab shows only Rank, Name, Amount and Total.

**Architecture:** A pure module (`summarySort.js`) sorts the row groups and decides the next sort state. `SalesSummary.vue` keeps the sort state and shows a computed sorted list. The rows stay frozen, so the sort returns a new array.

**Tech Stack:** Vue 3 with `@vue/compat`, vue-i18n, Vitest (jsdom).

**Spec:** `docs/superpowers/specs/2026-11-18-sales-summary-sorting-design.md`

## Global Constraints

- Sortable: Rank, Item/Name, Amount, Total, and Price on the items tab only.
- First click: ascending, except Amount and Total (descending). A second click on the same column reverses.
- Default (no click yet): the order of today, no arrow.
- Items tab: groups sort as one unit. Amount and Total use the group sum. Price uses the highest price of the group.
- Rows without a rank go to the end in a rank sort, in both directions.
- Equal values keep the default order.
- Text: `Intl.Collator(locale, { sensitivity: 'base' })` with the app locale (`getLocale()`).
- The names tab has no Price, VAT %, Netto and VAT columns (header, rows and footer).
- Work on `feature/sales-summary-sorting`, never on `main`. Do not commit `package-lock.json`.

## Review Focus

- A refresh while a sort is active: the new rows show in the chosen order. Test in Task 2 (`keeps the sort after a refresh`).
- Names tab with "Unknown / manual" rows and a rank sort: those rows go last in both directions. Test in Task 1 (`puts groups without a rank last in both directions`).
- Items tab product sold at two prices: its rows stay together. Test in Task 1 (`sorts a group by the sum of its rows`).
- Names with different case or accents ("élan", "Alles", "agent"): they sort as a person expects. Test in Task 1 (`sorts names with the locale, ignoring case`).
- The frozen rows: sorting must not throw on a frozen array. Test in Task 1 (`does not change a frozen input`).

---

### Task 1: Sort module

**Files:**
- Create: `resources/shared/js/utils/summarySort.js`
- Test: `resources/shared/js/utils/summarySort.test.js`

**Interfaces:**
- Produces: `export function nextSort(current: {key, direction}|null, key: 'rank'|'name'|'amount'|'price'|'total'): {key, direction: 'asc'|'desc'}`.
- Produces: `export function sortGroups(groups: Array, sort: {key, direction}|null, locale: string): Array`. With `sort` null it returns `groups` itself. Otherwise it returns a new array. A group is `{ rank?, menuItem?: { name }, visibleName?, sales: [{ amount, totalSales, price }] }`. The name of a group is `menuItem.name`, or else `visibleName`.

- [ ] **Step 1: Write the failing tests**
  - `nextSort(null, 'name')` → `{ key: 'name', direction: 'asc' }`. `nextSort(null, 'amount')` and `nextSort(null, 'total')` → direction `desc`. `nextSort({ key: 'amount', direction: 'desc' }, 'amount')` → `asc`. `nextSort({ key: 'amount', direction: 'asc' }, 'name')` → `{ key: 'name', direction: 'asc' }`.
  - `returns the input unchanged without a sort`: `sortGroups(g, null, 'nl')` is `g`.
  - `does not change a frozen input`: a frozen array sorted by name returns a different array, and the input order is the same.
  - `sorts a group by the sum of its rows`: group A with sales amounts 10 and 2, group B with 11 → amount desc gives A, B.
  - `sorts by the highest price of a group`: A with prices 2 and 1, B with 1.5 → price asc gives B, A.
  - `puts groups without a rank last in both directions`: ranks 2, undefined, 1 → rank asc gives 1, 2, none. Rank desc gives 2, 1, none.
  - `keeps the default order for equal values`: three groups with total 5 keep their input order in both directions.
  - `sorts names with the locale, ignoring case`: "élan", "Alles", "agent" → name asc gives "agent", "Alles", "élan".
- [ ] **Step 2: Run** `npx vitest run resources/shared/js/utils/summarySort.test.js`. Expected: FAIL, module not found.
- [ ] **Step 3: Implement** `summarySort.js` as in Interfaces. Break ties on the input index.
- [ ] **Step 4: Run** the same command. Expected: all pass.
- [ ] **Step 5: Commit** `git commit -m "Add sorting for the sales summary rows"`

### Task 2: Sortable headers and names columns

**Files:**
- Modify: `resources/shared/js/components/SalesSummary.vue`
- Modify: `resources/shared/js/components/SalesSummary.test.js`
- Modify: `resources/shared/js/i18n/{en,nl,fr,de,es}.js` (for a new string only)

**Interfaces:**
- Consumes: `nextSort`, `sortGroups` (Task 1).
- Produces: data `sort: null`. Method `setSort(key)` sets `sort = nextSort(sort, key)`. Computed `sortedGroups` returns `sortGroups(groupedItems, sort, getLocale())`. Computed `isNames` is `summaryType === 'names'`.

- [ ] **Step 1: Write the failing tests** in `SalesSummary.test.js`:
  - `sorts the rows when a header is clicked`: after a load with two products (amounts 2 and 5), `setSort('amount')` → `sortedGroups` has the amount-5 product first.
  - `keeps the sort after a refresh`: with `sort` on amount desc, a refresh that changes the amounts gives the new order in `sortedGroups`.
- [ ] **Step 2: Run** `npx vitest run resources/shared/js/components/SalesSummary.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement** the Interfaces. The `tbody` loops over `sortedGroups`.
- [ ] **Step 4: Headers.** Each sortable header holds a `<button type="button" class="btn btn-link p-0 font-weight-bold text-reset">` that calls `setSort(key)`. The `th` gets `aria-sort` (`ascending`, `descending`, or `none`). The active column shows `▲` (asc) or `▼` (desc) after its label. The Price header is sortable on the items tab only.
- [ ] **Step 5: Names columns.** Wrap the Price, VAT %, Netto and VAT cells in `v-if="!isNames"` in the header, the rows and the footer.
- [ ] **Step 6: Run** `npx vitest run` and `npm run dev`. Expected: all pass, the build has no errors.
- [ ] **Step 7: Check in the browser** (Manage, both tabs): a click on each header sorts, a second click reverses, the arrow follows. The names tab shows four columns. The order stays after a 30-second refresh.
- [ ] **Step 8: Commit** `git commit -m "Sort the sales summary by column and hide empty columns on the names tab"`
