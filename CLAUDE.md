# CasualiteOS — Claude Project Context

This file gives Claude the full context needed to work on CasualiteOS correctly.
**Read this entire file before writing any code.** Every section contains rules that have
been derived from the signed client proposal (`Casual_Lite_Website_Proposal_v9.docx`).
Deviating from these rules means deviating from the signed contract.

---

## 1. What This Project Is

**CasualiteOS** is the complete business operations system for **Casual Lite**, a fashion
brand based in Pakistan. It replaces manual notebooks, WhatsApp tracking, and Google
Forms with a single web application.

- **Tech Stack:** Laravel 13, PHP 8.3, MySQL, Blade templating, Alpine.js v3 (CDN),
  Tailwind CSS v3 (CDN)
- **Packages:** Spatie Laravel Permission v7, Spatie Laravel Activitylog v5
- **UI Style:** Apple-inspired — clean, minimal, mobile-first. CSS classes: `.card`,
  `.stat-card`, `.btn-primary`, `.btn-secondary`, `.apple-input`, `.apple-table`, `.badge`
- **No self-service password reset** on the login page (proposal requirement — security)

---

## 2. How the Business Works (Domain Knowledge)

### Catalogues

A Casual Lite season is called a **Catalogue**. Each has:

- A name (e.g. ISHQIA), cover photo, and a set of designs (each with its own selling price)
- A **qty_per_design** — pieces manufactured FROM EACH design (NOT total across all designs)
- A **quantity_benchmark** — the minimum order quantity at which a customer qualifies for the discounted price. Orders at or above this threshold use each design's `discount_price` instead of `selling_price` in the live total and final amount.
- A private **notes** field (internal only, never shown to customers)
- A unique **order_token** (auto-generated UUID, used in the shareable WhatsApp link)
- A **status**: `open` or `closed`

Each design is marked **In-House** or **Outsourced** at catalogue creation time.

Each design has two prices:
- `selling_price` — the standard price per suit
- `discount_price` — the discounted price applied when the customer's quantity meets or exceeds the catalogue's `quantity_benchmark`. Can be left null if no discount applies.

For **In-House** designs, a `needs_naeem_pakki` boolean flag can also be set at design creation/edit time. This means the design's fabric pieces need embroidery work done by Naeem Pakki before stitching begins. Naeem Pakki work is tracked separately on the Naeem Pakki screen.

### CRITICAL — qty_per_design vs total pieces

**`qty_per_design = 70` means 70 pieces FROM EACH design.**

```
qty_per_design = 70,  number_of_designs = 7
→ Total actual production = 70 × 7 = 490 pieces
```

**Never divide** `qty_per_design` by `number_of_designs`. The old column was named
`total_pieces` and was incorrectly divided by design count (70 ÷ 7 = 10). That logic
is wrong. `qty_per_design` is already the per-design number — use it directly.

```php
// CORRECT
$catalogue->totalPieces()         // = qty_per_design × number_of_designs = 490
$catalogue->qty_per_design        // = 70 (per design)

// WRONG — never do this
$catalogue->qty_per_design / $catalogue->number_of_designs   // ❌
```

The `Catalogue::totalPieces()` method returns `qty_per_design * number_of_designs`.
The `Catalogue::availablePieces()` method returns `totalPieces() - sum(all ordered quantities)`.

### How Orders Work

1. Admin shares the catalogue link on WhatsApp: `casuallite.com/order/{order_token}`
2. Customer fills in name, city, email, and piece quantities per size (XS/S/M/L/XL)
3. The **same quantity applies to ALL designs** in the catalogue
4. Total amount is shown live as they type. If the total quantity meets or exceeds the catalogue's `quantity_benchmark`, each design's `discount_price` is used instead of `selling_price`. If `discount_price` is null for a design, `selling_price` is always used for that design.
5. **Duplicate order prevention**: if the submitted email already has an order for the same catalogue, an alert modal warns the customer before they can re-submit.
6. On submit, the system looks up the email in the Customer Master List
7. Each saved order gets a sequential `order_number` starting from **1005335**, auto-incremented using the `order_number_sequence` table. This is displayed everywhere instead of the database `id`. Existing orders placed before this change retain their original random numbers (100000–999999 range); new sequential numbers (1005335+) can never collide with them.

### Bank Accounts

Bank accounts are managed in the `bank_accounts` table (admin-only). Each has:

- `title` — display name (e.g. "HBL", "Meezan", "Saleem")
- `is_active` — inactive accounts are hidden from the payment method dropdown

When recording a payment via **Bank Transfer**, the accountant or admin must select an active bank account from the dropdown. For **Cash** and **From Advance Credit** payments, no bank account is required and no receipt is required. Bank accounts are seeded — the 8 default accounts are: Saleem, Ehsan SB, Farhan, Meezan, HBL, Adnan, Osama, Akram.

### Assigned Bank Account (Title Given)

Each order has an `assigned_bank_account_id` nullable FK to `bank_accounts`. This is the **designated collection bank** for that order — the bank through which the accountant expects to receive payment. It is displayed as "Title Given" in the Bank Collection Report.

Assignment is done in two ways:

- **Per-order:** `PATCH /orders/{order}/assign-bank` (`orders.assign-bank`) — updates a single order's `assigned_bank_account_id`. Rendered as a dropdown on the order show page (admin + accountant only).
- **Bulk:** `POST /orders/bulk-assign-bank` (`orders.bulk-assign-bank`) — assigns a single bank to multiple selected orders at once. Available on the orders index page via checkboxes (admin + accountant only). Scoped to the active catalogue session to prevent cross-catalogue tampering.

The assigned bank is separate from the payment's `bank_account_id` (which records where money was actually received). The Bank Collection Report groups **expected** and **received** amounts by the assigned bank.

### Stitching Units

Stitching units are managed in the `stitching_units` table (not hardcoded integers). Each unit has:

- `number` — display number (1, 2, 3 …), auto-assigned, immutable
- `name` — human name (e.g. "Subhan", "Mumtaz")
- `payment_type` — `salary` or `per_piece`
- `per_piece_rate` — **required** for `per_piece` units. This is the rate used to calculate weekly wages. Salary units have no rate in CasualiteOS (tracked externally).
- `is_active` — inactive units are hidden from production assignment and stitching return forms

**Wages are auto-calculated — there is no manual wage entry form.** Every Friday at 23:45 the scheduler runs `wages:calculate-weekly`, which sums kameez returned (component = `kameez` in `stitching_return_items`) per catalogue per per-piece unit for the Saturday→Friday window, snapshots `per_piece_rate` from the unit, and creates or overwrites **unconfirmed** `Wage` records. Confirmed (paid) records are never overwritten. The `wages` table unique constraint is `(catalogue_id, stitching_unit_id, week_start)` — one record per catalogue per unit per week. A "Recalculate" panel on the wages index allows manual re-runs for backdated returns. The wages show page displays a per-design kameez breakdown table and shows who confirmed payment (`confirmed_by` → user name + `confirmed_at` timestamp).

### Catalogue Sold-Out

A catalogue becomes sold out when **either**:

- Admin manually sets `status = 'closed'`, OR
- `availablePieces()` reaches zero (`totalPieces()` minus all ordered quantities across all designs)

When sold out, the order link shows a sold-out screen. No form is rendered. Any POST
attempt is also rejected by the controller guard. The route for the order form GET is
named `order.public` — **not** `order.show`.

### Order Statuses (6 — exact enum values)

| Status                 | How it's set                                                                              |
| ---------------------- | ----------------------------------------------------------------------------------------- |
| `received`             | Automatically when customer submits the form                                              |
| `confirmed`            | **Auto** when first payment is recorded or advance credit is applied — also settable manually via the Confirm button on the order page (for zero-payment confirmations) |
| `stitching`            | **Automatically** when a fabric batch is recorded for the catalogue                       |
| `partially_dispatched` | Automatically when at least one dispatch batch is recorded but order is not fully shipped |
| `dispatched`           | Automatically when ALL ordered quantities are dispatched (`isFullyDispatched()` = true)   |
| `cancelled`            | **Automatically** when an order reduction brings `new_total` to 0 and the order is not yet dispatched |

