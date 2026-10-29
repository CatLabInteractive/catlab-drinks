# Event Creation Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the flag-named checkbox form for events in Manage with three plain questions, asked step by step when creating and on one screen when editing.

**Architecture:** A pure JS module (`eventSettings.js`) maps the three answers to the existing event flags and back, and validates them; it has the only non-trivial logic and is unit-tested without Vue. A new `EventForm.vue` component renders the questions as clickable tiles, in `wizard` (3 steps) or `single` (stacked) mode, and emits the mapped event on submit. `Events.vue` keeps the modal, the API calls and the list.

**Tech Stack:** Vue 3 via `@vue/compat` (compiler MODE 3), Bootstrap-Vue 2, vue-i18n (`$t()` with literal keys), Laravel Mix build, Vitest for JS tests (source-string tests for `.vue` files; no SFC compilation in Vitest).

**Spec:** `docs/superpowers/specs/2026-10-29-event-wizard-design.md`

## Global Constraints

- Manage app only. No changes under `app/`, `database/`, `resources/pos`, `resources/clients`.
- Every new `$t('...')` key must be added to all five locales in `resources/shared/js/i18n/` (`en.js`, `nl.js`, `fr.js`, `de.js`, `es.js`). `resources/tests/i18n-completeness.test.js` fails otherwise. English values equal the key; Dutch values are given in this plan; French, German and Spanish wording is the builder's.
- Translation keys must be literal `$t('...')` calls in templates, never looked up from a data structure (the completeness test only finds literals).
- Never run `npm update`; never commit `package-lock.json` (run `git checkout -- package-lock.json` before committing if it changed).
- Run tests with `npx vitest run`. Before committing, `git branch --show-current` must print `worktree-feature+event-wizard`.
- Commit messages end with `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.

## Review Focus

Inputs the spec implies but does not spell out; each has a pinned test in the task that owns it.

1. **Voucher value typed with a decimal comma ("2,50")**, the Belgian default. Must be stored as `2.5`, not rejected or NaN. → Task 1 (`parseVoucherValue`).
2. **Flags arriving as `0`/`1` integers instead of booleans** from older rows. Derivation and tiles must treat them as truthy/falsy. → Task 1 (`deriveOnlineOrdering`, `answersFromEvent`).
3. **Pressing Enter in the name field on step 1.** Must advance to step 2 (or do nothing if invalid), never submit a half-filled event. → Task 2 (`onSubmit` routing).
4. **Reopening the modal for "create" right after editing an event.** The form must show the defaults again, not the last edited event. → Task 2 (watch on `event` prop) and Task 3 (`resetForm` sets `model = {}`).
5. **An existing event with `checkin_url: null`** must show an empty field and, when left empty, be saved as `null` (column is nullable), never as the string `"null"` or `""`. → Task 1 (`answersFromEvent`, `eventFromAnswers`).

---

### Task 1: Answer ↔ flag mapping module

**Files:**
- Create: `resources/manage/js/services/eventSettings.js`
- Test: `resources/manage/js/services/eventSettings.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces (all named exports):
  ```js
  export const ONLINE_ORDERING = { NONE: 'none', UNPAID: 'unpaid', INTEGRATION: 'integration' };

  // 'unpaid' if allow_unpaid_online_orders is truthy; else 'integration' if is_selling and payment_cards are truthy; else 'none'.
  export function deriveOnlineOrdering(event) // -> 'unpaid' | 'integration' | 'none'

  // Accepts a number or string, decimal comma allowed. null when not a finite number.
  export function parseVoucherValue(value) // -> number | null

  // Answers object the form edits. For an event without `id` (create): name '', payment_cards true,
  // payment_cash false, payment_vouchers false, payment_voucher_value null, online_ordering null,
  // split_orders_by_categories false, checkin_url ''. For an existing event: each from the event
  // (booleans coerced with !!), checkin_url '' when null/undefined, online_ordering from deriveOnlineOrdering.
  export function answersFromEvent(event) // -> Answers

  // { name: 'required'|null, payment: 'none_selected'|'voucher_value'|null, onlineOrdering: 'required'|null, valid: boolean }
  // name: trimmed empty. payment: none of the three selected -> 'none_selected'; vouchers selected and
  // parseVoucherValue(value) null or <= 0 -> 'voucher_value'. onlineOrdering: null, or 'integration' while
  // payment_cards is false -> 'required'. valid: all three null.
  export function validateAnswers(answers) // -> Errors

  // Object.assign({}, original, mapped). mapped: name (trimmed), payment_cards, payment_cash, payment_vouchers,
  // payment_voucher_value (parseVoucherValue when vouchers selected, else null),
  // allow_unpaid_online_orders (online_ordering === 'unpaid'), split_orders_by_categories,
  // checkin_url (trimmed, null when empty), is_selling per the rule below.
  // is_selling: no original.id -> online_ordering !== 'none';
  //             original.id and online_ordering !== deriveOnlineOrdering(original) -> online_ordering !== 'none';
  //             otherwise original.is_selling unchanged.
  export function eventFromAnswers(answers, original) // -> event payload for EventService.create/update
  ```
  `Answers` keys: `name, payment_cards, payment_cash, payment_vouchers, payment_voucher_value, online_ordering, split_orders_by_categories, checkin_url`.

