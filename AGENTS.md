# CasualiteOS — Codex Project Context

This file gives Codex the full context needed to work on CasualiteOS correctly.
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
- **Packages:** Spatie Laravel Permission v7, Spatie Laravel Activitylog v5, laravel-notification-channels/webpush v11 (customer portal push notifications)
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

**Implementation note (2026-08-10):** the pricing math and write logic behind steps 3–6 no longer live inline in `PublicOrderController::submit()` — they were extracted into `App\Services\OrderPlacementService` (`quote()` for pricing, `place()` for the actual writes), with business failures raised as `App\Exceptions\OrderPlacementException`. This was done ahead of the mobile app's `POST /api/orders` endpoint (see this project's mobile-app branch), so both the web form and the app compute identical totals from one place instead of two copies drifting apart. `PublicOrderController::submit()` is now a thin adapter: validate → call the service → map exceptions to redirects. See "Order placement service" under Section 10 for the convention this establishes, and rule 5.32 for the strict `>` benchmark comparison now pinned as `OrderPlacementService::BENCHMARK_IS_EXCLUSIVE`. Every other place in this file that references `PublicOrderController::submit()` doing pricing/ledger/order-creation work is still accurate in effect — it does that work, just by delegating to the service — but the actual code to read or change is in `OrderPlacementService`, not the controller.

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

