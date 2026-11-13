# Menu import, menu sequence, and editable menu table

Date: 2026-11-12
Status: design, waiting for approval

## Why

Venues send their menu by email, as a table, a Word document, or an Excel file.
The team enters that menu in Manage about one hour before the event starts.
Today that means one popup form per product, with manual copy and paste for each line.
Products also show in creation order, and there is no way to change that order.

The goal has three parts. The team can paste a full menu in one step. The team can correct it fast.
The team can set the sequence in which products show on the order screens.

## Words used in this document

- **Sequence**: the position of a product in the menu, or of a heading in the list of headings.
  This document does not use the word "order" for this, because "order" already means a customer order.
- **Menu table**: the table on the Manage menu page (`resources/manage/js/views/Menu.vue`).
- **Import sheet**: the dialog in which the user pastes a menu.
- **Order screens**: the POS sales screen (`resources/shared/js/components/LiveSales.vue`, shown in
  `resources/pos/js/views/Headquarters.vue`) and the client order page (`resources/clients/js/views/Order.vue`).

## Goals

1. Products and headings have a sequence that the user controls.
2. The Manage menu page is an editable table with one Save button. The popup form goes away.
3. The user can paste a menu from an email, Word, or Excel into an import sheet and add all products in one step.
4. The order screens show products in the menu sequence.
5. The order screens group products under headings (stage 2).

## Non-goals

- No file upload (`.xlsx`, `.docx`, PDF) and no photo or AI reading of a menu. Paste only.
- No import in the POS app. The POS menu page keeps its one function: switch a product between selling and not selling.
- No import step in the event wizard.
- No automatic heading from a pasted heading line such as `BIEREN`. That line stays a marked row.
- No rename or delete of categories, unless Manage already has it. No delete of headings in Manage.
- No touch drag and no phone layout for the menu table and the import sheet.
- No product filter on the POS sales screen.

The browser mockups in `.superpowers/brainstorm/` are older than this document. If they differ, this document is correct.

## Anti-goals

- A change that goes live before the user presses Save. A wrong keystroke in a price cell during an event must not reach the POS devices.
- A Save in Manage that silently reverts a "not selling" status that a POS device set while the menu table was open.
- An import that replaces or removes existing products.
- A change in behavior for the Attendees page, which uses the existing `Spreadsheet.vue` component.

## Current state

- `menu_items` and `categories` have no position column. No query sorts them, so they return in creation order.
- Three reads return menu items: the Management API index (`GET /api/v1/events/{id}/menu`), the Device API index
  (`GET /pos-api/v1/events/{id}/menu`), and the public menu (`PublicController@menu`, only products that are selling).
  All three use `Event::menuItems()`.
- The Device API allows `index`, `view`, and `edit` on menu items. `MenuItemPolicy::editStatus()` lets a device change the
  selling status. `create`, `edit`, and `destroy` are for users only.
- Categories route orders to locations (`events.split_orders_by_categories`) and filter what a POS device handles.
  No screen groups products by category.
- Manage creates a category from the product popup, through a browser prompt for the name.
- `resources/shared/js/components/Spreadsheet.vue` is an existing grid with keyboard movement, paste, changed-row marks,
  a Save button, and row delete. `resources/shared/js/views/Attendees.vue` uses it, with bulk create
  (`X-Bulk-Request` header) and bulk delete (see `.ai/charon-bulk-delete-instructions.md`).
- `MenuService.mapToServerData()` (`resources/shared/js/services/MenuService.js`) does not send `vat_percentage`.
  Make sure that the new menu table saves the VAT value.

## Stage 1: sequence, editable menu table, import

### Sequence in the data

- Add an integer `position` column to `menu_items`. A migration fills it for existing rows, per event, in `id` order.
- All three menu reads return products sorted by `position`, then `id`.
- A new product gets the next position after the last product of its event, unless the request gives a position.
- `position` is visible in the menu item resource of both APIs. Only users can write it. A device cannot change the sequence.

### The menu table in Manage

