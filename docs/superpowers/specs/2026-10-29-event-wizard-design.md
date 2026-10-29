# Event creation wizard — design

**Date:** 2026-10-29
**Scope:** Manage app only (`resources/manage/js/views/Events.vue`). No backend changes.

## Intent

Creating an event today is a modal with a name field, five checkboxes named after
database flags, a voucher value and a check-in URL. Bar volunteers don't know what
"Onbetaalde online bestellingen toestaan (zonder kaart-alias)" means, so they leave it
off and then can't understand why the public order page refuses with "Het is niet
mogelijk te bestellen zonder een gekoppelde kaart". The same confusion exists for the
other flags, just less often.

Replace the checkbox list with three plain questions, asked one per step when creating
an event and all on one screen when editing one. The questions set the existing flags;
nothing about the data model or the APIs changes.

## The three real-world scenarios

These came out of the brainstorm and are the reason for the question wording:

1. **NFC topup cards** (the default). Guests load money on a card and tap at the POS.
   Online ordering here only works through an integration such as QuizWitz, which
   passes the card alias in a signed order URL.
2. **Online ordering, payment outside the system.** Guests order from their phone via
   the public order page, staff settles payment at the bar or on pickup. Requires
   `allow_unpaid_online_orders = true`. This can sit on top of scenario 1.
3. **Vouchers / cash.** Drink tokens with a fixed value, or plain cash. No cards.

Scenarios 1 and 2 are not exclusive, so the wizard asks questions instead of offering
mutually exclusive "scenario cards".

## Goals

- A volunteer with no knowledge of the flags ends up with a working event for any of the
  three scenarios above, without hitting an error on the public order page.
- Editing an event shows the same questions (one screen), so the mental model is the
  same in both places.
- The two rarely-used settings (split by category, check-in URL) stay reachable.

## Non-goals

- No change to the POS app. Its Events page only toggles "Remote orders" and already
  says editing happens in Manage.
- No change to backend flags, resource definitions, policies or migrations.
- No change to the wording of the public order page's error messages (worth a follow-up,
  not part of this).
- No scenario presets beyond the questions themselves.

## Anti-goals

- Hiding `split_orders_by_categories` or `checkin_url` so that integrators can no
  longer set them.
- Silently changing flags on events the user did not touch (see "is_selling" rules).
- Replacing the modal with a separate page. It stays a `b-modal` on the Events page.

## The questions

Labels below are the English source keys; Dutch is the primary translation and the
wording people will actually see. All five locales (`en`, `nl`, `fr`, `de`, `es` in
`resources/shared/js/i18n/`) must get every new key, because
`resources/tests/i18n-completeness.test.js` fails otherwise.

### Step 1 — Name

- Question: "What is the event called?" / "Hoe heet het evenement?"
- Hint with an example name.
- A single text input, `name`. Required to go to the next step (backend requires it
  too). Enter advances.

### Step 2 — How do guests pay?

