# Sales summary refresh and stale warning

## Intent

On 13 November 2026 a client settled with quiz teams from the "Orderer names" tab of the sales summary. The page was loaded at about 22:32 and was not reloaded. Teams kept ordering until 23:01, so €87 of processed orders were not charged. The data was correct. The page was old, and nothing on the page said so.

The sales summary pages (`summary` and `summary-names`, in Manage and POS) must stay current on their own. When the figures on screen can be old, the page must say so clearly, on screen and on a printout.

## Goals

- The page reloads the figures on its own while it is visible.
- The page shows the time of the last successful load and the age of the data on screen. The printout and the PDF show both.
- If the last successful load is too old, the page shows a clear warning.
- A refresh updates the table in place: no spinner, no blank table, no scroll jump.

## Non-goals

- Server-side change detection (ETag, "changed since" checks). One aggregate query per refresh is cheap.
- Highlighting rows that changed.
- Push updates (websockets).

## Anti-goals

- A page that looks current but is not. A failed refresh must never look like a success.
- Polling from tabs nobody looks at.
- Overlapping requests on a slow server.

## Approaches

1. Timer only: turn the commented-out `refresh()` back on. Rejected: browsers throttle timers in background tabs and tablets sleep, so the page can still be old without a warning.
2. Timer plus age-based warning (picked): the page reloads on a timer. It also reloads at once after it becomes visible again. The warning uses the time since the last successful load, not the timer. One rule covers sleep, network loss, and server errors.
3. Server change detection plus timer: saves bandwidth on big events, but adds server state and complexity for a query that takes milliseconds. Rejected for now.

## Design

- Refresh interval: 30 seconds on the items tab, 60 seconds on the names tab (the names tab can return thousands of rows at large events).
- While the document is hidden, the page does not poll. When it becomes visible again, it refreshes at once.
- Only one request runs at a time. A tick that comes while a request is in flight is skipped.
- On success, the page stores the load time and replaces the rows. On failure, it keeps the old rows and does not update the load time.
- Next to the title: "Updated at HH:MM:SS (N s ago)" in local time. The age counts up every second, in seconds below one minute and in minutes after that. The line is visible in print.
- If the last successful load is more than 2 minutes old, the page shows a red alert. The alert says that the figures can be out of date, and it has a "Refresh now" button. The alert is visible in print.
- The timing rules live in a small pure module with Vitest tests. `SalesSummary.vue` uses it.
- The first load still shows the spinner. Later refreshes do not.
- Server: `OrderSummaryController::getSummary()` loads the menu items of all rows in one query, instead of one lazy query per row.

## Left to the builder

- Module name and API shape.
- Exact Bootstrap-Vue markup for the alert and the timestamp.
- Translation strings (the component uses `$t`. Add Dutch strings where the other strings have them).
