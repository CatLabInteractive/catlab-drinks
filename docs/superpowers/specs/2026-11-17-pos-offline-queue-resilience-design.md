# POS offline queue: survive a failed upload

- Date: 2026-11-17
- Status: approved by the owner on 2026-11-17
- Trigger: the 422 on POS sales (pull request 256) showed three weaknesses in the upload queue.

## Intent

The POS stores each sale in a queue on the device and uploads the queue in the background. A bug made the
server reject every sale from the sales screen. The queue made that bug worse than it had to be. This
work makes the queue keep going after a failure and makes the POS state the truth about its sales.

## Current behavior

Source: `resources/shared/js/services/AbstractOfflineQueue.js` and `AbstractService.js`.

- `uploadQueue` sends the queue in batches of ten and then sets a timer of 30 seconds.
- If one request fails, `uploadQueue` stops. It sets no timer and its `uploading` flag stays set. If
  that flag is set, the online event starts no upload. The queue then waits until the sales screen
  is opened again.
- A batch is one request. If the server rejects one order, the POS keeps the full batch and does not try
  the later batches.
- The response interceptor shows a browser alert for each 422 and for each status of 500 or higher. A
  background upload therefore shows the same alert again and again.
- The POS settings show one count, "Pending queue items". The text below it says that these items upload
  after the connection is back. A sale that the server rejects looks the same as a sale that waits for
  a connection.

Two facts from a backend test on 2026-11-17:

- A repeated upload of an order with the same `uid` gets status 200 and stores no second order.
- In a batch with a good order and then a bad order, the answer is 422, but the good order is stored.

A retry of each order by itself is therefore safe.

## Goals

- After a failed upload, the queue tries again without a person.
- One order that the server rejects does not block other orders.
- A background upload shows no browser alert.
- The bartender can see how many sales are not stored, on the sales screen and in the POS settings.
- The developer learns about a rejected sale through the existing error reporting (Errbit).
- The code never deletes a sale that the server did not accept.

## Non-goals

- No new API endpoint and no Manage screen for rejected sales. The owner decided that this is too much
  for a rare case.
- No change to the backend.
- No change to how a sale is made, paid, or printed.
- No protection against a wiped device. That risk exists today for each queued sale and stays the same.

## Anti-goals

- A sale that disappears from the device before the server stored it.
- A rejected sale that is sent to the server every 30 seconds without end.

## Approaches

Three ways were considered for a sale that the server rejects.

1. Keep it on the device and show it, and report it one time to Errbit. Chosen by the owner.
2. Keep it and retry it without end and without a message. This is the smallest change, but the
   bartender still learns nothing.
3. Send it to a new endpoint and show it in Manage, with a retry button for the admin. This is the most
   complete. The owner judged it too large for the problem.

## Design

### Rejected or not

An upload error is one of two kinds.

| Kind | Condition | What the queue does |
|------|-----------|---------------------|
| retry | No response (network), status 500 or higher, or status 401, 403, 408, 429 | Stop this pass. Try again at the next timer. |
| rejected | Each other status from 400 to 499 | Send the orders of that batch one at a time. |

If a batch is rejected, the queue sends each of its orders in a request of its own. An order that is
stored leaves the queue. An order that is rejected again stays in the queue with a mark. The mark holds
the status, the message of the server, the time of the last try, and a flag that says that it was reported.

### Retry of a rejected sale

A marked sale is not part of the normal batches. The queue tries it again one time in each 10 minutes.
"Sync now" in the POS settings tries each marked sale at once. If the server stores it, it leaves the
queue. This gives recovery after a server fix without a new screen.

### The queue stays alive

`uploadQueue` clears its `uploading` flag and sets the next timer in each case, also after an error.

### No alert from a background upload

A request can carry a `silent` flag. The response interceptor shows no alert for a silent request and
still rejects the promise. The queue uploads are silent. Each other request keeps its alerts.

### What the bartender sees

- Sales screen: if the queue of the event holds marked sales, a red line shows the count. English text:
  "{count} sales are not stored. They stay on this device and are sent again later."
- POS settings, sync panel: "Pending queue items" counts only the sales that are not marked. A new line
  "Sales that are not stored:" shows the count of marked sales and the last message of the server.
- The logout warning in the POS settings counts both kinds, because a logout deletes both.

Each new text gets a translation in the five language files.

### Report to Errbit

A new small module holds the function that reports an error. The POS and Manage set it at start, with
the Airbrake client that they already create. The queue reports a rejected sale one time, with the
event, the status, and the message. The report does not hold the content of the sale.

## Verification

- Unit tests for the kind of an error and for the 10 minute rule.
- Unit tests for the queue, with these cases:
  - A failed pass sets a timer.
  - A rejected batch is sent one order at a time.
  - A good order leaves the queue.
  - A rejected order stays with its mark.
  - A marked order is reported one time.
- A test that a silent request shows no alert and that a normal request still does.
- `npm test`, `npx jest`, and a production build in a clean copy.
- A browser run with the NFC simulator stack: one valid sale and one sale that the server rejects are
  in the queue. Expected: the valid sale is stored, the rejected sale stays, the sales screen shows
  "1", no alert opens, and a later valid sale is stored.

## Left to the builder

- The names of the new functions and the shape of the mark, within the rules above.
- How the sales screen learns that the counts changed.
- How the browser run makes the server reject one sale.
- The translations.