The menu page shows one row per product, in sequence. Columns:

| Column | Cell type | Rule |
|--------|-----------|------|
| Drag handle | handle | Drag to move the row |
| Product name | text | Required |
| Description | text | Optional |
| Price | number | Required. 0 is valid. All taxes included |
| VAT % | number | Optional |
| Category | selector | Existing categories, empty, and "Create new category" |
| Status | toggle | Selling or not selling |
| Delete | button | Marks the row as deleted. The user can undo this before Save |

Behavior that the user decided:

- The user edits cells directly. There is no popup form.
- Nothing is sent to the server until the user presses Save. One Save button sends all changes.
- The user drags rows to change the sequence.

Behavior that is a design call:

- Changed rows, new rows, moved rows, and deleted rows are marked, so that the user sees what Save will do.
- The page has an empty row at the end. If the user types in it, it becomes a new product.
- Tab, Enter, and the arrow keys move between cells, as in the existing `Spreadsheet.vue`.
- If the user leaves the page with unsaved changes, the browser shows a warning.
- A "Reset" button discards all unsaved changes and loads the menu again.
- Save is blocked while a row has no name or no valid price. The table marks the cells that are wrong.
- If part of a Save fails, the rows that failed stay marked as unsaved, and the page shows the error message of the API
  (`getApiErrorMessage()`). Rows that saved are not sent again.
- A new category is still created at once through the existing prompt, not at Save. A category without products has no effect on the order screens.

Rules for Save:

- Save sends only rows that changed. For a changed row, it sends only the fields that changed. This protects the selling
  status that a POS device changed in the meantime. If Charon rejects a partial update, the builder finds a different way to meet the same anti-goal.
  One example is a fresh read of `is_selling` before the write.
- The sequence is saved in one request that carries the ordered list of product IDs for the event. The server writes the
  positions in one transaction. Suggested route: `PUT /api/v1/events/{id}/menu/sequence`, Management API only,
  authorized like an edit of the event menu (users of the organisation, no devices). IDs that do not belong to the event are rejected.
  Products of the event that are missing from the list keep their relative sequence and go after the listed ones.
- Save creates the new rows first. The sequence request goes out after that, because it needs the IDs of the new rows.

### The import sheet

The menu page has an "Import menu" button. It opens a dialog with an empty sheet.

1. The user clicks the first cell and pastes.
2. The sheet grows to the number of rows and columns of the pasted data.
3. Each column header has a selector for the column type: Product name, Description, Price, VAT %, or Ignore.
4. The import guesses the type of each column. The user can change each guess.
5. The user can edit cells and delete rows in the sheet.
6. Below the sheet, the user can select one category for all rows. The default is no category.
   The selector also offers "Create new category". It asks for a name and creates the category at once, as the menu table does.
7. The user presses "Add N products".

Rules:

- Each column type, except Ignore, can be used for one column at most. Product name and Price must each be assigned before the user can add products.
- Guess rules: a column in which most cells read as a price becomes Price. The first text column becomes Product name.
  The second text column becomes Description. All other columns become Ignore.
- Price reading accepts a comma or a point as the decimal mark, an optional currency mark before or after the number,
  and spaces. Examples that must read correctly: `2,50`, `2.5`, `€ 4,50`, `4,50 €`, `5 euro`, `EUR 3`, `3,-`.
- Smart split: if the pasted data has one column, the import splits each line. It takes a price from the end of the line
  as the Price, and the rest as the Product name. Dot leaders, dashes, and colons between name and price are removed
  (`Pils 25cl ..... 2,50` gives name `Pils 25cl` and price `2.50`). A number that is followed by a unit, such as `25cl`, is not a price.
  The import does not extract a description from plain lines.
- Empty lines are dropped.
- A row without a name or without a readable price is marked. Heading lines of the venue (for example `BIEREN`) and table
  header rows end up as marked rows. The "Add" button stays disabled while marked rows remain. A "Delete marked rows" button removes them in one step. This is a design call.