- [ ] **Step 1: Write the failing tests**

`resources/manage/js/services/eventSettings.test.js` (same style as `organisationSelection.test.js` next to it):

```js
import { describe, expect, it } from 'vitest';
import {
	ONLINE_ORDERING, deriveOnlineOrdering, parseVoucherValue,
	answersFromEvent, validateAnswers, eventFromAnswers,
} from './eventSettings';

describe('deriveOnlineOrdering', () => {
	it('is unpaid when unpaid online orders are allowed', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: true, is_selling: false, payment_cards: false })).toBe('unpaid');
	});
	it('is integration when selling with cards and no unpaid orders', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: true, payment_cards: true })).toBe('integration');
	});
	it('is none when selling without cards', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: true, payment_cards: false })).toBe('none');
	});
	it('is none when closed', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: false, is_selling: false, payment_cards: true })).toBe('none');
	});
	it('accepts integer flags', () => {
		expect(deriveOnlineOrdering({ allow_unpaid_online_orders: 0, is_selling: 1, payment_cards: 1 })).toBe('integration');
	});
});

describe('parseVoucherValue', () => {
	it('accepts a decimal comma', () => { expect(parseVoucherValue('2,50')).toBe(2.5); });
	it('accepts numbers and numeric strings', () => {
		expect(parseVoucherValue(3)).toBe(3);
		expect(parseVoucherValue('1.25')).toBe(1.25);
	});
	it('returns null for empty or garbage', () => {
		expect(parseVoucherValue('')).toBeNull();
		expect(parseVoucherValue(null)).toBeNull();
		expect(parseVoucherValue('abc')).toBeNull();
	});
});

describe('answersFromEvent', () => {
	it('gives create defaults for an event without id', () => {
		expect(answersFromEvent({})).toEqual({
			name: '', payment_cards: true, payment_cash: false, payment_vouchers: false,
			payment_voucher_value: null, online_ordering: null,
			split_orders_by_categories: false, checkin_url: '',
		});
	});
	it('derives answers from an existing event', () => {
		const answers = answersFromEvent({
			id: 7, name: 'Quiz', payment_cards: 1, payment_cash: 0, payment_vouchers: 1, payment_voucher_value: 2.5,
			allow_unpaid_online_orders: 0, is_selling: 1, split_orders_by_categories: 0, checkin_url: null,
		});
		expect(answers).toEqual({
			name: 'Quiz', payment_cards: true, payment_cash: false, payment_vouchers: true,
			payment_voucher_value: 2.5, online_ordering: 'integration',
			split_orders_by_categories: false, checkin_url: '',
		});
	});
});

describe('validateAnswers', () => {
	const valid = {
		name: 'Quiz', payment_cards: true, payment_cash: false, payment_vouchers: false,
		payment_voucher_value: null, online_ordering: 'none', split_orders_by_categories: false, checkin_url: '',
	};
	it('passes a complete answer set', () => {
		expect(validateAnswers(valid)).toEqual({ name: null, payment: null, onlineOrdering: null, valid: true });
	});
	it('requires a non-blank name', () => {
		expect(validateAnswers({ ...valid, name: '   ' }).name).toBe('required');
	});
	it('requires at least one payment method', () => {
		expect(validateAnswers({ ...valid, payment_cards: false }).payment).toBe('none_selected');
	});
	it('requires a positive voucher value when vouchers are selected', () => {
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '' }).payment).toBe('voucher_value');
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '0' }).payment).toBe('voucher_value');
		expect(validateAnswers({ ...valid, payment_vouchers: true, payment_voucher_value: '2,50' }).payment).toBeNull();
	});
	it('requires an online ordering answer', () => {
		expect(validateAnswers({ ...valid, online_ordering: null }).onlineOrdering).toBe('required');
		expect(validateAnswers({ ...valid, online_ordering: null }).valid).toBe(false);
	});
	it('treats integration without cards as unanswered', () => {
		expect(validateAnswers({ ...valid, payment_cards: false, payment_cash: true, online_ordering: 'integration' }).onlineOrdering).toBe('required');
	});
});

describe('eventFromAnswers', () => {
	const answers = {
		name: ' Quiz 2026 ', payment_cards: true, payment_cash: false, payment_vouchers: true,
		payment_voucher_value: '2,50', online_ordering: 'unpaid', split_orders_by_categories: true, checkin_url: ' ',
	};
	it('maps a new event and opens remote orders for an online answer', () => {
		expect(eventFromAnswers(answers, {})).toEqual({
			name: 'Quiz 2026', payment_cards: true, payment_cash: false, payment_vouchers: true,
			payment_voucher_value: 2.5, allow_unpaid_online_orders: true, is_selling: true,
			split_orders_by_categories: true, checkin_url: null,
		});
	});
	it('closes remote orders for a new event with no online ordering', () => {
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, {}).is_selling).toBe(false);
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, {}).allow_unpaid_online_orders).toBe(false);
	});
	it('maps integration to closed unpaid orders and open selling', () => {
		const result = eventFromAnswers({ ...answers, online_ordering: 'integration' }, {});
		expect(result.allow_unpaid_online_orders).toBe(false);
		expect(result.is_selling).toBe(true);
	});
	it('nulls the voucher value when vouchers are off', () => {
		expect(eventFromAnswers({ ...answers, payment_vouchers: false }, {}).payment_voucher_value).toBeNull();
	});
	it('keeps the id and other fields of an existing event', () => {
		const original = { id: 7, order_token: 'abc', is_selling: false, allow_unpaid_online_orders: true, payment_cards: true };
		const result = eventFromAnswers(answers, original);
		expect(result.id).toBe(7);
		expect(result.order_token).toBe('abc');
	});
	it('leaves is_selling alone when the online ordering answer did not change', () => {
		const paused = { id: 7, is_selling: false, allow_unpaid_online_orders: true, payment_cards: true };
		expect(eventFromAnswers(answers, paused).is_selling).toBe(false);
		const pausedIntegration = { id: 8, is_selling: false, allow_unpaid_online_orders: false, payment_cards: true };
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, pausedIntegration).is_selling).toBe(false);
	});
	it('writes is_selling when the online ordering answer changed', () => {
		const closed = { id: 7, is_selling: false, allow_unpaid_online_orders: false, payment_cards: true };
		expect(eventFromAnswers(answers, closed).is_selling).toBe(true);
		const open = { id: 9, is_selling: true, allow_unpaid_online_orders: true, payment_cards: true };
		expect(eventFromAnswers({ ...answers, online_ordering: 'none' }, open).is_selling).toBe(false);
	});
	it('exposes the online ordering constants', () => {
		expect(ONLINE_ORDERING).toEqual({ NONE: 'none', UNPAID: 'unpaid', INTEGRATION: 'integration' });
	});
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/manage/js/services/eventSettings.test.js`
Expected: FAIL, "Failed to resolve import ./eventSettings".