- Question: "How do your guests pay?" / "Hoe betalen je gasten?"
- Hint: "Pick everything that applies." / "Kies alles wat van toepassing is."
- Three multi-select tiles, each with an icon, a bold title and one line of
  explanation:
  - 💳 **NFC topup cards** / **NFC-opwaardeerkaarten** — "Guests load credit on a card
    and tap at the POS. Recommended." Sets `payment_cards`. On by default for new
    events.
  - 💶 **Cash** / **Contant** — "Cash or Payconiq at the bar, outside the system."
    Sets `payment_cash`.
  - 🎟️ **Vouchers** / **Bonnen** — "Drink tokens with a fixed value." Sets
    `payment_vouchers`. When selected, an inline number input appears: "Value of one
    voucher: €" / "Waarde van één bon: €", bound to `payment_voucher_value`, required
    and > 0 while vouchers are selected. When vouchers are deselected the value is sent
    as `null` (same as today's `save()`).
- At least one tile must be selected to continue (my call; see "Calls made by the
  author").

### Step 3 — Can guests order from their own phone?

- Question: "Can guests order from their own phone?" / "Kunnen gasten zelf bestellen
  via hun telefoon?"
- Hint: "Via the order page with QR code or link."
- Three mutually exclusive tiles (radio behaviour):
  - 🧑‍🍳 **No, only at the bar** / **Nee, enkel aan de bar** — "Staff take orders on
    the POS."
  - 📱 **Yes, and we handle payment ourselves** / **Ja, en we regelen de betaling
    zelf** — "Guests order ahead and pay at the bar or on pickup."
  - 🔗 **Yes, through an integration that passes the card** / **Ja, via een integratie
    die de kaart meegeeft** — "E.g. QuizWitz. Orders are charged to the linked NFC
    card." This tile is disabled with a short hint ("Requires NFC topup cards") when
    the cards tile in step 2 is off.
- **No tile is preselected when creating an event.** The volunteer has to pick one;
  "Create event" stays disabled until they do. (User's decision: forcing the choice is
  the point of the wizard.) In edit mode the tile is derived from the stored flags, see
  below.
- Below the tiles, a collapsed **Advanced settings** / **Geavanceerde instellingen**
  section (`<details>` or a bootstrap collapse) holding:
  - checkbox "Split orders by product category (bar / kitchen)" →
    `split_orders_by_categories`
  - text input "Check-in URL (callback)" → `checkin_url`
- Footer: "‹ Back" and "Create event" / "Evenement aanmaken".

### Mapping answers → flags

| Answer | Writes |
|---|---|
| Cards / Cash / Vouchers tiles | `payment_cards`, `payment_cash`, `payment_vouchers`, `payment_voucher_value` |
| No, only at the bar | `allow_unpaid_online_orders = false`, `is_selling = false` |
| Yes, we handle payment ourselves | `allow_unpaid_online_orders = true`, `is_selling = true` |
| Yes, through an integration | `allow_unpaid_online_orders = false`, `is_selling = true` |
| Advanced | `split_orders_by_categories`, `checkin_url` |

`is_selling` is the "Remote orders: Open/Closed" toggle on the Events list. Setting it
to true on creation for the online options is deliberate: without it the volunteer hits
the next error ("De bar aanvaardt op dit moment geen bestellingen") straight after
fixing the first.

### Mapping flags → answers (edit mode)

The two "No" and "integration" answers both store `allow_unpaid_online_orders = false`,
so there is no column that records which was chosen. The edit screen derives the
answer:

```
if allow_unpaid_online_orders       → "Yes, we handle payment ourselves"
else if is_selling && payment_cards → "Yes, through an integration"
else                                → "No, only at the bar"
```

Consequences, all accepted:

- An integration event whose remote orders are currently paused shows as "No, only at
  the bar". That matches what guests can actually do at that moment, and saving the
  form unchanged does not alter anything (see the `is_selling` write rules).
- A cash-only event that someone opened via the "Remote orders" toggle (so
  `is_selling = true`, cards off) also shows as "No, only at the bar", never as the
  disabled integration tile. Saving unchanged leaves `is_selling` alone.

### Conflicts created while editing

- **Unticking cards while "integration" is selected** (single mode, or going back to
  step 2 in the wizard): the integration tile becomes disabled *and deselected*. The
  online-ordering block then has no answer, shows the hint "Choose how guests order" /
  "Kies hoe gasten bestellen", and Save / Create stays disabled until the user picks
  one. Nothing is auto-switched, so `is_selling` is only written if they then pick
  an answer that differs from the derived one.
- **Stored events that fail step 2** (no payment method at all, or vouchers on with an
  empty value): the single-screen form shows the same inline hints as the wizard and
  keeps Save disabled until fixed. Such an event cannot sell anything anyway (the POS
  already shows "No payment methods have been enabled for this event"), and the fix is
  one click. This is a consequence of author's call 4 below; veto that and this goes
  with it.

### `is_selling` write rules

- **Create:** always written from the step 3 answer.
- **Edit:** written only when the step 3 answer differs from the derived current
  answer. Editing the name of a paused event must not reopen remote orders.

## Edit mode: same questions, one screen

Editing opens the same modal with all three blocks stacked (name, payment tiles,
online-ordering tiles, collapsed advanced section) and the existing "Reset" / "Save"
footer. "Reset" reverts to the event as loaded (today it clears the model and turns the
modal into a create form, which is a bug). No step counter. The payment tiles may use a compact three-column layout
(icon + title only) to save vertical space; the online-ordering tiles keep their
one-line explanations.

## Where it lives

- New component `resources/manage/js/components/EventForm.vue` (name is the builder's
  choice) that renders the three question blocks and takes a `mode` prop:
  `'wizard'` (steps, used for create) or `'single'` (stacked, used for edit). It emits
  the resulting event model on submit. `Events.vue` keeps owning the modal, the
  `EventService` calls and the list refresh.
- Pure mapping functions (answers → flags, flags → answers, validation per step) in a
  plain JS module next to it so they can be unit-tested without mounting Vue.
- The modal title: "New event — step {n} of 3" / "Nieuw evenement — stap {n} van 3"
  in wizard mode; the existing "Edit event ID#{id}" in single mode.

## Visual direction

The mockup accepted during the brainstorm is in
`.superpowers/brainstorm/*/content/create-wizard.html` (not committed). Key points:

- Tiles are bordered boxes with a checkbox (multi-select) or radio-style dot
  (exclusive), an emoji icon, a bold title and a muted one-line explanation. Selected
  tiles get a green border/background in the app's existing success colour.
- Tiles are full-width rows in the wizard; the whole tile is clickable.
- The advanced section looks clearly secondary (dashed separator, smaller text).
- Everything is Bootstrap-Vue (`b-modal`, `b-form-*`, `b-button`) like the rest of
  Manage. Keep it usable at phone width since volunteers set events up on their phone.

## Validation and errors

- Step 1: name non-empty.
- Step 2: at least one payment method; voucher value > 0 when vouchers selected.
- Step 3: an online-ordering answer must be selected (none is preselected on create,
  and unticking cards deselects a chosen integration tile). The integration tile is
  disabled rather than invalid.
- Inline hints under the block, not browser alerts. "Next" is disabled until the step
  is valid; in single mode "Save" is disabled until everything is valid.
- API errors keep the existing handling in `Events.vue` (a separate branch is fixing
  the 422 crash; don't depend on it).

## Tests

- Vitest (`npx vitest run`), in `resources/tests/`:
  - unit tests for the mapping module: each answer combination → expected flags;
    each flag combination → expected answers; the `is_selling` edit rule; voucher
    value nulling.
  - a template-level test in the style of `events-views.test.js` asserting the Manage
    Events view uses the new form component and no longer contains the raw
    "Allow unpaid online orders" checkbox.
  - `i18n-completeness.test.js` already enforces translations; keep it green. It only
    finds literal `$t('...')` calls, so tile titles and explanations must be literal
    `$t()` calls in the template, not keys looked up from a data structure.
- No PHP changes, so no PHPUnit additions.

## Calls made by the author (open to veto)

1. Remote orders are opened on creation when an online-ordering answer is chosen.
2. Edit mode derives the online-ordering answer from `allow_unpaid_online_orders` and
   `is_selling` instead of adding a column.
3. In edit mode `is_selling` is only written when that answer changed.
4. At least one payment method is required to continue past step 2.
5. The integration option is disabled (with a hint) rather than auto-enabling cards.
6. The old flag-named translation keys may be removed once unused.

## Left to the builder

- Exact component split and file names.
- Whether tiles are custom markup or `b-form-checkbox-group` / `b-form-radio-group`
  with `button` styling, as long as the visual direction above is met.
- French, German and Spanish wording for the new strings.
- Transition/animation between steps (none is fine).
