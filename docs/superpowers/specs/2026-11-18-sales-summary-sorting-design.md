# Sales summary sorting and names columns

## Intent

The sales summary tables (`summary` and `summary-names`, in Manage and POS) are used to find products and teams and to compare amounts. The user wants to sort them by clicking a column header. The names tab shows four columns that are always empty, and they must go.

## Goals

- The user sorts a table by clicking a column header. A second click reverses the order.
- The names tab shows only Rank, Name, Amount and Total.

## Non-goals

- Sorting on the server.
- Saving the chosen order between visits.
- Sorting by the VAT columns.

## Approaches

1. Sort in the browser (picked). All rows are already in the browser, and the rows are frozen. A computed sorted list costs one sort per refresh or click.
2. Sort on the server with a query parameter. Rejected: it adds a request per click and changes the API for no gain.
3. Bootstrap-Vue `b-table` with built-in sorting. Rejected: the items tab groups several price rows under one product, which `b-table` does not support, and `b-table` is heavy for thousands of rows.

## Design

- Sortable headers: Rank, Item (Name on the names tab), Amount, Total, and Price on the items tab only.
- A first click sorts ascending, except Amount and Total, which sort largest first. A second click on the same column reverses the order. An arrow shows the active column and the direction.
- The default order is the order of today: rank on the items tab, name on the names tab. No arrow shows until the user clicks.
- The items tab sorts groups. The rows of a product stay together. Amount and Total sort by the sum of the group. Price sorts by the highest price in the group.
- A different order does not change the rank number of a row.
- In a rank sort, rows without a rank ("Unknown / manual" on the names tab) go to the end in both directions.
- Equal values keep the default order.
- Text sorts with the app locale and ignores case (`Intl.Collator`).
- The chosen order survives a refresh, and a printout shows it.
- The names tab does not show the Price, VAT %, Netto and VAT columns.
- The sort rules live in a pure module with Vitest tests.

## Left to the builder

- Module name and API shape.
- Markup of the clickable headers. They must work with the keyboard (a button in the header cell).
