# Order token modal (Manage Events)

Date: 2026-08-05

## Goal

Stop showing the remote order token as a column in the Manage Events table.
Instead, expose it via the per-event Actions dropdown, in a modal that also
explains how the token is used.

## Scope

- **Manage Events view** (`resources/manage/js/views/Events.vue`) only.
- The POS Events table never rendered the column (no `order_token` entry in
  `fields`); its leftover `cell(order_token)` template slot and
  `selectOrderToken` method are removed as dead-code cleanup.
- No backend changes: `full_order_token` and `order_url` are already exposed
  on the event resource.

## Changes

1. Remove the `order_token` field from the Manage Events table `fields` array
   and delete the `cell(order_token)` template slot (including the
   commented-out legacy block).
2. Add a dropdown item to the Actions → Sales group, next to
   "Client order form": `🔑 Remote order token`. Clicking it opens the modal
   for that event.
3. New modal (`ref="orderTokenModal"`, OK-only), title
   "Remote order token" with the event name, containing:
   - **Order page URL** (`order_url`) in a readonly click-to-select input,
     labeled as the link to share with customers for remote ordering.
   - **Full order token** (`full_order_token`) in a readonly click-to-select
     input.
   - **Usage notes** (see `.ai/signed-order-urls.md`): the token is
     `{public}-{secret}`; the public part identifies the event in the order
     URL, the secret part is used by integrating applications — explicitly
     naming **QuizWitz** as such a client — to sign order parameters
     (`card`, `name`) with HMAC-SHA256. The full token must only be shared
     with trusted integrations.
4. All user-facing strings go through `$t()`.

## Testing

Extend `resources/tests/events-views.test.js` (existing string-on-template
pattern):

- Manage template: no `order_token` column field, no `cell(order_token)`
  slot; has the "Remote order token" action item, the modal, the
  `full_order_token` input and the QuizWitz mention.
- POS template: no `order_token` remnants at all.