**The `stitching` status is automatic, not a manual button.** When a FabricBatch is
created for a catalogue, all `confirmed` orders for that catalogue must auto-transition
to `stitching`.

**`partially_dispatched` vs `dispatched`:** Every dispatch batch sets the status to
`partially_dispatched` first. Only when `$order->isFullyDispatched()` returns `true`
does the status advance to `dispatched`. The "Dispatch Again" button on the dispatch
show page is hidden when status is `dispatched`. Customer portal labels this status
as "Partially Dispatched".

---

## 3. User Roles & Access Control

There are exactly **4 internal roles** plus customers (who have no login).

| Role                    | Spatie name          | What they can access                                       |
| ----------------------- | -------------------- | ---------------------------------------------------------- |
| **admin**               | `admin`              | Everything — the full system                               |
| **accountant**          | `accountant`         | Customers, orders, payments, ledger, reports               |
| **production_manager**  | `production_manager` | Catalogue management (create/edit/open/close), all production tracking, dispatch, wages, packed inventory, orders (read-only, no financials) |
| **creative_head**       | `creative_head`      | Catalogue management (create/edit/open/close, no delete), orders (read-only, no financials), all production screens (read-only — cannot create/edit/delete) |

### Creative Head access (as of 2026-06-10)

The `creative_head` role **cannot** access:

- Customer records or ledger
- Payment history or financial data
- The dashboard widgets that show orders/payments/production
- Any write action on production screens (create, edit, delete forms are hidden; controller guards return 403)
- Catalogue delete/destroy

The `creative_head` role **can** access:

- Catalogue management — create, edit, open/close (same as production_manager). `CatalogueController` enforces this via `adminOrProductionManager()` which includes `creative_head`; `destroy()` still uses `adminOnly()`.
- Orders index, PDF export, Excel export — with financials hidden (same `$hideFinancials = true` flag as production_manager)
- All production screens (fabric batches, production assignments, Naeem Pakki, stitching returns, Tarpai, press, packed inventory, outsourced batches, dispatch, wages, Tarpai charges, production tracker) in read-only mode. Write actions are blocked by `$this->denyCreativeHead()` in each controller's mutating methods.

### Route middleware groups currently in `routes/web.php`

- `role:admin` — user management, order reductions, bank accounts, stitching units, piece reassignment, cron logs
- `role:admin|accountant` — customers, payments, reports
- `role:admin|accountant|production_manager|creative_head` — orders index + exports
- `role:admin|production_manager|creative_head` — all production routes, dispatch
- `role:admin|production_manager|accountant|creative_head` — wages, Tarpai charges
- No role restriction (auth only) — dashboard, catalogues (accessible to all including creative_head)

---

## 4. Database Enum Values — Must Match Exactly

### `orders.status`

```
received | confirmed | stitching | partially_dispatched | dispatched | cancelled
```

### `customer_ledger.transaction_type`

```
advance_received | order_charged | payment_received | credit_applied | order_reduced | refund_issued
```

`surplus_to_advance` has been **removed** — it double-counted `order_reduced`. Surplus credit is reflected via `order_reduced` alone; the advance_credit_balance column tracks the actual balance. Do not add `surplus_to_advance` back.

### `payments.payment_type`

```
cash | bank_transfer | advance
```

`easypaisa` and `jazzcash` have been removed from the system. Do not add them back.

### `designs.manufacturing_type`

```
in_house | outsourced
```

### `designs.needs_naeem_pakki`

Boolean. Only meaningful when `manufacturing_type = 'in_house'`. Set at design creation time. If `true`, pieces of this design are sent to Naeem Pakki for embroidery before stitching — tracked via `naeem_pakki_sends` and `naeem_pakki_returns`. Always forced to `false` for outsourced designs.

### `production_assignment_items.size`

```
xs | s | m | l | xl | np
```

`'np'` is a special value used for Naeem Pakki assignments only — NP has no size breakdown, so the total pieces are stored as a single row with `size = 'np'`. All stitching assignments use `xs/s/m/l/xl` only. Migration `2026_05_02_000001` added `'np'` to this enum.

### `catalogues.status`

```
open | closed
```

### `tarpai_sends.tarpai_house`

```
rashid_bhai | yousaf_bhai | in_house
```

Gate pass is only generated for `rashid_bhai` and `yousaf_bhai`. Never for `in_house`.

### `order_reductions.adjustment_type`

```
damage | short_supply | price_correction | other
```

### `order_reductions.surplus_action`

```
none | credit_to_advance | refund
```

Only meaningful when `total_paid > new_total` (customer has overpaid after the reduction). `none` is stored when there is no surplus.

### `refunds.refund_method`

```
cash | bank_transfer
```

For `bank_transfer` refunds: `refund_reference` (free-text bank name / transaction ref) and `refund_document` (S3 file upload — image or PDF) may also be stored. For `cash` refunds: neither field is required.

---

## 5. Key Business Rules (Non-Negotiable)

### 5.1 Email Matching on Order Submission

When a customer submits the order form:

- System looks up `submitted_email` in the `customers` table
- **If found:** Order is linked to that customer (`customer_id` set), saved normally
- **If NOT found:** The order is **rejected** and the customer sees an "Account Not Found"
  modal telling them to contact the Casual Lite admin. The flagged-orders feature has been
  removed from the system.

### 5.2 Dispatch Rules

The `DispatchController::store()` **must** check `outstanding_balance` before dispatching.

```php
if ($order->outstanding_balance > 0) {
    return back()->with('error',
        'Order cannot be dispatched — outstanding balance: PKR ' .
        number_format($order->outstanding_balance) . '.');
}
```

If the balance is not cleared, dispatch is blocked. The production manager sees this message clearly.

### 5.3 Cargo Document Is a File Upload (Not Text)

Dispatch cargo document = **file upload** (PDF or image), stored in `cargo-documents/`
on the public disk, exactly like receipt images. The column `dispatch_batches.cargo_document`
stores the file path. Validation: `required|file|mimes:pdf,jpeg,jpg,png|max:10240`

### 5.4 Packed Inventory Deduction on Dispatch

After each dispatch batch is recorded, the quantities in that batch must be subtracted
from the `press_pack_records` (packed inventory). The `DispatchController::store()`
must loop through `dispatch_batch_items` and decrement the corresponding
`press_pack_records` rows by design and size.

### 5.5 Order Reduction — Full Three-Case Logic

`OrderReductionController::store()` implements the full three-case logic based on whether the customer has overpaid after the reduction. The admin selects a `surplus_action` on the form, but it is only applied if a real surplus exists (`total_paid > new_total`).

**Case 1 & 2 — No surplus (`total_paid <= new_total`):**

- Update `total_amount = new_total`, recalculate `outstanding_balance = max(0, new_total - total_paid)`.
- Create one ledger entry: `order_reduced`, amount = `−$totalReduced` (negative — reduces what the customer owes).

**Case 3 — Customer has OVERPAID (`total_paid > new_total`, i.e. surplus exists):**

