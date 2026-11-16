# Frontend walk-through

This list names each screen of the three frontend apps and each Blade page.
For each screen it names each modal, dropdown, form, and table, with the action to do and the result to expect.
Each pull request that changes the frontend runs this list again and compares the result with the baseline.
The baseline of November 2026 is in `docs/testing/baseline-2026-11-main.md`.

## How to use this list

- If the mode is `auto`, the script `tools/walkthrough/walkthrough.mjs` does the item. The README in `tools/walkthrough/` tells how to start it.
- If the mode is `manual`, a person does the item in a browser, or in the Android app for the last section.
- The script does the `auto` items in the sequence of its `SEQUENCE` list. Later items use the event, the device, and the card of earlier items.
- The browser console must show no error during the walk-through. The script records each console error and each page error for the item that runs.
- An item fails in three cases: its action fails, the console shows an error, or the browser tried to load a page from a public host.
- The script blocks each request to a host that is not `localhost` or `127.0.0.1`. Web fonts from Google are blocked without a failure.

### Words in this list

| Word | Meaning |
|------|---------|
| Station | The UI name of a menu category (`category` in the code) |
| Heading | A title that groups products on the order screens |
| The event | The event that `manage-events-create` makes, named `Walk Event <run>` |
| The device | The POS that `manage-devices-pair` pairs, named `Walk POS <run>` |
| The card | The simulated NFC card with UID `04a1b2c3d4e5f6` |
| Known error | The console error of issue 251: `assign is not a function` or `el[assignKey] is not a function`. A production build shows a short name, for example `i is not a function`. |

## Blade pages

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `blade-setup` | `/` and `/setup` on a new database | Open `/`. Fill name, email, password, password again, and organisation name. Click the submit button. | `/` sends you to `/setup`. After the submit, `/getting-started` opens and you are logged in. | auto |
| `blade-getting-started` | `/getting-started` | Open the page. | The page shows its title and the steps. | auto |
| `blade-register-closed` | `/register` | Open the page while you are logged out. | The page tells that registration is closed. The status is 403. | auto |
| `blade-login-wrong-password` | `/login` | Enter the email and a wrong password. Click the submit button. | The login form shows an error message below the email field. | auto |
| `blade-welcome` | `/` | Open the page while you are logged out. | The welcome page shows. It has a link to log in or to Manage. | auto |
| `blade-topup-not-available` | `/topup/{card uid}` | Open the page for the card. No payment gateway is active. | The page tells that online top-up is not available. | auto |
| `blade-qr-generator` | `/qr-generator` and `/qr-generator/code?uid=...` | Open the page. Request the QR code image for the card. | The page loads. The image request answers 200 with a PNG image. | auto |
| `blade-qr-generator-cards` | `/qr-generator` | Store an NFC server in the POS on the same browser. Open the page. Tap two cards. | The page shows one QR code with its UID for each card. | manual |
| `blade-landing-pages` | `/student-union-cashless`, `/indie-festival-payments`, `/hacker-camp-nfc`, `/whitepaper` | Open each page. | Each page answers 200 and shows its content. | auto |
| `blade-docs` | `/docs` | Open the page. | The Swagger UI shows the API documentation. | auto |
| `blade-home` | `/home` | Open the page while you are logged in. | The page shows. You are not sent to `/login`. | auto |
| `blade-daily-report` | `/report/daily/{organisation}/{date}` | Open the report of the organisation for today while you are logged in. Write the date in the form `YYYYMMDD`. | The report shows the sales of the day. | manual |
| `blade-topup-form` | `/topup/{card uid}` with an active Pay.nl gateway | Open the page. Enter an amount. Submit. | The page sends you to Pay.nl. This contacts a public host, so do it only against staging. | manual |
| `blade-database-error` | Any page while MySQL is down | Stop the database. Open `/`. | The page `errors/database.blade.php` shows. | manual |
| `manage-logout` | Manage navbar, `Logout` | Click `Logout`. Open `/manage/events`. | You are logged out. Manage sends you to `/login`. | auto |
| `blade-login` | `/login` | Enter the email and the password. Click the submit button. Open `/manage/events`. | Manage opens on the events list. | auto |