- "Add N products" closes the dialog. The rows go to the end of the menu table, as new unsaved rows, in the pasted sequence.
  The Save button of the menu page stores them. This is a design call. It keeps one rule for the full page: nothing goes live before Save.
- Imported products have the status "selling", the same as a product that the user adds by hand.
- Existing products are never changed or removed by an import. The import does not look for duplicates.

Clipboard formats: a table copied from Excel, Word, or an email arrives as text with tab characters between cells. A cell
with a line break inside arrives in double quotes. The parser must handle both. The parser reads only the plain-text form of the clipboard.

#### Copy from a different event

The dialog has a selector "Copy from event" above the sheet. Its first option is empty.
The other options are the events of the organisation, in the sequence of the events page. The event whose menu is open is not in the list.
If there is no other event, the dialog does not show the selector.

When the user selects an event, the dialog loads the menu of that event and fills the sheet.
The sheet gets four columns: Product name, Description, Price, and VAT %. It gets one row per product, in the menu sequence.
The price has two decimals. An empty description or VAT gives an empty cell.

After the fill, the sheet works as after a paste. The user can edit cells, change column types, delete rows, and select a category for all rows.
"Add N products" puts the rows at the end of the menu table as new unsaved rows. Nothing reaches the server before Save.

In stage 1, categories are not copied, and the copied products get the category that the user selects for the full batch, or no category.
Stage 2 changes this: see "Headings and categories in the import".
Copied products have the status "selling", the same as every import.

The source event never changes. The only request to the source event reads its menu.

### Code shape for stage 1

These are constraints. The rest is for the builder.

- The paste parser, the price reader, the column guess, and the smart split are pure JavaScript functions in a module
  without Vue, with Vitest tests next to the module (`resources/**/*.test.js`). The examples in this document are test cases.
- The change tracking of the menu table (which rows are new, changed, moved, deleted, and what Save must send) is also a pure
  module with Vitest tests.
- The menu table and the import sheet can extend `Spreadsheet.vue` or be new components. The builder decides. The Attendees page must keep its current behavior.
- The owner enters menus in Manage on a laptop. The menu table, the import sheet, and the drag of rows must work well with a mouse and a keyboard.
  Drag on a touch screen and a phone layout for these screens are not required.
- Prefer drag and drop without a new npm package. Native HTML5 drag and drop is enough. If a package is needed, name it in the plan, because the project rule on `package-lock.json` needs a decision from the owner.
- All new interface text goes through `$t()` and is added to all five language files in `resources/shared/js/i18n/`.
- Follow the project patterns in `CLAUDE.md`: Charon resource definitions, policies that check the parent, and the common API error format.

## Stage 2: headings on the order screens

The owner changed the direction of stage 2 on 2026-11-13. The first design used categories as headings. That fails for a bar that
serves "Beers" and "Sodas". A POS device filters on one category. An event that splits orders per category sends that bar
two orders for one customer. Headings are now a separate property of a product. Categories keep their one job, which is routing.
Nothing changes in the POS category filter, in the split of orders, or in the assignment of orders to devices.

### Words

- **Category**: routes an order to a location, for example "Drinks" to the bar and "Food" to the kitchen. Unchanged.
- **Heading**: a title on the order screens that groups products for the reader, for example "Beers", "Sodas", "Snacks".
  A product has one heading or none. A heading belongs to one event and has a name and a position.

### Headings in the data

- A new table `menu_headings` with `event_id`, `name`, `position`, timestamps, and soft delete. A new nullable `menu_items.heading_id`.
- Heading reads return headings sorted by `position`, then `id`. A new heading goes last.
- The Management API has create, read, update, and delete for headings of an event. The Device API has read only.
- The menu item resource of both APIs carries the heading of a product with its `id`, `name`, and `position`. The public menu does too.
  Only users can change the heading of a product. A device cannot.
- The server rejects a product that links a heading of a different event. The same check is added for the category of a product.
- If a heading is deleted, its products keep existing and have no heading.
- The sequence of headings is stored in one request, in the same way as the product sequence
  (`PUT /api/v1/events/{id}/headings/sequence`, Management API only, users only).
