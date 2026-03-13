# Table Service Integration

## Overview

The Table Service feature enables waiter-operated table ordering in the POS application.
Waiters can take orders at tables, track patrons, manage fulfillment/payment states
independently, and settle tabs.

---

## Database Schema

### `tables` Table

| Column       | Type     | Notes                                  |
|--------------|----------|----------------------------------------|
| id           | int (PK) | Auto-increment                         |
| event_id     | int (FK) | References `events.id`                 |
| table_number | int      | Unique within event (non-soft-deleted)  |
| name         | string   | Display name, e.g., "Table 1"          |
| created_at   | datetime |                                        |
| updated_at   | datetime |                                        |
| deleted_at   | datetime | Soft delete support                    |

**Constraints:** `UNIQUE(event_id, table_number)`

### `patrons` Table

| Column     | Type     | Notes                      |
|------------|----------|----------------------------|
| id         | int (PK) | Auto-increment             |
| event_id   | int (FK) | References `events.id`     |
| name       | string   | Nullable (anonymous patrons)|
| table_id   | int (FK) | Nullable, references `tables.id` |
| created_at | datetime |                            |
| updated_at | datetime |                            |

### `orders` Table (New Columns)

| Column         | Type     | Notes                                  |
|----------------|----------|----------------------------------------|
| patron_id      | int (FK) | Nullable, references `patrons.id`      |
| table_id       | int (FK) | Nullable, references `tables.id`       |
| payment_status | string   | `'unpaid'`, `'paid'`, `'voided'`; default `'paid'` |

### `events` Table (New Columns)

| Column                    | Type | Notes                               |
|---------------------------|------|-------------------------------------|
| allow_unpaid_table_orders | bool | Default `false`; allows waiters to open tabs |

---

## Models

### `Table` (`App\Models\Table`)

- Uses `SoftDeletes` and `HasFactory` traits
- **Relationships:** `event()`, `patrons()`, `orders()`
- **Methods:**
  - `getLatestPatron()` — returns the most recently created patron at this table
  - `bulkGenerate(Event $event, int $count)` — static; creates `$count` tables starting
    from the highest existing `table_number + 1`, named "Table N"

### `Patron` (`App\Models\Patron`)

- **Relationships:** `event()`, `table()`, `orders()`
- **Methods:**
  - `getOutstandingBalance()` — sum of prices of all unpaid orders
  - `hasUnpaidOrders()` — boolean check for any unpaid orders

### `Order` (Updated)

New status constants:
```php
Order::STATUS_PREPARED  = 'prepared'
Order::STATUS_DELIVERED = 'delivered'

Order::PAYMENT_STATUS_UNPAID = 'unpaid'
Order::PAYMENT_STATUS_PAID   = 'paid'
Order::PAYMENT_STATUS_VOIDED = 'voided'
```

New relationships: `patron()`, `table()`

---

## Patron Assignment Algorithm

`PatronAssignmentService` resolves which patron should own an incoming order:

### 1. Named Orders (e.g., Quiz App)

```
If name is provided:
  → Search for existing patron with that name who has orders within last 24 hours
  → If found: reuse that patron
  → If not found: create a new patron with the name
```

### 2. Anonymous Orders (e.g., Table QR Scan)

```
If table is provided (no name):
  → Get the last patron assigned to this table
  → If that patron has unpaid orders: reuse them
  → If all orders are paid: create a new patron for the table
```

### 3. No Context

```
If neither name nor table: return null (no patron assignment)
```

### Auto-create Tables

`findOrCreateTable(Event $event, int $tableNumber)` finds an existing non-soft-deleted table
or creates one. Used when remote orders arrive referencing unknown table numbers.

---

## API Endpoints

### Table Endpoints

| Method | Path                                    | Action           | Auth            |
|--------|-----------------------------------------|------------------|-----------------|
| GET    | `/events/{id}/tables`                   | List tables       | Both APIs       |
| POST   | `/events/{id}/tables`                   | Create table      | Both APIs       |
| POST   | `/events/{id}/tables/generate`          | Bulk generate     | Both APIs       |
| GET    | `/tables/{id}`                          | View table        | Both APIs       |
| PUT    | `/tables/{id}`                          | Edit table        | Both APIs       |
| DELETE | `/tables/{id}`                          | Soft-delete table | Management only |

### Patron Endpoints

| Method | Path                                    | Action           | Auth            |
|--------|-----------------------------------------|------------------|-----------------|
| GET    | `/events/{id}/patrons`                  | List patrons      | Both APIs       |
| POST   | `/events/{id}/patrons`                  | Create patron     | Both APIs       |
| GET    | `/patrons/{id}`                         | View patron       | Both APIs       |
| PUT    | `/patrons/{id}`                         | Edit patron       | Both APIs       |

### Updated Order Fields