## Manage

### Navbar (all Manage screens)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-navbar-settings-dropdown` | Navbar dropdown `Settings` | Open the dropdown. Click `Public Keys`. Open it again. Click `Organisation Settings`. | Each click opens the screen of that name. | auto |
| `manage-language-toggle` | Navbar language dropdown | Choose `Nederlands`. Then choose `English`. | The texts change to Dutch (`Evenementen`) and back to English (`Events`). | auto |
| `manage-navbar-mobile` | Navbar at a phone width (390 px) | Click the navbar toggle button. Click `Points of sale`. | The menu opens. The link opens the devices screen. | auto |
| `manage-org-switch` | Navbar organisation dropdown | Give the user a second organisation (see the README). Reload Manage. Choose the other organisation. | The dropdown shows both organisations. The page reloads and shows the data of the chosen organisation. | manual |
| `manage-test-mode-banner` | Below the navbar | Set `PRODUCTION_ORGANISATION_IDS` to the ID of a different organisation. Open Manage. | A yellow banner tells that the instance is in testing mode. | manual |

### Events (`/manage/` and `/manage/events`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-events-list` | Events table and the card `POS Device Pairing` | Open `/manage/`. | The heading `Events`, the events table, and the pairing card show. | auto |
| `manage-events-create` | Button `+`, modal with the event wizard | Click `+`. Step 1: enter a name, click `Next`. Step 2: choose `NFC topup cards` and `Cash`, click `Next`. Step 3: choose `Yes, and we handle payment ourselves`. Click `Create event`. | The modal closes. The table shows the new event. | auto |
| `manage-events-wizard-back-cancel` | Event wizard | Fill step 1 and step 2. On step 3 click `Back`, then `Cancel`. | `Back` shows step 2 with the earlier choice. `Cancel` closes the modal. No event is made. | auto |
| `manage-events-toggle-open` | Events table, column with `Open` or `Closed` | Click the button two times. Leave the event `Open`. | The button changes between `Closed` (red) and `Open` (green) after each click. | auto |
| `manage-events-edit` | Row dropdown `Actions`, item `Edit`, modal with the event form | Open `Edit`. Click the checkbox `Split orders per station` two times. Choose `Vouchers` and enter `2,50`. Change the name. Click `Save`. | The modal closes. The table shows the new name. The baseline shows the known error for each checkbox click. | auto |
| `manage-events-order-token` | Row dropdown `Actions`, item `Remote order token`, modal | Open the modal. Read both fields. Click `OK`. | The modal shows the order page URL with `/order/` and the token with a dash. `OK` closes it. | auto |
| `manage-events-actions-links` | Row dropdown `Actions` | Click `Edit menu`, `Sales overview`, `Order history`, and `Register attendees`, one at a time. Read the link of `Client order form`. | Each item opens its screen of the event. The link of `Client order form` contains `/order/`. | auto |
| `manage-events-client-order-form-tab` | Row dropdown `Actions`, item `Client order form` | Click the item. | A new tab opens on the order page of the event. | manual |
| `manage-events-delete` | Row dropdown `Actions`, item `Delete` | Click `Delete` for the second event. Accept the browser dialog. | The row goes away. | auto |

