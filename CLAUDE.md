# CasualOS — Claude Project Context

This file gives Claude the full context needed to work on CasualOS correctly.
**Read this entire file before writing any code.** Every section contains rules that have
been derived from the signed client proposal (`Casual_Lite_Website_Proposal_v9.docx`).
Deviating from these rules means deviating from the signed contract.

---

## 1. What This Project Is

**CasualOS** is the complete business operations system for **Casual Lite**, a fashion
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
7. Each saved order gets a randomly generated `order_number` (not a sequential ID). This is displayed everywhere instead of the database `id`.

### Stitching Units

Stitching units are managed in the `stitching_units` table (not hardcoded integers). Each unit has:

- `number` — display number (1, 2, 3 …), auto-assigned, immutable
- `name` — human name (e.g. "Subhan", "Mumtaz")
- `payment_type` — `salary` or `per_piece`
- `per_piece_rate` — **required** for `per_piece` units. This is the rate used to calculate weekly wages. Salary units have no rate in CasualOS (tracked externally).
- `is_active` — inactive units are hidden from production assignment and stitching return forms

**Wage rate is stored on the stitching unit, not on the catalogue.** When recording weekly wages, the manager selects a stitching unit and the rate auto-populates from `stitching_units.per_piece_rate`. The `wages` table stores `stitching_unit_id` (FK) and a snapshot of `wage_rate` at the time of recording.

### Catalogue Sold-Out

A catalogue becomes sold out when **either**:

- Admin manually sets `status = 'closed'`, OR
- `availablePieces()` reaches zero (`totalPieces()` minus all ordered quantities across all designs)

When sold out, the order link shows a sold-out screen. No form is rendered. Any POST
attempt is also rejected by the controller guard. The route for the order form GET is
named `order.public` — **not** `order.show`.

### Order Statuses (4 only — exact enum values)

| Status       | How it's set                                                        |
| ------------ | ------------------------------------------------------------------- |
| `received`   | Automatically when customer submits the form                        |
| `confirmed`  | Accountant logs a payment or applies advance credit                 |
| `stitching`  | **Automatically** when a fabric batch is recorded for the catalogue |
| `dispatched` | Manager dispatches the order (only when `outstanding_balance = 0`)  |

**The `stitching` status is automatic, not a manual button.** When a FabricBatch is
created for a catalogue, all `confirmed` orders for that catalogue must auto-transition
to `stitching`.

---

## 3. User Roles & Access Control

There are exactly **4 internal roles** plus customers (who have no login).

| Role           | Spatie name  | What they can access                                       |
| -------------- | ------------ | ---------------------------------------------------------- |
| **admin**      | `admin`      | Everything — the full system                               |
| **accountant** | `accountant` | Customers, orders, payments, ledger, reports               |
| **manager**    | `manager`    | All production tracking, dispatch, wages, packed inventory |
| **designer**   | `designer`   | Catalogue view + design photo upload ONLY                  |

### Designer restrictions (non-negotiable)

The designer role **cannot** see:

- Customer records or ledger
- Payment history or financial data
- Production tracking screens
- Order management
- The dashboard widgets that show orders/payments/production

The designer gets access only to catalogue listing and design detail/edit for photo upload.
Route middleware must enforce this — the designer must not land on a screen showing
financial or order data.

### Route middleware groups currently in `routes/web.php`

- `role:admin` — user management, order reductions
- `role:admin|accountant` — customers, orders, payments, reports
- `role:manager` — all production routes, dispatch, wages
- No role restriction (auth only) — dashboard, catalogues (accessible to all including designer)

---

## 4. Database Enum Values — Must Match Exactly

### `orders.status`

```
received | confirmed | stitching | dispatched
```

### `customer_ledger.transaction_type`

```
advance_received | order_charged | payment_received | credit_applied | order_reduced | surplus_to_advance
```

### `payments.payment_type`

```
cash | bank_transfer | easypaisa | jazzcash | advance
```

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

### `order_reductions.adjustment_type`

```
damage | short_supply | price_correction | other
```

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

If the balance is not cleared, dispatch is blocked. The manager sees this message clearly.

### 5.3 Cargo Document Is a File Upload (Not Text)

Dispatch cargo document = **file upload** (PDF or image), stored in `cargo-documents/`
on the public disk, exactly like receipt images. The column `dispatch_batches.cargo_document`
stores the file path. Validation: `required|file|mimes:pdf,jpeg,jpg,png|max:10240`