`OrderResourceDefinition` now exposes:
- `payment_status` — filterable, writeable
- `patron_id` — filterable, writeable
- `table_id` — filterable, writeable

---

## Authorization Policies

### `TablePolicy`

| Action   | User (in org) | Device (in org) | Other/Null |
|----------|:-------------:|:---------------:|:----------:|
| index    | ✅            | ✅              | ❌         |
| create   | ✅            | ✅              | ❌         |
| view     | ✅            | ✅              | ❌         |
| edit     | ✅            | ✅              | ❌         |
| destroy  | ✅            | ❌              | ❌         |

### `PatronPolicy`

Same as `TablePolicy` — devices can CRUD except destroy.

---

## Frontend

### Services

- **`TableService`** — extends `AbstractService`, sets `indexUrl = events/{id}/tables`,
  `entityUrl = tables`. Has `bulkGenerate(count)` method.
- **`PatronService`** — extends `AbstractService`, sets `indexUrl = events/{id}/patrons`,
  `entityUrl = patrons`.
- **`PaymentService`** — has `orders(orders)` batch payment method for settling multiple
  unpaid orders in a single payment transaction. Has `payLater()` method and
  `allow_pay_later` flag for deferred payment.

### POS Device Settings

- **`allowTableService`** — stored in `SettingService`, persisted in localStorage.
  Mutually exclusive with `allowLiveOrders` and `allowRemoteOrders`.
  When enabled, the POS Headquarters shows the waiter dashboard instead of the bar
  live/remote orders interface.

### Component Architecture

| Component             | Location                          | Purpose                                                  |
|-----------------------|-----------------------------------|----------------------------------------------------------|
| `TableService.vue`    | `pos/js/components/`             | Isolated table service component: table grid, patron modal (selection + details), order queue |
| `LiveSales.vue`       | `shared/js/components/`          | Menu + order form. Accepts optional `patronId`, `tableId`, `allowPayLater` props for table service context |
| `PaymentPopup.vue`    | `shared/js/components/`          | Payment modal. Shows "Pay later" button when `allow_pay_later` is set on PaymentService |

### Views

| View                  | Location                          | Purpose                                                  |
|-----------------------|-----------------------------------|----------------------------------------------------------|
| `Tables.vue`          | `shared/js/views/`               | Table management: bulk generate, inline rename, delete (manage app only) |
| `WaiterDashboard.vue` | `shared/js/views/`               | Standalone waiter dashboard (used by manage app)         |
| `PatronDetail.vue`    | `shared/js/views/`               | Standalone patron detail (used by manage app)            |
| `Headquarters.vue`    | `pos/js/views/`                  | Thin orchestrator: bar mode OR `<table-service>` component |

### Modal Flow (POS)

1. Click table card → modal opens at patron selection step
2. Select patron or click "New Patron" → modal transitions to patron details
3. Patron details show: outstanding balance, order history, settle button, and LiveSales new order form
4. "Back to patron list" button returns to step 2

### Routes

**Manage app** registers standalone routes:

| Path                            | Name    | Component        |
|---------------------------------|---------|------------------|
| `/events/:id/tables`            | tables  | Tables           |
| `/events/:id/waiter`            | waiter  | WaiterDashboard  |
| `/events/:id/patron/:patronId`  | patron  | PatronDetail     |

**POS app** integrates table service into the Headquarters component via `TableService.vue` (no standalone routes).

### Navigation

- **POS Events.vue**: Standard actions only (sales overview, order history, check-in).
  Table service access is via Headquarters when enabled in settings.
- **Manage Events.vue**: "Manage tables" and "Waiter dashboard" links in "Table Service"
  dropdown group; `allow_unpaid_table_orders` checkbox in event edit modal

### Pay Later Flow

When `event.allow_unpaid_table_orders` is true:
1. LiveSales sets `allow_pay_later = true` on PaymentService before triggering payment
2. PaymentPopup shows a "Pay later" button alongside cash/card/voucher options
3. Clicking "Pay later" resolves with `paymentType: 'pay-later'`
4. LiveSales sets `payment_status: 'unpaid'` on the order
5. `allow_pay_later` is reset to `false` after each order

---

## Event Settings

| Setting                      | Type | Default | Description                              |
|------------------------------|------|---------|------------------------------------------|
| `allow_unpaid_table_orders`  | bool | false   | When true, waiters can "Pay Later" on orders, leaving payment_status as 'unpaid' while fulfillment continues |

---

## Offline Considerations

The table service must work offline. Key design decisions:
- `OrderService` extends `AbstractOfflineQueue` which stores orders in IndexedDB
- Table and patron data is cached via the `ApiCacheService` interceptors
- Waiters can create orders offline; they sync when connectivity returns
- Bar preparation status won't update offline, but the waiter can manually mark
  orders as delivered