### Menu (`/manage/events/{id}/menu`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-menu-add-products` | Menu table, last row, columns `Station` and `Heading` | Type three products with a price in the last row. Choose `+ Create new station` and `+ Create new heading` and answer the prompts (`Bar`, `Kitchen`, `Drinks`, `Food`). Click `Save changes`. Reload. | Each typed row adds a new empty last row. The new stations and headings are selected. After the reload the products are there. | auto |
| `manage-menu-selling-delete-undo` | Menu table, columns `Status` and `Delete` | Click `Selling`, then `Not selling`. Click the delete button, then the undo button. | The status button changes colour and text. The deleted row is marked, and undo removes the mark. | auto |
| `manage-menu-reset` | Toolbar button `Reset` | Change a description. Click `Reset`. Accept the dialog. | The browser asks `Discard all unsaved changes?`. The old description comes back. | auto |
| `manage-menu-leave-unsaved` | Navbar link while the menu has changes | Change a description. Click `Events` in the navbar. Cancel the dialog. Click `Events` again and accept. | The first time you stay on the menu. The second time the events list opens. | auto |
| `manage-menu-reorder-drag` | Menu table, drag handle | Drag the second product onto the first row. Click `Save changes`. | The second product moves to the top. | auto |
| `manage-menu-keyboard` | Menu table cells | Use the arrow keys, Enter, and Tab in the cells. | The focus moves between the cells as in a spreadsheet. | manual |
| `manage-menu-headings-rename` | Section `Headings` | Change the name of the first heading. Click `Save changes`. Reload. Set the old name back and save. | The new name is kept after the reload. | auto |
| `manage-menu-headings-reorder` | Section `Headings`, drag handle | Drag the second heading onto the first. Click `Save changes`. | The heading order changes. The POS and the order page show the new order. | manual |
| `manage-menu-import-paste` | Button `Import menu`, modal `Import menu` | Paste two tab-separated rows (name and price) into the first cell. Choose `Drinks` in `Heading for all products`. Click `Add 2 products`. Click `Save changes`. | The modal shows the rows with guessed column types. After `Add` the modal closes and the menu has both products. | auto |
| `manage-menu-import-cancel` | Modal `Import menu`, button `Cancel` | Type in a cell. Click `Cancel`. Accept the dialog. | The browser asks `Discard the pasted menu?`. The modal closes. | auto |
| `manage-menu-import-copy-event` | Modal `Import menu`, select `Copy from event` | Make a second event. Open its menu and the import modal. Choose the first event. Click `Add N products`. Save. | The sheet fills with the products of the first event. The second menu has them after the save. | auto |
| `manage-menu-import-columns` | Modal `Import menu`, column type selects and `Delete marked rows` | Paste a row without a price. Change a column type. Click `Delete marked rows`. | The row without a price is marked. The marked row goes away. | manual |

### Attendees (`/manage/events/{id}/attendees`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-attendees-edit` | Spreadsheet table, buttons `Add rows` and `Save changes` | Type an alias, a name, and an email in the first row. Click `Add rows`. Click `Save changes`. Reload. | `Add rows` adds five rows. `Saved` shows. The attendee is there after the reload. | auto |
| `manage-attendees-paste-dialog` | Spreadsheet, modal `Import pasted data` | Paste two tab-separated rows into a cell. Choose `Tab` in both selects. Click `Import`. Save. | The modal shows a preview of two rows. After `Import` the rows are in the sheet. | auto |
| `manage-attendees-delete-all` | Button `Delete all` | Click `Delete all`. Accept the dialog. Save. | All rows are marked as deleted. After the save the sheet is empty. | manual |

### Sales screens (shared with the POS)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-summary` | `/manage/events/{id}/summary`, summary table | Open the page after the sales of the POS section. | The table lists the sold products with totals. | auto |
| `manage-summary-names` | Tabs `Sold items` and `Orderer names` | Click `Orderer names`, then `Sold items`. | Each tab opens its summary. | auto |
| `manage-summary-detail-modal` | Tab `Orderer names`, name button, modal `Order details` | Click the first name button. Click `OK`. | The modal lists the orders of that name. Only this tab has the buttons. | auto |
| `manage-sales-history` | `/manage/events/{id}/sales` | Open the page. | The page lists the orders with their status. | auto |
| `manage-transactions` | `/manage/transactions`, table, modals `Order details` and `Card details` | Click an `Order #` button and close the modal. Click a card UID. | Each modal shows its details. The card modal shows the balance and the transactions of the card. | auto |
| `manage-card-details-topup` | Modal `Card details`, top-up form | Enter amount `3` and a reason. Click `Topup`, then `Confirm`. | The modal shows `Topup successful.`. | auto |
| `manage-financial-overview` | `/manage/financial-overview` | Open the page. | The table shows `Total credit` and the top-ups of the last 24 hours. | auto |
| `manage-cards` | `/manage/cards` | Open the page. | The page shows its heading and a message about the card reader. | auto |
| `manage-test-transactions` | `/manage/tests/transactions` | Open the page. | The page asks to scan a card. | auto |
| `manage-hello` | `/manage/hello` | Open the page. | The page shows `Hello world`. | auto |