- [ ] **Step 3: Implement `resources/manage/js/services/eventSettings.js`**

Plain ES module with the six exports from the Interfaces block. Use `!!` for flag coercion and `String(value).trim().replace(',', '.')` before `parseFloat` in `parseVoucherValue`; `Number.isFinite` decides null.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/manage/js/services/eventSettings.test.js`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add resources/manage/js/services/eventSettings.js resources/manage/js/services/eventSettings.test.js
git commit -m "Add event settings mapping for the event wizard"
```

---

### Task 2: EventForm component and translations

**Files:**
- Create: `resources/manage/js/components/EventForm.vue`
- Modify: `resources/shared/js/i18n/en.js` (Manage - Events section, lines 278-297), `nl.js`, `fr.js`, `de.js`, `es.js` (same section in each)
- Test: `resources/tests/event-form.test.js`

**Interfaces:**
- Consumes: everything exported by `resources/manage/js/services/eventSettings.js` (Task 1).
- Produces: component `EventForm` with
  - props: `event` (Object, default `() => ({})`), `mode` (String, `'wizard'` or `'single'`, default `'single'`), `saving` (Boolean, default `false`)
  - emits: `submit` with the result of `eventFromAnswers(answers, event)`; `cancel` with no payload; `step` with the current step number (1–3) whenever it changes and once on mount in wizard mode.
  - data: `answers` (from `answersFromEvent(event)`), `step` (1).
  - methods: `next()`, `back()`, `reset()` (re-derives `answers` from `event`, sets `step` to 1), `onSubmit()`, `togglePayment(key)`, `selectOnlineOrdering(value)`.
  - computed: `errors` (`validateAnswers(answers)`), `isWizard`, `stepValid` (step 1 → `errors.name === null`, step 2 → `errors.payment === null`, step 3 → `errors.onlineOrdering === null`), `integrationAvailable` (`answers.payment_cards`).