**Implementation note (2026-08-11):** both conditions are checked via `Catalogue::isSoldOut()`
(`isClosed() || availablePieces() <= 0`), used by `OrderPlacementService::assertCatalogueOpen()`
(the guard shared by `PublicOrderController::submit()` and the mobile app's `POST /api/orders`)
and by `PublicOrderController::show()`'s `$soldOut` flag. Until this date the code only checked
`status`, not `availablePieces()`, so a catalogue that sold out without being manually closed
still rendered the order form and would have accepted a submission — discovered and fixed while
building the mobile app's catalogue-browsing endpoints (see "Mobile API — Catalogue Browsing &
Order Placement" under Section 9).

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
| **production_manager**  | `production_manager` | Catalogue management (create/edit/open/close), all production tracking, dispatch, wages, packed inventory, orders (read-only, no financials), customers (view + edit, no create/delete, no financials) |
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

### Production Manager — Customer Access (as of 2026-07-17)

The `production_manager` role can view and edit customer records (`customers.index`, `customers.show`, `customers.edit`, `customers.update`), but:

- **Cannot** create new customers — `customers.create` / `customers.store` remain `admin|accountant` only.
- **Cannot** delete customers — there is no `customers.destroy` route for any role (see Section 10, "Deleting records").
- **Financials are hidden**, on both the customer list and detail pages, via the same `$hideFinancials` pattern used elsewhere for this role (e.g. Orders). `CustomerController::index()` and `show()` compute `$hideFinancials = Auth::user()->role === 'production_manager'` and pass it to the views. Hidden specifically: the Advance Credit stat card and list column, the order Amount column, the "Ledger" button, the "Record Advance Payment" form, and the Advance Payments History table.
- **Cannot** reach `customers.ledger`, `customers.ledger.pdf`, or any `advance-payments.*` route — these stay `admin|accountant` only, including by direct URL.
- Sidebar: "Customers" and "Orders" appear together under the **Sales** section for `production_manager`, in the same order as admin/accountant — not duplicated under Production.

### Route middleware groups currently in `routes/web.php`

- `role:admin` — user management, order reductions, bank accounts, stitching units, piece reassignment, cron logs
- `role:admin|accountant` — customer create/store, customer ledger, advance payments, payments, reports
- `role:admin|accountant|production_manager` — customers index/show/edit/update (financials hidden for production_manager — see above)
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

**Correction (2026-08-18):** this section previously stated that `DispatchController::store()` must block dispatch when `outstanding_balance > 0`. That was wrong — confirmed directly with the Casualite owner that dispatch is intentionally allowed to proceed even when a customer has an outstanding balance (e.g. the customer is trusted to settle later, or partial payment is acceptable to release goods). `DispatchController::store()` has no `outstanding_balance` guard, and it should not have one. This was previously tracked as an open bug (Known Bugs #2) — it is not a bug, and no guard should be added.

**Editing a payment on an already-`dispatched` order (see rule 5.21) can lower `total_paid` enough to introduce or increase a positive `outstanding_balance`.** This is expected and allowed — the edit form shows a non-blocking warning, but does not reverse the dispatch, revert the order status, or restore packed inventory. Do not assume `dispatched` implies `outstanding_balance == 0`, and do not add a balance check anywhere in the dispatch flow without re-confirming with the owner first.

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

**Advance-type amounts exceeding the customer's available credit are not blocked** — the excess falls through to a normal payment entry instead. See rule 5.19.

**Why Cash requires a bank account:** even when a customer pays in cash, the company staff deposits that cash into a specific bank. The bank account field records the deposit destination — it is not about the payment being electronic.

A receipt is required **only** for `bank_transfer` (PDF, JPG, PNG or WebP, max 5 MB **per file**).

**Receipts are multiple files per payment, not one (changed by migration `2026_06_04_000002_convert_receipt_image_to_json_in_payments`; documented here 2026-08-18 — this section previously described a single file and was stale).** The upload form field is `receipt_images` (plural, `required_if:payment_type,bank_transfer|nullable|array|min:1`, each item `file|mimes:pdf,jpeg,jpg,png,webp|max:5120`). The DB column is still named `payments.receipt_image` (singular, unchanged to avoid a rename) but is cast `'array'` on the `Payment` model and stores a JSON array of S3 paths — the migration wrapped every pre-existing single path in `JSON_ARRAY(...)` so historical rows read the same way. `PaymentController::update()` (rule 5.21) supports removing individual receipts from that array and adding new ones in the same edit, rather than replacing one file wholesale.

These rules are enforced in `PaymentController::store()`/`update()` via `required_if` validation and in `orders/show.blade.php` via Alpine.js conditional rendering (`needsBank` getter returns `true` for `cash` and `bank_transfer`; `isBankTransfer` getter returns `true` only for `bank_transfer`).

The receipt upload UI uses the same pattern as the refund document upload in `reduce.blade.php`, extended for multiple files: hidden file input accessed via `x-ref`, `processFile()` detects PDF vs image by extension per file, image shows a thumbnail + lightbox, PDF shows a red PDF icon. In the Payments History table, each path in the `receipt_image` array is rendered the same way, `pathinfo($path, PATHINFO_EXTENSION)` determining PDF icon vs image thumbnail per file.

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

**This is the "fast path" only** — orders in other statuses, or with reduction/refund history, or with payments recorded, are deleted through the fuller refund/credit + Free Pieces flow instead. See rule 5.28.

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

**Advance-type payment reversal (fixed 2026-07-14):** if the deleted payment was `payment_type = 'advance'` and had been split into a credit portion + payment portion (rule 5.19), `destroy()` restores only the **credit portion** to `advance_credit_balance` — derived from the linked `payment_received` ledger entry's amount (`creditPortion = payment.amount − paymentPortion`), not the full payment amount. The earlier implementation incorrectly restored the full amount even when part of it had been a genuine payment, silently inflating the customer's advance credit on every split-advance-payment deletion. The surplus-reversal check (previously only applied to non-advance payments) now also runs for advance-type payments. This logic lives in the shared private `PaymentController::reversePaymentContribution()` helper, also used by Edit (rule 5.21).

**UI:** "Delete" link in each row of the Payments History table on `orders/show.blade.php`, visible to admin and accountant only. Uses `$store.confirm.show()` with `danger: true`.

### 5.17 Payment Overpayment — Auto-Convert Surplus to Advance Credit

When `PaymentController::store()` results in `total_paid > total_amount` (i.e. the customer has overpaid):

- `surplus = total_paid − total_amount`
- Increment `customer.advance_credit_balance` by `$surplus`
- **No ledger entry is created** — the overpayment is already visible in the ledger via the `payment_received` entries exceeding the `order_charged` amount. Adding an `advance_received` entry would cancel out the existing credit and misrepresent the balance.
- The order show page displays an **"Overpaid"** stat card (instead of "Outstanding") showing the surplus in green with "Added to advance credit" below.
- The order show page shows a **green notice banner** above the Record Payment section when the customer has advance credit and the order still has outstanding balance.
- The **"From Advance Credit"** option in the payment type dropdown is always rendered (see rule 5.19 for what happens when the entered amount exceeds the available balance). The available amount is shown inline only when `customer.advance_credit_balance > 0`.

**On payment deletion (`PaymentController::destroy()`):** If the deleted payment contributed to a surplus, `advance_credit_balance` is decremented by the reduction in surplus — floored at the current balance (no negatives). No ledger entry for this reversal either.

### 5.19 Advance Payment Exceeding Available Credit — Split Into Credit + Payment

The **"From Advance Credit"** payment method is always selectable, even when the customer's `advance_credit_balance` is 0 (changed 2026-07-12 — previously this option was hidden from the dropdown unless the balance was greater than 0).

When `PaymentController::store()` receives `payment_type = 'advance'` and the entered `amount` exceeds the customer's current `advance_credit_balance` (including the case where the balance is 0), the request is **never blocked or rejected**. The amount is split automatically:

- `creditPortion = min(amount, advance_credit_balance)` — decremented from `advance_credit_balance`. No ledger entry is created for this portion (same convention as the rest of rule 5.17 — credit consumption here is not separately logged).
- `paymentPortion = amount − creditPortion` — recorded as a normal `payment_received` ledger entry (negative amount), exactly as a cash/bank transfer payment would be.

The rule 5.17 overpayment-surplus check still runs **unconditionally** on the full requested amount regardless of how it was split. This matters even when `paymentPortion` is 0 — e.g. a customer with PKR 1,000 credit paying PKR 700 toward an order that only owes PKR 500: the full PKR 700 is drawn from credit, but the resulting PKR 200 overpayment still flows back into `advance_credit_balance` (300 → 500), rather than being silently lost. Do not re-nest the surplus check inside a `paymentPortion > 0` condition — it must run for every advance payment.

`orders/show.blade.php` shows a live inline preview below the Amount field (Alpine.js `creditPortion` / `paymentPortion` getters, driven by an `advanceBalance` value passed from the customer's `advance_credit_balance`) whenever the entered amount exceeds the available balance — e.g. "PKR 10,700 will be applied from advance credit. PKR 4,300 will be recorded as an additional payment." — so the accountant sees the split before submitting.

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

### 5.20 Piece Tags & Country Pricing (Barcode Labels for Dispatch)

**Purpose:** Physical barcode tags applied to finished pieces before packing, generated and tracked by the system instead of being blank/unlinked.

**Country pricing (admin-only):** `design_country_prices` table — one price per design per destination country (`Customer::COUNTRIES`: Australia, Bangladesh, Canada, Kuwait, Oman, Pakistan, Qatar, Saudi Arabia, UAE, UK, USA — expanded from the original 7 by commit `ff4b9ec`, which added Bangladesh, Kuwait, Oman, Qatar; corrected here 2026-08-18, this section previously still listed the old 7), unique on `(design_id, country)`. Managed from the **Country Pricing** screen (`country-pricing.index` / `country-pricing.store`, admin only), scoped to the active catalogue (same `session('active_catalogue_id')` pattern as other production screens). Leaving a cell blank deletes that design+country's price row. `Customer::CURRENCY_SYMBOLS` maps each country to its tag currency label: Pakistan → `Rs.`, UK → `£`, USA → `US $`, Australia → `AUS $`, Canada → `CAD $`, Saudi Arabia → `SR`, UAE → `AED`, Bangladesh → `TK`, Kuwait → `KWD`, Oman → `OMR`, Qatar → `QAR`.

**Piece tags:** `piece_tags` table — one row per `(order_id, design_id, size)` combination (unique constraint), created lazily the first time tags are printed for that order. `barcode` is a 10-digit, zero-padded numeric string derived from the row's own auto-increment `id` (`PieceTag::booted()` sets it after insert, since the id isn't known before). `price` and `country` are snapshotted onto the row at creation time from `design_country_prices` + the order's customer, so historical tags stay accurate even if country pricing changes later. Barcode uniqueness is guaranteed globally (stronger than the "unique per customer" requirement) since it's tied to the row's own PK — pieces of the same design+size for the same customer share one barcode.

**Print flow:** `DispatchController::printTags()` (route `dispatch.print-tags`, same access as the rest of Dispatch — admin, production_manager, creative_head), triggered by a blue tag icon next to the customer name on the Dispatch index (both mobile card and desktop table, beside the existing red sack-label icon). Before generating anything it validates: (1) the order's customer has a `country` set — customers created before the country field existed may still have `country = null`; (2) every design in the order has a price set for that country. Either failure redirects back with a specific error message. For each order item + size with qty > 0, it finds-or-creates the `PieceTag`, then repeats that tag's label once per physical piece (a design+size with qty 3 produces 3 identical-barcode label pages).

**Output format:** one 2"×1" PDF page per physical piece (`production/dispatch/piece-tags-pdf.blade.php`), sized via `Pdf::setPaper([0, 0, 144, 72])` to match the Zebra TLP2844 label roll — **not** a multi-tag grid sheet on plain paper. Barcodes are generated with `picqer/php-barcode-generator` (`BarcodeGeneratorSVG`, `TYPE_CODE_128_C`), embedded as base64 SVG data URIs, width dynamically fitted to the label via a private `fittedBarcodeSvg()` helper on `DispatchController`.

**Barcode scan result (public, no auth):** `GET /tags/{barcode}` (`tags.scan`, `PieceTagController::scan()`) displays Casualite, customer name, catalogue name, design name, and size — not price — matching the `order.public`/`portal.show` pattern of public informational routes.

**dompdf gotcha — read before touching `piece-tags-pdf.blade.php` or any other small custom-page-size PDF:**
1. An explicit `height` on a container equal to (or very close to) the `@page` height, combined with `box-sizing: border-box`, makes dompdf silently overflow onto a spurious second page — even when content is shorter than the box. Never set an explicit `height` on the outermost per-page container; let it size from content, with a few points of slack under the page height.
2. A percentage-width child (`width: 100%`) inside a `box-sizing: border-box` parent with padding gets sized against the wrong containing block and overflows past the padding into the page edge, clipping text. Give the child a fixed point-width equal to the parent's content width (page width minus padding), not a percentage.

Both only surfaced when forcing `page-break-after: always` between many small pages — after changing this template, render to an image and check text bounding boxes, not just the page count.

### 5.21 Payment Editing

**Purpose:** correct a payment's amount, method, bank account, date, notes, or receipt after it was recorded, without losing its identity in the Payments History, invoice, ledger, or customer portal.

**Route:** `orders.payments.edit` (GET) / `orders.payments.update` (PUT `/orders/{order}/payments/{payment}`), admin + accountant only — same access as Delete (rule 5.16). No order-status gate: editable even when the order is `dispatched`, for the same "fix a mistake" reason deletion isn't gated either.

**The edited payment keeps the same row and the same `sequence_number`.** Editing never deletes and recreates the `payments` row — that would silently reassign its Payment ID (see rule 5.22; e.g. `#1005342p2` becoming `#1005342p4`), breaking references to it on the invoice, ledger, and portal history.

**Implementation — reverse then reapply, in one `DB::transaction()`:**
1. Lock the order row and the customer row (`lockForUpdate()`), same pattern as `store()`'s sequence-number lock.
2. **Reverse the OLD state** via the shared `reversePaymentContribution()` helper (also used by `destroy()`, see rule 5.16): deletes the payment's linked `payment_received` ledger entry (if any), restores exactly the credit portion an `advance`-type payment had drawn, and reverses any overpayment surplus it contributed — all computed while `order.total_paid` still includes the payment's old amount.
3. **Mutate the same row** with the new field values. `sequence_number` and `order_id` are never included in the update payload.
4. **Recompute `order.total_paid` / `outstanding_balance`** from a fresh, authoritative `SUM()` over the order's payments (not incremental math) — mirrors `destroy()`'s recompute, not `store()`'s.
5. **Reapply the NEW state**: creates a fresh `payment_received` ledger entry (or advance-credit consumption) exactly as `store()` would, using a surplus baseline computed as "total_paid as if the new amount weren't added yet" so the delta applied to `advance_credit_balance` is correct.

**Receipts:** existing receipt files can be individually removed (checkbox-style UI mirrors the create form's upload widget) and deleted from S3 on save. **Switching `payment_type` away from `bank_transfer` unconditionally clears any existing receipt** (deleted from S3), regardless of what the accountant explicitly removed — even though `advance` optionally supports a receipt on create (rule 5.12), an edit that changes the type away from `bank_transfer` always drops the old one rather than leaving a stale file attached.

**Dispatched-order warning:** if lowering a payment's amount would reintroduce a positive `outstanding_balance` on an order that's already `dispatched`, the edit form shows a non-blocking amber warning (Alpine.js, computed client-side from `total_amount` / `total_paid` / the payment's old and new amount). The submission is never blocked — see the rule 5.2 addendum.

**Activity log:** `Payment` model already auto-logs field-level diffs via `LogsActivity` (`logAll()`). `update()` additionally writes one manual order-level `activity()` entry (same pattern as `store()`/`destroy()`) with a headline like `Payment #1005342p2 edited on Order #1005342 (PKR 260,003 → PKR 265,000)`.

### 5.22 Payment IDs (Sequential per Order)

**Purpose:** give each payment a stable, human-readable identifier — distinct from the row's database `id` — mirroring how `orders.order_number` (Section 2, "How Orders Work") replaced the raw order `id` everywhere customer-facing or accountant-facing.

**Format:** `{order_number}p{sequence_number}` — e.g. Order #1005342's first payment is `#1005342p1`, second is `#1005342p2`. This string is **never stored** — it's computed on the fly wherever displayed, from `payments.sequence_number` (a real integer column) plus the order's already-known `order_number`. Storing the full string would duplicate `order_number` into every payment row for no benefit.

**`payments.sequence_number`** — nullable unsigned integer, unique per `(order_id, sequence_number)` (composite unique index). Assigned in `PaymentController::store()` by locking the order row (`Order::where('id', $order->id)->lockForUpdate()->first()`) then computing `MAX(sequence_number) WHERE order_id = X, + 1` — the same locking pattern as `order_number_sequence`, but without needing a dedicated counter table, since sequence numbers are cleanly per-order and always start at 1 (unlike `order_number`, which needed a global sequence table because historical orders had random numbers in an unrelated range).

**Deletion leaves gaps — numbers are never reused.** Deleting `#1005342p2` leaves `p1` and `p3` as-is; the next new payment on that order becomes `p4`, not `p2`, because generation always uses `MAX()` over whatever rows currently exist. This mirrors how `order_number` never gets reused after a hard-delete.

**Editing never changes `sequence_number`** — see rule 5.21. A corrected payment keeps its original ID.

**Backfill:** migration `2026_07_14_000001_add_sequence_number_to_payments_table` assigned sequence numbers to all pre-existing payments, per order, ordered by `payment_date` then `id` as a tiebreaker — computed dynamically from whatever was actually in the table at migrate-time (not hardcoded), so it's safe to run against any environment's real data. `HistoricalPaymentSeeder` was also updated to assign sequence numbers the same way, so a fresh dev/test seed stays consistent with production.

**Displayed in six places, scoped to `payment_received`-type ledger rows where the display context is a ledger:**
1. `orders/show.blade.php` — Payments History table (mobile card + desktop table), right after the date.
2. `orders/invoice.blade.php` — dedicated "Payment #" column after Date.
3. `portal/dashboard.blade.php` — Activity section under each order, only on Payment Received rows.
4. `customers/ledger.blade.php` + `customers/ledger-pdf.blade.php` — under the order number in the Order column, only on `payment_received` rows. `LedgerController::show()`/`pdf()` extend their `$orderMap` entries with `payment_seq` when resolving a `Payment`-referenced ledger entry.
5. `reports/customer-ledger.blade.php` — dedicated "Payment #" column, only on `payment_received` rows. `ReportController::customerLedger()` builds a `$paymentMap` (`payment.id → "order_number" . "p" . sequence_number`) for this.
6. Activity log headlines in `PaymentController::store()`, `destroy()`, and `update()` (e.g. `"Payment #1005342p2 of PKR 260,003 recorded on Order #1005342"`).

**Pre-existing bug found while wiring this up, fixed 2026-08-19:** `reports/customer-ledger.blade.php` referenced `$entry->entry_type`, a column that doesn't exist (the real column is `transaction_type` — see Section 4); `ReportController::customerLedger()` itself was never affected, it already used `transaction_type` correctly. The typo made the Type badges render blank on every row, and — not previously documented — also zeroed out the report's Total Charged / Total Paid summary cards, since those sums used the same wrong column name. The view's badge/label maps also listed the removed `surplus_to_advance` type (dead, can never occur) while missing a mapping for the real `refund_issued` type (which would have rendered as a raw unstyled string). All fixed in one pass: `entry_type` → `transaction_type` throughout the view, `surplus_to_advance` swapped for `refund_issued` in both maps. No controller or database changes were needed — the bug and the fix were confined entirely to this one Blade file.

### 5.23 Advance Payments (Standalone, Not Tied to an Order)

**Purpose:** money received from a customer that isn't attached to any order (e.g. a customer who has already fully paid their current order pre-pays toward whatever they book next). This fills in the previously unused `advance_received` ledger type (Section 4), which existed in the schema from the start but had no code path writing it until now.

**Why a separate table, not the `payments` table:** `payments.order_id` is nullable at the DB level, but the entire `PaymentController` flow (sequence numbers, Payment IDs, dispatch checks) assumes an order exists. Reusing it for order-less money would either break that assumption or scatter null-checks through code that's supposed to be order-scoped. `advance_payments` is a new, simpler table instead (`customer_id`, `payment_type`, `amount`, `bank_account_id`, `payment_date`, `notes`, `receipt_image`, `logged_by`). No `sequence_number`, no Payment ID format, since there's no `order_number` to anchor one to.

**Route:** `customers/{customer}/advance-payments` (store/edit/update/destroy), admin + accountant only, same role group as the rest of Customers.

**Payment method rules:** only `cash` and `bank_transfer` (unlike order payments, there's no "From Advance Credit" option here, since the customer can't pay their own advance credit into itself). Bank account is required for both, receipt required only for `bank_transfer` (same convention as rule 5.12).

**On store:** creates the `advance_payments` row, writes an `advance_received` ledger entry (`amount` positive, `running_advance_balance` read before the increment, per rule 5.7), then increments `customer.advance_credit_balance` by the full amount. Wrapped in `DB::transaction()` with the customer row locked.

**On edit/delete, floor at zero instead of going negative:** `advance_credit_balance` is one shared pool, not earmarked per source. By the time an advance payment is edited or deleted, some of the credit it originally added may already have been spent elsewhere (applied to an order via `credit_applied`, or consumed by the advance-type payment split in rule 5.19). The private `reverseAdvancePaymentContribution()` helper (mirrors `PaymentController::reversePaymentContribution()` from rule 5.21) removes only `min($amount, $available)` from the balance, never pushing it below 0. Any unreversed portion (`$shortfall`) is surfaced to the accountant: an amber, non-blocking warning on the edit form, and inline in the delete confirmation modal, both stating exactly how much of the original amount could not be reversed. Edit reuses the same reverse-then-reapply pattern as rule 5.21 (same row id preserved, no delete-and-recreate).

**Where it shows up:** a collapsible "Record Advance Payment" form plus an "Advance Payments" history table (with Edit/Delete) on the customer show page (`customers/{customer}`), directly above the Orders list. No changes were needed to the customer ledger view, ledger PDF, or customer portal dashboard: all three already had `advance_received` rendering wired in (badge colour, amount colour, and a dedicated `$generalLedger` section in the portal) waiting for a source of data.

---

### 5.24 Advance Credit Auto-Applied to New Orders

**Purpose:** eliminate the manual step where an accountant/admin had to open a brand-new order and record an "Advance" payment by hand whenever the customer already held `advance_credit_balance`. Now this happens automatically the moment the order is submitted through the public order form.

**Where it runs:** inside the same `DB::transaction()` that creates the `Order`, its `OrderItem`s, and the `order_charged` ledger entry — immediately after the ledger entry, via `AdvanceCreditAutoApplyService::apply($order, $customer)`. As of 2026-08-10 this transaction lives in `OrderPlacementService::place()`, called by `PublicOrderController::submit()` — see the "Order placement service" convention in Section 10.

**Amount applied:** `min($customer->advance_credit_balance, $order->total_amount)`. Nothing happens if the balance is 0. Because the order is brand new (`total_paid` starts at 0), the applied amount can never exceed `total_amount` — there is never a surplus and never a "payment portion" to split off, unlike the manual advance-payment flow in rule 5.19. This is deliberately simpler than `PaymentController::store()`'s `advance` branch and does not reuse it — that method is built around an HTTP request/response/receipt-upload cycle that doesn't apply to a system-triggered call.

**What gets created:** a real `payments` row (`payment_type = 'advance'`, its own `sequence_number`, `notes = 'Auto-applied from advance credit balance'`, `logged_by = null`). `customer.advance_credit_balance` is decremented by the applied amount — **no `CustomerLedger` entry is created for this consumption**, matching the existing convention for the credit portion in rule 5.19's `advance` branch.

**`payments.logged_by` is nullable** (migration `2026_07_14_000003_make_logged_by_nullable_on_payments_table`, mirrors the `customer_ledger.created_by` nullable migration from rule 5.1) — this is the only kind of payment that can have a null logger, since it's system-generated with no staff user in the request. No existing view rendered `payment->logged_by` before this change, so nothing else needed updating.

**Auto-confirm threshold — a deliberate carve-out from the general auto-confirm rule:** normally *any* payment (including a manually-recorded advance payment via `PaymentController::store()`) auto-confirms `received → confirmed` regardless of amount (see the "Auto-confirm on payment" entry in Section 9). This automatic path is the **one exception**: the order only auto-confirms if the applied amount is **greater than** `config('casualite.advance_credit_auto_confirm_threshold')` (currently PKR 50,000, sourced from `.env` — see below). Smaller auto-applied amounts leave the order in `received` for a human to review and confirm manually. This carve-out applies **only** to this automatic path — manual advance payments recorded by staff are unaffected and keep auto-confirming unconditionally.

**Config:** `config/casualite.php` — the project's first non-stock config file, introduced specifically so this threshold (and any future business constant) can be changed by editing one `.env` value (`ADVANCE_CREDIT_AUTO_CONFIRM_THRESHOLD`) and running `php artisan config:clear`/`config:cache`, with no code change. Never call `env()` outside a config file — always read this threshold via `config('casualite.advance_credit_auto_confirm_threshold')`.

**Activity log:** one manual `activity()` entry is written on the order after the transaction closes (same pattern as `PaymentController::store()`'s headline entries), e.g. `"Payment #1005410p1 of PKR 60,000 auto-applied from advance credit on Order #1005410"`.

### 5.25 Cost Estimation

**Purpose:** digitize the production manager's paper "Cost Estimation Sheet" — the per-design breakdown of fabric, embroidery, and stitching costs used to work out the true cost per piece and compare it against the selling price. One record per design (`cost_estimations`, unique on `design_id`), editable in place, no version history.

**Where it lives:** a "Cost Estimation" link on each in-house design's card on the catalogue show page (`catalogues.show`), scoped to in-house designs only — outsourced designs have no fabric/stitching cost to estimate, since they arrive finished via `OutsourcedBatch`.

**Route + access:** `designs.cost-estimation.edit` (GET) / `designs.cost-estimation.update` (POST) / `designs.cost-estimation.pdf` (GET), all in the same production-tracking route group as Fabric Batches, Naeem Pakki, etc. (`role:admin|production_manager|creative_head`). `update()` calls `$this->denyCreativeHead()` — creative_head can view and download but not save.

**Nine cost categories**, matching the paper sheet: Fabric Cost, Dupatta, Block Printing, Dying, Computer Embroidery, Pakki Embroidery, Hand Embroidery, Accessories, Stitching Cost. Each has repeatable rows (`cost_estimation_items`: `particulars`, `avg`, `qty`, `rate`, `amount`), Pakki Embroidery excepted (see below).

**System-derived fields — never manually entered:**
- **Production Qty** — `SUM(fabric_batch_items.quantity)` for the design, snapshotted onto `cost_estimations.production_plan_qty` on every save.
- **Stitched By** — distinct `stitching_units.name` (ordered by `number`, joined `"A + B"`) for every `StitchingReturn` actually logged against the design (i.e. units that returned stitched pieces, not merely units the work was assigned to). Fully read-only in the UI; the server recomputes and overwrites it on every save regardless of what's posted, so it can't be spoofed or hand-edited.
- **Pakki Embroidery** — the one category that is entirely locked, not just pre-filled. When `design.needs_naeem_pakki` is true, computed from `ProductionAssignmentNpDesign` (see rule/note in Section 6 — **not** `NaeemPakkiSend`, which is dead code): qty = sum of every batch's quantity, amount = sum of each batch's own `quantity × per_piece_price` (batches can carry different rates), rate shown on the form is the blended `amount ÷ qty` for display only. When the design doesn't need Naeem Pakki, or needs it but has no batches yet, the whole section renders disabled/empty and contributes PKR 0.

**Amount is always system-multiplied.** Every editable row's Amount column is plain computed text (`qty × rate`, live via Alpine on the client, recomputed authoritatively server-side on save) — never an input, never accepted from the client. The production manager enters Particulars/Avg/Qty/Rate only.

**Totals:** `total_cost` = sum of every category's row amounts including the locked Pakki Embroidery line; `per_unit_cost` = `total_cost ÷ production_plan_qty`. Market Rate and Margin are manual, optional, informational only (not used in any calculation).

**PDF export:** `designs.cost-estimation.pdf`, same `Pdf::loadView(...)->download(...)` pattern as the invoice/sack-label PDFs, logo via `pdf_logo_data_uri()`. Only reachable once a cost estimation has actually been saved (`per_unit_cost` is set) — the design card only shows the download icon in that case.

**Number formatting:** this feature uses plain `number_format()` grouping (`794,150`) throughout, both server-side (PHP) and in the live-total JS (`formatNum`/`formatPkr` in `edit.blade.php`) — **not** the South Asian lacs grouping (`7,94,150`) that `lacs_format()` produces elsewhere in the app (e.g. Bank Collection Report). Do not port `lacs_format()`-style grouping into this feature without being asked.

---

### 5.26 Customer Portal PWA + Push Notifications

**Purpose:** the customer portal (`portal.show` / `portal.dashboard`) is now installable as a PWA, and pushes a browser notification to the customer's phone when their order's status changes — no more relying on the customer to remember to check the link.

**Persistent login on installed devices:** `customer_devices` table (`customer_id`, sha256 `token_hash`, `user_agent`, `ip_address`, `last_seen_at`) backs a `portal_device` cookie, scoped to `/portal`, 400-day sliding expiry (reissued on every successful validation — Chrome's hard ceiling on `Set-Cookie` Max-Age is 400 days, so this is as long-lived as a cookie can be). `CustomerPortalController::show()` resolves the cookie against `customer_devices` scoped to that specific customer's `portal_token` — a device cookie verified for one customer must not grant access to another customer's portal even if somehow presented there. If resolved, the customer skips straight to `portal.dashboard` without re-entering their email.

**Which status changes notify, and which deliberately don't:** `OrderStatusChanged` (a `ShouldQueue` notification, delivered via `WebPushChannel` — browser/PWA — and, as of Slice 4, also `ExpoPushChannel` — the React Native app; see "Mobile API — Push Notifications" under Section 9) fires on `confirmed`, `stitching`, `partially_dispatched`, `dispatched`, `cancelled`. It deliberately does **not** fire on `received` (nothing has happened yet) or on status **reverts** (e.g. `confirmed` → `received` when a payment is deleted per rule 5.16) — a revert is not news the customer needs pushed to their phone. `OrderStatusNotificationService::notify(Order $order, string $newStatus)` is called explicitly at each of the ~7 status-mutation call sites across `PaymentController`, `FabricBatchController`, `DispatchController`, and `OrderReductionController` — **not** via a model observer, since `FabricBatchController`'s bulk `Order::whereIn(...)->update()` bypasses Eloquent events entirely, and observer-based filtering of reverts would need the same explicit case-by-case logic anyway. Any new status-mutation code path must call this service explicitly; it will not fire on its own.

**Push subscriptions live on the `Customer` model** (`HasPushSubscriptions` trait, `push_subscriptions` table, polymorphic `subscribable`), opted into via an "Enable Order Updates" card on the dashboard (`pushOptIn()` Alpine component). The subscribe flow races `Notification.requestPermission()` (20s) and `navigator.serviceWorker.ready` (10s) against a timeout — both are promises that can hang forever with no timeout of their own (an unanswered OS permission prompt, or a service worker that never activates), which is exactly what happened in first-round production testing (a real customer's card was stuck on "Enabling…" indefinitely with no way to recover short of reloading the page). The `error` state is clickable to retry rather than being a dead end.

**Splash screen is custom HTML, not manifest-driven — this is a platform limitation, not an oversight.** Android/iOS auto-generate a PWA launch splash from the manifest icon centered on `background_color`, and that sizing cannot be controlled via the manifest — it always renders small and centered regardless of the icon's resolution. `portal.partials.pwa-splash` is a fixed, full-viewport overlay (`public/images/pwa/splash.png`, `object-fit: cover`) shown only when `display-mode: standalone` is detected, that fades out once the page finishes loading. The manifest's `background_color` is black to match, avoiding a light-to-black flash between the OS splash and this one.

**Reverse-proxy detection is required, not optional, in production too.** `bootstrap/app.php` calls `trustProxies(at: '*', ...)` — needed locally for ngrok, but equally needed in production since the app sits behind a reverse proxy (Apache/LiteSpeed on Namecheap shared hosting) that terminates HTTPS and forwards over plain HTTP internally. Without it, Laravel thinks every request is HTTP, which breaks the manifest's absolute URLs and the service worker's HTTPS requirement. Do not remove this thinking it's dev-only.

**Shared hosting has no persistent process for `queue:work`.** `OrderStatusChanged` is queued (`QUEUE_CONNECTION=database`), but Namecheap shared hosting can't run a supervisor/systemd-managed worker. Instead, `routes/console.php` schedules `queue:work --stop-when-empty --max-time=50` every minute, riding the same `schedule:run` cron entry already configured on the server (see Section 2's wages/Tarpai scheduled jobs) — no second cron entry needed. Do not "fix" this by trying to run a long-lived `queue:work` process on shared hosting; it will get killed and won't restart.

**VAPID keys are per-environment.** `config/services.php` reads `VAPID_SUBJECT` / `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` from `.env` (never call `env()` outside a config file — same convention as the rest of the project). These must be generated fresh on each environment via `php artisan webpush:vapid` — never copy a local dev environment's keys into production `.env`.

**Manual testing in production:** `php artisan notify:test-push {email} {status=confirmed} {--order=}` sends a real push to a customer's subscribed devices without mutating the order's actual `status` column — looks up the customer by email, finds their most recent order (or a specific `--order=` number), and reports both the customer's active web push subscription count and registered Expo push token count (as of Slice 4) so a "nothing arrived" test isn't a mystery either way. `status` must be one of `OrderStatusChanged::statuses()` (the five values above). Since delivery rides the same `queue:work`-via-scheduler cron, allow up to ~60 seconds for a test push to actually arrive.

**Mobile (React Native) push is a separate channel, not a replacement.** `ExpoPushChannel` (Slice 4, see Section 9) reuses this exact same trigger pipeline — `OrderStatusNotificationService::notify()`, the same five statuses, the same revert-suppression — so nothing above needed to change to support the app. It only adds a second delivery channel (`app/Notifications/Channels/ExpoPushChannel.php`) and a second token store (`expo_push_tokens`, distinct from `push_subscriptions` since an Expo token is a single opaque string, not a WebPush endpoint+keys object). A customer with both the installed PWA and the RN app gets both pushes for the same event.

---

### 5.27 HD Image Gallery (Per-Catalogue)

**Purpose:** customers frequently ask for the high-resolution photos of a catalogue's designs (to use on their own websites). Admin or creative_head uploads these HD originals once per catalogue; a public, no-auth link (shared the same way the order link is) lets any customer browse and download them.

**Not the same thing as design photos.** `designs.photo` (used for catalogue/design management UI) is a separate, unrelated field — the HD Gallery is a new per-catalogue collection (`catalogue_hd_images`), independent of designs entirely, since HD photos don't necessarily map 1:1 to individual designs.

**Gallery token:** `catalogues.hd_gallery_token` (nullable, unique, `Str::random(32)` — same convention as `order_token`, not a real UUID), generated in `Catalogue::booted()` alongside `order_token` so the shareable link exists from catalogue creation, before any images are uploaded. Migration `2026_07_16_000001` added the column and backfilled existing catalogues.

**Access:** admin + creative_head only — deliberately narrower than the usual `adminOrProductionManager()` group (`production_manager` does not get this). Enforced entirely via `role:admin|creative_head` route middleware in `routes/web.php`; `CatalogueHdImageController` has no additional per-method guard beyond that, matching `DesignCountryPriceController`'s pattern of trusting route middleware alone.

**Two entry points, one shared view.** `catalogues/show.blade.php` links directly to a specific catalogue's gallery (a "Shareable HD Gallery Link" card + "Manage Images" link, same placement as the existing "Shareable Order Link" card) — this always targets the catalogue actually being viewed, regardless of what's selected elsewhere. A sidebar link ("HD Gallery", in the Production section, gated `in_array($r, ['admin','creative_head'])` — not `production_manager`) was added as a follow-up (2026-07-16) so the gallery doesn't require opening a specific catalogue first: `GET /hd-gallery` (`hd-gallery.index`) has no catalogue in the URL at all — `CatalogueHdImageController::active()` resolves whichever catalogue is currently selected in the sidebar's own Active Catalogue dropdown (`session('active_catalogue_id')`, same resolution as `DesignCountryPriceController::index()`) and renders the identical view via a shared private `renderIndex(Catalogue $catalogue)`, same as `index()`.

**`active()` must render directly — it must never redirect to `catalogues.hd-images.index`.** An earlier version of this route did exactly that (redirect to the catalogue-scoped URL) and shipped a real bug caught in testing right after: once redirected, the browser is sitting on a URL with one specific catalogue's ID baked in (`/catalogues/11/hd-images`). Switching the sidebar's Active Catalogue dropdown afterward only updates the session — `ActiveCatalogueController::store()` returns a plain `back()`, which just reloads that same ID-pinned URL, so the page (and any upload made on it) silently kept targeting the catalogue that was active when you first landed there, not the one now shown as selected in the dropdown. Every other session-scoped screen in this app (Country Pricing, Fabric Batches, etc.) avoids this because their URL never encodes a catalogue ID — they re-resolve `session('active_catalogue_id')` fresh on every load, so a `back()` reload naturally picks up whatever was just selected. `active()` now does the same: it stays on `/hd-gallery` and re-resolves the session on every request, so switching catalogues and landing back on `/hd-gallery` shows the newly selected one every time. The catalogue-ID route (`catalogues.hd-images.index`) is unaffected and still pinned to one specific catalogue by design — that's the correct behavior for the contextual "Manage Images" link, since it's meant to always mean "this catalogue," not "whichever one is active."

**Direct-to-S3 presigned uploads — the first of their kind in this codebase.** A single catalogue's gallery can total 4-5GB (files up to 1GB each, normally JPG/PNG ~100MB or less). Routing that through this app's shared-hosting PHP process is not viable (`upload_max_filesize`/`post_max_size`/execution time). Instead `CatalogueHdImageController::presign()` returns a short-lived (`temporaryUploadUrl`, 30 min) presigned S3 PUT URL for a browser to upload straight to S3; Laravel only ever sees the metadata `store()` call afterward. This is a first for the project — no prior code used `temporaryUploadUrl`/`temporaryUrl`/raw `S3Client` presigning.

**CORS is a required one-time infra step, not optional.** A presigned PUT from browser JS is a cross-origin request to the S3 bucket and triggers a preflight; without a CORS policy on the bucket allowing `PUT` from the app's origin, every upload fails client-side before reaching S3. `php artisan s3:configure-gallery-cors` (`app/Console/Commands/ConfigureS3GalleryCors.php`) applies this policy via `Aws\S3\S3Client::putBucketCors()` and **must be run once per environment** (local/staging/production) before uploads will work — same category of one-time setup as `php artisan webpush:vapid` (rule 5.26). It is not scheduled and does not write a `CronLog` entry. `AllowedOrigins` is deliberately `['*']` — the real access control is the presigned URL itself (short-lived, scoped to one exact key), not the browser's CORS check. **Warning baked into the command's own docblock:** `putBucketCors()` replaces the bucket's entire CORS configuration, not just adds to it.

**Thumbnails are generated client-side, not server-side.** Decoding a 100MB-class original with GD in a PHP request (the same technique `CatalogueController::generateOgImage()` uses for cover photos) risks exhausting shared-hosting memory limits at this file size, and queuing the job would mean up to a ~60-second wait (the queue only drains once a minute, rule 5.26) before a thumbnail appears — poor fit for an "upload and see it immediately" Unsplash-style UI. Instead, the browser downsizes the image via `createImageBitmap()` + canvas (long edge capped at 700px, re-encoded as JPEG) *before* upload, and both the original and the thumbnail are uploaded as two separate presigned PUTs sharing one client-generated UUID, so `store()` can pair them back up by filename convention (`catalogue-hd-images/{catalogue_id}/{uuid}.{ext}` and `catalogue-hd-images/{catalogue_id}/thumbnails/{uuid}.jpg`). If client-side thumbnailing fails for any reason, the original still uploads and the gallery falls back to displaying it directly — never a hard failure.

**`store()` trusts S3, not the client, for size/existence.** Before creating the `CatalogueHdImage` row it checks `Storage::disk('s3')->exists($originalKey)` (422 if missing — the PUT never completed) and reads the authoritative file size via `Storage::disk('s3')->size($originalKey)` rather than any client-supplied value, re-checking the 1GB ceiling server-side and deleting the object if it's somehow been exceeded.

**Downloads redirect straight to a presigned S3 URL (changed 2026-07-24, see rule 5.31) — this is no longer proxied through the app.** The original design proxied every download through `GalleryController::download()` via `Storage::disk('s3')->download()` specifically so an in-page progress bar could read the response body via `fetch()` + `ReadableStream` same-origin. That progress bar was removed after it caused real download failures on large files (PHP's `max_execution_time` killing the stream mid-transfer) — see rule 5.31 for the full history and the current implementation. Thumbnails in both the admin grid and the public gallery are still plain `<img src="{{ Storage::url(...) }}">` tags straight to S3 (no CORS needed there, then or now).

**Deletion is a genuine hard-delete**, a new explicit exception alongside the two documented under "Deleting records" in Section 10 — HD images are media with no financial/audit footprint to preserve. `CatalogueHdImageController::destroy()` removes both the original and thumbnail from S3 (`Storage::disk('s3')->delete()`) then the DB row, gated by the same danger-styled `$store.confirm.show()` modal used everywhere else in the app.

**Auto-surfaced to customers in the portal (2026-07-17) — no manual sharing needed.** Each order card on `portal/dashboard.blade.php` (`CustomerPortalController::show()`/`verify()` already eager-load `orders.catalogue`, so no new query was needed) renders an "HD Photos" link to `route('gallery.show', $order->catalogue->hd_gallery_token)`, opened in a new tab. This is shown **unconditionally** — regardless of whether that catalogue's gallery has any images yet — since every catalogue always has a `hd_gallery_token` (generated at creation, backfilled for old rows). It is per-order, not deduped per catalogue: a customer with two orders on the same catalogue sees the same link twice, once on each order card — accepted deliberately for simplicity rather than adding cross-order dedup logic. No push notification is tied to this — that was explicitly ruled out when this was scoped, unlike the order-status pushes in rule 5.26.

---

### 5.28 Order Deletion — Refund/Credit Choice + Free Pieces Pool

**Purpose:** decouple hard-deleting an order from deciding what happens to the pieces it frees up. Previously a single screen had to both settle the money and immediately reassign every freed piece to another order. This is now two independent actions: (1) delete the order and settle any money paid, and (2) whenever convenient afterward, assign the freed pieces from a standing pool ("Free Pieces") to any target — an existing order on the same catalogue, or a brand-new order for a customer who hasn't ordered on it yet.

**Two distinct delete paths, chosen automatically by `orders/show.blade.php` based on the order's state — not by the admin:**

- **Fast path** (`OrderController::destroy()`, route `orders.destroy`) — unchanged from rule 5.15: only when `status = 'received'` AND `total_paid = 0`. The show-page button additionally requires `reductions` and `refunds` to both be empty before rendering this one-click path; if either exists, the page renders the full-flow link instead, even though the order still technically meets the status/paid conditions — a reduction or refund on an otherwise-untouched order is still real financial history worth walking through the guided screen. **`OrderController::destroy()` itself still only checks `status`/`total_paid`** — the reduction/refund condition is UI routing only, not a second server-side guard.
- **Full flow** (`OrderDeleteController`, routes `orders.delete.create` GET / `orders.delete.store` POST) — admin + accountant, any status except `dispatched`/`partially_dispatched`. Shown whenever the fast path's narrower conditions aren't met (has payments, has reduction/refund history, or is in `confirmed`/`stitching`/etc.).

**What the full flow does, in one `DB::transaction()`:**
1. Deletes the order's own prior `OrderReduction`/`Refund` rows and their ledger entries.
2. Deletes the `order_charged` ledger row(s) — there can be more than one (initial charge + any Piece Reassignment additions).
3. Reverses any `credit_applied` entries (restores the credit to `advance_credit_balance`).
4. Reverses and deletes every payment, one at a time, via `PaymentController::reversePaymentContribution()` — made `public` (was `private`) specifically so this controller could reuse it, same reverse-then-delete mechanics as rules 5.16/5.21.
5. Applies the admin's refund/credit choice to whatever was actually paid (`refundableAmount`, summed from the surviving `payment_received` ledger amounts before they're deleted in step 4):
   - **`credit_to_advance`** — increments `advance_credit_balance`, and creates an `advance_received` ledger entry, `amount = -$refundableAmount` (see sign-convention fix below), `notes = "Credited from deleted Order #{order_number} on the {catalogue name} catalogue"`. Without this entry the ledger would show no trace of where the credit came from, since the order's own `order_charged`/`payment_received` rows were just deleted in the same transaction.
   - **`refund`** — creates a `Refund` row (`refund_method`, optional `refund_reference`, optional `refund_document` S3 upload) with `order_id`/`order_reduction_id` left `null` (migration `2026_07_22_000001_make_refund_order_fields_nullable` made both columns nullable specifically for this — a refund created here isn't tied to any `OrderReduction`, unlike the Log Reduction refund path in rule 5.5). No ledger entry — the order's ledger footprint was already zeroed by steps 2/4.
6. Pushes the order's own per-size quantities into the `free_pieces` pool — **once per design in the order** (every design gets the same uniform per-size amounts, per "How Orders Work"'s uniform sizing rule): `FreePiece::firstOrCreate(['catalogue_id', 'design_id', 'size'], ['quantity' => 0])->increment('quantity', $qty)`.
7. Fires `ProductionAssignmentAlertService::checkOrder(..., 'order_deleted')` **unconditionally** — since nothing is ever reassigned inline at delete time anymore, every delete is now a real, immediate demand decrease. Must run before step 8, since the alert's `order_id` FK needs the order row to still exist at insert time (it survives the delete via `nullOnDelete`, same as a fresh `Refund` row).
8. Deletes the order itself.
9. One manual `activity()` "detail" log entry summarizing the whole action (refund choice, amount, pieces freed).

**The `free_pieces` table** (`catalogue_id`, `design_id`, `size` enum, `quantity`, unique on all three) is a running total per cell, not a per-deletion batch ledger — rows are deleted outright (not zeroed) once `quantity` reaches 0, so listing screens never need to filter zero-rows. The `FreePiece` model has no special logic beyond `catalogue()`/`design()` relations.

**`FreePieceController`** (routes `free-pieces.index`, `free-pieces.assign`, `free-pieces.store` — admin + accountant, Sales sidebar section). **Reworked 2026-07-23** to drop per-design display and per-design assignment entirely — see below.

- **`index()`** — renders one summary row per catalogue (`Sizes | XS | S | M | L | XL | Total` header, single data row), **not** a design × size breakdown grid. The per-size number is computed by the shared private helper `FreePieceController::sizeTotals()` as the **minimum quantity across every design carrying that size** — free pieces are always uniform per size across every design in the catalogue (they originate from a deleted order, which itself applied one uniform size split to every design — the same "same quantity applies to ALL designs" rule the public order form follows), so MIN is a defensive cap against drift, not a real aggregation. **Never SUM across designs** — an earlier iteration of this screen summed `quantity` per size across all designs (so a catalogue with 7 designs each holding "1 free Small" displayed "7"), which is wrong; the correct reading is "1", the same number that applies to any one design.
- **`assign()`** — rebuilt to mirror `OrderAdjustController` / `orders/adjust.blade.php`'s pattern exactly: one uniform per-size quantity (`qty_xs`..`qty_xl`) applied identically to every design with free stock, plus a **single** target per submission (existing order or new customer) — replacing the earlier per-design-per-size-per-target grid that allowed picking different quantities for different designs, or splitting one submission across several targets. To send the same pool to two different customers, submit twice — availability drops after the first submission, so the second submission naturally sees the reduced numbers. One combined type-to-filter combobox lists both valid targets — existing non-`dispatched`/non-`cancelled` orders on the catalogue (labeled `"<customer> — Order #<n> (<status>)"`) and customers with no order yet on this catalogue (labeled `"<customer> — New Order"`) — so the admin doesn't need to know in advance which bucket a name belongs to.
  - **Client-side guards (Alpine.js):** a size input whose `available` is 0 renders `disabled` (can't be typed into). Entering more than `available` for a size flags that input red (ring + text + background) via an `isOverLimit(key)` check and disables the Assign Free Pieces button (`canSubmit` also requires `!hasOverLimit`), swapping the piece-count summary for a red "Fix the highlighted size…" message. These are UX guards only, mirrored (not replaced) by the server-side check below.
- **`store()`** — locks every `FreePiece` row for the catalogue (`lockForUpdate()`), re-validates the requested per-size quantities against the live per-design minimum, decrements/deletes the rows uniformly across every design carrying free stock, then either:
  - **Existing-order target** — increments that order's `OrderItem` size columns for every design that has free stock (`OrderItem::booted()` recomputes `total_qty`/`total_amount`), bumps the order's `total_amount`/`outstanding_balance`, writes an `order_charged` ledger entry.
  - **New-customer target** — re-checks the duplicate-order guard inside the transaction (same reasoning as `PublicOrderController::submit()`, closing a race between concurrent submissions), creates a brand-new `status = 'received'` order (`notes = 'Created from Free Pieces assignment.'`), one `OrderItem` per design carrying free stock, pricing each design independently — `discount_price` if the uniform per-design piece count exceeds the catalogue's `quantity_benchmark`, else `selling_price` (same pricing rule `PublicOrderController::submit()`/`OrderAdjustController::store()` use) — and writes an `order_charged` ledger entry. **Does not** call `AdvanceCreditAutoApplyService` (rule 5.24) — not relevant to this system-triggered path.
  - Either branch calls `ProductionAssignmentAlertService::checkOrder(..., 'free_pieces_assigned')`, scoped to only the designs actually touched by this specific assignment.

**`ProductionAssignmentAlertService::REASON_LABELS`** gained two entries for this feature: `order_deleted` → "permanently deleted", `free_pieces_assigned` → "given newly assigned free pieces".

**Sign-convention fix, discovered while adding the Delete Order ledger entry above:** `advance_received` ledger entries were being stored with a **positive** `amount` everywhere in the app (`AdvancePaymentController::store()`/`update()`, rule 5.23) — a genuine pre-existing bug, since `advance_received` is money in the customer's favor and per Section 7's sign convention should be **negative**, same direction as `payment_received`/`credit_applied`. The bug was masked because the ledger's row **color** is keyed by `transaction_type` (a fixed map, always green for `advance_received`), not by raw sign — so it visually looked right until `LedgerController`'s raw `SUM(amount)` — which drives the "Outstanding Balance" card — read the balance backwards (a credit displaying as a red "Debit"). Fixed at both `AdvancePaymentController` write-sites and the new `OrderDeleteController` entry above; no changes needed to `LedgerController`, `ledger.blade.php`, `ledger-pdf.blade.php`, or the portal dashboard, since their sign-handling logic (color-by-type, prefix-by-raw-sign, displayed value always `abs()`) was already correct — only the stored sign was wrong. **One historical row per environment predates this fix** — no automatic data-migration was run; if an old `advance_received` row still shows the wrong sign in a given environment's ledger, it needs a manual one-off correction.

### 5.29 Refund Audit Trail — Customer-level Refunds Screen

**Purpose:** before this, a `Refund` created by Delete Order's full flow (rule 5.28) had no screen it could ever be viewed on again. `orders/show.blade.php` renders refunds via `$order->refunds` — unreachable once the order is hard-deleted. `orders/reductions/{reduction}` and the ledger's reduction modal are both keyed by `order_reduction_id`, which Delete Order always leaves `null`. And Delete Order's own `'refund'` branch deliberately writes **no** `CustomerLedger` row at all (see rule 5.28 — the order's ledger footprint is already zeroed by that point), so the refund was invisible everywhere except a direct query against the `refunds` table. This was discovered when an admin refunded a customer via Delete Order, attached a transfer slip, and then had no way to pull that slip back up to prove the refund happened if the customer later disputed it.

**Fix — a customer-level Refunds history, not an order-level or reduction-level one.** A refund is a refund regardless of which flow created it, so `customers/show.blade.php` now renders a single unified "Refunds" table (mobile card list + desktop table, same visual pattern as the existing Advance Payments History section) sourced from a new `Customer::refunds(): HasMany` relation — every `Refund` row for that `customer_id`, from either `OrderReductionController`'s surplus-refund branch or `OrderDeleteController`'s Delete Order flow, newest first. Gated by `!$hideFinancials` (hidden for `production_manager`, same as Ledger/Advance Payments) — no new route or middleware needed, since it's populated entirely through `CustomerController::show()`'s existing eager-load (`'refunds.refundedBy', 'refunds.order', 'refunds.reduction'`). Display-only: no edit/delete actions exist for a `Refund` row from either creation path, so none were added here either.

**The real problem: `refunds.order_id` goes `null` on delete, so "which order/catalogue was this for" is lost at the exact moment it matters most.** Migration `2026_07_23_000002_add_order_snapshot_to_refunds_table` adds two plain string columns — `order_number` and `catalogue_name` — snapshotted onto the `refunds` row **at creation time**, before the order can ever be deleted out from under it. Both `Refund::create()` call sites now populate these: `OrderReductionController::store()`'s surplus-refund branch and `OrderDeleteController::store()`'s `'refund'` branch. This mirrors the existing snapshot pattern used for `Payment::getOrderNumberAttribute()` (Section 10) — except here it has to be a **persisted column**, not a computed accessor, precisely because the live `order()` relation this refund's `order_id` points to may itself be gone by the time anyone looks.

**Source column logic on the Refunds screen:** if `order_reduction_id` is set and the order still resolves, links to `orders.reductions.show`; else if the order still resolves, links to `orders.show`; else (order hard-deleted) falls back to the snapshotted `order_number`/`catalogue_name` as plain text with a "(deleted)" tag — never a dead link.

**Backfill, including one historical edge case:** the migration backfills `order_number`/`catalogue_name` for all pre-existing refund rows whose `order_id` still resolves, via a straight `orders`/`catalogues` join. One row (a Delete Order refund created before this fix existed, where the order had already been deleted) had `order_id` already `null` with nothing to join against — but its `notes` column happened to contain the plain-text pattern `"Refund from deleted Order #{number}"` written by `OrderDeleteController` (see rule 5.28), so a second backfill statement recovers just the `order_number` for rows matching that pattern via `SUBSTRING_INDEX(notes, '#', -1)`. `catalogue_name` stays `null` for that one row — it was never captured anywhere else to recover from. Every refund created after this migration gets both fields populated correctly at creation time regardless of what happens to the order later.

### 5.30 Free Pieces — Advance Credit Auto-Applied to New-Customer Assignments

**Purpose:** close a gap in the Free Pieces feature (rule 5.28) — when the target of an assignment is a customer with no existing order on the catalogue, `FreePieceController::store()`'s `new_customer` branch creates a brand-new `Order` exactly like `PublicOrderController::submit()` does, but until now it stopped short of also running the advance-credit auto-apply step the public order form has run since rule 5.24. A customer sitting on `advance_credit_balance` from a prior catalogue would have that credit ignored — the new order was created with `total_paid = 0` and left in `received`, even though the credit could have covered some or all of it.

**Fix:** immediately after the new order's `order_charged` ledger entry is written (still inside the same `DB::transaction()` as the rest of the assignment), `FreePieceController::store()` now calls `AdvanceCreditAutoApplyService::apply($order, $customer)` — the identical call `PublicOrderController::submit()` makes. This applies `min(advance_credit_balance, order total)` as a real `advance`-type payment (own `sequence_number`, no ledger entry for the consumption, same convention as rules 5.19/5.24), decrements the customer's `advance_credit_balance`, and auto-confirms the order (`received` → `confirmed`) only if the applied amount exceeds `config('casualite.advance_credit_auto_confirm_threshold')` — otherwise it's left in `received` for manual review, same carve-out as rule 5.24. When a payment is auto-applied, a matching `activity()` "detail" log entry is written on the order (`"Payment #{order_number}p{n} of PKR X auto-applied from advance credit on Order #{order_number}"`), mirroring the log entry `PublicOrderController::submit()` writes.

**Scope — deliberately narrow:** this only applies to the `new_customer` branch, which is the only place in `FreePieceController` that creates a brand-new order. The `existing_order` branch (assigning free pieces onto an order that already exists) only increments that order's `order_items`/`total_amount`/`outstanding_balance` — it does not re-run advance-credit auto-apply, consistent with rule 5.24's original scope of firing only at order *creation*, never when an existing order's total is later increased (Piece Reassignment, Adjust Order, and Free Pieces' own `existing_order` branch all share this same non-behavior).

---

### 5.31 HD Gallery Downloads — Direct-to-S3 Redirect, Not a Proxied Stream

**Purpose:** fix two real customer-reported bugs in the HD Gallery's Download button (rule 5.27): (1) a "network error" appearing partway through downloading a large HD original (customers reported it around 5-6% progress), and (2) downloaded files sometimes looking low-resolution. Root-caused 2026-07-24 while investigating both.

**What was wrong with the original design:** rule 5.27, as originally built, deliberately proxied every download through `GalleryController::download()` via `Storage::disk('s3')->download($path, $filename)` — a same-origin stream — specifically so the public gallery's Download button could show an in-page progress bar (`fetch()` + `ReadableStream`, reading `Content-Length` to compute a percentage). That design had two flaws that only surfaced under real HD-file sizes (100MB-1GB, per rule 5.27):

1. **PHP's `max_execution_time` (60s in both `public/.htaccess` and `php.ini`) killed the response mid-stream** for any download slower than 60 seconds — which is routine for a large HD original on an ordinary customer connection. Response headers (including the full `Content-Length`) had already been sent by the time PHP was killed, so the browser's `fetch()`/`ReadableStream` loop detected the truncated body and threw — surfacing as the reported "network error," consistently early in the progress bar because 60 seconds' worth of bytes is a small, fairly repeatable fraction of a large file's total size.
2. **The "too low quality" complaint was not a wrong-file bug** — `s3_path` (the true original) was always what got served, never `thumbnail_path`. The likely explanation: customers hitting the network error above gave up on the broken Download button and instead right-clicked "Save Image As" on the gallery grid's preview `<img>` (`resources/views/public/gallery.blade.php`), which intentionally renders the client-generated thumbnail (700px long edge, JPEG quality 0.78) for fast page loads — a genuinely low-res asset when mistaken for a real download.

**The fix — two changes, in this order:**

1. **Dropped the in-page progress bar and its `fetch()`/`ReadableStream`/`Blob` JS entirely.** `gallery.blade.php`'s Download button is now a plain `<a href="{{ route('gallery.download', ...) }}" download="...">` — no Alpine `x-data`, no custom JS at all. The Alpine.js CDN `<script>` tag was also removed from this view since nothing on the page uses it anymore.
2. **`GalleryController::download()` no longer proxies bytes through PHP at all.** It now builds a short-lived (5-minute) presigned S3 GET URL via `Storage::disk('s3')->temporaryUrl($hdImage->s3_path, now()->addMinutes(5), ['ResponseContentDisposition' => 'attachment; filename="..."'])` and does a plain `redirect()->away($url)`. The browser follows the redirect and downloads directly from S3 — S3 itself sends the `Content-Disposition: attachment` header and filename via the signed `ResponseContentDisposition` override — with PHP completely out of the transfer path. This removes the `max_execution_time` risk outright (no PHP process is alive during the actual byte transfer), and also removes a secondary, smaller delay that was tried and rejected as a fix in isolation (see below).

**A middle-ground fix was tried first and rejected as insufficient:** before removing the proxy entirely, `Storage::disk('s3')->download()` was changed to pass `Content-Type`/`Content-Length` explicitly (from the `mime_type`/`file_size` columns already stored on `catalogue_hd_images` at upload time), specifically to skip Flysystem's separate `mimeType()`/`fileSize()` S3 HeadObject calls (`vendor/league/flysystem-aws-s3-v3/AwsS3V3Adapter.php:342,364`) that Laravel's `download()` otherwise makes before it can send any response headers. In production testing this did not visibly change the multi-second delay before the browser's download UI appeared, so the proxy approach was abandoned entirely in favor of the direct-redirect design above, rather than continuing to chase the remaining delay inside a PHP-proxied response.

**Why this is safe to do now, even though rule 5.27 originally ruled it out:** the *only* reason downloads were proxied in the first place was to let client-side JS read the response body for a progress bar without needing bucket CORS. Once that progress bar was removed (this rule), the original justification no longer applies — a plain top-level navigation to a signed URL needs no CORS at all (CORS only ever applies to `fetch()`/`XHR`, never to a browser's own navigation/download of a link), so bucket CORS configuration (`s3:configure-gallery-cors`, rule 5.27) is unaffected and still only needed for uploads.

**User-visible behavior change:** the gallery no longer shows an in-page download progress bar. Download progress is now shown via the browser's own native download UI (e.g. the browser's downloads tray/shelf) instead, the same way any other direct file download behaves.

**Thumbnails are unaffected** — they still render via plain `<img src="{{ Storage::url(...) }}">` tags straight to S3, exactly as before.

---

### 5.32 Recalculate Order Amount — Manual Re-Pricing Against Current Benchmark/Design Prices

**Purpose:** when a catalogue's `quantity_benchmark` is changed after orders have already been placed against it (e.g. the admin set it wrong initially and corrected it later), existing orders' `order_items.unit_price` can end up mismatched against what the current benchmark says they should be — some orders that should now qualify for the discount price are still charged the selling price, or vice versa. There is no automatic bulk correction and no stored "needs recalculation" flag anywhere — the admin manually triggers re-pricing per order via a "Recalculate Order Amount" button on the order show page.

**Route:** `orders.recalculate-price` (`POST /orders/{order}/recalculate-price`), `OrderPriceRecalculationController::store()`, admin-only (same access tier as Piece Reassignment — this touches ledger and advance-credit balances directly). Hidden entirely on `cancelled` orders (no financial obligation to recalculate).

**What it does, in one `DB::transaction()`, with the order and customer rows locked:**
1. For every `order_item`, recomputes the correct `unit_price` using the item's own current `total_qty` against the catalogue's **current** `quantity_benchmark` (the same strict `qty > benchmark` comparison used everywhere else — see note below) and the design's current `selling_price`/`discount_price`. Saves it — `OrderItem::booted()` recomputes `total_amount` from the unchanged `qty_*` columns.
2. Sums the new grand total and compares it against the order's stored `total_amount`. If they match, it's a no-op ("already correct, no changes needed") and nothing below runs — this makes the button safe to click repeatedly, and safe to click on an order some other flow (Adjust Order, Free Pieces) already silently fixed in the meantime.
3. Updates `order.total_amount` and `order.outstanding_balance` from the fresh totals.
4. **Adjusts the customer's `advance_credit_balance` by the incremental delta only, not the full recomputed surplus** — the same trap Payment Editing/Deletion already had to solve (rule 5.16/5.21/5.23): `oldSurplus = max(0, total_paid − oldTotal)`, `newSurplus = max(0, total_paid − newTotal)`, `creditDelta = newSurplus − oldSurplus`. A positive delta increments the balance; a negative delta (a price *increase* shrinking or eliminating an existing surplus) decrements it, floored at whatever's actually available — same as `PaymentController::reversePaymentContribution()` — with any unreclaimed shortfall reported back to the admin as a non-blocking warning rather than pushing the balance negative. Naively crediting the *entire* new surplus instead of just the delta would double-count whatever portion of that surplus was already auto-credited earlier (rule 5.17), since that original event left no separate ledger trace to subtract against.
5. **Adjusts the order's existing `order_charged` ledger line by the delta — never overwrites it with the new total outright.** Bypasses `CustomerLedger`'s immutability guard via `DB::table('customer_ledger')`, same as `OrderAdjustController` already does, targeting the oldest `order_charged` row for the order. Unlike `OrderAdjustController` (which sets that row's `amount` to the full new total), this sets it to `oldAmount + delta`. The distinction matters the moment an order has a **second** `order_charged` row — e.g. from a Piece Reassignment (rule 5.14) adding pieces onto it afterward — because the recomputed grand total already includes that second row's contribution (reassignment increases the same `order_item` rows' quantities rather than creating new ones). Setting the oldest row to the full new total while leaving the second row untouched would double-count the reassignment amount in the ledger sum; adjusting by delta keeps `SUM(order_charged rows) == order.total_amount` regardless of how many such rows exist. **`OrderAdjustController`'s "set to the full new total" approach has this same latent bug** — noticed while building this feature, not fixed as part of it since it's out of scope; worth knowing if Adjust Order is ever used on an order that previously received a Piece Reassignment addition.
6. Writes one `activity()` "detail" entry recording the benchmark used, old/new totals, and any credit adjustment or shortfall.

**No new ledger row is ever created or deleted, and no `Order`/`OrderItem` row is ever deleted and recreated** — everything is an in-place update of the same rows, so every other screen pointing at this order (invoice, portal, payments history, the ledger itself) keeps working unchanged.

**Deliberately does not auto-trigger off a catalogue's `quantity_benchmark` being edited.** No stored flag, no background job, no icon anywhere (a live-computed "needs recalculation" indicator was considered and explicitly rejected). The button is simply always available on a non-cancelled order and computes correctness live, every time it's clicked, against whatever the catalogue's benchmark and the designs' prices currently are — which also means it silently self-corrects for **any** cause of price drift, not just a benchmark change (e.g. a design's own `selling_price`/`discount_price` edited directly after the order was placed).

**Discount comparison is strictly `>`, not `>=` — this is existing, intentional behavior, not something this feature changed.** `PublicOrderController`, `OrderAdjustController`, `FreePieceController`, and the public order form's Alpine.js all already compare `qty > quantity_benchmark` (strict), not `qty >= quantity_benchmark`. This contradicts the "meets or exceeds" wording used in Section 2's discount-pricing description — the strict-`>` behavior was confirmed 2026-07-27 (while building this feature) to be the actually-intended rule: a customer ordering exactly the benchmark quantity does **not** get the discount. This feature reuses that same strict-`>` comparison for consistency. Section 2's wording should eventually be corrected to say "strictly exceeds," not "meets or exceeds" — not done as part of this change.

### 5.33 Staff Mobile Login — Embedded WebView, Not Native Screens

**Purpose:** the mobile app (Section 9's "Mobile API" work) was customer-only — `Api\AuthController::verify()` only ever matched against `Customer::portal_token`. The Casualite owner asked for CasualiteOS staff (`admin`, `accountant`, `production_manager`, `creative_head`) to also be able to sign in through the app. Staff do **not** get native mobile screens — the app has none built for them, and building role-scoped native UI would duplicate the Spatie role middleware that already governs every web screen. Instead, staff are hard-redirected into an embedded WebView showing the real CasualiteOS website, already logged in, so screen-by-screen visibility per role is inherited for free from the existing web middleware.

**Every `User` gets its own permanent `mobile_login_token`** (nullable unique string, `User::booted()`'s `creating` hook auto-generates a `Str::uuid()` on creation — same pattern as `Customer::portal_token`, migration `2026_09_09_000001` backfilled existing rows). Deliberately **not** named `portal_token` — that name is reserved for the customer-facing portal elsewhere in this codebase.

**`Api\AuthController::verify()` now tries two lookups, in order:** `Customer::portal_token` first (the existing hot path — unchanged), then on a miss, `User::mobile_login_token`. A `User` match (+ case-insensitive email match + `is_active`) returns `{"account_type": "staff", "redirect_url": "..."}` — **no Sanctum bearer token is issued for staff**, since they never call another `/api/*` endpoint. The existing success response also gained `"account_type": "customer"` as a clean discriminant for the app, additive and non-breaking. Any non-match — wrong email, disabled staff account, unknown token — returns the exact same vague 422 message the customer path always has, so a probing request can never tell whether a token belongs to a customer, a staff account, or a disabled one.

**The handoff is a single-use, ~90-second token, not the bearer token itself.** `staff_mobile_login_tokens` (migration `2026_09_09_000002`: `user_id`, unique `token_hash`, `expires_at`, nullable `used_at`, `ip_address`, `user_agent`) stores only a SHA-256 hash of the raw token — never the raw value — at rest, same convention as `customer_devices`. `Api\AuthController::buildStaffRedirectUrl()` generates the raw token, stores its hash with `expires_at = now()->addSeconds(config('casualite.staff_mobile_login_token_ttl'))` (default 90s, env `STAFF_MOBILE_LOGIN_TOKEN_TTL`), and returns `rtrim(config('casualite.web_app_url'), '/') . '/mobile-login/' . $rawToken` (`WEB_APP_URL` env, defaults to `https://casualiteos.com`).

**`MobileLoginController::consume()` (`GET /mobile-login/{token}`, route `mobile-login.consume`, public — this is a plain web controller, not under the `Api\` namespace, since it starts a real Laravel session rather than returning JSON) is what the app's WebView actually opens.** Inside one `DB::transaction()`: hashes the incoming token, `lockForUpdate()`s the matching row (closes the race of the same link being opened twice concurrently), rejects to `route('login')` with an error if the row is missing, already `used_at`, or `expires_at` has passed. **Marks `used_at` immediately — before checking anything else** — so the token can never be replayed even if a later check (e.g. `is_active`) rejects it. Only then re-checks the linked `User`'s `is_active` (defense in depth against the account being disabled in the gap between token issuance and the staff member opening the WebView), then `Auth::login($user)` + `session()->regenerate()` + `last_login_at` update + `redirect()->intended(route('dashboard'))` — mirrors the tail of the existing web `AuthController::login()`.

**From that point on, this is just the normal website inside a WebView** — no new permission code exists or is needed. The mobile-app side (separate repo `casualite-app`) opens `redirect_url` in an embedded `react-native-webview`, and intercepts navigation back to `/login` (detected via `onShouldStartLoadWithRequest`, which fires before the destination renders) to know the staff member logged out inside the WebView — at which point it clears the WebView's cookie jar and returns to the app's own native login screen, rather than letting the website's own login form render inside the app. See `casualite-app/CLAUDE.md` for the app-side detail; this repo's contract ends at `MobileLoginController::consume()` redirecting into `dashboard`.

**Tests:** `tests/Feature/Api/AuthTest.php` covers the staff branch of `verify()` (no Sanctum token issued, correct `redirect_url` shape, hashed-not-raw storage, inactive-user rejection, email-mismatch rejection). `tests/Feature/MobileLoginTest.php` covers `consume()` (valid token logs in and redirects to dashboard, reused token rejected and never logs in twice, expired token rejected, unknown token rejected, a user disabled between issuance and consumption rejected with the disabled-account message — the token is still consumed even on this rejection, so it can't be retried once the account is reactivated). Both extend this project's existing hand-built-schema pattern (`RefreshDatabase` is unsafe here — see the note on `tests/Feature/Api/AuthTest.php`).

### 5.34 Customer Self-Signup — Mobile App, Pending Admin Approval

**Purpose:** until now the only way for a customer to exist in CasualiteOS was for admin/accountant to create the record manually (`CustomerController::store()`), then hand the customer their `portal_token` link out of band. There was no path for someone who found the mobile app on their own to get in. This adds self-service signup **without** letting the app create a `Customer` directly — the submission queues a `customer_signup_requests` row for admin review; only an explicit Approve creates the real `Customer`.

**Why a separate table, not a status column on `customers`:** `customers.created_by` is a required, non-nullable FK to `users`, and the `Customer` model has no status/approval concept anywhere. Rather than reworking that FK or bolting an approval state onto a table that's meant to represent real, live customers, `customer_signup_requests` (migration `2026_09_09_000003`) is a parallel table — `name`, `contact_number`, `city`, `country`, `address` (nullable), unique `email`, `status` (`pending`|`approved`|`rejected`, default `pending`), nullable `customer_id` FK (null-on-delete, set on approval), nullable `reviewed_by` FK to `users` (null-on-delete) and `reviewed_at`. `email` is unique so there is exactly **one row per email ever** — a rejected request is updated back to `pending` in place on resubmission rather than accumulating duplicate rows.

**`POST /api/auth/signup` (`api.auth.signup`, public, no auth)** — a new method on the existing `Api\AuthController` (kept alongside `verify()` since both are "how does an unauthenticated person get into the system" entry points). Validation is field-for-field identical to `CustomerController::store()` (including `Customer::COUNTRIES` for the `country` check), minus `unique:customers,email` — that case is handled explicitly so the response can distinguish "you're already a customer" from "you already have a pending request" rather than a generic validation error:
1. If a `Customer` already exists for that email (or a `CustomerSignupRequest` row is already `approved`, a defensive edge case) → `422`, `{"message": "An account already exists for this email. Please contact Casual Lite for your portal link."}`
2. If a `CustomerSignupRequest` for that email is already `pending` → `200`, `{"status": "pending", "message": "You already have a signup request pending review."}` — idempotent, no duplicate row.
3. Otherwise (brand new email, or a previously `rejected` row) → `CustomerSignupRequest::firstOrNew(['email' => ...])` is filled with the new data, `status` reset to `pending`, `customer_id`/`reviewed_by`/`reviewed_at` cleared, and saved. `201`, `{"status": "pending", "message": "Your details have been submitted. Casual Lite will review them and send you your portal link once approved."}`

**No status-polling endpoint was added** (e.g. `GET /api/auth/signup-status`) — deliberately out of scope. The customer is told to wait; admin still shares the portal link manually via WhatsApp once approved, exactly as they already do for admin-created customers. No auto-login and no push notification is tied to approval, since the customer has no account (and therefore no bearer token or push subscription) to notify until admin approves.

**Admin screen — `PendingSignupController`** (`app/Http/Controllers/PendingSignupController.php`, routes `pending-signups.index`/`approve`/`reject`, `role:admin` only — pure route-middleware guard, no inline `denyCreativeHead()`-style check, same trust-the-middleware precedent as `AnnouncementController`). `index()` shows two lists: pending requests with Approve/Reject actions, and a read-only history of already-reviewed ones. Both `approve()` and `reject()` `abort_unless($signup->status === 'pending', 404)` — a request can only be decided once; there is no "undo" or re-review.

**`approve()`, inside one `DB::transaction()`:**
- Creates a real `Customer::create([...])` from the signup row's fields, with **`created_by` set to the approving admin's own user id** — not the customer, and not left null. This reuses the existing non-nullable FK with no schema change: the admin clicking Approve is, in a real sense, the staff member responsible for the account existing, exactly as if they'd typed the same details into the manual create form themselves. `Customer::booted()`'s `creating` hook auto-generates `portal_token` exactly as it does for a manually-created customer — nothing about the portal/mobile-login path downstream needed to change.
- Writes the same manual `activity()->performedOn($customer)->causedBy(Auth::user())->event('detail')->withProperties([...])->log(...)` entry `CustomerController::store()` writes, so the customer's activity history reads the same either way (just with a different log line noting it came from a signup request).
- Updates the `customer_signup_requests` row: `status = 'approved'`, `customer_id`, `reviewed_by`, `reviewed_at`.
- Redirects to `customers.show` (not a new "here's the link" page) — that page already renders the portal link (`customers/show.blade.php` links `route('portal.show', $customer->portal_token)`), so approval drops the admin exactly where they need to be to copy the link and message it, reusing existing UI rather than building a duplicate.

**`reject()`** — sets `status = 'rejected'`, `reviewed_by`, `reviewed_at`. No `Customer` row is ever created on this path. No rejection-reason field exists; Reject goes through the global `$store.confirm.show({danger: true})` modal (per-row hidden form, same pattern as `users/index.blade.php`'s Disable button) as its only confirmation step.

**Sidebar:** a "Pending Signups" link in the System section (`resources/views/layouts/app.blade.php`, same `@if($r === 'admin')` block as Cron Logs/Announcements), with a small red count badge showing `CustomerSignupRequest::where('status', 'pending')->count()` — computed only inside the admin branch so no extra query runs for other roles.

**Tests:** `tests/Feature/Api/AuthTest.php` extends its existing hand-built-schema pattern with a `customer_signup_requests` table and covers `signup()`: new request creates a pending row, duplicate email (already a customer) is rejected, resubmitting while pending doesn't duplicate the row, a rejected request can be resubmitted and reuses the same row, and country validation against `Customer::COUNTRIES`.

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
Dispatch (batch-wise, no payment requirement — outstanding balance does not block dispatch, see rule 5.2 — deducts packed inventory)
    → Order status = dispatched only when fully dispatched
```

### Naeem Pakki — key rules

- `needs_naeem_pakki` is set on the **Design** at catalogue creation time, not at assignment time.
- Naeem Pakki sends and returns are **piece-based only** — no size breakdown. Sizes are irrelevant until stitching.
- **`NaeemPakkiSend` / `naeem_pakki_sends` is dead code — do not read from it.** It exists in the schema and as a model but has **zero rows** and no code path writes to it. The real per-design quantity and per-piece rate for each Naeem Pakki batch live on **`production_assignment_np_designs`** (model `ProductionAssignmentNpDesign`), created when a `ProductionAssignment` with `destination = 'naeem_pakki'` is saved — see "Production Assignments for NP" below. This was discovered 2026-07-14 while building Cost Estimation (rule 5.25): the feature was first built against `NaeemPakkiSend` per an earlier (wrong) draft of this file, silently returned no data for every real design, and the bug only surfaced because a design with genuine NP history was tested. Any future code that needs "how much was sent to Naeem Pakki for this design, at what rate" must query `ProductionAssignmentNpDesign::where('design_id', ...)`, **not** `NaeemPakkiSend`.
- A single design can have **multiple NP batches at different rates** (e.g. 385 pcs @ PKR 42 in one assignment, 203 pcs @ PKR 10 in another) — never assume one flat rate for a design. Sum each batch's own `quantity × per_piece_price` individually before adding; do not multiply a total quantity by a single rate.
- `naeem_pakki_returns` has **no `quantity` column** — totals are computed from `naeem_pakki_return_items`. Each return batch has a header row (`naeem_pakki_returns`) and one `naeem_pakki_return_items` row per design returned (`np_design_id` + `quantity`, where `np_design_id` FKs to `production_assignment_np_designs`).
- **Production Assignments for NP:** Production Manager uses the New Assignment form, selects Naeem Pakki as destination, and sees a table of all NP-eligible designs. Can assign multiple designs at once, each with qty + rate. One `ProductionAssignment` record is created per design, plus one `ProductionAssignmentNpDesign` row holding that design's `quantity` and `per_piece_price` for this batch. Quantity is also stored as a single `production_assignment_items` row with `size = 'np'`.
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
| `portal.show`    | `GET /portal/{token}`         | Customer portal (email entry, or dashboard if the device cookie resolves) |
| `portal.verify`  | `POST /portal/{token}/verify` | Portal email verification      |
| `portal.manifest`       | `GET /portal/{token}/manifest.json`             | Per-customer PWA manifest (`start_url`/`scope` point at this customer's own portal URL) |
| `portal.push-subscribe` | `POST /portal/{token}/push-subscribe`           | Store/update the browser's push subscription (device-cookie gated) |
| `dispatch.store`         | `POST /dispatch/{order}`                        | Record a dispatch batch              |
| `dispatch.sack-label`    | `GET /dispatch/{order}/sack-label`              | Download sack label PDF              |
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
| `orders.destroy`            | `DELETE /orders/{order}`                           | Hard-delete order — fast path (admin + accountant) |
| `orders.delete.create`      | `GET /orders/{order}/delete`                       | Delete Order form — refund/credit choice, full flow (admin + accountant) |
| `orders.delete.store`       | `POST /orders/{order}/delete`                      | Save deletion — settles refund/credit, frees pieces to the pool (admin + accountant) |
| `free-pieces.index`         | `GET /free-pieces`                                 | Free Pieces pool grid for the active catalogue (admin + accountant) |
| `free-pieces.assign`        | `GET /free-pieces/assign`                          | Assign Free Pieces form (admin + accountant)      |
| `free-pieces.store`         | `POST /free-pieces/assign`                         | Save a free-pieces assignment (admin + accountant) |
| `orders.payments.destroy`   | `DELETE /orders/{order}/payments/{payment}`        | Delete a payment (admin + accountant)     |
| `orders.payments.edit`      | `GET /orders/{order}/payments/{payment}/edit`      | Edit Payment form (admin + accountant)    |
| `orders.payments.update`    | `PUT /orders/{order}/payments/{payment}`           | Save edited payment (admin + accountant)  |
| `og.image`                  | `GET /og-image/{token}`                            | Proxy catalogue OG image through app domain (public, no auth) |
| `country-pricing.index`     | `GET /country-pricing`                             | Country Pricing screen (admin only)       |
| `country-pricing.store`     | `POST /country-pricing/{catalogue}`                | Save country prices for a catalogue's designs (admin only) |
| `dispatch.print-tags`       | `GET /dispatch/{order}/print-tags`                 | Download piece-tag barcode PDF (one 2"x1" label per piece) |
| `tags.scan`                 | `GET /tags/{barcode}`                              | Public barcode scan result (no auth)      |
| `dispatch-optimizer.index`  | `GET /dispatch-optimizer`                          | Recommended dispatch allocation across pending orders (advisory only, admin + production_manager + creative_head) |
| `advance-payments.store`    | `POST /customers/{customer}/advance-payments`      | Record an advance payment for a customer (admin + accountant) |
| `advance-payments.edit`     | `GET /customers/{customer}/advance-payments/{advancePayment}/edit` | Edit Advance Payment form (admin + accountant) |
| `advance-payments.update`   | `PUT /customers/{customer}/advance-payments/{advancePayment}`      | Save edited advance payment (admin + accountant) |
| `advance-payments.destroy`  | `DELETE /customers/{customer}/advance-payments/{advancePayment}`   | Delete an advance payment (admin + accountant) |
| `designs.cost-estimation.edit`   | `GET /designs/{design}/cost-estimation`     | Cost Estimation form (admin + production_manager + creative_head, in-house designs only) |
| `designs.cost-estimation.update` | `POST /designs/{design}/cost-estimation`    | Save cost estimation (admin + production_manager only) |
| `designs.cost-estimation.pdf`    | `GET /designs/{design}/cost-estimation/pdf` | Download Cost Estimation PDF (same access as edit, only once saved) |
| `hd-gallery.index`               | `GET /hd-gallery`                           | Sidebar entry point — redirects to the active catalogue's HD Gallery |
| `catalogues.hd-images.index`     | `GET /catalogues/{catalogue}/hd-images`     | HD Gallery management screen (admin + creative_head only) |
| `catalogues.hd-images.presign`   | `POST /catalogues/{catalogue}/hd-images/presign` | Issue a presigned S3 PUT URL for a direct browser upload |
| `catalogues.hd-images.store`     | `POST /catalogues/{catalogue}/hd-images`    | Register an HD image already uploaded to S3 |
| `catalogues.hd-images.destroy`   | `DELETE /catalogues/{catalogue}/hd-images/{hdImage}` | Hard-delete an HD image (S3 + DB) |
| `gallery.show`                   | `GET /gallery/{token}`                      | Public HD gallery (no auth) — shared with customers |
| `gallery.download`               | `GET /gallery/{token}/images/{hdImage}/download` | Redirects to a short-lived presigned S3 URL (direct download, no app-side streaming — rule 5.31) |
| `production-alerts.resolve`      | `POST /production-alerts/{alert}/resolve`   | Mark a stale-assignment alert as resolved (admin + production_manager only) |
| `orders.recalculate-price`       | `POST /orders/{order}/recalculate-price`    | Re-price an order against the catalogue's current benchmark/design prices (admin only, see rule 5.32) |
| `announcements.index`            | `GET /announcements`                        | Timeline/Announcements admin compose screen + history (admin only, see "Timeline / Announcements" under Section 9) |
| `announcements.store`            | `POST /announcements`                       | Send an announcement to every customer (admin only) |
| `api.auth.verify`                | `POST /api/auth/verify`                     | Mobile app login — portal_token + email, issues a Sanctum bearer token (public, no auth) |
| `api.auth.logout`                | `POST /api/auth/logout`                     | Revokes only the bearer token used for the request; other devices stay signed in (`auth:sanctum`) |
| `mobile-login.consume`           | `GET /mobile-login/{token}`                 | Consumes a staff member's single-use handoff token from `Api\AuthController::verify()`'s staff branch, starts a real Laravel web session (public, no auth — see rule 5.33) |
| `api.me`                         | `GET /api/me`                               | Returns the authenticated customer's profile (`auth:sanctum`) |
| `api.orders.index`               | `GET /api/orders`                           | Authenticated customer's order history, newest first (`auth:sanctum`) |
| `api.orders.show`                | `GET /api/orders/{order}`                   | One order's full detail — 404s (not 403) if it isn't the authenticated customer's own (`auth:sanctum`) |
| `api.orders.store`               | `POST /api/orders`                          | Place an order via `OrderPlacementService::place()` (`auth:sanctum`, see rule under Section 9) |
| `api.ledger.index`               | `GET /api/ledger`                           | Authenticated customer's ledger entries (`auth:sanctum`) |
| `api.announcements.index`        | `GET /api/announcements`                    | Authenticated customer's announcement/Timeline history, newest first (`auth:sanctum`) |
| `api.announcements.read`         | `POST /api/announcements/{id}/read`         | Marks one announcement read, scoped to the authenticated customer (`auth:sanctum`) |
| `api.catalogues.index`           | `GET /api/catalogues`                       | Catalogues currently open for ordering (`auth:sanctum`) |
| `api.catalogues.show`            | `GET /api/catalogues/{catalogue}`           | One catalogue's designs + pricing/availability (`auth:sanctum`) |
| `api.catalogues.quote`           | `POST /api/catalogues/{catalogue}/quote`    | Live pricing preview via `OrderPlacementService::quote()`, no writes (`auth:sanctum`) |
| `api.push-tokens.store`          | `POST /api/push-tokens`                     | Register/reassign an Expo push token for the authenticated customer (`auth:sanctum`) |
| `api.push-tokens.destroy`        | `DELETE /api/push-tokens`                   | Unregister a token, e.g. on logout (`auth:sanctum`) |
| `api.auth.signup`                | `POST /api/auth/signup`                     | Mobile app self-signup — queues a `customer_signup_requests` row for admin review, never creates a `Customer` directly (public, no auth — see rule 5.34) |
| `pending-signups.index`          | `GET /pending-signups`                      | Pending signup review queue (admin only, see rule 5.34) |
| `pending-signups.approve`        | `POST /pending-signups/{signup}/approve`    | Approve a signup — creates the real `Customer` (admin only) |
| `pending-signups.reject`         | `POST /pending-signups/{signup}/reject`     | Reject a signup — no `Customer` is created (admin only) |

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
- **"From Advance Credit" always available + split-payment handling** (2026-07-12): The "From Advance Credit" option in the Record Payment dropdown (`orders/show.blade.php`) is no longer hidden when `customer.advance_credit_balance` is 0 — it is always shown, with the available amount displayed inline only when the balance is greater than 0. `PaymentController::store()`'s `advance` branch no longer requires the entered amount to fit within the available balance: any amount beyond `advance_credit_balance` is automatically split into a credit portion (consumes the balance, no ledger entry, existing convention) and a payment portion (recorded as a `payment_received` ledger entry, same as cash/bank transfer). The rule 5.17 overpayment-surplus check now runs unconditionally for advance payments (fixed a bug where it was incorrectly nested inside the payment-portion branch, causing overpayment surplus to be lost when the entire amount was covered by credit). A live Alpine.js preview below the Amount field shows the credit/payment split before the accountant submits. See rule 5.19 for full detail.
- **Customer Country + Address fields** (2026-07-13, country list expanded 2026-08-18 per commit `ff4b9ec`): `customers.country` (required, fixed dropdown list — Australia, Bangladesh, Canada, Kuwait, Oman, Pakistan, Qatar, Saudi Arabia, UAE, UK, USA — the countries Casualite dispatches to, originally just the first 7 at launch; type-to-filter combobox built with Alpine.js, no native `<select>`; backed by `Customer::COUNTRIES` const, validated server-side with `in:`) and `customers.address` (optional free-text street address, `nullable|string|max:255`) both added via migration `2026_07_13_000001_add_country_to_customers_table`. Field order on `customers/create.blade.php` and `customers/edit.blade.php`: Full Name, Email, Contact Number, **Address**, City, **Country**. Country is shown on the Customer show page, the Orders List (`orders/index.blade.php`), and 5 reports — Customer Master List, Receivables by Bank, Bank Account Breakdown, Customer Order Bill, Bank Collection (web + PDF + Excel exports for all 5). Address is shown **only** on the Customer show page — deliberately excluded from Orders List and every report; do not add an Address column anywhere else without asking first. `Customer::COUNTRIES` is the single source of truth for the fixed country list — do not duplicate the array in Blade views or elsewhere.
- **Dispatch — Sack Label PDF download** (2026-07-13): `DispatchController::sackLabel()` (route `dispatch.sack-label`, `GET /dispatch/{order}/sack-label`, same access group as the rest of dispatch: admin, production_manager, creative_head) generates an A4 PDF (`production/dispatch/sack-label-pdf.blade.php`) showing the order's Customer Name, Contact Number, Address, City/Country, plus two blank boxes — **Sack #** and **Total Pieces** — left empty for staff to fill in by hand after physically packing a sack. Purpose: a single order can be split across multiple sacks, so the piece count per sack isn't known to the system in advance; the printed label is filled in and taped onto the sack. The download is triggered by a PDF icon next to the customer name on the Dispatch index page (both the mobile card list and desktop table) — **not** on the Dispatch show/detail page. Staff download it as many times as needed, one per sack.
- **Piece Tags & Country Pricing** (2026-07-13): New per-design, per-country pricing (`design_country_prices` table, admin-only Country Pricing screen scoped to the active catalogue) feeds a new barcode tag system. `PieceTag` records (`piece_tags` table, unique per order+design+size, numeric Code128C barcode derived from its own id, price/country snapshotted at creation) are created lazily when the admin clicks the new blue Print Tags icon on the Dispatch index (next to the existing red sack-label icon, both mobile and desktop). Generates one 2"×1" PDF page per physical piece, sized for the Zebra TLP2844 label roll, using `picqer/php-barcode-generator`. Validates the customer has a country set and every design in the order has a price for that country before generating anything. Scanning a tag's barcode hits a public route (`tags.scan`) showing Casualite, customer name, catalogue name, design name, and size. See rule 5.20 for full detail, including two dompdf small-page-size layout bugs worth reading before touching the label template again.
- **Dispatch Optimizer** (2026-07-14): `DispatchOptimizerService` recommends how to split current packed inventory across pending orders in the active catalogue (route `dispatch-optimizer.index`, view `production.dispatch-optimizer.index`, same access as the rest of Dispatch: admin, production_manager, creative_head). Advisory only — it does not create any dispatch batches itself; each recommended order links straight to its existing `dispatch.show` page where staff record the batch as normal. Candidate orders exclude `dispatched`/`cancelled` orders and any order with `outstanding_balance > 0` (rule 5.2 blocks these from being dispatched anyway, so recommending them would be unactionable). The first design attempted was a whole-order selection (pick a subset of whole orders whose combined demand exactly zeroes out packed inventory per design+size) — this is a 0/1 multidimensional-knapsack problem, NP-hard, and testing against real data (Azzurra) showed it recommends **zero** orders in practice, because every order demands a piece from every design in the catalogue (see "How Orders Work" in Section 2) while different designs deplete unevenly, so almost no whole order can ever be fully satisfied. The shipped design instead allocates per design+size cell independently: since dispatch is already partial per order in this system (`partially_dispatched` status, batch-wise dispatch), the most that can ever be dispatched from a cell is `min(available stock, combined demand for that cell)`, reached deterministically by handing out the stock until it or the demand runs out — no search needed, and always maximal. When a cell's demand exceeds supply, the remaining stock goes to the oldest order first (by `submitted_at`) — this is an assumption made for the first version, not a confirmed business rule, and would be the first thing to revisit if priority should instead depend on order size, customer tier, or something else.
- **Payment IDs — sequential per order** (2026-07-14): `payments.sequence_number` (nullable int, unique per `(order_id, sequence_number)`) gives every payment a stable ID in the format `{order_number}p{n}` (e.g. `#1005342p2`), computed on the fly from the column plus the order's `order_number` — never stored as a string. Assigned via `MAX(sequence_number) WHERE order_id=X, + 1` under a row lock in `PaymentController::store()`; deletion leaves gaps, numbers are never reused. Backfilled for all historical payments via migration `2026_07_14_000001_add_sequence_number_to_payments_table`, ordered by `payment_date` then `id`. Displayed on the order show page's Payments History, the invoice PDF, the customer portal, the customer ledger (page + PDF), and the Reports → Customer Ledger screen — scoped to `payment_received`-type rows in ledger contexts. See rule 5.22 for full detail, including a pre-existing unrelated bug found (and deliberately left unfixed) on the Reports Customer Ledger page.
- **Payment Editing** (2026-07-14): `PaymentController::edit()` / `update()` (`orders.payments.edit` GET + `orders.payments.update` PUT, admin + accountant only, same access as Delete). Edits reuse the same `payments` row and `sequence_number` — never delete-and-recreate — so the Payment ID (`#{order_number}p{n}`) stays stable across corrections. Implemented as reverse-old-then-reapply-new inside one transaction, sharing a new private `reversePaymentContribution()` helper with `destroy()`. Fixed alongside: `destroy()` previously restored the *full* amount of a deleted `advance`-type payment to `advance_credit_balance`, even when part of it had been a genuine payment (rule 5.19 split) — it now restores only the credit portion (derived from the linked ledger entry) and also reverses any surplus the payment contributed, which the old code skipped entirely for advance-type deletions. See rule 5.21 for full detail, rule 5.16 for the deletion-side fix, and the rule 5.2 addendum for the dispatched-order interaction.
- **Advance Payments (standalone)** (2026-07-14): New `advance_payments` table plus `AdvancePaymentController` (store/edit/update/destroy, admin + accountant only) lets money be recorded against a customer directly, with no order involved, filling in the previously dormant `advance_received` ledger type. UI: "Record Advance Payment" form plus an "Advance Payments" history table on the customer show page. Store creates an `advance_received` ledger entry and increments `customer.advance_credit_balance`. Edit/delete reverse-then-reapply against that same balance, floored at 0 rather than going negative when some of the credit has already been spent elsewhere (e.g. applied to an order since), with an amber, non-blocking warning shown to the accountant whenever that floor kicks in. No changes were required to the customer ledger, ledger PDF, or portal dashboard views: all three already rendered `advance_received` entries correctly, just had no data feeding them until now. See rule 5.23 for full detail.
- **Advance Credit Auto-Applied to New Orders** (2026-07-14): `AdvanceCreditAutoApplyService` is called from `PublicOrderController::submit()` right after order creation — if the customer holds `advance_credit_balance`, `min(balance, order_total)` is automatically recorded as a real advance-type `payments` row (own sequence number, `logged_by = null`, notes = `Auto-applied from advance credit balance`), no manual accountant step required. If the applied amount exceeds `config('casualite.advance_credit_auto_confirm_threshold')` (PKR 50,000 by default, sourced from `.env` via the new `config/casualite.php` — the project's first non-stock config file, introduced so this kind of business constant is a one-place edit), the order auto-confirms; otherwise it stays `received` for manual review — a deliberate carve-out from the general "any payment auto-confirms" rule that applies only to this automatic path. `payments.logged_by` was made nullable (migration `2026_07_14_000003_make_logged_by_nullable_on_payments_table`) to support this. See rule 5.24 for full detail.
- **Cost Estimation** (2026-07-14): `CostEstimationController` (`designs.cost-estimation.edit`/`update`/`pdf`) — per-design cost breakdown across 9 categories (`cost_estimations` + `cost_estimation_items` tables), replacing the paper Cost Estimation Sheet. Production Qty (from `fabric_batch_items`), Stitched By (from `StitchingReturn`, distinct units ordered by number), and Pakki Embroidery (from `ProductionAssignmentNpDesign`, summed per-batch since rates can differ between batches) are all system-derived and locked — the production manager only enters Particulars/Avg/Qty/Rate on the other 8 categories, and Amount is always server-computed as `qty × rate`, never accepted from the client. PDF export via the same `dompdf` pattern as the invoice/sack-label, with a download icon on the design card once `per_unit_cost` is saved. Building this surfaced that `NaeemPakkiSend`/`naeem_pakki_sends` (previously documented in Section 6 as the live NP-tracking table) is actually dead code with zero rows — the real source is `production_assignment_np_designs`; Section 6 has been corrected accordingly. See rule 5.25 for full detail.
- **HD Image Gallery (per-catalogue)** (2026-07-16, portal auto-share follow-up 2026-07-17, download flow reworked 2026-07-24): `catalogue_hd_images` table + `catalogues.hd_gallery_token` (generated alongside `order_token`) let admin/creative_head upload and delete full-resolution photos per catalogue, shared with customers via a public no-auth link (`gallery.show`) styled like Unsplash — masonry grid, per-file upload progress. Uploads go directly from the browser to S3 via presigned PUT (`Storage::disk('s3')->temporaryUploadUrl()`) — the first use of presigned URLs in this codebase — since a single gallery can hold 4-5GB and this app's shared hosting can't proxy that through PHP. Thumbnails are generated client-side (canvas, not GD) for the same shared-hosting-memory reason. **Requires a one-time per-environment step:** `php artisan s3:configure-gallery-cors` must be run before uploads will work (sets the bucket's CORS policy for the presigned PUT's preflight) — this was not run automatically as part of this change. **Downloads no longer proxy through the app** — after a production bug where PHP's `max_execution_time` killed large downloads mid-stream (customers saw a "network error" partway through), the download button was reworked to redirect straight to a presigned S3 URL instead, dropping the in-page progress bar in the process. See rule 5.31 for the full incident and fix. As of 2026-07-17, the gallery link is also auto-surfaced (no manual sharing needed) as an "HD Photos" link on every order card in the customer portal, unconditionally, whether or not that catalogue has any images uploaded yet. See rule 5.27 for full detail.
- **Customer Portal PWA + Push Notifications** (2026-07-15/16): Customer portal is now installable (per-customer manifest at `portal.manifest`, `public/sw.js`, custom full-screen splash) and pushes a browser notification (`OrderStatusChanged` via `laravel-notification-channels/webpush`) on `confirmed`/`stitching`/`partially_dispatched`/`dispatched`/`cancelled` — never on `received` or a status revert. Persistent login on installed devices via a `customer_devices` table + 400-day `portal_device` cookie, so a returning customer skips the email-verification step. `queue:work` runs via the existing `schedule:run` cron tick (no persistent process, since this is shared hosting) rather than a supervisor-managed worker. `php artisan notify:test-push {email} {status} {--order=}` sends a real test push without mutating order data, for production QA. A 2026-07-16 follow-up fixed the subscribe flow (`pushOptIn()` in `dashboard.blade.php`) hanging indefinitely on a real customer's device — `Notification.requestPermission()` and `navigator.serviceWorker.ready` now race against a timeout (20s / 10s) and the `error` state is clickable to retry. See rule 5.26 for full detail.
- **Production Manager — Customer view/edit access** (2026-07-17): `production_manager` can now view and edit customer records (`customers.index`/`show`/`edit`/`update`), matching a new client requirement — but cannot create or delete customers, and all financial data on the customer pages is hidden for this role (Advance Credit, order Amounts, Ledger button, Record Advance Payment form, Advance Payments History), via a `$hideFinancials` flag on `CustomerController::index()`/`show()`, the same pattern already used for Orders. Routes were split: `customers.index`/`show`/`edit`/`update` now allow `admin|accountant|production_manager`, while `customers.create`/`store` and all ledger/advance-payment routes stay `admin|accountant` only. Sidebar nav moved "Customers" and "Orders" into the Sales section for `production_manager` (previously Orders was duplicated under Production and Customers wasn't shown at all), matching admin/accountant's layout and ordering. See "Production Manager — Customer Access" in Section 3 for full detail.
- **Production Assignment Alerts** (2026-07-21): New `production_alerts` table + `ProductionAlert` model catch the real-world cause behind most Production Tracker "Size Mismatch" badges — a customer's order gets changed (Adjust Order, Log Reduction, or the auto-cancellation Log Reduction can trigger when it zeroes an order out) *after* the production manager has already committed that design's fabric to a stitching assignment, so the size split baked into the assignment silently no longer matches current demand. Previously this was only discoverable by chance, by someone opening Production Tracker later and spotting the badge. `ProductionAssignmentAlertService::checkOrder()` — called from `OrderAdjustController::store()` (checks every design in the order, since Adjust Order applies one uniform size split across all designs at once) and `OrderReductionController::store()` (checks only the reduced designs) — reuses the exact same "fully committed" gate `ProductionTrackerController` uses for its Size Mismatch check (`fabricQty > 0 && assignedQty >= fabricQty`, summed from `fabric_batch_items` and `production_assignment_items`, in-house designs only) to decide whether to fire. Skips creating a duplicate if an unresolved alert already exists for that catalogue+design, so repeated changes before anyone reacts don't pile up. Surfaced as a red banner at the top of Production Tracker (`ProductionTrackerController::index()` loads unresolved alerts for the active catalogue) rather than only the existing per-row badge; resolved via `production-alerts.resolve` (`ProductionAlertController::resolve()`, admin + production_manager only — `creative_head` sees the banner but not the Resolve button, via the standard `denyCreativeHead()` guard). Deliberately does not (yet) cover Piece Reassignment, which can also shift order demand — out of scope for this round. **Auto-resolve (2026-07-23):** `ProductionTrackerController::index()` now re-checks each unresolved alert's design against that design's live Size Mismatch (the same computation driving the per-row badge) on every page load — if the mismatch has cleared (e.g. a later Free Pieces reassignment brought the size split back in line), the alert auto-resolves itself (`resolved_by` left `null` to distinguish from a manual click) instead of waiting for someone to press Resolve. The manual Resolve button still exists for cases the live check can't see.
- **Order Deletion — Free Pieces Pool** (2026-07-22/23): Delete Order was split into two paths — the existing one-click fast-path hard-delete (rule 5.15, unchanged) for `received`+unpaid orders with no reduction/refund history, and a new full-flow `OrderDeleteController` (`orders.delete.create`/`orders.delete.store`, admin + accountant) for any other non-dispatched/non-partially-dispatched order, which settles refund/credit for money paid and pushes the order's freed per-design/per-size quantities into a new standing `free_pieces` pool instead of requiring an immediate reassignment decision. A new `FreePieceController` (`free-pieces.index`/`assign`/`store`, Sales sidebar section, admin + accountant) lets pooled pieces be assigned later to an existing order or a brand-new order for a customer without one. Building this surfaced and fixed a real pre-existing bug: `advance_received` ledger entries (`AdvancePaymentController`) were stored with the wrong (positive) sign. See rule 5.28 for full detail.
- **Activity Log — descriptive deleted-record entries** (2026-07-23): `Order` and `Payment` now render a full narrative Description for their hard-delete activity-log rows — e.g. "Order #1005395 on the SAKOON catalogue from FIVE CLOTH has been deleted" / "Payment #1005395p1 on the Order #1005395 on the SAKOON catalogue from FIVE CLOTH has been deleted" — instead of a raw database id and a bare "deleted". See the "Activity Log — descriptive Subject/Description for hard-deleted records" entry in Section 10 for the mechanism.
- **Free Pieces — display and assignment reworked to be size-only, not per-design** (2026-07-23, follow-up to rule 5.28): The Free Pieces index previously broke free stock down one row per design (e.g. 7 identical rows for a 7-design catalogue, each showing the same S/M/L/XL numbers) — this was wrong for the same reason the public order form never shows a per-design total: a size quantity is understood to apply to every design at once, so showing it 7 times (or, worse, summing it to a number 7x too large) misrepresents what's actually available. `index()` now renders a single summary row (`Sizes | XS | S | M | L | XL | Total`), reading each size as the **minimum quantity across every design carrying it** (`FreePieceController::sizeTotals()`), never a sum. **Assign Free Pieces was rebuilt to match** — instead of a per-design/per-size grid where the admin picked arbitrary quantities per design and could split one submission across multiple targets, it's now a single row of XS/S/M/L/XL inputs (styled like `orders/adjust.blade.php`'s Adjust Order form) applied identically to every design at once, targeting one existing order or new customer per submission. The form disables any size input whose availability is 0, and turns a size input red (plus disables the submit button) if the entered quantity exceeds what's available — enforced again server-side in `store()` regardless of the client-side guard. See rule 5.28 for full detail.
- **Refund Audit Trail — Customer Refunds screen** (2026-07-23): A refund created by Delete Order's full flow (rule 5.28) previously had no screen it could ever be viewed on again once the order was hard-deleted — `orders/show.blade.php`'s refund block requires a live order, the reduction detail page requires `order_reduction_id` (always null for this path), and Delete Order's `'refund'` branch writes no ledger entry at all. `customers/show.blade.php` now has a unified "Refunds" history table (same visual pattern as Advance Payments History, gated by `!$hideFinancials`) sourced from a new `Customer::refunds(): HasMany` relation, covering refunds from both `OrderReductionController` and `OrderDeleteController`. Migration `2026_07_23_000002_add_order_snapshot_to_refunds_table` adds `order_number`/`catalogue_name` snapshot columns to `refunds`, populated at both `Refund::create()` call sites going forward, so the source stays identifiable even after `refunds.order_id` nulls out via `nullOnDelete`. See rule 5.29 for full detail, including the one historical row whose `order_number` was recovered from its `notes` text.
- **Free Pieces — advance credit auto-applied to new-customer assignments** (2026-07-23): `FreePieceController::store()`'s `new_customer` branch (rule 5.28) now calls `AdvanceCreditAutoApplyService::apply($order, $customer)` right after creating the new order — the same call `PublicOrderController::submit()` makes for the public order form (rule 5.24). If the targeted customer already holds `advance_credit_balance`, it's automatically applied to the new order (capped at the order total), and the order auto-confirms if the applied amount exceeds `ADVANCE_CREDIT_AUTO_CONFIRM_THRESHOLD` — otherwise it stays `received` for manual review. No changes were made to the `existing_order` branch, since advance-credit auto-apply only ever fires at order creation, not when an order's total increases afterward. See rule 5.30 for full detail.
- **Recalculate Order Amount** (2026-07-27): admin-only "Recalculate Order Amount" button on the order show page (`OrderPriceRecalculationController`, route `orders.recalculate-price`) re-prices an order's items against the catalogue's current `quantity_benchmark` and each design's current selling/discount price, on demand — no automatic trigger, no stored flag, no sync icon (all explicitly considered and rejected in favor of a plain always-available button that computes correctness live on click). Updates the same `Order`/`OrderItem` rows in place, adjusts `customer.advance_credit_balance` by only the incremental delta (floored at zero on a reclaim, with a shortfall warning surfaced via a new amber `warning` flash-message type added to the layout), and adjusts the order's existing `order_charged` ledger line by delta rather than adding a new line or overwriting it outright. Built specifically to correct three SAKOON orders (#1005371/117, #1005374/120, #1005394/140) charged `selling_price` instead of `discount_price` after the catalogue's `quantity_benchmark` was corrected from 24 to 23; verified against order #1005371's live data after running it (all 7 items re-priced, ledger and advance credit balance both confirmed correct). Also fixed, while building this: `orders/show.blade.php`'s header no longer squeezes the title and the (now up to 7) action buttons into a fixed side-by-side row — both stack full-width instead, at every screen size — and the hidden `<form>` elements backing the confirm-modal buttons now carry `class="hidden"` so they don't consume a flex gap slot and visually offset the button next to them. See rule 5.32 for full detail, including a related latent bug noticed (but not fixed) in `OrderAdjustController`'s ledger-sync logic.
- **Mobile API — Authentication** (2026-08-10): the first slice of the React Native customer app's backend. Customers have never had passwords (Section 5.11 applies to them too — no reset flow was ever built), so mobile login reuses the same two-factor identity the web portal already relies on (`CustomerPortalController::verify()` — permanent `portal_token` + email match) instead of adding a password column. `Customer` now implements `Illuminate\Contracts\Auth\Authenticatable` (via the `Authenticatable` trait) and gets Laravel Sanctum's `HasApiTokens` trait — no new DB columns were needed. `app/Http/Controllers/Api/AuthController.php` (a new `Api` namespace, kept separate from web controllers since the response shape is JSON-only, not Blade) exposes two endpoints, both in `routes/api.php` (scaffolded by `php artisan install:api`, which also added the `personal_access_tokens` migration):
  - `POST /api/auth/verify` (`api.auth.verify`, public) — body `{ portal_token, email }`. `portal_token` accepts either the bare UUID or a full pasted portal link (`Str::of(...)->trim()->explode('/')->last()` extracts it server-side, so no client has to pre-parse it). Email is matched case-insensitively against the customer the token resolves to. On match: `$customer->createToken('mobile-app')->plainTextToken`, returned with a small customer payload (`id`, `name`, `email`, `city`, `country`). On mismatch: `422` with `{"message": "We could not verify those details. Check your portal link and email address."}` — deliberately vague about which of the two fields was wrong, same as any credential-check error message.
  - `GET /api/me` (`api.me`, behind `auth:sanctum`) — returns the authenticated customer's profile. This is the "prove the token round-trips" endpoint; the RN app calls it on every cold start to decide whether a stored token is still valid before showing the authenticated screen, and treats a `401` as "clear the stored token, show login" rather than a hard error.
  - `tests/Feature/Api/AuthTest.php` covers both endpoints (correct token+email issues a token, wrong email is rejected, unknown token is rejected, a full pasted link resolves the same customer as the bare token, `/api/me` 401s with no token and 200s with a valid one). Like `OrderPlacementServiceTest`, this suite does not run the full migration set — several existing migrations use MySQL-only raw `ALTER TABLE ... ENUM(...)` DDL that SQLite can't execute, so `RefreshDatabase` is unsafe here. It instead builds a minimal `users`/`customers`/`personal_access_tokens` schema directly in `setUp()`.
  - Verified end-to-end against the real dev database (a real customer, a real ngrok tunnel, real HTTP calls) before being handed to the separate React Native app repo, whose Claude session built the login screen, secure token storage, and one authenticated screen against this exact contract. The RN app's login form has two fields — portal link/token and email — matching this endpoint's two required inputs; there is intentionally no password field anywhere in the mobile app.
  - This is Slice 1 of a multi-slice mobile API build-out. See "Mobile API — Portal (Slice 2)" and "Mobile API — Catalogue Browsing & Order Placement (Slice 3)" below for what followed.
- **Mobile API — Portal (Slice 2)** (2026-08-11): three read-only endpoints exposing the same data `CustomerPortalController::show()`/`portal/dashboard.blade.php` already render to customers on the web, scoped to `$request->user()` (the authenticated `Customer`) throughout — never `Order::all()` or any other unscoped query.
  - `GET /api/orders` (`api.orders.index`) — `$request->user()->orders()` (already customer-scoped via the `Customer::orders()` relation), newest first, summarized via `OrderSummaryResource`.
  - `GET /api/orders/{order}` (`api.orders.show`) — route-model-bound, but ownership is re-checked explicitly (`$order->customer_id === $request->user()->id`) before anything is loaded; a mismatch returns `404`, never `403`, so a customer probing another customer's order id can't even confirm it exists. Returns `OrderResource`: order detail, the size-wise XS/S/M/L/XL breakdown via the existing `Order::netSizeQty()` helper (rule 5.10), items, payments (with the `{order_number}p{sequence_number}` Payment ID computed on the fly per rule 5.22, never stored), and dispatch batches.
  - `GET /api/ledger` (`api.ledger.index`) — `Api\LedgerController::index()` mirrors `LedgerController::show()`'s reference-resolution pattern (batch-resolving `order_number`/`payment_id` across `Order`/`Payment`/`OrderReduction`/`Refund` reference types, including `Refund`'s snapshotted `order_number` column from rule 5.29 so a refund on a since-hard-deleted order still resolves), trimmed to just what the mobile ledger view needs. Amount sign is returned exactly as stored — Section 7's convention is never flipped or reinterpreted for the API.
  - New `app/Http/Resources/Api/` namespace (`OrderSummaryResource`, `OrderResource`, `OrderItemResource`, `PaymentResource`, `DispatchBatchResource`, `LedgerEntryResource`) — the first use of Laravel API Resources in this codebase (see the updated "Mobile API" convention note under Section 10).
  - `tests/Feature/Api/OrderTest.php` and `LedgerTest.php` extend `AuthTest`'s hand-built-schema approach (same MySQL-enum-migration reason) to a wider table set (`catalogues`, `designs`, `order_items`, `dispatch_batches`/`dispatch_batch_items`, `customer_ledger`, plus empty `order_reductions`/`order_reduction_items` so `netSizeQty()`'s lazy relation query doesn't hit a missing-table error under SQLite). Covers cross-customer scoping on both the list and detail endpoints (including the 404-not-403 assertion), size-breakdown correctness, Payment ID formatting, and ledger sign preservation.
- **Mobile API — Catalogue Browsing & Order Placement (Slice 3)** (2026-08-11): the first mobile endpoints that write data. Reuses `OrderPlacementService`/`OrderPlacementException` — introduced ahead of this slice specifically so the web form and the app never compute or enforce order rules twice (see "Order placement service" under Section 10) — without a single line of new pricing or guard logic.
  - `GET /api/catalogues` (`api.catalogues.index`) / `GET /api/catalogues/{catalogue}` (`api.catalogues.show`) — `Api\CatalogueController`, open catalogues only. Each reports `available_pieces`/`sold_out` (via the new `Catalogue::isSoldOut()`, see the fix below) and `already_ordered` (batch-computed once per request, not per row, to avoid an N+1 across the list) so the app can disable ordering pre-emptively rather than only reacting to a failed submission.
  - `POST /api/catalogues/{catalogue}/quote` (`api.catalogues.quote`) — thin wrapper over `OrderPlacementService::quote()`, returning its array verbatim. `quote()`'s own docblock states it's safe to call on every keystroke and that callers must never reimplement this math client-side — this endpoint exists specifically so the app doesn't have to.
  - `POST /api/orders` (`api.orders.store`) — `Api\OrderController::store()`, wraps `OrderPlacementService::place()`. Unlike the web form, the customer is `$request->user()` directly — `resolveCustomerByEmail()` is never called, so `CUSTOMER_NOT_FOUND` can't occur on this path. `OrderPlacementException::reason()` maps to `422` (`no_quantity`, `catalogue_closed`) or `409` (`duplicate_order`); success returns `201` with the same `OrderResource` shape `GET /api/orders/{id}` already returns, so the mobile app reuses its Slice 2 order-detail screen instead of needing a new one.
  - **Sold-out gap fixed while building this**: see the "Catalogue Sold-Out" implementation note in Section 2 — `OrderPlacementService::assertCatalogueOpen()` and `PublicOrderController::show()`'s `$soldOut` flag previously only checked `status`, not `availablePieces()`. Both now call the new `Catalogue::isSoldOut()`, the single source of truth also used by these catalogue endpoints — one fix closes the gap on the web form and the mobile app at once.
  - `tests/Feature/Api/CatalogueTest.php` and `OrderPlacementTest.php`, same hand-built-schema approach, widened further to include `order_number_sequence` (seeded — `Order::boot()` auto-generates `order_number` from it) and `payments` (for `AdvanceCreditAutoApplyService`, though the covered scenarios keep `advance_credit_balance` at 0 so that path never actually writes). Includes a regression test placing two customers' orders back-to-back against a catalogue with exactly one available piece, asserting the second is rejected with `catalogue_closed` even though `status` stays `open` — the exact case the sold-out fix addresses.
  - This is Slice 3. See "Mobile API — Push Notifications (Slice 4)" below for what followed.
- **Mobile API — Push Notifications (Slice 4)** (2026-08-11): the last slice on the roadmap. Reuses the entire existing push-trigger pipeline from rule 5.26 — `OrderStatusNotificationService::notify()`, the same five notifying statuses, the same revert-suppression — untouched; this slice only adds a second delivery channel alongside the existing web push, so a customer using both the installed PWA and the RN app gets both.
  - `expo_push_tokens` table (`customer_id`, unique `token`, nullable `platform`) + `ExpoPushToken` model + `Customer::expoPushTokens()` — deliberately a separate table from `push_subscriptions` (rule 5.26's `HasPushSubscriptions`/webpush package table), since an Expo token is a single opaque string, structurally unlike a WebPush endpoint+keys subscription object.
  - `POST /api/push-tokens` (`api.push-tokens.store`) / `DELETE /api/push-tokens` (`api.push-tokens.destroy`) — `Api\PushTokenController`. Registration is an upsert keyed on the token itself (not `customer_id`), so re-registering the same physical device under a different logged-in customer (a shared device, previous user logged out) reassigns it rather than leaving stale rows. Unregister is scoped to the authenticated customer's own tokens only.
  - `App\Notifications\Channels\ExpoPushChannel` — the delivery mechanism, mirroring `WebPushChannel`'s role for this second push type: no-ops silently with zero registered tokens, batches all of a customer's tokens into one POST to Expo's push API (`https://exp.host/--/api/v2/push/send`), and self-prunes any token Expo's response reports as `DeviceNotRegistered` (app uninstalled, etc.) so the table doesn't accumulate dead rows without needing a separate cleanup job. Optional `EXPO_ACCESS_TOKEN` (`config('services.expo.access_token')`, never `env()` outside the config file) avoids Expo's stricter unauthenticated rate limit — not required for it to work.
  - `OrderStatusChanged::via()` now returns `[WebPushChannel::class, ExpoPushChannel::class]`; the new `toExpoPush()` method reuses the exact same status→copy `CONTENT` map `toWebPush()` already uses, so notification text can't drift between the two channels. Its `data` payload (`order_id`, `order_number`) is what the RN app deep-links on tap — deliberately not the portal URL `toWebPush()` sends, since the app has its own order-detail screen rather than opening a browser.
  - `notify:test-push` needed zero logic changes — it already just calls `OrderStatusNotificationService::notify()`, so adding the channel to `via()` made it exercise both web push and native push automatically. Its output now reports both the web push subscription count and the Expo token count so a "nothing arrived" test isn't ambiguous about which channel to check.
  - `tests/Feature/Api/PushTokenTest.php` (register/reassign/scoped-unregister/auth) and `tests/Feature/ExpoPushChannelTest.php` (no-op with zero tokens, batched send payload shape, `Authorization` header only sent when configured, `DeviceNotRegistered` pruning, no pruning on a failed HTTP response) — the latter tests `ExpoPushChannel` against a small stub notification rather than `OrderStatusChanged` itself, so the channel's own mechanics are covered independent of any one notification's content.
  - What's still outside this repo: the RN app has to actually call these two endpoints (request permission, register on grant, unregister on logout) and the EAS project needs production push credentials (Apple Push key, Firebase FCM) configured before a store build will deliver anything — neither is a backend concern.
- **Mobile API — Announcements + session logout** (2026-08-11, undocumented until 2026-08-18): `Api\AnnouncementController::index()`/`markRead()` (routes `api.announcements.index`/`api.announcements.read`) expose the mobile app's announcement/Timeline history. `index()` reads `$request->user()->notifications()` (the `Notifiable` trait's relation, already newest-first via `->latest()`), filtered to `type = App\Notifications\AnnouncementNotification::class` so any future notification class writing to the `database` channel can't leak into this feed. `markRead()` scopes `findOrFail()` to the authenticated customer's own notifications — a mismatch 404s rather than confirming another customer's announcement exists. `AnnouncementResource` (`app/Http/Resources/Api/`) maps a `DatabaseNotification` row's `data` array to `{id, title, body, image_url, sent_at, read_at}`, `image_url` via `Storage::url()` from the stored `image_path`. `POST /api/auth/logout` (`api.auth.logout`) shipped in the same pass — Sanctum-guarded, revokes only the token used for the request so other devices stay signed in, covered by `tests/Feature/Api/AuthTest.php`.
- **Timeline / Announcements — CasualOS admin compose screen** (2026-08-18): the client's own name for the customer-facing feed is "Timeline" — a Facebook-style scrolling feed of admin posts (new-catalogue announcements, customer-highlight photos, etc.), delivered as a push notification plus an in-app history row. The mobile API + notification plumbing above shipped first; this is the CasualOS side that was missing — previously the only way to send one was `php artisan announcements:send` from a terminal.
  - **Two tables, deliberately separate.** The framework's own `notifications` table (migration `2026_07_15_000002`) stores one row **per customer per send** — that's the correct, unchanged read path for the mobile API above. The new `announcements` table (migration `2026_08_18_153405_create_announcements_table`, model `App\Models\Announcement`, no `LogsActivity` per this project's rule) stores one row **per broadcast** (`title`, `body`, `image_path`, nullable `sent_by` FK to `users`, `sent_at`, `recipient_count`) — the admin-facing "what have I posted" record, since there was previously no single row representing one send action to list on a history screen.
  - **`App\Services\AnnouncementService::send(string $title, string $body, ?string $imagePath, ?User $sentBy): Announcement`** is the shared logic between the two ways an announcement gets sent — the artisan command and the new admin screen — creating the `Announcement` row (`recipient_count = Customer::count()` at send time) then looping `Customer::all()` calling `$customer->notify(new AnnouncementNotification(...))`, exactly as the command always has. This is a lighter version of the "Order placement service" pattern (Section 10) — shared side-effecting logic reused by two callers — but **not** the same shape: there's no pure-calculation half analogous to `quote()`, so don't cite `OrderPlacementService` as doing the same thing.
  - **`AnnouncementController`** (`app/Http/Controllers/`, **not** `Api\`) — `index()` renders the compose form plus a paginated history table on one page (no separate create/edit/show; posts are never edited or deleted once sent, matching this project's "narrow exceptions only" deletion policy under Section 10), `store()` validates `title`/`body`/optional `image` (`nullable|image|max:10240`, same rule as `CatalogueController::store()`'s cover photo), uploads to S3 under `announcements/` explicitly (`$file->store('announcements', 's3')`, matching the folder the artisan command already used), then calls the service. Routes `announcements.index`/`announcements.store`, fully inside a `role:admin` group — no inline guard in the controller, following `CronLogController`/`StitchingUnitController`'s precedent of trusting the middleware alone (that inline guard only exists on controllers like `CatalogueController` whose routes aren't role-split at the router level).
  - **View** `resources/views/admin/announcements/index.blade.php` — the Send button submits the real compose form (not a hidden duplicate) through the global `$store.confirm.show({..., formId: 'announcement-form'})` modal, since `proceed()` just calls `formId.submit()` with no awareness of field contents. Every other use of this modal in the codebase submits a separate no-field hidden form instead — this is the first confirm-modal usage on a form that actually carries real data, worth knowing before copying the pattern elsewhere.
  - **`SendAnnouncement` artisan command** (`announcements:send {title} {body} {--image=}`) now delegates to `AnnouncementService::send()` instead of looping customers inline — kept as the CLI path alongside the new admin screen, not replaced by it.
  - **Sidebar**: admin-only, added to the System section (the only fully admin-only sidebar section) alongside Cron Logs.
  - **Test**: `tests/Feature/AnnouncementServiceTest.php` covers `AnnouncementService::send()` only (recipient count, one row per customer notified) — deliberately does **not** test the web controller's HTTP layer, since this project had zero prior Feature-test coverage of any web/admin route before this (only API and CLI paths were tested), and adding that scaffolding was judged not worth it for one screen.
  - **Local-dev gotcha, not a bug**: `AnnouncementNotification` is `ShouldQueue` (same as `OrderStatusChanged`). In production this relies on the scheduled `queue:work --stop-when-empty` cron tick (rule 5.26). Locally, nothing plays that role unless a worker is run by hand (`php artisan queue:work` or `queue:listen` in a spare terminal) — sending from the admin screen looks instant (the `Announcement` row is created synchronously) but nothing actually delivers until a worker drains the `jobs` table. Confirmed by real testing: a send left 198 jobs (99 customers × 2 channels — `database` + `ExpoPushChannel`, since this Laravel version queues one job per channel per notifiable, not one job per notifiable) sitting unprocessed until `queue:work --stop-when-empty` was run manually.
  - **`Customer` account deletion**: there is currently no way to delete a `Customer` record anywhere in this codebase (Section 10's "Deleting records" policy only carves out Orders and Payments). The mobile app's Settings screen (Module 05 in `casualite-app`) does **not** include an account-deletion request — an earlier scope draft called for one as Apple-rejection insurance, but the Casualite owner confirmed there is no deletion path and none is wanted, so this gap is not expected to be closed. See `casualite-app/CLAUDE.md` §3.
- **Staff Mobile Login — Embedded WebView** (2026-09-09): backend piece of a two-repo feature letting staff (`admin`/`accountant`/`production_manager`/`creative_head`) sign into the mobile app through the same token+email form customers use, without building any native staff UI. `User.mobile_login_token` (auto-generated, mirrors `Customer.portal_token`), `Api\AuthController::verify()`'s new staff branch, `staff_mobile_login_tokens` (single-use, hashed, ~90s TTL), and `MobileLoginController::consume()` (`GET /mobile-login/{token}`, starts a real Laravel session) are all built, tested (`tests/Feature/Api/AuthTest.php`, `tests/Feature/MobileLoginTest.php` — 18 tests total, all passing), and verified end-to-end against the live dev server via `curl`. See rule 5.33 for full detail. **The mobile-app side (`casualite-app`, embedded `react-native-webview` + logout interception) is built but not yet confirmed working on-device** — an EAS development build is still being debugged as of this writing; this backend contract (what `verify()` returns, what `/mobile-login/{token}` does) is stable regardless of that in-progress work. This mobile-app half falls outside `casualite-app/CLAUDE.md`'s five contracted modules — see that file's own note on this feature.

- **Customer Self-Signup — Mobile App, Pending Admin Approval** (2026-09-09): `POST /api/auth/signup` (public) lets someone with no `portal_token` submit the same fields staff already collect on the manual create form; it queues a `customer_signup_requests` row (unique on `email`) rather than creating a `Customer` directly. `PendingSignupController` (`pending-signups.index`/`approve`/`reject`, admin only) reviews the queue — Approve creates the real `Customer` (`created_by` set to the approving admin, `portal_token` auto-generated exactly as on manual creation) and redirects to `customers.show` so the admin can copy the portal link and share it the same manual way they already do; Reject leaves no `Customer` behind. A resubmission after rejection reuses the same row rather than piling up duplicates. No auto-login, no status-polling endpoint, and no push notification were added — deliberately out of scope; approval stays a fully manual hand-off. Sidebar gets a "Pending Signups" link (System section, admin only) with a live pending-count badge. Tested in `tests/Feature/Api/AuthTest.php` (new signup, duplicate email, already-pending, resubmit-after-reject, country validation) and verified end-to-end against the live dev server (`curl` + `tinker`, test data cleaned up afterward). See rule 5.34 for full detail.

### Known Bugs / Incomplete Features (must fix)

1. **`order.show` route name used in controller** — ✅ Fixed (2026-06-23): `PublicOrderController::submit()` now redirects to `order.public` instead of the non-existent `order.show`
4. **Cargo document is text, not file** — ✅ Fixed: `DispatchController::store()` validates `'cargo_document' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240'` and stores it via `$request->file('cargo_document')->store('cargo-documents', 's3')`, matching rule 5.3. Bullet had never been marked fixed even though rule 5.3 already documented it as implemented — corrected 2026-08-18.
5. **Packed inventory not deducted after dispatch** — ✅ Fixed: `DispatchController::store()` has a "Deduct dispatched quantities from packed inventory (FIFO)" block that decrements `PressReturnItem`/`OutsourcedBatchItem` rows. Corrected 2026-08-18 — same as bullet 4, the fix predated this note.
6. **Order status auto-transition to stitching** — ✅ Fixed: `FabricBatchController::store()` auto-transitions confirmed orders on fabric batch creation
7. **Order reduction surplus logic** — ✅ Fixed (2026-05-20): full three-case logic implemented in `OrderReductionController`
8. **`running_advance_balance` hardcoded to 0** in all ledger entries — ✅ Fixed: every `CustomerLedger::create()` call site app-wide (`PaymentController`, `OrderReductionController`, `AdvancePaymentController`, `FreePieceController`, `OrderPieceReassignmentController`, `OrderDeleteController`, `OrderPlacementService`) now passes the customer's actual `advance_credit_balance`, not just the `payment_received` entries this bullet originally called "partially fixed" for. Corrected 2026-08-18.
9. **Dispatch order status** — ✅ Fixed (2026-05-19): `partially_dispatched` status added; `DispatchController::store()` now sets `partially_dispatched` on partial dispatch and `dispatched` only when `isFullyDispatched()` returns true
10. **Creative Head role expansion** — ✅ Fixed (2026-06-10): `creative_head` now has catalogue management write access, orders read-only (no financials), and production screens read-only. See Completed entry for full detail.
11. **`OrderPieceReassignmentController` creates `order_charged` with wrong sign** — ✅ Fixed (2026-06-04): changed `amount => -$totalAdded` to `amount => $totalAdded` in `OrderPieceReassignmentController::store()`; historical wrong-sign entries corrected by migration `2026_06_04_000001`.
12. **`reports/customer-ledger.blade.php` referenced non-existent `$entry->entry_type`** — ✅ Fixed (2026-08-19): changed to `transaction_type` (see Section 4) throughout the view; also replaced the dead `surplus_to_advance` badge/label entries with the real `refund_issued` type, which had no mapping at all. Discovered 2026-07-14 while adding Payment IDs (rule 5.22), left unfixed until now. See rule 5.22's note under "Displayed in six places" for the original discovery context.

### All Migrations (run `php artisan migrate` after pulling)

All migrations have been run (including the two HD Gallery migrations added 2026-07-16, listed below) against the local dev database. Run `php artisan migrate` in any other environment (staging/production) that hasn't picked them up yet. For reference, the full set introduced across branches:

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
- `2026_06_04_000002_convert_receipt_image_to_json_in_payments` — converts `payments.receipt_image` from a single-path string to `text` storing a JSON array (wraps existing single paths in `JSON_ARRAY(...)`); see rule 5.12 — this was missing from this list until 2026-08-18 despite being live and load-bearing in `PaymentController` since it shipped
- `2026_06_05_144325` — data fix: adds PKR 2,665 overpayment surplus to Saad Bhai Wijdan's `advance_credit_balance` for Order #524308 (no ledger entry — surplus already in ledger via payment_received entries)
- `2026_06_05_200000` — creates `tarpai_payments` table (`catalogue_id` FK, `tarpai_house` enum(`rashid_bhai`,`yousaf_bhai`), `week_start`, `week_end`, `total_pieces_sent`, `total_amount` decimal, `is_confirmed`, `confirmed_by` nullable FK to users, `confirmed_at`); unique constraint `(catalogue_id, tarpai_house, week_start)`
- `2026_06_05_210000` — creates `cron_logs` table (`job_name`, `job_label`, `triggered_by`, `week_start` nullable, `week_end` nullable, `records_created`, `records_updated`, `records_skipped`, `status` enum(`success`,`failed`), `output` text nullable, `ran_at` timestamp)
- `2026_06_06_000001` — adds `assigned_bank_account_id` nullable FK to `orders` (references `bank_accounts`, nullOnDelete)
- `2026_06_10_000001` — creates `order_number_sequence` table (single row, `last_number` seeded at 1005334); new orders increment this counter atomically instead of using `random_int`
- `2026_07_13_170212_create_design_country_prices_table` — creates `design_country_prices` table (`design_id` FK cascade delete, `country`, `price` decimal 10,2); unique constraint `(design_id, country)`
- `2026_07_13_170212_create_piece_tags_table` — creates `piece_tags` table (`order_id` FK cascade delete, `design_id` FK cascade delete, `size` enum(xs,s,m,l,xl), `barcode` nullable unique string, `country`, `price` decimal 10,2); unique constraint `(order_id, design_id, size)`
- `2026_07_14_000001_add_sequence_number_to_payments_table` — adds nullable `sequence_number` unsigned integer to `payments`; backfills per-order sequential numbers ordered by `payment_date` then `id`; adds unique constraint `(order_id, sequence_number)`
- `2026_07_14_000002_create_advance_payments_table` — creates `advance_payments` table (`customer_id` FK, `payment_type` enum(`cash`,`bank_transfer`), `amount` decimal 12,2, `bank_account_id` nullable FK to `bank_accounts`, `payment_date`, `notes`, `receipt_image` json, `logged_by` FK to `users`)
- `2026_07_14_000003_make_logged_by_nullable_on_payments_table` — makes `payments.logged_by` nullable (drops FK, alters column, re-adds FK with `nullOnDelete()`), same pattern as `2026_04_24_000001`'s `customer_ledger.created_by` fix; needed so system-generated payments (auto-applied advance credit, rule 5.24) can be stored without a staff user reference
- `2026_07_14_183443_create_cost_estimations_table` — creates `cost_estimations` table (`catalogue_id` FK, `design_id` FK unique, `estimation_date`, `stitched_by`, `production_plan_qty`, `total_cost`, `per_unit_cost`, `market_rate`, `margin`, `approved_by`, `prepared_by` nullable FK to users)
- `2026_07_14_183444_create_cost_estimation_items_table` — creates `cost_estimation_items` table (`cost_estimation_id` FK cascade delete, `category` enum of the 9 cost sections, `particulars`, `avg`, `qty`, `rate`, `amount`)
- `2026_07_14_185513_drop_cutting_by_from_cost_estimations_table` — drops the `cutting_by` column (added then immediately removed in the same session per instruction — Cutting By was dropped from the sheet entirely, not just hidden)
- `2026_07_14_190754_drop_actual_qty_and_variation_from_cost_estimations_table` — drops `actual_production_qty` and `production_variation` columns (same reasoning — removed from the sheet entirely, not just hidden)
- `2026_07_15_000001_create_customer_devices_table` — creates `customer_devices` table (`customer_id` FK, `token_hash` unique string(64), `user_agent`, `ip_address`, `last_seen_at`) backing the persistent portal-login cookie
- `2026_07_15_000002_create_notifications_table` — creates Laravel's standard `notifications` table (uuid `id`, `type`, morphs `notifiable`, `data`, `read_at`) — not currently populated (`OrderStatusChanged`'s `via()` only uses `WebPushChannel`, not the `database` channel), added alongside webpush for a future in-app notifications feed
- `2026_07_15_000003_create_push_subscriptions_table` — creates the `push_subscriptions` table shipped by `laravel-notification-channels/webpush` (morphs `subscribable`, `endpoint` unique, `public_key`, `auth_token`, `content_encoding`)
- `2026_07_16_000001_add_hd_gallery_token_to_catalogues_table` — adds nullable unique `hd_gallery_token` to `catalogues`; backfills existing rows with `Str::random(32)`
- `2026_07_16_000002_create_catalogue_hd_images_table` — creates `catalogue_hd_images` table (`catalogue_id` FK cascade delete, `s3_path`, `thumbnail_path` nullable, `original_filename`, `file_size` bytes, `mime_type`, `uploaded_by` nullable FK to users)
- `2026_07_21_000001_create_production_alerts_table` — creates `production_alerts` table (`catalogue_id` FK cascade delete, `design_id` FK cascade delete, `order_id` nullable FK null-on-delete, `order_number`, `trigger` string, `message` text, `resolved_by` nullable FK to users, `resolved_at` nullable); index on `(catalogue_id, design_id, resolved_at)`
- `2026_07_22_000001_make_refund_order_fields_nullable` — makes `refunds.order_id` and `refunds.order_reduction_id` nullable (`order_id` re-added with `nullOnDelete()`) so a refund created by Delete Order's full flow (rule 5.28, no `OrderReduction` involved) can be stored and survives as a standalone audit record once its order is later deleted
- `2026_07_23_000001_create_free_pieces_table` — creates `free_pieces` table (`catalogue_id` FK cascade delete, `design_id` FK cascade delete, `size` enum(xs,s,m,l,xl), `quantity` unsigned int default 0); unique constraint `(catalogue_id, design_id, size)`
- `2026_07_23_000002_add_order_snapshot_to_refunds_table` — adds nullable `order_number` and `catalogue_name` string columns to `refunds`; backfills existing rows whose `order_id` still resolves from a live `orders`/`catalogues` join, plus a second backfill statement that recovers `order_number` (via `SUBSTRING_INDEX`) for the one pre-existing Delete Order refund whose `order_id` was already null, by parsing the `"Refund from deleted Order #{number}"` pattern out of its `notes` column
- `2026_08_18_153405_create_announcements_table` — creates `announcements` table (`title`, `body`, `image_path` nullable, `sent_by` nullable FK to `users` null-on-delete, `sent_at`, `recipient_count` unsigned int default 0) — one row per broadcast, distinct from the framework `notifications` table's one-row-per-customer-per-send; see "Timeline / Announcements" under Section 9
- `2026_09_09_000001_add_mobile_login_token_to_users_table` — adds nullable unique `mobile_login_token` (string 64) to `users`; backfills existing staff with `Str::uuid()`, same shape as `2026_07_16_000001_add_hd_gallery_token_to_catalogues_table`; see rule 5.33
- `2026_09_09_000002_create_staff_mobile_login_tokens_table` — creates `staff_mobile_login_tokens` table (`user_id` FK, unique `token_hash`, `expires_at`, nullable `used_at`, `ip_address`, `user_agent`) — the single-use handoff credential for staff mobile login; see rule 5.33
- `2026_09_09_000003_create_customer_signup_requests_table` — creates `customer_signup_requests` table (`name`, `contact_number`, `city`, `country`, `address` nullable, unique `email`, `status` default `pending`, nullable `customer_id` FK null-on-delete, nullable `reviewed_by` FK to `users` null-on-delete, `reviewed_at`) — the mobile app's self-signup review queue; see rule 5.34

---

## 10. Coding Conventions

### Always eager-load designs when passing catalogues to Alpine.js

```php
$catalogues = Catalogue::where('status', 'open')->with('designs')->get();
```

Without `->with('designs')`, `Js::from($catalogues)` produces `undefined` for
`cat.designs` in Alpine and causes a crash.

### Business constants belong in `config/casualite.php`, sourced from `.env`

`config/casualite.php` is the project's single home for tunable business constants (e.g.
`advance_credit_auto_confirm_threshold`, rule 5.24) — never hardcode a business number
(a threshold, a rate, a limit) directly in a controller or service. Add it to `.env` /
`.env.example`, expose it via `config/casualite.php` (`env('VAR_NAME', $default)`), and
read it elsewhere via `config('casualite.key')`. Never call `env()` outside a config file —
this project follows that convention strictly everywhere else.

### Order placement service — the pattern for any logic two callers must share

**`App\Services\OrderPlacementService`** (introduced 2026-08-10, ahead of the mobile app's `POST /api/orders`) is the first instance of this pattern in the codebase — the model to copy when a second caller needs to reuse business logic currently living in a web controller. The mobile app's catalogue-browsing and order-placement endpoints (Slice 3, see Section 9) are exactly that second caller, and needed zero new pricing or guard logic as a result:

- **Pure calculation is separated from writes.** `quote($catalogue, $sizes)` prices an order and returns a plain array — safe to call on every keystroke for a live total, no DB writes, no side effects. `place(...)` calls `quote()` internally and then writes (`Order`, `OrderItem`s, ledger entry, advance-credit auto-apply) inside `DB::transaction()`. `Api\CatalogueController::quote()` and `Api\OrderController::store()` do exactly this — call `quote()` to drive the app's live total and `place()` to actually submit. Never reimplement the pricing math client-side or in a second PHP copy.
- **Business failures are a typed exception, not a controller-shaped response.** `App\Exceptions\OrderPlacementException` carries a `reason()` (`NO_QUANTITY`, `CUSTOMER_NOT_FOUND`, `DUPLICATE_ORDER`, `CATALOGUE_CLOSED` — a stable contract the mobile app switches on) and a human-readable message. The service never knows how a caller wants to respond — `PublicOrderController::submit()` catches it and maps each reason to a Blade redirect/flash; `Api\OrderController::store()` catches the same exception and maps reasons to HTTP status codes (`422`/`409`) instead. Follow this split (service throws typed reasons, controller decides response shape) for any other logic that ends up needing a second caller.
- **Guard ordering is a business decision baked into the service's method order, not incidental.** `place()` prices first (so an empty order is rejected before looking up the customer), then resolves the customer, then checks for a duplicate order. Do not reorder these calls without confirming the guard-ordering is still intentional.

### Mobile API — customers authenticate via Sanctum tokens, never a password

`app/Http/Controllers/Api/` is a new namespace, separate from the web controllers in `app/Http/Controllers/`, for the React Native customer app. Conventions established by `Api\AuthController` (see "Mobile API — Authentication" under Section 9) that any future `Api\*` controller should follow:

- **Customers are a second authenticatable model, not a second guard config.** `Customer` implements `Illuminate\Contracts\Auth\Authenticatable` and uses Sanctum's `HasApiTokens` directly — there is no separate `customer` guard in `config/auth.php`. `auth:sanctum` resolves whichever model the bearer token was issued to (`$request->user()` returns a `Customer` on these routes, a `User` on any future staff-facing API route using the same middleware).
- **No password field exists on `Customer` and none should be added.** The mobile login flow proves identity the same way the web portal always has — a permanent `portal_token` plus an email match (`CustomerPortalController::verify()`) — and issues a bearer token instead of starting a session. Do not introduce a password column or a traditional login form for customers; this was a deliberate call, not an oversight (see Section 5.11's "no self-service password reset" rule, which applies to customers for the same reason).
- **API responses are plain JSON arrays for single small payloads, `JsonResource` for anything reused across endpoints.** `AuthController::customerPayload()` stayed a small private array-building method since it's single-use. `app/Http/Resources/Api/` (introduced in Slice 2, extended in Slice 3 — `OrderSummaryResource`, `OrderResource`, `OrderItemResource`, `PaymentResource`, `DispatchBatchResource`, `LedgerEntryResource`, `CatalogueResource`, `CatalogueSummaryResource`, `DesignResource`) is the shared resource layer for everything else. Use `Illuminate\Http\Resources\Json\JsonResource` for any new shape more than one `Api\*` method needs, rather than copy-pasting an array.
- **Business logic is still owned by the same services web controllers use.** The mobile API does not get its own copy of pricing/order logic — see "Order placement service" above. `Api\OrderController::store()` (`POST /api/orders`) calls `OrderPlacementService::place()` exactly as `PublicOrderController` does, catching `OrderPlacementException` and mapping its `reason()` to an HTTP status instead of a Blade redirect.

### Blade views

- Admin panel views: `resources/views/` — extend `layouts.app`
- Public pages (order form, portal): standalone HTML with CDN scripts, no layout extension
- Use `Storage::url($path)` for all uploaded file URLs

### File uploads — S3 only

**All file uploads go to S3.** There is no local public disk storage. `FILESYSTEM_DISK=s3` is the default. Always use `Storage::url($path)` (not `Storage::disk('public')->url()`).

| File type          | S3 folder           | Store call                                          |
| ------------------ | ------------------- | --------------------------------------------------- |
| Payment receipts   | `receipts/`         | `$file->store('receipts')` — **multiple per payment** (`receipt_images[]` field, `payments.receipt_image` stores a JSON array — see rule 5.12), implicit default disk rather than an explicit `'s3'` arg |
| Design photos      | `designs/`          | `$file->store('designs', 's3')`                     |
| Catalogue covers   | `catalogues/`       | `$file->store('catalogues', 's3')`                  |
| Cargo documents    | `cargo-documents/`  | `$file->store('cargo-documents', 's3')`             |
| Refund documents   | `refund-documents/` | `$file->store('refund-documents', 's3')`            |
| Announcement images | `announcements/`   | `$file->store('announcements', 's3')` (admin compose screen) / `Storage::disk('s3')->put(...)` (artisan command, local file path in) — see "Timeline / Announcements" under Section 9 |

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
{{-- Hidden form — class="hidden" is required, not cosmetic --}}
<form id="form-unique-id" method="POST" action="..." class="hidden">@csrf</form>

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

**`class="hidden"` on the form is required whenever the trigger button sits inside a `flex`/`grid` container (e.g. a row of action buttons on a show page) — never omit it.** Without it, the empty `<form>` is still a real flex/grid item: even though it renders no visible content, it still occupies a `gap` slot, which visually shifts the very next button over by one gap-width. This is easy to miss in review since the form is invisible — the symptom is only ever "why is this one button indented differently from the others." Found and fixed 2026-07-27 on `orders/show.blade.php` (rule 5.32) after stacking that page's title and button row into always-full-width blocks made three pre-existing offset forms suddenly visible as a left-indent on the first button in the row.

### Deleting records — narrow exceptions only

The general rule is **never delete** — user accounts are disabled, orders are reduced, the audit trail must stay intact.

**Two explicit exceptions exist:**

1. **Orders** — `OrderController::destroy()` hard-deletes a `received` + `total_paid=0` order (see rule 5.15). These have no financial footprint to preserve.
2. **Payments** — `PaymentController::destroy()` hard-deletes any payment (see rule 5.16). Used to correct duplicate entries.

Do not add further `destroy()` routes without an explicit business justification.

### dompdf on small custom page sizes (labels, tags)

Any PDF sized to a physical label rather than A4/Letter (e.g. `piece-tags-pdf.blade.php`, sized to the 2"×1" Zebra label) is prone to two dompdf-specific bugs: an explicit `height` on a per-page container combined with `box-sizing: border-box` causes a spurious blank second page even when content fits, and a percentage-width child inside a padded `border-box` parent overflows past the padding into the page edge. See rule 5.20 for the full explanation and the fix (no explicit height; fixed point-widths instead of percentages). Verify any change to a label-sized PDF by rendering to an image and checking text bounding boxes — the page count alone can look correct while text is silently clipped.

### Bypassing the CustomerLedger boot-level deletion guard

`CustomerLedger` has `static::deleting(fn() => false)` in its `boot()` method to prevent accidental deletion via Eloquent. When a legitimate hard-delete requires removing a ledger entry (order delete, payment delete), bypass the guard using raw DB:

```php
DB::table('customer_ledger')
    ->where('reference_type', 'App\Models\Payment')
    ->where('reference_id', $payment->id)
    ->delete();
```

Never remove the boot guard from the model itself.

### Activity Log — descriptive Subject/Description for hard-deleted records

`Order` and `Payment` are the only two `LogsActivity` models whose rows get hard-deleted (rules 5.15/5.16/5.28) rather than only updated, so a deleted row's activity-log entry can no longer join back to the live table for a human-readable label — by default it falls back to the raw database `id`. Both models override `getDescriptionForEvent()` to build a narrative sentence instead of the bare `$eventName`, e.g. `"Order #1005395 on the SAKOON catalogue from FIVE CLOTH has been deleted"` / `"Payment #1005395p1 on the Order #1005395 on the SAKOON catalogue from FIVE CLOTH has been deleted"` — reading `$this->catalogue?->name` / `$this->customer?->name` (Order) or `$this->order?->catalogue?->name` / `$this->order?->customer?->name` (Payment) from relations that are still resolvable via in-memory FK values even after the row's own DB delete, since Eloquent's `deleted` event fires with `exists = false` but the model's attributes (and therefore its relation FKs) are untouched.

`Payment` also gets a virtual `order_number` accessor (`getOrderNumberAttribute()`) added to `logOnly(['*', 'order_number'])`, so the parent order's `order_number` is snapshotted straight into the log row's own `attribute_changes->old` — needed because `Payment` only stores the raw `order_id` FK, and the parent `Order` row itself may already be gone by the time anyone reads the log later (e.g. Delete Order removes both in the same request). `reports/activity-log.blade.php`'s Subject-column fallback for a deleted Order/Payment reads this same `attribute_changes` column (aliased `$changes` in the view) — **not** `properties`, a genuinely separate JSON column on this project's `activity_log` table that only gets populated by manual `activity()->withProperties([...])` calls, never by the automatic `LogsActivity` create/update/delete logging (which writes to `attribute_changes` via `withChanges()`). There is no `tapActivity()` hook in the installed `spatie/laravel-activitylog` v5.0.0 — don't reach for it to inject extra data into an automatic log entry; override `getActivitylogOptions()`/`logOnly()`/`getDescriptionForEvent()` instead, or add a virtual accessor as done here.

**Known gap, left unaddressed:** the "▼ details" expand row on the Activity Log screen only ever reads from `properties`, never `attribute_changes` — so for every model using automatic `LogsActivity` logging (not just Order/Payment), the field-level before/after diff has never rendered in that expandable section; only manual `activity()->withProperties([...])`-style "Detail" entries show anything when expanded. Noticed while wiring up the above, intentionally out of scope for this round.

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