### 5.4 Packed Inventory Deduction on Dispatch

After each dispatch batch is recorded, the quantities in that batch must be subtracted
from the `press_pack_records` (packed inventory). The `DispatchController::store()`
must loop through `dispatch_batch_items` and decrement the corresponding
`press_pack_records` rows by design and size.

### 5.5 Order Reduction — Surplus-to-Advance Credit Logic

`OrderReductionController::store()` must implement the full three-case logic:

**Case 1 — Customer has NOT paid anything (`total_paid = 0`):**

- Simply reduce `total_amount` and `outstanding_balance`. Done.

**Case 2 — Customer has paid PARTIALLY (`total_paid > 0` but `total_paid < new_total`):**

- Reduce `total_amount` to the new total
- Recalculate `outstanding_balance = new_total - total_paid`
- Create ledger entry: `order_reduced`, amount = `+$totalReduced` (positive, it's a credit)

**Case 3 — Customer has OVERPAID (`total_paid >= new_total`, i.e. surplus exists):**

- `$surplus = $total_paid - $new_total`
- Set `outstanding_balance = 0`, mark order fully paid
- Add `$surplus` to `customer->advance_credit_balance`
- Create TWO ledger entries:
    1. `order_reduced` — the reduction amount
    2. `surplus_to_advance` — the surplus added to advance credit
- Save `customer->advance_credit_balance`

### 5.6 Advance Credit Balance Must Be Kept Current

`Customer->advance_credit_balance` is the live running total of credit the customer holds.
It must be updated whenever:

- Advance payment received (`advance_received`) → **increase** balance
- Credit applied to an order (`credit_applied`) → **decrease** balance
- Surplus from order reduction (`surplus_to_advance`) → **increase** balance

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

Each dispatch is a **batch**, not necessarily the whole order. The order status only
changes to `dispatched` when ALL ordered quantities have been dispatched across all
batches. Use `$order->isFullyDispatched()` (already implemented in Order model) to
determine this. Never mark an order `dispatched` unless that method returns `true`.

### 5.10 Portal Access — Email Verification

`CustomerPortalController::verify()` must compare the email entered by the visitor
against `$customer->email` (case-insensitive). Only on exact match is access granted
and the dashboard shown. If no match: return back with error. Customer portal shows
3 tabs: current order status, payments & balance, full order history. The order status
tab displays quantities **broken down per size** (XS / S / M / L / XL) for each order.

### 5.11 No Password Reset on Login Screen

There is no "Forgot Password" link on the login page. Intentional — the admin resets
passwords manually. Do not add one.

---

## 6. Production Flow (In-House)

```
Fabric Batch arrives (FabricBatch)
    ↓ Auto-transitions all confirmed orders → stitching
Production Assignment (ProductionAssignment) — New Assignment form
    ↓ Manager picks: Catalogue → Destination (Naeem Pakki | Stitching Unit)
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
[Shirts only] TarpaiSend → TarpaiReturn
    ↓
PressPack (all designs, by size) → enters Packed Inventory
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
- `naeem_pakki_returns` has a single `quantity` column — no items sub-table.
- Per-piece rate (`per_piece_price`) is recorded on each send record. Different sends for the same design can have different rates.
- **Production Assignments for NP:** Manager uses the New Assignment form, selects Naeem Pakki as destination, and sees a table of all NP-eligible designs. Can assign multiple designs at once, each with qty + rate. One `ProductionAssignment` record is created per design. Quantity is stored as a single `production_assignment_items` row with `size = 'np'`.
- **Available qty guard:** The available qty shown in the NP assignment table = fabric received (from `fabric_batch_items`) minus already assigned (from `production_assignment_items`). The form prevents submitting if any qty exceeds available.
- `NaeemPakkiSend` and `NaeemPakkiReturn` models do **NOT** use `LogsActivity` trait — only `Order` and `Catalogue` do.

### Tarpai pricing

Same as above — per-piece rate is per design, stored on `TarpaiSendItem`.

### Stitching reconciliation

After all stitching returns: each design's returned quantities by size must exactly match
what was assigned. Any size-level discrepancy is flagged. The system does not prevent
returns that cause discrepancies — it flags them for review.

---

## 7. Financial Logic Summary

| Event                           | Ledger type                            | `amount` sign | Effect on `advance_credit_balance` |
| ------------------------------- | -------------------------------------- | ------------- | ---------------------------------- |
| Customer pays advance           | `advance_received`                     | positive      | increase                           |
| Order placed                    | `order_charged`                        | negative      | none                               |
| Payment received on order       | `payment_received`                     | positive      | none                               |
| Advance credit applied to order | `credit_applied`                       | positive      | decrease                           |
| Order reduced (no surplus)      | `order_reduced`                        | positive      | none                               |
| Order reduced (surplus)         | `order_reduced` + `surplus_to_advance` | both positive | increase by surplus                |

---

## 8. Key Route Names

| Route name       | URL pattern                   | Purpose                        |
| ---------------- | ----------------------------- | ------------------------------ |
| `order.public`   | `GET /order/{token}`          | Public catalogue order form    |
| `order.submit`   | `POST /order/{token}`         | Order form submission          |
| `order.thankyou` | `GET /order/{token}/thankyou` | Thank-you page                 |
| `portal.show`    | `GET /portal/{token}`         | Customer portal (email entry)  |
| `portal.verify`  | `POST /portal/{token}/verify` | Portal email verification      |
| `dispatch.store` | `POST /dispatch/{order}`      | Record a dispatch batch        |

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
- **Random order numbers** — `orders.order_number` is a randomly generated unique identifier displayed everywhere instead of the database `id`
- Orders view and management — Order Status card shown to all roles; only admin/manager can change status
- Payment recording (with receipt image upload and preview)
- Apply advance credit to orders
- Customer ledger view
- Order reduction (admin only) — _financial surplus-to-advance logic still incomplete_
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
- Press & Pack records
- Packed inventory tracker
- Outsourced batch arrivals
- Dispatch management (create batches)
- **Worker wages** — rate is now sourced from `stitching_units.per_piece_rate` (not catalogue); wage form has a unit selector that auto-populates the rate; `wages.stitching_unit_id` FK added
- All 12 reports — payroll history report shows stitching unit per wage record
- User management (create, enable, disable, password reset — admin only)

### Known Bugs / Incomplete Features (must fix)

1. **`order.show` route name used in controller** — should be `order.public`
2. **Dispatch payment check missing** — `DispatchController::store()` has no outstanding balance guard
4. **Cargo document is text, not file** — must be a file upload stored on disk
5. **Packed inventory not deducted after dispatch** — `DispatchController::store()` must decrement press_pack_records
6. **Order status auto-transition to stitching** — ✅ Fixed: `FabricBatchController::store()` auto-transitions confirmed orders on fabric batch creation
7. **Order reduction surplus logic** — three-case financial logic not implemented
8. **`running_advance_balance` hardcoded to 0** in all ledger entries — must be actual customer balance
9. **Dispatch order status** — order marked `dispatched` on every batch dispatch, should only be set when `isFullyDispatched()` returns true
10. **Designer role dashboard restriction** — designer should not see financial/order/production data on dashboard

### All Migrations (run `php artisan migrate` after pulling)

All migrations have been run. No pending migrations. For reference, the full set introduced across branches:

- `2026_05_02_000001` — adds `'np'` to `production_assignment_items.size` enum
- `2026_05_06_000001` — adds `discount_price` to `designs` and `quantity_benchmark` to `catalogues`
- `2026_05_06_000002` — creates `stitching_units` table and seeds Units 1–4
- `2026_05_06_000003` — migrates `stitching_unit` integer columns to FK on `production_assignments` and `stitching_returns`
- `2026_05_06_000004` — adds `per_piece_rate` to `stitching_units`
- `2026_05_06_000005` — adds `stitching_unit_id` FK to `wages`; drops `wage_rate` from `catalogues`

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

### File uploads

- Receipts: `storage/app/public/receipts/` → `$file->store('receipts', 'public')`
- Design photos: `storage/app/public/designs/`
- Catalogue covers: `storage/app/public/catalogues/`
- Cargo documents: `storage/app/public/cargo-documents/`
- All of these directories are in `.gitignore`

### Ledger entries — always use exact enum values

See Section 4. Never invent new transaction types. The migration is the source of truth.

### DB transactions

Wrap any operation that touches multiple tables in `DB::transaction(fn() => ...)`.
This includes: order submission, payment recording, order reduction, dispatch.

### Activitylog

Order and Catalogue models use `LogsActivity`. Changes to flagged fields are
automatically logged. Do not add manual activity log calls for these models.

### Never delete records

No `destroy()` routes exist by design. User accounts are disabled, not deleted.
Orders are reduced, not deleted. The audit trail must always be intact.

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