- [ ] **Step 1: Write the failing source tests**

`resources/tests/event-form.test.js`, reading the SFC as text like `events-views.test.js` does:

```js
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const source = readFileSync(resolve(__dirname, '..', 'manage', 'js', 'components', 'EventForm.vue'), 'utf-8');

describe('EventForm.vue', () => {
	it('takes event, mode and saving props', () => {
		expect(source).toMatch(/props:\s*{[\s\S]*event:[\s\S]*mode:[\s\S]*saving:/);
	});
	it('uses the mapping module', () => {
		expect(source).toContain("from '../services/eventSettings'");
		expect(source).toContain('answersFromEvent(');
		expect(source).toContain('eventFromAnswers(');
		expect(source).toContain('validateAnswers(');
	});
	it('asks the three questions with literal translation keys', () => {
		expect(source).toContain("$t('What is the event called?')");
		expect(source).toContain("$t('How do your guests pay?')");
		expect(source).toContain("$t('Can guests order from their own phone?')");
	});
	it('offers the three payment tiles and the three online ordering tiles', () => {
		for (const key of ['NFC topup cards', 'Cash', 'Vouchers', 'No, only at the bar',
			'Yes, and we handle payment ourselves', 'Yes, through an integration that passes the card']) {
			expect(source).toContain(`$t('${key}')`);
		}
	});
	it('keeps the advanced settings reachable', () => {
		expect(source).toContain("$t('Advanced settings')");
		expect(source).toContain('answers.split_orders_by_categories');
		expect(source).toContain('answers.checkin_url');
	});
	it('routes Enter through onSubmit instead of submitting directly', () => {
		expect(source).toContain('@submit.prevent="onSubmit"');
	});
	it('re-derives answers when the event prop changes', () => {
		expect(source).toMatch(/watch:\s*{[\s\S]*event\s*[:(]/);
	});
	it('drops a chosen integration when cards are unticked', () => {
		expect(source).toMatch(/['"]answers\.payment_cards['"]\s*[:(]/);
	});
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/tests/event-form.test.js`
Expected: FAIL, ENOENT for `EventForm.vue`.

- [ ] **Step 3: Add the translation keys to all five locales**