### Organisation configuration (`/manage/settings`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-settings-currency` | Form `Currency` with a select | Choose `USD`. Click `Save`. Reload. Set the old currency back and save. | `Saved` shows. The select keeps `USD` after the reload. The baseline shows the known error for each change of the select. | auto |
| `manage-settings-gateway-add` | Button `Add Payment Gateway`, modal with the gateway form | Choose `Pay.nl` in the select. Fill token, secret, and service ID. Click `Save`. | The modal closes. The gateways table shows Pay.nl. The baseline shows the known error for the select. | auto |
| `manage-settings-gateway-edit-delete` | Gateways table, row dropdown `Actions` | Click `Edit`, change the service ID, and save. Click `Delete` and accept the dialog. | The edit modal opens with the title `Edit Payment Gateway`. After `Delete` the table is empty. | auto |

### Points of sale (`/manage/devices`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-devices-list` | Devices table | Open the page. | The heading and the button `+` (pair a device) show. | auto |
| `manage-devices-pair-cancel` | Button `+`, modal `Connect & authenticate a device` | Open the modal. Click `Cancel`. | The modal shows a QR code and the connection URL. `Cancel` closes it. | auto |
| `manage-devices-pair` | Same modal, step with the pairing code | Open the modal. Open the connect URL on the local host in a second browser (never on `drinks.catlab.eu`). Click `Continue in Browser`. Enter the pairing code of the POS and a device name. Click `Pair Device`. | The local `/connect` page shows `Continue in Browser` (`blade-connect.png`). The POS shows a pairing code (`pos-pairing-code.png`), then opens. The modal closes. The table shows the device. | auto |
| `manage-devices-open-pos-button` | Modal `Connect & authenticate a device`, button `Open POS on this device` | Read the link of the button. Do not click it. | The link is the connect URL. Today it points to `https://drinks.catlab.eu/connect`. | manual |
| `manage-devices-approve-key` | Row dropdown `Actions`, item `Approve Key` | Click it after `nfc-key-generate`. Accept the dialog. | The column with the key status shows `Approved`. | auto |
| `manage-devices-signed-cards` | Column `Signed cards`, modals for the signed cards and `Card details` | Click the number of signed cards. Click the card UID. Close both modals. | The first modal lists the card. The second modal shows the card details. | auto |
| `manage-devices-license` | Row dropdown `Actions`, item `Enter License`, modal | Enter `not-a-valid-license`. Click `Apply License`. Click `Cancel`. | The modal shows an error in red. `Cancel` closes it. | auto |
| `manage-devices-buy-license-link` | Row dropdown `Actions`, item `Buy License` | Read the link. Do not click it. | The item has a link. | auto |
| `manage-devices-edit` | Row dropdown `Actions`, item `Edit`, modal `Edit device` | Click each of the two checkboxes two times. Click `Save`. | The modal closes. The device keeps `Sales` and `Topup`. The baseline shows the known error for each click. | auto |
| `manage-devices-revoke-key` | Row dropdown `Actions`, item `Revoke Key` | Click it. Accept the dialog. | The key status is no longer `Approved`. | auto |
| `manage-devices-delete` | Row dropdown `Actions`, item `Delete` | Click it. Accept the dialog. | The row shows `Deleted` or goes away. | auto |

### Public keys (`/manage/public-keys`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `manage-public-keys-empty` | Page without devices | Open the page. | The heading `Public Keys` shows. | auto |
| `manage-public-keys-list` | Keys table, row dropdown `Actions` | Open the dropdown of the device. | The row shows `Approved`. The dropdown has `Revoke Key`. | auto |
| `manage-public-keys-approve` | Row dropdown `Actions`, item `Approve` | Approve the new key after `nfc-key-revoked-regenerate`. Accept the dialog. | The row shows `Approved`. After a reload the NFC button of the POS is green. | auto |

## POS