- Categories get no position. The category sequence of the first design is dropped.

### Headings on the order screens

- The POS sales screen and the client order page show one title per heading, in heading sequence.
  Below each title are the products of that heading, in menu sequence.
- Products without a heading come first, without a title.
- A heading without visible products shows no title.
- If no visible product has a heading, the screen shows no titles and looks as it does today.
- The grouping is one pure shared function with Vitest tests. Both order screens use it.
- A heading row on the client order page is not a product. It must never reach the order that the customer sends.

### Headings in Manage

- The menu table gets a "Heading" column after "Category". The selector lists no heading, the headings of the event, and "Create new heading".
  A new heading is created at once through a name prompt, as a new category is.
- A change of the heading of a product is saved with the Save button, as all other cells are.
- The menu page gets a "Headings" section below the menu table. It lists the headings in sequence. The user drags a heading to
  move it and edits its name in place. Both changes are marked and are saved with the same Save button. A heading name must not be empty.
- The menu table stays one flat list. The sequence of products below a title follows from the flat sequence.
- A short hint in the "Headings" section explains the difference: headings group products on the order screens, and categories send orders to a location.
- Delete of a heading is not in the Manage screens. A heading without products shows nowhere.

### Headings and categories in the import

- The import sheet gets two more column types: "Category" and "Heading". The import never guesses these two types.
- Below the sheet, next to "Category for all products", there is "Heading for all products", with "Create new heading".
- A row with a value in a "Category" or "Heading" column uses that value. A row with an empty cell uses the selection for all products.
- A name in such a column is compared with the existing categories or headings of the event. Letter case and outer spaces do not count.
  If the names are equal, the row uses the existing one.
  If no match exists, "Add" creates the category or heading at once, and then adds the rows.
  If a create request fails, the dialog stays open, shows the error, and adds no rows.
- A copy from a different event also fills the sheet with the category name and the heading name of each product.
  A column that is empty for all products is left out. New headings are created in the heading sequence of the source event.

### Calls made for stage 2

The owner decided: headings are separate from categories, and a copy from a different event brings its categories and headings.
The owner declined: a pasted heading line that becomes a heading automatically.

The designer decided these. The owner can overrule each one.

1. The word in the interface is "Heading". The code uses `heading` too.
2. Heading names and sequence are saved with the Save button. A new heading is created at once.
3. Rename is possible in Manage. Delete is not.
4. "Category" and "Heading" are open as column types for a paste too, not only for a copy.
5. The server checks that the heading and the category of a product belong to the same event as the product.
6. Categories get no position.

## Tests

- PHPUnit feature tests: menu reads return products in `position` order for the Management API, the Device API, and the public menu.
  The sequence endpoint stores the sequence, rejects IDs of a different event, rejects a user of a different organisation, and rejects a device. A new product goes last. The same for headings in stage 2.
- Vitest: parser, price reader, column guess, smart split, change tracking, and the grouping function.
- A manual walk through both stages in the browser before each stage is called complete: paste from a real Excel table, from a Word
  table, and from plain email lines.

## Delivery

Two pull requests against `main`, stage 1 first. Stage 2 depends on stage 1.

## Calls made in this design

The owner did not decide these. The designer did. The owner can overrule each one.

1. One flat product sequence. No separate sequence per category.
2. The sequence is saved through one dedicated request, not through one update per row.
3. Imported rows land in the menu table as unsaved rows. The Save button of the page stores them.
4. The "Add" button of the import is blocked while marked rows remain, with a "Delete marked rows" shortcut.
5. Stage 1 had no "Category" column type in the import sheet. Stage 2 adds it.
6. Smart split takes only the price from a plain line. It does not extract a description.
7. A warning on leaving the page with unsaved changes, and a Reset button.
8. New categories are still created at once, not at Save.
9. Heading sequence is set in a small section on the menu page.
10. Products without a heading show first on the order screens, without a title.
11. Two stages, two pull requests.