In the `// Manage - Events` section of each locale file, add these keys (English value = key; Dutch as below; French, German, Spanish: builder's translation of the English):

| Key | nl |
|---|---|
| `What is the event called?` | `Hoe heet het evenement?` |
| `For example "Quiz 2026" or "Summer bar Friday".` | `Bijvoorbeeld "Quiz 2026" of "Zomerbar vrijdag".` |
| `Please enter a name.` | `Vul een naam in.` |
| `How do your guests pay?` | `Hoe betalen je gasten?` |
| `Pick everything that applies.` | `Kies alles wat van toepassing is.` |
| `NFC topup cards` | `NFC-opwaardeerkaarten` |
| `Guests load credit on a card and tap at the POS. Recommended.` | `Gasten laden saldo op een kaart en tikken aan de kassa. Aanbevolen.` |
| `Cash` | `Contant` |
| `Cash or Payconiq at the bar, outside the system.` | `Cash of Payconiq aan de bar, buiten het systeem om.` |
| `Vouchers` | `Bonnen` |
| `Drink tokens with a fixed value.` | `Drankbonnetjes met een vaste waarde.` |
| `Value of one voucher` | `Waarde van één bon` |
| `Pick at least one payment method.` | `Kies minstens één betaalwijze.` |
| `Enter the value of one voucher.` | `Vul de waarde van één bon in.` |
| `Can guests order from their own phone?` | `Kunnen gasten zelf bestellen via hun telefoon?` |
| `Via the order page with QR code or link.` | `Via de bestelpagina met QR-code of link.` |
| `No, only at the bar` | `Nee, enkel aan de bar` |
| `Staff take orders on the POS.` | `Medewerkers nemen bestellingen op via de kassa.` |
| `Yes, and we handle payment ourselves` | `Ja, en we regelen de betaling zelf` |
| `Guests order ahead and pay at the bar or on pickup.` | `Gasten bestellen vooraf en betalen aan de bar of bij afhaling.` |
| `Yes, through an integration that passes the card` | `Ja, via een integratie die de kaart meegeeft` |
| `E.g. QuizWitz. Orders are charged to the linked NFC card.` | `Bijv. QuizWitz. Bestellingen worden van de gekoppelde NFC-kaart afgehouden.` |
| `Requires NFC topup cards` | `Vereist NFC-opwaardeerkaarten` |
| `Choose how guests order.` | `Kies hoe gasten bestellen.` |
| `Advanced settings` | `Geavanceerde instellingen` |
| `Split orders by product category (bar / kitchen)` | `Bestellingen splitsen per productcategorie (bar / keuken)` |
| `Next` | `Volgende` |
| `Create event` | `Evenement aanmaken` |
| `New event: step {step} of 3` | `Nieuw evenement: stap {step} van 3` |

Already present, reuse as-is: `Name`, `Checkin URL (callback)`, `Back`, `Cancel`, `Reset`, `Save`, `Saving`, `Edit event ID#{id}`, `New event`.

- [ ] **Step 4: Implement `resources/manage/js/components/EventForm.vue`**

Template:
- `<form @submit.prevent="onSubmit">` wrapping three blocks and a button row. In wizard mode a block renders only when `step` equals its number; in single mode all three render, each as a `b-form-group`-style section with the question as heading.
- Block 1: question `$t('What is the event called?')` (wizard) or `$t('Name')` (single), hint `$t('For example "Quiz 2026" or "Summer bar Friday".')` (wizard only), `b-form-input` bound to `answers.name`, `autofocus` in wizard mode; error `$t('Please enter a name.')` shown when `errors.name` is set and the field has been touched (`touchedName` data flag set on `@input`/`@blur`), so a fresh form shows no error.
- Block 2: question, hint, three tiles (`togglePayment('payment_cards' | 'payment_cash' | 'payment_vouchers')`), icon 💳 / 💶 / 🎟️. Under the vouchers tile, when selected: label `$t('Value of one voucher')` with a `b-form-input type="number" step="0.01" min="0.01"` bound to `answers.payment_voucher_value`. Errors: `errors.payment === 'none_selected'` → `$t('Pick at least one payment method.')`; `'voucher_value'` → `$t('Enter the value of one voucher.')`.
- Block 3: question, hint, three tiles (`selectOnlineOrdering(ONLINE_ORDERING.NONE | UNPAID | INTEGRATION)`), icons 🧑‍🍳 / 📱 / 🔗. The integration tile gets `:disabled="!integrationAvailable"` and, when disabled, the hint `$t('Requires NFC topup cards')`. Whenever `errors.onlineOrdering` is set, show the muted hint `$t('Choose how guests order.')` under the tiles (it reads as an instruction on a fresh form and as the reason Save is disabled after cards were unticked). Payment errors in block 2 likewise show whenever set; a fresh form never has one because cards start on. Then a native `<details>` with `<summary>{{ $t('Advanced settings') }}</summary>` containing a `b-form-checkbox` bound to `answers.split_orders_by_categories` labelled `$t('Split orders by product category (bar / kitchen)')` and a `b-form-input` bound to `answers.checkin_url` labelled `$t('Checkin URL (callback)')`.
- Tile markup: `<button type="button" class="event-tile" :class="{ 'event-tile--selected': selected }" :aria-pressed="selected" :disabled="disabled">` containing an icon span, a `<strong>` title and a muted description span. Single mode may drop descriptions on the payment tiles and lay them out three across.
- Button row (`d-flex justify-content-end`): wizard step 1–2 → `b-button variant="light"` `$t('Cancel')` (emits `cancel`) and `b-button variant="primary" type="submit" :disabled="!stepValid"` `$t('Next')`; wizard step 3 → `$t('Back')` (calls `back()`) and `variant="success" type="submit" :disabled="!errors.valid || saving"` `$t('Create event')`; single → `$t('Reset')` (calls `reset()`) and `variant="success" type="submit" :disabled="!errors.valid || saving"` `$t('Save')`. Show `$t('Saving')` next to the submit button while `saving`.

Script (Options API, like the rest of Manage):
- `onSubmit()`: wizard and `step < 3` → `next()`; otherwise if `errors.valid` emit `submit` with `eventFromAnswers(this.answers, this.event)`.
- `next()`: only when `stepValid`; increments `step` and emits `step`. `back()`: decrements and emits `step`.
- `watch`: `event` (deep: false) → `reset()`; `'answers.payment_cards'` → when it becomes `false` and `answers.online_ordering === ONLINE_ORDERING.INTEGRATION`, set `answers.online_ordering = null`.
- `mounted()`: emit `step` with 1 when in wizard mode.

Style (`<style scoped>`): `.event-tile` full-width, left-aligned, `border: 2px solid #dee2e6; border-radius: .5rem; padding: .6rem .75rem; background: #fff; display: flex; gap: .6rem; align-items: flex-start; margin-bottom: .5rem;` `.event-tile--selected { border-color: #28a745; background: #f0faf3; }` `.event-tile:disabled { opacity: .5; }` plus a `details > summary` style with `cursor: pointer` and muted colour.

- [ ] **Step 5: Run the source tests and the i18n completeness test**

Run: `npx vitest run resources/tests/event-form.test.js resources/tests/i18n-completeness.test.js`
Expected: PASS for both files (completeness passes only once all five locales have every new key).

- [ ] **Step 6: Commit**

```bash
git add resources/manage/js/components/EventForm.vue resources/tests/event-form.test.js resources/shared/js/i18n/en.js resources/shared/js/i18n/nl.js resources/shared/js/i18n/fr.js resources/shared/js/i18n/de.js resources/shared/js/i18n/es.js
git commit -m "Add EventForm component with wizard and single-screen modes"
```

---

### Task 3: Wire EventForm into the Manage Events page

**Files:**
- Modify: `resources/manage/js/views/Events.vue` (modal at lines 144-200, script at 226-351)
- Modify: `resources/shared/js/i18n/en.js`, `nl.js`, `fr.js`, `de.js`, `es.js` (remove keys that become unused)
- Test: `resources/tests/events-views.test.js` (add a describe block)

**Interfaces:**
- Consumes: `EventForm` from Task 2 (props `event`, `mode`, `saving`; events `submit`, `cancel`, `step`).
- Produces: nothing new; `Events.vue` keeps `createNew()`, `edit(model)`, `remove(model)`, `toggleIsSelling(model)`, `refreshEvents()`.

- [ ] **Step 1: Write the failing source tests**

Append to `resources/tests/events-views.test.js`:

```js
describe('Manage Events.vue event form', () => {
	const file = readVueFile('manage');
	const template = readVueTemplate('manage');

	it('renders the EventForm component inside the modal', () => {
		expect(file).toContain("import EventForm from '../components/EventForm.vue'");
		expect(template).toContain('<event-form');
		expect(template).toContain('@submit="save"');
	});
	it('switches between wizard and single mode on the event id', () => {
		expect(template).toMatch(/:mode="model\.id \? 'single' : 'wizard'"/);
	});
	it('no longer renders the raw flag checkboxes', () => {
		expect(template).not.toContain('model.allow_unpaid_online_orders');
		expect(template).not.toContain('model.payment_cards');
		expect(template).not.toContain('Allow unpaid online orders');
	});
	it('titles the wizard with the step number', () => {
		expect(file).toContain("$t('New event: step {step} of 3'");
	});
	it('resets to an empty model so the form falls back to create defaults', () => {
		expect(file).toContain('this.model = {};');
	});
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/tests/events-views.test.js`
Expected: FAIL on the five new tests; the existing ones still pass.

- [ ] **Step 3: Replace the modal body in `Events.vue`**

- Replace lines 144-200 (the edit `b-modal`, its `<form>` and `#modal-footer`) with:
  ```vue
  <b-modal :title="modalTitle" hide-footer @hide="resetForm" ref="editFormModal">
  	<event-form :event="model" :mode="model.id ? 'single' : 'wizard'" :saving="saving"
  				@submit="save" @cancel="$refs.editFormModal.hide()" @step="wizardStep = $event" />
  </b-modal>
  ```
- Import `EventForm from '../components/EventForm.vue'` and register it in `components`.
- Add `wizardStep: 1` to `data()`.
- Add `computed: { modalTitle() }` returning `this.$t('Edit event ID#{id}', { id: this.model.id })` when `model.id`, else `this.$t('New event: step {step} of 3', { step: this.wizardStep })`.
- Change `save()` to `save(payload)`: set `saving`, call `this.service.update(payload.id, payload)` when `payload.id` else `this.service.create(payload)`, clear `saving`, hide the modal, `refreshEvents()`. Drop the voucher-value nulling (Task 1 does it).
- `resetForm()` becomes `this.model = {}; this.wizardStep = 1;` (defaults now live in `answersFromEvent`).
- `createNew()` and `edit()` unchanged except that `edit()` keeps `this.model = Object.assign({}, model)`.

- [ ] **Step 4: Remove the translation keys that are now unused**

For each of these keys, confirm with `grep -rn "<key>" resources/ --include=*.vue --include=*.js --include=*.ts | grep -v i18n/` that nothing uses it, then delete it from all five locale files: `Allow payment with NFC topup cards`, `Allow payment in cash`, `Allow payment with vouchers`, `Allow unpaid online orders (without providing card alias)`, `Split orders by product categories (e.g drinks for the bar, food for the kitchen)`, `Voucher value`. Keep any key the grep still finds.

- [ ] **Step 5: Run the whole JS suite**

Run: `npx vitest run`
Expected: PASS, every file green (205 tests before this feature, plus the new ones).

- [ ] **Step 6: Build the Manage bundle**

Run: `npm run dev 2>&1 | tail -20`
Expected: webpack reports `compiled successfully` with no errors for `res/manage.js`. Then `git status --short` must not list `package-lock.json`; if it does, run `git checkout -- package-lock.json`.

- [ ] **Step 7: Commit**

```bash
git add resources/manage/js/views/Events.vue resources/tests/events-views.test.js resources/shared/js/i18n/
git commit -m "Use the event wizard on the Manage events page"
```

---

### Task 4: Walk through the wizard in the running app

**Files:** none changed unless the walk-through finds a defect; then fix in the owning file, add a test in the owning task's test file, and commit.

**Interfaces:** none.

- [ ] **Step 1: Start the dev stack**

Run: `docker compose up -d webserver` from the worktree root, then wait for `http://localhost:8095` to answer. If Docker is not available, skip this task, say so in the hand-back, and leave the browser checks to the human.

- [ ] **Step 2: Create one event per scenario**

In Manage (`/manage/`, Events page, the "+" button), create three events and check the result in the list and via "Edit":
1. "Kaarten" — step 2 leave cards on; step 3 "Nee, enkel aan de bar". Expected: list shows Remote orders **Closed**; Edit shows cards on, "No, only at the bar".
2. "Telefoon" — step 2 cards + cash; step 3 "Ja, en we regelen de betaling zelf". Expected: list shows **Open**; the client order form (Actions → Client order form) loads the menu without the "gekoppelde kaart" error.
3. "Bonnen" — step 2 untick cards, tick vouchers, value `2,50`; step 3: the integration tile is disabled; pick "Nee". Expected: Edit shows voucher value 2.5.

- [ ] **Step 3: Check the edit rules**

- Open "Telefoon", press the Remote orders toggle to Closed, then Edit → change only the name → Save. Expected: still Closed.
- Edit "Kaarten" → untick cards after picking integration. Expected: integration deselects, Save disabled until an answer is picked.
- Edit any event → change the name → Reset. Expected: the original name comes back and the modal stays in edit mode.

- [ ] **Step 4: Record the outcome**

Report each check as pass/fail with what was seen. A failure is a defect to fix (test first) before the branch is finished.