- `$surplus = $total_paid − $new_total`
- Set `outstanding_balance = 0`.
- Create ledger entry: `order_reduced`, amount = `−$totalReduced`.
- Then apply `surplus_action`:

  **`credit_to_advance`** — add `$surplus` to `customer->advance_credit_balance`. No extra ledger entry (the balance impact is already captured by `order_reduced`).

  **`refund`** — create a `Refund` record with `refund_method` (cash/bank_transfer), optional `refund_reference` (free-text bank/transaction ref), optional `refund_document` (S3 upload). Create a second ledger entry: `refund_issued`, amount = `+$surplus` (positive — cancels out the credit created by the over-payment so the ledger balance returns to 0).

  **`none`** — do nothing with the surplus (admin's choice to leave it in limbo).

**Auto-cancellation:** After any reduction, if `new_total == 0` and the order is not `dispatched`, the order status is set to `cancelled` automatically.

### 5.6 Advance Credit Balance Must Be Kept Current

`Customer->advance_credit_balance` is the live running total of credit the customer holds.
It must be updated whenever:

- Advance payment received (`advance_received`) → **increase** balance
- Credit applied to an order (`credit_applied`) → **decrease** balance
- Surplus from order reduction with `surplus_action = credit_to_advance` → **increase** balance

### 5.7 `running_advance_balance` in Ledger Entries

Every `CustomerLedger` entry must store the customer's **actual** `advance_credit_balance`
at the time of that transaction — not `0`. Always read `$customer->advance_credit_balance`
fresh before creating a ledger entry.

### 5.8 Stitching Status Auto-Transition

When a `FabricBatch` is created (in `FabricBatchController::store()`), after saving:

```php
Order::where('catalogue_id', $batch->catalogue_id)
     ->where('status', 'confirmed')
     ->update(['status' => 'stitching']);
```

This is automatic — there is no manual "Mark as Stitching" button for this transition
in the proposal. The existing manual `orders.stitch` route should be removed or kept
only as an admin override.

### 5.9 Batch-Wise Dispatch — Order Status Logic

Each dispatch is a **batch**, not necessarily the whole order. After saving each batch:

```php
if ($order->isFullyDispatched()) {
    $order->update(['status' => 'dispatched']);
} else {
    $order->update(['status' => 'partially_dispatched']);
}
```

- `partially_dispatched` — at least one batch recorded, but quantities remain outstanding
- `dispatched` — all ordered quantities shipped; `isFullyDispatched()` returns `true`

Never mark an order `dispatched` unless `isFullyDispatched()` returns `true`. The
"Dispatch Again" button on the dispatch show page must be hidden when status is `dispatched`.

### 5.10 Portal Access — Email Verification

`CustomerPortalController::verify()` must compare the email entered by the visitor
against `$customer->email` (case-insensitive). Only on exact match is access granted
and the dashboard shown. If no match: return back with error. Customer portal shows
3 tabs: current order status, payments & balance, full order history. The order status
tab displays quantities **broken down per size** (XS / S / M / L / XL) for each order.

### 5.11 No Password Reset on Login Screen

There is no "Forgot Password" link on the login page. Intentional — the admin resets
passwords manually. Do not add one.

### 5.12 Payment Method Rules

| Payment type        | `bank_account_id` | Receipt upload        |
| ------------------- | ----------------- | --------------------- |
| `cash`              | **required**      | not required          |
| `bank_transfer`     | **required**      | **required**          |
| `advance` (credit)  | not required      | optional (may attach) |

**Why Cash requires a bank account:** even when a customer pays in cash, the company staff deposits that cash into a specific bank. The bank account field records the deposit destination — it is not about the payment being electronic.

`receipt_image` is required **only** for `bank_transfer` (PDF, JPG, PNG or WebP, max 5 MB).

These rules are enforced in `PaymentController::store()` via `required_if` validation and in `orders/show.blade.php` via Alpine.js conditional rendering (`needsBank` getter returns `true` for `cash` and `bank_transfer`; `isBankTransfer` getter returns `true` only for `bank_transfer`).

The receipt upload UI uses the same pattern as the refund document upload in `reduce.blade.php`: hidden file input accessed via `x-ref`, `processFile()` detects PDF vs image by extension, image shows a thumbnail + lightbox, PDF shows a red PDF icon. In the Payments History table, `pathinfo($payment->receipt_image, PATHINFO_EXTENSION)` determines whether to render a PDF icon link or an image thumbnail.

### 5.13 Order Cancellation

An order is **never** cancelled manually. Cancellation happens automatically inside `OrderReductionController::store()` when the reduction brings `new_total` to exactly 0 and the order is not yet `dispatched`:

```php
if ($newTotal == 0 && $order->status !== 'dispatched') {
    $order->update(['status' => 'cancelled']);
}
```

- A `cancelled` order does not appear in production flows (not targeted by stitching auto-transition, not available as a reassignment target).
- The `orders.status` enum includes `cancelled` (migration `2026_05_20_000001`).
- There is no standalone "Cancel Order" button — full reduction to zero is the only path.

### 5.15 Order Hard-Delete

An order may be **permanently deleted** (not cancelled) only when **both** conditions hold:
- `status = 'received'` (no production workflow has started)
- `total_paid = 0` (no payment has ever been recorded)

**Who:** admin and accountant roles only. Route: `orders.destroy` (`DELETE /orders/{order}`).

**What gets deleted in a single DB transaction:**
1. The `customer_ledger` row with `transaction_type = 'order_charged'` linked to this order — deleted via raw `DB::table()` to bypass `CustomerLedger`'s boot-level deletion guard.
2. The `orders` row — `order_items` cascade automatically via FK.

**What is preserved:** activity log entries (`activity_log` table) — these are never touched.

**UI:** "Delete Order" button on `orders/show.blade.php`, visible only when the two conditions above are met. Uses the global Alpine `$store.confirm.show()` with `danger: true`.

### 5.16 Payment Deletion

A payment record may be **permanently deleted** by admin or accountant at any time, regardless of order status (including `dispatched` and `partially_dispatched`). The primary use case is correcting accidentally duplicated payments.

**Route:** `orders.payments.destroy` (`DELETE /orders/{order}/payments/{payment}`).

**What happens in a single DB transaction:**
1. Delete the `customer_ledger` row where `reference_type = 'App\Models\Payment'` AND `reference_id = $payment->id` — via raw `DB::table()` to bypass the boot-level deletion guard.
2. Delete the `payments` row.
3. Recalculate `order.total_paid` from a fresh DB sum of remaining payments.
4. Recalculate `order.outstanding_balance = total_amount − new_total_paid`.
5. If `new_total_paid == 0` AND `order.status === 'confirmed'` → revert status to `received`.

**Advance credit (`applyCredit()`) is a separate flow** — it does not create a `payments` row, so it never appears in the Payments list and cannot be deleted via this route. No `advance_credit_balance` adjustment is needed on payment deletion.

**UI:** "Delete" link in each row of the Payments History table on `orders/show.blade.php`, visible to admin and accountant only. Uses `$store.confirm.show()` with `danger: true`.

### 5.17 Payment Overpayment — Auto-Convert Surplus to Advance Credit

When `PaymentController::store()` results in `total_paid > total_amount` (i.e. the customer has overpaid):

- `surplus = total_paid − total_amount`
- Increment `customer.advance_credit_balance` by `$surplus`
- **No ledger entry is created** — the overpayment is already visible in the ledger via the `payment_received` entries exceeding the `order_charged` amount. Adding an `advance_received` entry would cancel out the existing credit and misrepresent the balance.
- The order show page displays an **"Overpaid"** stat card (instead of "Outstanding") showing the surplus in green with "Added to advance credit" below.
- The order show page shows a **green notice banner** above the Record Payment section when the customer has advance credit and the order still has outstanding balance.
- The **"From Advance Credit"** option in the payment type dropdown is only rendered when `customer.advance_credit_balance > 0`. It also shows the available amount inline.

**On payment deletion (`PaymentController::destroy()`):** If the deleted payment contributed to a surplus, `advance_credit_balance` is decremented by the reduction in surplus — floored at the current balance (no negatives). No ledger entry for this reversal either.

### 5.18 Adjust Order — Final Settlement Dispatch Flow

**Purpose:** When the actual pieces dispatched to a customer differ from the original order in size distribution or total quantity (e.g., a customer is last in the queue and receives whatever physically remains in the factory, with the owner's agreement), the admin can adjust the order quantities before dispatch and then log a reduction after dispatch to settle the account.

**Controller:** `OrderAdjustController` — routes `orders.adjust` (GET) / `orders.adjust.store` (POST). Accessible to admin + accountant. Not available when order is `dispatched` or `cancelled`.

**How Adjust Order works:**
- Works exactly like the public customer order form — one uniform set of XS/S/M/L/XL quantities that applies to **every design** in the order simultaneously.
- `unit_price` per design is **never changed** — prices agreed at order time remain fixed.
- On submit: updates `order_items.qty_xs/s/m/l/xl` for all designs. `OrderItem::booted()` auto-recomputes `total_qty` and `total_amount` per item. `orders.total_amount` and `orders.outstanding_balance` are recalculated from the fresh item totals.
- No ledger entry is created by Adjust Order — it is a quantity correction, not a financial transaction. If the total amount decreases, the admin must use Log Reduction to formally record the financial adjustment in the customer ledger.
- Creates an activity log entry recording the new size values and new total amount.

**The full final-settlement flow:**

1. **Adjust Order** — Admin changes sizes to the maximum per size that will actually be dispatched (e.g., original XS:1 S:1 M:3 L:2 XL:1 → adjusted XS:0 S:2 M:2 L:3 XL:1). Total pieces per design may remain the same (pure size redistribution) or change.
2. **Dispatch** — Production manager dispatches the physically available pieces per design per size. The dispatch per-size validation now passes because `order_items` reflects the adjusted sizes.
3. **Log Reduction** — Admin logs a reduction for the pieces NOT dispatched (the shortfall per design per size). Log Reduction now **also decrements `order_items.qty_*`** for each reduced item (see rule below), reducing the ordered total to match what was actually dispatched.
4. **Auto-dispatch transition** — After the `order_items` decrement, if the order is `partially_dispatched` and `isFullyDispatched()` now returns `true` (total ordered after reduction = total dispatched), the order status auto-transitions to `dispatched`.

**Key constraint:** `isFullyDispatched()` in `Order::isFullyDispatched()` compares `$this->items->sum('total_qty')` against `SUM(dispatch_batch_items.quantity)`. For the auto-transition to work, the Log Reduction must bring `order_items.total_qty` totals in line with actual dispatch quantities.

### 5.14 Piece Reassignment

**Purpose:** When pieces originally allocated to one customer's order need to be given to another customer in the same catalogue (e.g. a cancelled or reduced order frees up inventory), the admin can reassign piece quantities without creating a new order.

**Controller:** `OrderPieceReassignmentController` — admin-only route `orders.reassign.create` / `orders.reassign.store`.

**Rules:**
- Source and target orders must belong to the **same catalogue**.
- Target order must not be `dispatched` or `cancelled`.
- The form shows the source order's items (design + size) and lets the admin specify how many pieces of each to move.
- **Only the target order is modified** — the target's `order_items.qty_{size}` columns are incremented, `total_amount` and `outstanding_balance` increase by `unit_price × qty` for each item added.
- A `order_charged` ledger entry is created for the **target customer** reflecting the added amount.
- The **source order is not automatically modified** — if a corresponding reduction is needed on the source, it must be logged separately via Log Reduction.

---

## 6. Production Flow (In-House)

```
Fabric Batch arrives (FabricBatch)
    ↓ Auto-transitions all confirmed orders → stitching
Production Assignment (ProductionAssignment) — New Assignment form
    ↓ Production Manager picks: Catalogue → Destination (Naeem Pakki | Stitching Unit)
    │
    ├─ [Naeem Pakki destination]
    │   Multi-design table: only designs with needs_naeem_pakki=true shown
    │   One ProductionAssignment per design (size='np' item, no size breakdown)
    │   Tracks: available qty guard, per-piece rate on each assignment
    │   ↓
    │   NaeemPakkiSend (physical sending, piece count, per-piece rate)
    │   NaeemPakkiReturn (piece count only)
    │   ↓
    │   [After embroidery returns] → back to Stitching Unit flow below
    │
    └─ [Stitching Unit destination]
        Single design + unit (selected from active per-piece units in stitching_units table) + qty by size (XS/S/M/L/XL)
        ↓
StitchingReturn (daily, by design + size)
    ↓ Size-level reconciliation flagged if mismatch
TarpaiSend → TarpaiReturn (every kameez of every in-house design goes through Tarpai — no exceptions)
    ↓
PressSend → PressReturn (= Packed Inventory)
    Manager sends pieces to the press unit (capped by Tarpai returns - already press sent).
    Returns are always against a specific PressSend. Partial returns across multiple trips allowed.
    Pieces returned from press are already packed — PressReturn IS the packed inventory entry.
    ↓
[Outsourced designs arrive separately as OutsourcedBatch → also enters Packed Inventory]
    ↓
Dispatch (batch-wise, full payment required, deducts packed inventory)
    → Order status = dispatched only when fully dispatched
```

### Naeem Pakki — key rules

- `needs_naeem_pakki` is set on the **Design** at catalogue creation time, not at assignment time.
- Naeem Pakki sends and returns are **piece-based only** — no size breakdown. Sizes are irrelevant until stitching.
- `naeem_pakki_sends` links directly to `catalogue_id` + `design_id` (NOT to `production_assignments`).
- `naeem_pakki_returns` has **no `quantity` column** — totals are computed from `naeem_pakki_return_items`. Each return batch has a header row (`naeem_pakki_returns`) and one `naeem_pakki_return_items` row per design returned (`np_design_id` + `quantity`).
- Per-piece rate (`per_piece_price`) is recorded on each send record. Different sends for the same design can have different rates.
- **Production Assignments for NP:** Production Manager uses the New Assignment form, selects Naeem Pakki as destination, and sees a table of all NP-eligible designs. Can assign multiple designs at once, each with qty + rate. One `ProductionAssignment` record is created per design. Quantity is stored as a single `production_assignment_items` row with `size = 'np'`.
- **Available qty guard:** The available qty shown in the NP assignment table = fabric received (from `fabric_batch_items`) minus already assigned (from `production_assignment_items`). The form prevents submitting if any qty exceeds available.
- `NaeemPakkiSend` and `NaeemPakkiReturn` models do **NOT** use `LogsActivity` trait — only `Order` and `Catalogue` do.

### Press — key rules

- **Every kameez** of every in-house design goes through Tarpai before press. No design skips Tarpai.
- **Available qty guard for PressSend:** `tarpai_return_items` total (for catalogue+design+size) minus `press_send_items` total already sent. The form prevents submitting more than available.
- **PressReturn always references a specific PressSend.** Partial returns are allowed — one send can have multiple return trips.
- **PressReturn = Packed Inventory.** There is no separate "log as packed" step. When the manager records a press return, those pieces are immediately available for dispatch.
- **Packed Inventory** is computed from `press_return_items` (by catalogue+design+size). Dispatch must deduct from `press_return_items`.
- Tables: `press_sends` (header) + `press_send_items` (design+size+qty) + `press_returns` (header, FK to press_send) + `press_return_items` (design+size+qty).
- `PressSend` and `PressReturn` use `LogsActivity`. `PressSendItem` and `PressReturnItem` do not.

### Tarpai — house options and gate pass rule

`tarpai_sends.tarpai_house` has three valid values:

| Value          | Label        | Gate Pass | Badge colour |
| -------------- | ------------ | --------- | ------------ |
| `rashid_bhai`  | Rashid Bhai  | Yes       | Purple       |
| `yousaf_bhai`  | Yousaf Bhai  | Yes       | Indigo       |
| `in_house`     | In-House     | **No**    | Emerald      |

**In-House sends never generate a gate pass.** The "Print Gate Pass" button on the Tarpai Send show page and the "Gate Pass" link in the Tarpai index table are both hidden when `tarpai_house = 'in_house'`. Do not render or link to the gate-pass route for in_house rows.

### Tarpai pricing

Same as above — per-piece rate is per design, stored on `TarpaiSendItem`.

### Stitching reconciliation

After all stitching returns: each design's returned quantities by size must exactly match
what was assigned. Any size-level discrepancy is flagged. The system does not prevent
returns that cause discrepancies — it flags them for review.

---

## 7. Financial Logic Summary

**Sign convention:** `SUM(amount)` for a customer = their ledger balance. `balance > 0` means the customer owes Casualite (Debit/red). `balance < 0` means the customer has credit (Credit/green).

| Event                                    | Ledger type        | `amount` sign | Effect on `advance_credit_balance` |
| ---------------------------------------- | ------------------ | ------------- | ---------------------------------- |
| Customer pays advance                    | `advance_received` | positive      | increase                           |
| Order placed                             | `order_charged`    | **positive**  | none                               |
| Payment received on order                | `payment_received` | **negative**  | none                               |
| Advance credit applied to order          | `credit_applied`   | **negative**  | decrease                           |
| Order reduced (any case)                 | `order_reduced`    | **negative**  | none (unless surplus_action=credit_to_advance → increase by surplus) |
| Refund issued on reduction surplus       | `refund_issued`    | **positive**  | none (surplus already returned to customer as cash) |
| Payment causes overpayment (total_paid > total_amount) | *(no ledger entry)* | — | increase by surplus |

**Why `order_charged` is positive:** it increases the customer's balance — they now owe more.
**Why `payment_received` is negative:** it decreases the balance — they owe less.
**Why `order_reduced` is negative:** it decreases the balance — the charge is partially reversed.
**Why `refund_issued` is positive:** after a reduction that created a credit (negative balance), the refund pays out that credit as cash, bringing the balance back toward zero.

---

## 8. Key Route Names

| Route name       | URL pattern                   | Purpose                        |
| ---------------- | ----------------------------- | ------------------------------ |
| `order.public`   | `GET /order/{token}`          | Public catalogue order form    |
| `order.submit`   | `POST /order/{token}`         | Order form submission          |
| `order.thankyou` | `GET /order/{token}/thankyou` | Thank-you page                 |
| `portal.show`    | `GET /portal/{token}`         | Customer portal (email entry)  |
| `portal.verify`  | `POST /portal/{token}/verify` | Portal email verification      |
| `dispatch.store`         | `POST /dispatch/{order}`                        | Record a dispatch batch              |
| `press-sends.index`      | `GET /press-sends`                              | Press sends list                     |
| `press-sends.create`     | `GET /press-sends/create`                       | Log a press send                     |
| `press-sends.store`      | `POST /press-sends`                             | Save a press send                    |
| `press-sends.show`       | `GET /press-sends/{pressSend}`                  | Press send detail + return form      |
| `press.return`           | `POST /press-sends/{pressSend}/return`          | Log a press return                   |
| `orders.reduce`          | `GET /orders/{order}/reduce`                    | Log Reduction form (admin + accountant)      |
| `orders.reduce.store`    | `POST /orders/{order}/reduce`                   | Save reduction (admin + accountant)          |
| `orders.reductions.show` | `GET /orders/{order}/reductions/{reduction}`    | Reduction detail page (admin + accountant)   |
| `orders.adjust`          | `GET /orders/{order}/adjust`                    | Adjust Order form (admin + accountant)       |
| `orders.adjust.store`    | `POST /orders/{order}/adjust`                   | Save adjusted order (admin + accountant)     |
| `orders.reassign.create`    | `GET /orders/{order}/reassign-pieces`              | Reassign Pieces form (admin only)         |
| `orders.reassign.store`     | `POST /orders/{order}/reassign-pieces`             | Save reassignment (admin only)            |
| `orders.destroy`            | `DELETE /orders/{order}`                           | Hard-delete order (admin + accountant)    |
| `orders.payments.destroy`   | `DELETE /orders/{order}/payments/{payment}`        | Delete a payment (admin + accountant)     |
| `og.image`                  | `GET /og-image/{token}`                            | Proxy catalogue OG image through app domain (public, no auth) |

**Never use `order.show` — it does not exist. The correct route name is `order.public`.**

---

## 9. Implementation Status

### Completed

- All database migrations and models
- Spatie Permission and Activitylog setup
- Auth (login/logout, role-based middleware, active check)
- Catalogue management (create, view, close/reopen, shareable link)
- Design management (CRUD, photo upload) — shows In-House / Outsourced badge + Naeem Pakki amber badge per design card
- Customer management (create, edit, view, portal token auto-generation)
- Customer portal (email verification, 3 tabs) — order status tab shows **size-wise quantity breakdown** per order
- Public order form (sold-out screen, real-time totals with discount price logic, customer email matching UI, **duplicate order alert modal**)
- **Discount pricing** — catalogues have `quantity_benchmark`; designs have `selling_price` + optional `discount_price`; the order form applies the correct price tier live and on submission
- **Sequential order numbers** (2026-06-10) — `orders.order_number` is a sequential number starting from 1005335, generated via the `order_number_sequence` table using `lockForUpdate` for atomicity. `Order::boot()` reads `last_number`, increments it, saves it back, and assigns the result — all in a single DB transaction. Existing orders retain their original random numbers (100000–999999). A cancelled order keeps its number (the record stays in DB). A hard-deleted order's number is never reused because the counter only moves forward. Migration: `2026_06_10_000001`
- Orders view and management — Order Status card shown to all roles; only admin/production_manager can change status
- Payment recording — receipt upload and bank account selection are conditional on payment method (bank transfer requires both; cash and advance require neither)
- **Bank Accounts** — `bank_accounts` table, admin-only management page, seeded with 8 accounts (Saleem, Ehsan SB, Farhan, Meezan, HBL, Adnan, Osama, Akram); `payments.bank_account_id` FK added; bank account title shown in payment history
- Apply advance credit to orders
- Customer ledger view
- **Order Reduction — fully implemented** (2026-05-20/21, branch `log-reduction-and-order-cancellation-work`):
  - Admin-only form (`orders.reduce`): select adjustment type, items reduced (design + size + qty), notes, and surplus action
  - Three-case logic: no surplus (Cases 1 & 2) updates totals + `order_reduced` ledger; surplus (Case 3) applies `surplus_action`
  - `surplus_action = credit_to_advance`: increments `customer.advance_credit_balance` by surplus, no extra ledger entry
  - `surplus_action = refund`: creates `Refund` record with method (cash/bank_transfer), optional `refund_reference` (free-text), optional `refund_document` (S3 upload — image or PDF); creates `refund_issued` ledger entry
  - Reduction detail page (`orders.reductions.show`) — also accessible inline as a modal from the customer ledger "View" link
  - `OrderReduction` model uses `LogsActivity`
- **Order Cancellation** (auto-only, 2026-05-20): when a reduction brings `new_total` to 0 and order is not `dispatched`, status is set to `cancelled` automatically inside `OrderReductionController::store()`
- **Piece Reassignment** (2026-05-20, admin only): `OrderPieceReassignmentController` — moves qty from a source order to a target order in the same catalogue; increments target `order_items.qty_{size}`, increases target `total_amount` and `outstanding_balance`, creates `order_charged` ledger entry (positive amount) for the target customer
- **Customer ledger `order_charged` data fix** (2026-06-04): migration `2026_06_04_000001` corrects two historical data bugs: (1) flips all negative `order_charged` amounts to positive (wrong sign from old controller code and the reassignment bug); (2) inserts missing `order_charged` entries for orders that were placed before the ledger entry was wired up in `PublicOrderController`
- Fabric batch arrivals — validation allows qty=0 per item (zeros filtered out); index shows per-catalogue / per-design received breakdown cards; show page has formula callout without stat card clutter
- **Stitching Units** — `stitching_units` table introduced; units are no longer hardcoded integers. `production_assignments.stitching_unit_id` and `stitching_returns.stitching_unit_id` are proper foreign keys. Each per-piece unit holds its own `per_piece_rate`.
- **Production assignments** — redesigned form (2026-05-02):
  - Flow: Catalogue → Destination radio cards (Naeem Pakki | Stitching Unit) → conditional section
  - Naeem Pakki: multi-design table showing only `needs_naeem_pakki=true` designs; qty + rate per design; one assignment per design; size=`np` item
  - Stitching: single design selector + active per-piece unit from `stitching_units` + per-size qty
  - Controller split into `storeNaeemPakki()` and `storeStitchingUnit()` private methods
  - Index page: Destination and Stitching Unit columns use consistent pill badges (amber for NP, purple for stitching unit); **mobile-responsive** — card layout on small screens, table on md+
- Naeem Pakki sends and returns — sidebar nav link added; `LogsActivity` removed from both models
- Stitching returns (size-level reconciliation)
- Tarpai sends and returns
- **Press sends and returns** — complete rework: `press_sends` + `press_send_items` + `press_returns` + `press_return_items` tables; available qty guard sources from Tarpai returns; returns reference a specific send and are the packed inventory entry; old `press_pack_records` table removed
- Packed inventory tracker (sourced from `press_return_items`)
- Outsourced batch arrivals
- Dispatch management (create batches)
- **Auto-confirm on payment** — `PaymentController::store()` and `PaymentController::applyCredit()` both auto-transition order status from `received` → `confirmed` when the first payment or credit is applied. Manual Confirm button on the order page remains for zero-payment confirmations.
- **`partially_dispatched` order status** — added 2026-05-19; migration `2026_05_19_000001`; `DispatchController::store()` sets `partially_dispatched` on partial dispatch and `dispatched` only when `isFullyDispatched()` returns true; "Dispatch Again" button hidden on dispatch show page when status is `dispatched`; status badge (purple) added to all views: orders index, orders show, dispatch show, customer portal, customer-orders report, production-status report
- **Orders page catalogue filter** — removed standalone catalogue dropdown; page now reads `session('active_catalogue_id')` directly (same pattern as all production/report controllers); catalogue is always driven by the sidebar selector
- **Worker wages — fully automated** (2026-05-19): `wages:calculate-weekly` Artisan command sums kameez returned per catalogue per per-piece stitching unit for the Saturday→Friday window; scheduled every Friday at 23:45 via `routes/console.php`; wages index has week/unit/status filters and a Recalculate panel for backdated returns; wages show page has per-design kameez breakdown table and displays confirmed-by name + timestamp; manual wage entry form has been removed entirely; unique constraint is `(catalogue_id, stitching_unit_id, week_start)`
- All 12 reports — payroll history report shows stitching unit per wage record
- User management (create, enable, disable, password reset — admin only)
- **Order hard-delete** (2026-05-22): `OrderController::destroy()` — permanently removes a `received` + `total_paid=0` order; deletes the `order_charged` ledger entry via raw `DB::table()` (bypasses model boot guard), then deletes the order (items cascade); activity log preserved; admin + accountant only; Alpine danger-modal confirmation
- **Payment deletion** (2026-05-22): `PaymentController::destroy()` — deletes any payment regardless of order status; removes the linked `payment_received` ledger entry via raw `DB::table()`; recalculates `total_paid` and `outstanding_balance`; reverts order status `confirmed` → `received` if `total_paid` drops to 0; admin + accountant only; Alpine danger-modal confirmation
- **PDF receipts for bank transfer payments** (2026-05-22): `PaymentController::store()` now accepts PDF in addition to JPG/PNG/WebP (validation: `mimes:pdf,jpeg,jpg,png,webp`); upload UI rebuilt to match the refund document upload pattern — hidden file input + `processFile()` Alpine method; PDF shows icon, image shows thumbnail + lightbox; Payments History table renders PDF icon or image thumbnail based on file extension
- **Orders search fix** (2026-06-05): `OrderController::index()` search now also queries `customers.name` via `whereHas` — previously only `submitted_name` was searched, causing mismatches when the displayed name came from the linked customer record
- **Overpayment surplus → advance credit** (2026-06-05): `PaymentController::store()` detects when `total_paid > total_amount`, increments `customer.advance_credit_balance` by the surplus, and shows an "Overpaid" stat card on the order show page. No ledger entry is created for the surplus — it is already reflected via the payment_received entries. Payment deletion reverses the surplus from `advance_credit_balance` if applicable. The "From Advance Credit" dropdown option is now only shown when `customer.advance_credit_balance > 0` (with available amount shown inline). A green advance credit notice banner is shown on the order page when the customer has credit and the order has an outstanding balance. Data fix migration `2026_06_05_000001` applied PKR 2,665 surplus to Saad Bhai Wijdan's `advance_credit_balance`.
- **Tarpai Charges Calculation — fully automated** (2026-06-05): `tarpai:calculate-weekly` Artisan command sums pieces sent × per-piece rate across all `TarpaiSend` records for `rashid_bhai` and `yousaf_bhai` (never `in_house`) within the Saturday→Friday window. Creates/overwrites **unconfirmed** `TarpaiPayment` records; confirmed records are never overwritten. Unique constraint: `(catalogue_id, tarpai_house, week_start)`. Scheduled every Friday at 23:50 via `routes/console.php`. Accessible to admin, production_manager, and accountant. Index has week/house/status filters and a Recalculate panel for backdated sends. Show page (`tarpai-charges.show`) displays a per-send breakdown table (Send ID, date, pieces, rate, amount) and a formula callout. Confirm Payment button on show page sets `is_confirmed`, `confirmed_by`, `confirmed_at`. Both the command and `CalculateWeeklyWages` accept `--triggered-by` option and write structured `CronLog` entries on every run (success, failure, or no-data). `TarpaiPayment` model uses `LogsActivity`. Placed in the Analytics sidebar section (visible to admin, production_manager, and accountant).
- **Cron Logs** (2026-06-05): Admin-only page (`cron-logs.index`) showing execution history for all scheduled and manually triggered jobs. Uses `cron_logs` DB table (not flat log files). Columns: `job_name`, `job_label`, `triggered_by`, `week_start`, `week_end`, `records_created`, `records_updated`, `records_skipped`, `status` enum(`success|failed`), `output`, `ran_at`. Both `wages:calculate-weekly` and `tarpai:calculate-weekly` write a `CronLog` entry on every invocation (success, failure, and no-data early-return paths). Manual recalculate passes `--triggered-by=Manual — {user name}`. Filters: job, triggered-by (Scheduler / Manual — matched via `LIKE 'Manual%'`), status. Table rows are expandable — clicking a row reveals the output message; implemented via `<tbody x-data="{ open: false }">` per row pair (multiple `<tbody>` elements are valid HTML and correctly scope Alpine's `open` variable to both the main row and the output row). Placed in the System sidebar section (admin only).
- **Assigned Bank Account on orders** (2026-06-06): `orders.assigned_bank_account_id` nullable FK to `bank_accounts` added via migration `2026_06_06_000001`. Represents the designated collection bank for each order ("Title Given" in reports). `OrderBankAssignmentController` handles two routes: `orders.assign-bank` (per-order dropdown on orders show, admin + accountant) and `orders.bulk-assign-bank` (bulk checkbox assignment on orders index, admin + accountant, scoped to active catalogue session). The assigned bank drives the per-bank groupings in the Bank Collection Report.
- **Bank Collection Report — per-order format** (2026-06-06/07): Redesigned from a 3-row summary into a full per-order breakdown matching the accountant's working Excel. `BankCollectionReportController::loadData()` now queries per-order data: customer name/city, size quantities (XS/S/M/L/XL from first `order_item` — all designs share the same qty), total qty per design, over-all total qty (sum across all designs), effective rate (`total_amount ÷ over_all_qty`), total bill, amount received (`total_paid`), amount receivable (`outstanding_balance`), assigned bank title, per-bank payment breakdown (bank transfer payments per `bank_account_id`), and misc (= `total_paid − sum(bank transfer payments)`, covering cash + advance credits). Footer has **three rows**: (1) **Total** — sums of all quantity and amount columns; (2) **Total Payment** (blue) — per-bank expected/total-bill amounts (`$expected[$bank->id]`); (3) **Receivable** (yellow) — per-bank outstanding amounts (`$receivable[$bank->id]`). All monetary values use `lacs_format()` throughout (web blade, PDF blade, and Excel export). Excel uses pre-formatted `lacs_format()` strings (no numeric format codes) for consistent South Asian number grouping. PDF uses 6.5px font on A4 landscape to fit all columns.
- **OG image proxy for WhatsApp broadcast previews** (2026-06-18): WhatsApp broadcast lists require the `og:image` URL to be on the same domain as the page — direct S3 URLs (`amazonaws.com`) cause broadcasts to show only a small thumbnail instead of the full rich preview. Fix: `OgImageController::show()` fetches the OG image from S3 and streams it through `casualiteos.com/og-image/{token}` with `Cache-Control: public, max-age=86400`. The `og:image` meta tag in `order.blade.php` now uses `route('og.image', $catalogue->order_token)` instead of `Storage::url()`. The image generation logic (`generateOgImage()` in `CatalogueController`) and S3 storage (`catalogues/og/{uuid}.jpg`) are unchanged — only the delivery URL changed. **Do not revert `og:image` back to a direct S3 URL** — that breaks broadcast previews.
- **`creative_head` role expansion + `production_manager` catalogue access** (2026-06-10, branch `start-order-numbers-in-sequence`): `creative_head` now has full catalogue write access (create/edit/open/close, no delete), orders read-only access with financials hidden, and read-only access to all production screens. `production_manager` similarly gained catalogue management access (create/edit/open/close). Implementation: `CatalogueController::adminOrProductionManager()` extended to include `creative_head`; `$this->denyCreativeHead()` guard added to all mutating methods across 10 production controllers (`FabricBatchController`, `ProductionAssignmentController`, `NaeemPakkiController`, `StitchingReturnController`, `TarpaiController`, `PressController`, `OutsourcedBatchController`, `DispatchController`, `WagesController`, `TarpaiPaymentController`); `$hideFinancials` flag in `OrderController` extended to cover `creative_head`; route middleware groups in `routes/web.php` updated; sidebar nav and all production index/show views updated to hide write actions for `creative_head`.
- **Adjust Order feature** (2026-06-24): `OrderAdjustController` (`orders.adjust` GET + `orders.adjust.store` POST, admin + accountant). Allows admin to re-enter uniform XS/S/M/L/XL quantities — identical UX to the public customer order form — that apply to every design in the order. Use case: "final settlement dispatch" where a customer (typically the last to be dispatched) agrees to receive fewer or differently-sized pieces than originally ordered. On submit: all `order_items` rows updated with new uniform sizes; `OrderItem::booted()` auto-recomputes `total_qty` + `total_amount`; `orders.total_amount` and `orders.outstanding_balance` recalculated; activity log entry written. `unit_price` per design is never changed. Not available when status is `dispatched` or `cancelled`. Button appears on the order show page next to Log Reduction (admin + accountant only, same status guard). No new DB migrations — no schema changes required.
- **Log Reduction now updates `order_items` + auto-transitions dispatch status** (2026-06-24): `OrderReductionController::store()` gained two additions inside its DB transaction, placed after the auto-cancel check: **(1)** For each reduction item, the corresponding `order_items.qty_{size}` column is decremented by `qty_reduced` (floored at 0). `OrderItem::save()` triggers `booted()` which recomputes `total_qty` and `total_amount` automatically. **(2)** If `$order->status === 'partially_dispatched'`, the `items` relation is reloaded fresh (`unsetRelation` then `load`) and `$order->isFullyDispatched()` is called. If it returns `true` (meaning total ordered after reduction now equals or is less than total dispatched), the order status is set to `dispatched`. This makes the full final-settlement flow work end-to-end without any manual status override. See rule 5.18 for the complete flow.
- **Audit log pruning — automated** (2026-06-19): `audit-log:prune` Artisan command deletes all `activity_log` entries older than **45 days**. Scheduled every first Sunday of the month at 00:00 via cron expression `0 0 1-7 * 0`. Writes a `CronLog` entry (`job_label = 'Audit Log Pruning'`, red dot) on every run — success or failure. Visible in the Cron Logs screen (admin only). Triggered by Scheduler only — no manual recalculate panel. No migrations required.
- **Backup file pruning — automated** (2026-06-19): `backups:prune` Artisan command deletes all `.sql` backup files in the S3 `backups/` folder that are older than **30 days**. There is no separate database table for backup metadata — the Database Backup screen lists files directly from S3, so deleting a file from S3 removes it from that screen immediately. Scheduled every first Sunday of the month at 00:05 (5 minutes after `audit-log:prune`) via cron expression `5 0 1-7 * 0`. Writes a `CronLog` entry (`job_label = 'Backup Pruning'`, orange dot) on every run — success or failure. Visible in the Cron Logs screen (admin only). Triggered by Scheduler only — no manual recalculate panel. No migrations required.

### Known Bugs / Incomplete Features (must fix)

1. **`order.show` route name used in controller** — ✅ Fixed (2026-06-23): `PublicOrderController::submit()` now redirects to `order.public` instead of the non-existent `order.show`
2. **Dispatch payment check missing** — `DispatchController::store()` has no outstanding balance guard
4. **Cargo document is text, not file** — must be a file upload stored on disk
5. **Packed inventory not deducted after dispatch** — `DispatchController::store()` must decrement `press_return_items` quantities (old `press_pack_records` table has been removed)
6. **Order status auto-transition to stitching** — ✅ Fixed: `FabricBatchController::store()` auto-transitions confirmed orders on fabric batch creation
7. **Order reduction surplus logic** — ✅ Fixed (2026-05-20): full three-case logic implemented in `OrderReductionController`
8. **`running_advance_balance` hardcoded to 0** in all ledger entries — must be actual customer balance (partially fixed 2026-06-05: `PaymentController::store()` now reads actual balance for `payment_received` entries)
9. **Dispatch order status** — ✅ Fixed (2026-05-19): `partially_dispatched` status added; `DispatchController::store()` now sets `partially_dispatched` on partial dispatch and `dispatched` only when `isFullyDispatched()` returns true
10. **Creative Head role expansion** — ✅ Fixed (2026-06-10): `creative_head` now has catalogue management write access, orders read-only (no financials), and production screens read-only. See Completed entry for full detail.
11. **`OrderPieceReassignmentController` creates `order_charged` with wrong sign** — ✅ Fixed (2026-06-04): changed `amount => -$totalAdded` to `amount => $totalAdded` in `OrderPieceReassignmentController::store()`; historical wrong-sign entries corrected by migration `2026_06_04_000001`.

### All Migrations (run `php artisan migrate` after pulling)

All migrations have been run. No pending migrations. For reference, the full set introduced across branches:

- `2026_05_02_000001` — adds `'np'` to `production_assignment_items.size` enum
- `2026_05_06_000001` — adds `discount_price` to `designs` and `quantity_benchmark` to `catalogues`
- `2026_05_06_000002` — creates `stitching_units` table and seeds Units 1–4
- `2026_05_06_000003` — migrates `stitching_unit` integer columns to FK on `production_assignments` and `stitching_returns`
- `2026_05_06_000004` — adds `per_piece_rate` to `stitching_units`
- `2026_05_06_000005` — adds `stitching_unit_id` FK to `wages`; drops `wage_rate` from `catalogues`
- `2026_05_11_000001` — creates `bank_accounts` table
- `2026_05_11_000002` — adds `bank_account_id` nullable FK to `payments`
- `2026_05_11_112300` — drops orphaned `quantity` column from `naeem_pakki_returns` (totals now computed from `naeem_pakki_return_items`)
- `2026_05_11_113000` — adds `tarpai_house` enum and drops `design_id` from `tarpai_sends` (finishing the partial refactor that `2026_05_09_000002` assumed had already run)
- `2026_05_11_120000` — drops `press_pack_records` + `press_pack_record_items`; creates `press_sends`, `press_send_items`, `press_returns`, `press_return_items`
- `2026_05_11_200000` — adds `in_house` to `tarpai_sends.tarpai_house` enum (valid values: `rashid_bhai`, `yousaf_bhai`, `in_house`)
- `2026_05_18_110503` — renames `users.role` enum values: `manager` → `production_manager`, `designer` → `creative_head`; updates Spatie `roles` table records accordingly
- `2026_05_19_000001` — adds `partially_dispatched` to `orders.status` enum (value sits between `stitching` and `dispatched`); applied to production via raw SQL on 2026-05-19
- `2026_05_19_100000` — fixes `wages` unique constraint from `(catalogue_id, week_start)` to `(catalogue_id, stitching_unit_id, week_start)`
- `2026_05_20_000001` — adds `cancelled` to `orders.status` enum
- `2026_05_20_000002` — adds `refund_issued` to `customer_ledger.transaction_type` enum
- `2026_05_20_000003` — creates `refunds` table (`order_id`, `order_reduction_id`, `customer_id`, `amount`, `refund_method`, `refund_date`, `notes`, `refunded_by`)
- `2026_05_20_000004` — adds `surplus_action` enum (`none|credit_to_advance|refund`) to `order_reductions`; corrects `adjustment_type` enum to (`damage|short_supply|price_correction|other`)
- `2026_05_21_000001` — drops `bank_account_id` FK from `refunds`; adds `refund_reference` (nullable string) and `refund_document` (nullable string for S3 path)
- `2026_06_01_000001` — adds `original_quantity` to `outsourced_batch_items`
- `2026_06_01_000002` — adds `original_quantity` to `press_return_items`
- `2026_06_04_000001` — data fix: flips negative `order_charged` ledger entries to positive; inserts missing `order_charged` entries for orders that had none
- `2026_06_05_144325` — data fix: adds PKR 2,665 overpayment surplus to Saad Bhai Wijdan's `advance_credit_balance` for Order #524308 (no ledger entry — surplus already in ledger via payment_received entries)
- `2026_06_05_200000` — creates `tarpai_payments` table (`catalogue_id` FK, `tarpai_house` enum(`rashid_bhai`,`yousaf_bhai`), `week_start`, `week_end`, `total_pieces_sent`, `total_amount` decimal, `is_confirmed`, `confirmed_by` nullable FK to users, `confirmed_at`); unique constraint `(catalogue_id, tarpai_house, week_start)`
- `2026_06_05_210000` — creates `cron_logs` table (`job_name`, `job_label`, `triggered_by`, `week_start` nullable, `week_end` nullable, `records_created`, `records_updated`, `records_skipped`, `status` enum(`success`,`failed`), `output` text nullable, `ran_at` timestamp)
- `2026_06_06_000001` — adds `assigned_bank_account_id` nullable FK to `orders` (references `bank_accounts`, nullOnDelete)
- `2026_06_10_000001` — creates `order_number_sequence` table (single row, `last_number` seeded at 1005334); new orders increment this counter atomically instead of using `random_int`

---

## 10. Coding Conventions

### Always eager-load designs when passing catalogues to Alpine.js

```php
$catalogues = Catalogue::where('status', 'open')->with('designs')->get();
```

Without `->with('designs')`, `Js::from($catalogues)` produces `undefined` for
`cat.designs` in Alpine and causes a crash.

### Blade views

- Admin panel views: `resources/views/` — extend `layouts.app`
- Public pages (order form, portal): standalone HTML with CDN scripts, no layout extension
- Use `Storage::url($path)` for all uploaded file URLs

### File uploads — S3 only

**All file uploads go to S3.** There is no local public disk storage. `FILESYSTEM_DISK=s3` is the default. Always use `Storage::url($path)` (not `Storage::disk('public')->url()`).

| File type          | S3 folder           | Store call                                          |
| ------------------ | ------------------- | --------------------------------------------------- |
| Payment receipts   | `receipts/`         | `$file->store('receipts', 's3')`                    |
| Design photos      | `designs/`          | `$file->store('designs', 's3')`                     |
| Catalogue covers   | `catalogues/`       | `$file->store('catalogues', 's3')`                  |
| Cargo documents    | `cargo-documents/`  | `$file->store('cargo-documents', 's3')`             |
| Refund documents   | `refund-documents/` | `$file->store('refund-documents', 's3')`            |

### Ledger entries — always use exact enum values

See Section 4. Never invent new transaction types. The migration is the source of truth.

### DB transactions

Wrap any operation that touches multiple tables in `DB::transaction(fn() => ...)`.
This includes: order submission, payment recording, order reduction, dispatch.

### Activitylog

Order and Catalogue models use `LogsActivity`. Changes to flagged fields are
automatically logged. Do not add manual activity log calls for these models.

### Confirmation dialogs — always use the global Alpine modal

Never use `onclick="return confirm(...)"`. The layout has a global Alpine.js store-based confirmation modal. Use it like this:

```html
{{-- Hidden form --}}
<form id="form-unique-id" method="POST" action="...">@csrf</form>

{{-- Trigger button --}}
<button type="button"
        @click="$store.confirm.show({
            title: 'Action Title',
            message: 'Descriptive message about what will happen.',
            formId: 'form-unique-id',
            confirmText: 'Confirm',
            danger: true   {{-- red variant for destructive actions, omit for blue --}}
        })">
    Action Label
</button>
```

The modal submits the form on confirm, does nothing on cancel. `danger: true` shows a red warning icon and red confirm button. Omitting `danger` (or `false`) shows a blue icon and blue button.

### Deleting records — narrow exceptions only

The general rule is **never delete** — user accounts are disabled, orders are reduced, the audit trail must stay intact.

**Two explicit exceptions exist:**

1. **Orders** — `OrderController::destroy()` hard-deletes a `received` + `total_paid=0` order (see rule 5.15). These have no financial footprint to preserve.
2. **Payments** — `PaymentController::destroy()` hard-deletes any payment (see rule 5.16). Used to correct duplicate entries.

Do not add further `destroy()` routes without an explicit business justification.

### Bypassing the CustomerLedger boot-level deletion guard

`CustomerLedger` has `static::deleting(fn() => false)` in its `boot()` method to prevent accidental deletion via Eloquent. When a legitimate hard-delete requires removing a ledger entry (order delete, payment delete), bypass the guard using raw DB:

```php
DB::table('customer_ledger')
    ->where('reference_type', 'App\Models\Payment')
    ->where('reference_id', $payment->id)
    ->delete();
```

Never remove the boot guard from the model itself.

## Brand & Design System

- **Color Scheme:** Light
- **Primary / Accent:** #0071E3
- **Background:** #FFFFFF
- **Text Primary:** #1D1D1F
- **Link Color:** #0066CC
- **Border Radius (global):** 6px
- **Base Spacing Unit:** 4px

### Typography

- Heading font: SF Pro Display → fallback: Helvetica Neue, Arial, sans-serif
- Body font: SF Pro Text → fallback: Helvetica Neue, Arial, sans-serif
- H1: 34px | Body: 28px

### Components

- **Primary Button:** Background #0071E3, white text, fully pill-shaped (border-radius 980px)
- **Secondary Button:** Background #F5F5F7, text/border #0066CC, pill-shaped
- **Input:** Transparent background, text #333336, no border, no shadow

### Personality

- Tone: Modern | Energy: High | Audience: Tech-savvy consumers