### Pairing and navbar

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `pos-pair-qr-scanner` | `/pos/`, button `Scan QR Code` | On an unpaired device click `Scan QR Code`. Scan the QR code in Manage. | The camera view opens. After the scan the pairing code shows. | manual |
| `pos-language-toggle` | Navbar language dropdown | Choose `Nederlands`, then `English`. | The navbar link changes to `Evenementen` and back to `Events`. | auto |
| `pos-offline-badge` | Navbar | Turn off the network of the POS. Wait a few seconds. Turn it on again. | The badge `Offline` shows and goes away again. | manual |
| `pos-license-warning` | Banner above the navbar, modal `License Required` | Scan more cards than the free limit on a device without a license. | The yellow banner and the modal tell that a license is required. | manual |

### Events, menu and sales

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `pos-events` | `/pos/events`, events table, row dropdown `Actions` | Open the dropdown. Click `Open` two times. Click the event name. | The dropdown has `Sales overview`, `Order history`, and `Check-In attendees`. The button changes between `Closed` and `Open`. The name opens the sales screen. | auto |
| `pos-menu` | `/pos/events/{id}/menu`, products table | Click the selling button of a product two times. | The button changes between red and green. | auto |
| `pos-hq` | `/pos/events/{id}/hq` | Open the page. | The live order tiles show, grouped by heading. The remote orders column shows. | auto |
| `pos-sale-cash` | Live order tiles, modals `Confirm order`, `Payment`, `Payment successful` | Click `Walk Cola` two times and its minus button one time. Click `Walk Beer` two times. Click `Confirm` and `OK`. Click the cash button. | The amounts follow the clicks. The payment modal shows the cash button. After the payment, `Payment successful` shows and closes itself. | auto |
| `pos-sale-vouchers` | Modal `Payment`, voucher button | Order one `Walk Cola`. Click `Confirm` and `OK`. Click the voucher button. | The voucher button shows the number of vouchers. `Payment successful` shows. | auto |
| `pos-sale-cancel-payment` | Modal `Payment`, button `Cancel` | Make an order. Click `Cancel` in the payment modal. | `Payment failed` shows. `OK` closes it. | auto |

### Remote orders

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `pos-remote-orders-accept` | Remote orders column, button `Completed`, modals `Payment` and `Order accepted` | After `client-order-place`, click `Completed`. Pay with the cash button. Click `OK`. | The order shows in the column. `Order accepted` shows the table number. | auto |
| `pos-remote-orders-decline` | Button `Not accepted`, modals `Confirm declined order` and `Order declined` | After `client-order-place-second`, click `Not accepted`. Click `OK` two times. | `Order declined` shows the table number. The order goes away. | auto |
| `pos-remote-orders-filters` | Station select and the checkbox `Only show assigned orders` | Choose `Kitchen`, then the first option. Click the checkbox two times. | The list follows the filter. The baseline shows the known error for each checkbox click. | auto |
| `pos-remote-orders-round-robin` | Remote orders with two POS devices online | Pair a second device. Open the sales screen on both. Place client orders. | The badge `Round robin` shows. Each POS gets a part of the orders. | manual |

### POS configuration (`/pos/settings`)

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `pos-settings-live-remote` | Form, checkbox `Allow live orders at this terminal`, button `Save` | Clear the checkbox. Click `Save`. Open the sales screen. Set the checkbox again and save. | The page reloads. The sales screen shows only remote orders. After the second save it shows both. | auto |
| `pos-settings-sync` | Section `Sync status`, button `Sync now`, button `Reset` | Click `Sync now`. Click `Reset`. | `Synchronization complete.` shows. | auto |
| `pos-settings-logout-cancel` | Section `Device`, button `Logout` | Click `Logout`. Cancel the dialog. | The browser shows a dialog. You stay on the page and stay paired. | auto |
| `pos-disabled-device` | `/pos/disabled` | In Manage clear both checkboxes of the device and save. Open the POS. Set both again. | The POS opens `/pos/disabled` with a warning. After the second save the events list shows again. | auto |
| `pos-unpaired` | `/pos/` after `manage-devices-delete` | Open the POS. | The POS returns to the screen `Connect Device`. | auto |
| `pos-authenticate-manual-entry` | Screen `Connect Device`, button `Enter Token Manually` | Click the button. Enter `not-a-valid-token`. Click `Authenticate`. Click `Back`. | A red error tells that the token is not valid. `Back` shows the two buttons again. | auto |

