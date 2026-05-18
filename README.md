# CasualiteOS — Business Operations System

A full-stack internal business operations system built for **Casual Lite**, a fashion brand. CasualiteOS replaces manual spreadsheets, notebooks, and WhatsApp-based workflows with a centralised, role-based web application.

---

## Tech Stack

- **Framework:** Laravel 13
- **Language:** PHP 8.3
- **Database:** MySQL
- **Frontend:** Blade templates · Tailwind CSS (CDN) · Alpine.js (CDN)
- **Packages:** Spatie Laravel Permission v7 · Spatie Laravel Activitylog v5

---

## Features

### Catalogue Management
- Create catalogues with cover photo, designs, per-design selling price, wage rate, and internal notes
- Auto-generated shareable order link (unique token per catalogue)
- Manual open/close; sold-out state enforced on the public order form

### Customer Management
- Customer master list with full profile, order history, and ledger
- Auto-generated permanent customer portal link (token-based)
- Email-based order matching — incoming orders are automatically linked to the correct customer record

### Public Customer Order Form
- No login required — customers open their catalogue link and submit quantities
- Live real-time order total calculation (Alpine.js) as the customer types
- Email verified against the master list before accepting the order

### Customer Self-Service Portal
- Unique link per customer; email verification required before data is shown
- Three views: current order status · payment balance & advance credit · full order history

### Order & Payment Management
- Four order statuses: Received → Confirmed → Stitching → Dispatched
- Full, partial, and advance-credit payment logging
- Per-customer financial ledger with chronological transaction history
- Order flagging for attention; flagged orders listed separately

### In-House Production Tracking
- **Fabric Batch Arrivals** — record per-design pieces received from embroidery factory
- **Production Assignments** — assign every design to Naeem Pakki or Stitching Unit, by size
- **Naeem Pakki** — log sends (per-design rate) and returns; outstanding pieces highlighted
- **Stitching Returns** — daily returns recorded by design and size; discrepancies flagged
- **Tarpai Finishing** — send/return tracking with per-design rate
- **Press & Pack** — record pressed and packed suits by design and size
- **Packed Inventory** — live view of all suits ready for dispatch

### Outsourced Design Tracking
- Log batches received from outside factories (per-design, per-size quantities)
- Automatically feeds into the Packed Inventory pool

### Dispatch & Delivery
- Multiple batch dispatches per order (batch number, date, shipping address, cargo document)
- Full dispatch history per order

### Worker Wages
- Weekly wage calculation: suits stitched × catalogue wage rate
- Manager confirms payment; full payroll history maintained

### Reports (12 total)
- Catalogue Summary · Customer Master List · Customer Orders · Customer Ledger
- Production Status · Stitching Reconciliation · Packed Inventory · Payroll History
- Outsourced Designs · Dispatch History · Activity Log · Damage & Reductions

### Order Reduction (Admin Only)
- Reduce order quantity by design and size when pieces are damaged
- Automatically adjusts order total and creates a ledger credit for the customer

### User & Access Management
- Four roles: **Admin** · **Accountant** · **Manager** · **Designer**
- Admin creates, enables, and disables accounts (no self-service password reset)
- Role-based middleware on every route group
- Tamper-proof activity log for all manager and accountant actions

---

## Local Setup

### Requirements
- PHP 8.3+
- Composer
- MySQL
- Laravel CLI (`composer global require laravel/installer`)

### Steps

```bash
# 1. Clone the repository
git clone <repo-url> casualos
cd casualos

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and configure
cp .env.example .env
# Edit .env — set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 4. Generate application key
php artisan key:generate

# 5. Run migrations
php artisan migrate

# 6. Seed the admin user
php artisan db:seed --class=AdminSeeder

# 7. Create the storage symlink (for uploaded files)
php artisan storage:link

# 8. Start the development server
php artisan serve
```

Then open `http://localhost:8000` and log in with the admin credentials created by `AdminSeeder`.

---

## Project Structure

```
app/
├── Http/Controllers/       # 23 controllers
├── Models/                 # 31 Eloquent models
resources/
├── views/
│   ├── layouts/            # Main app layout (sidebar, nav, auth check)
│   ├── auth/               # Login
│   ├── dashboard/          # Role-aware dashboard
│   ├── catalogues/         # Catalogue + design management
│   ├── customers/          # Customer profiles, ledger, portal
│   ├── orders/             # Order management, reduction, flagged
│   ├── portal/             # Customer self-service portal
│   ├── public/             # Public order form (no auth)
│   ├── production/         # Fabric, assignments, Naeem Pakki, stitching,
│   │                       # Tarpai, press/pack, outsourced, dispatch, wages
│   ├── reports/            # 12 report views
│   └── users/              # User account management
database/
└── migrations/             # 23 migrations
```

---

## Notes

- No npm/Vite build step — Tailwind CSS and Alpine.js are loaded via CDN
- The public order form and customer portal are standalone Blade files (no `@extends` layout) because they are unauthenticated routes
- `php artisan storage:link` is required for catalogue cover photos and design images to be served correctly

---

*Built by [The Techmint](mailto:thetechmint2025@gmail.com) for Casual Lite.*