### Other POS screens

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `pos-summary` | `/pos/events/{id}/summary` and the tab `Orderer names` | Open the page. Click the tab. | Both summaries show a table. | auto |
| `pos-sales-history` | `/pos/events/{id}/sales` | Open the page. | The page lists the orders. | auto |
| `pos-transactions` | `/pos/transactions` | Open the page. | The transactions table shows. | auto |
| `pos-financial-overview` | `/pos/financial-overview` | Open the page. | The table with `Total credit` shows. | auto |
| `pos-test-transactions` | `/pos/tests/transactions` | Open the page. | The page asks to scan a card. | auto |

## POS with the NFC simulator

These items use the NFC simulator in `tools/nfc-simulator/`. The script taps and removes the card through its HTTP interface.

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `nfc-settings-connect` | `/pos/settings`, section `Remote NFC reader` | Enter the simulator URL and any password. Click `Save`. | The page reloads. The navbar shows an NFC button. | auto |
| `nfc-key-generate` | NFC button in the navbar, modal `NFC Card Signing Credentials` | Click the NFC button. Click `Generate Credentials`. Click `Check Approval Status`. Click `Close`. | The modal shows `Waiting for approval`. The NFC button shows the hourglass. | auto |
| `nfc-reader-ready` | Same modal after `manage-devices-approve-key` | Open the modal. Click `Check Approval Status`. Close it. Reload. | The modal shows `Credentials approved!`. The NFC button is green after the reload. | auto |
| `nfc-card-first-tap` | `/pos/cards` | Tap the card. If the navbar shows `Corrupt card`, remove the card and tap it again. | The navbar shows `Balance: 0.00`. The card view shows the card ID. A new organisation shows `Corrupt card` on the first tap (known, see the baseline). | auto |
| `nfc-card-topup-default` | Card view, first amount tile, modals `Confirm topup` and `Topup successful.` | Click the first amount. Click `OK` two times. | The navbar shows `Balance: 5.00`. | auto |
| `nfc-card-topup-custom` | Card view, field `Custom amount` | Enter `7.50`. Click `Topup`. Click `OK` two times. | The navbar shows `Balance: 12.50`. | auto |
| `nfc-card-remove-retap` | Navbar | Remove the card. Tap it again. | The balance goes away and comes back as `12.50`. The transactions table lists the top-ups. | auto |
| `nfc-card-aliases` | Card view, section `Aliases` | Add the alias `walk-tmp-alias`. Remove it. | The alias shows in the list and goes away again. | auto |
| `nfc-card-discount` | Card view, section `Discount` | Enter `10`. Click `Save`. Set `0` and save. | `Saved` shows. | auto |
| `nfc-card-payment` | Sales screen, modal `Payment` | Remove the card. Order one `Walk Beer`. In the payment modal tap the card. | `Payment successful` shows. The navbar shows `Balance: 10.00`. | auto |
| `nfc-card-insufficient-funds` | Modal `Payment` | Order four `Walk Fries`. Tap the card. Click `Cancel`. | The modal shows a card error about the balance. `Payment failed` shows. The balance does not change. | auto |
| `nfc-checkin` | `/pos/events/{id}/check-in`, modal `Confirm check-in` | Tap the card. Click `Walk Attendee One`. Click `OK`. | The list of attendees shows. After `OK` the card view shows the alias `walk-alias-1`. | auto |
| `nfc-cards-transactions-link` | `/pos/cards`, button in the heading | Click the transactions button. | `/pos/transactions` opens with the table. | auto |
| `nfc-card-reset` | Card view, button `Reset` | Click `Reset`. Accept the dialog. Click `OK`. | `Card reset successful.` shows. The navbar shows `Balance: 0.00`. | auto |
| `nfc-card-rebuild` | Card view, button `Rebuild` | Click `Rebuild`. Accept the dialog. | The browser shows the alert `Card is rebuilt from online data.`. | auto |
| `nfc-key-revoked-regenerate` | NFC modal after `manage-devices-revoke-key` | Reload the POS. Click the NFC button. Click `Generate Credentials`. Close. | The NFC button shows the key symbol. The modal shows `Credentials revoked`, then `Waiting for approval`. | auto |
| `nfc-test-transactions` | `/pos/tests/transactions`, button `Start test` | Tap a test card. Click `Start test`. Accept the dialog. Click `Stop test`. | The log lists random transactions. Use only a test card. | manual |

## Client

| ID | Where | Action | Expected | Mode |
|----|-------|--------|----------|------|
| `client-order-load` | `/order/{token}`, order table | Open the order URL of the event. | The table lists the products below the headings `Drinks` and `Food`. | auto |
| `client-order-empty` | Button `Place order`, modal `Oops` | Click `Place order` without products. Click `OK`. | The modal tells you what is missing. `OK` closes it. | auto |
| `client-order-place` | Order table, field `Table number`, modals `Confirm order` and `We're on our way!` | Click `+` and `-` on `Walk Cola`. Click `+` two times on `Walk Beer`. Enter table `12`. Click `Place order`. Click `OK`. Click `New order`. | The amounts follow the clicks. The modal `Confirm order` lists the products. The success modal shows. `New order` closes it. | auto |
| `client-order-place-second` | Same as above | Order one `Walk Fries` for table `13`. | Same as above. | auto |
| `client-language-toggle` | Navbar language dropdown | Choose `Nederlands`, then `English`. | The heading changes to `Bestellen` and back to `Order`. | auto |
| `client-order-signed-url` | `/order/{token}?card=...&name=...&signature=...` | Open a signed URL as described in `.ai/signed-order-urls.md`. Place an order. | The page shows `Welcome, <name>!`. The order is charged to the card. | manual |
| `client-order-closed-event` | `/order/{token}` of a closed event | Close the event in Manage. Open the order page. | The page shows an error instead of the table. | manual |

## Owner on a device

The owner does these steps in the Android app against the staging instance, before the merge of pull requests 2 and 3.
Use a real NFC card and the built-in reader of the phone.

1. If the app is paired, log out in `Settings` and accept the dialog.
2. In Manage on staging, open `Points of sale` and click `+`.
3. In the app, tap `Scan QR Code` and scan the QR code. Make sure that the app shows a pairing code.
4. Enter the code and a device name in Manage. Make sure that the app opens the events list.
5. Open the events list. Open the row dropdown `Actions` and make sure that it opens and closes.
6. Tap an event name. Make sure that the product tiles and the remote orders column show.
7. Tap the NFC button in the navbar. Tap `Generate Credentials`. Make sure that the modal shows `Waiting for approval`.
8. Approve the key in Manage. Tap `Check Approval Status` in the app. Make sure that the modal shows `Credentials approved!`.
9. Open `Cards`. Hold a blank card to the phone. If the app shows `Corrupt card`, take the card away and hold it again.
10. Make sure that the navbar shows `Balance: 0.00`. Tap the first amount and tap `OK` two times.
11. Make sure that the navbar shows the new balance.
12. Open the sales screen of the event. Tap two products. Tap `Confirm` and `OK`.
13. Hold the card to the phone. Make sure that `Payment successful` shows and that the balance goes down.
14. Make an order and tap the cash button in the payment modal. Make sure that `Payment successful` shows.
15. Make an order and tap `Cancel` in the payment modal. Make sure that `Payment failed` shows.
16. Place an order on the order page of the event with a second phone. Tap `Completed` in the app and pay with cash.
17. Make sure that `Order accepted` shows the table number.
18. Open `Settings`. Change the checkbox `Allow live orders at this terminal` and tap `Save`. Make sure that the sales screen follows. Set it back.
19. Tap `Sync now`. Make sure that `Synchronization complete.` shows.
20. Open the sales overview and the order history of the event. Make sure that the sales of this walk-through show.
21. Open the browser console of the app through remote debugging. Make sure that it shows no error.
