# AGENTS.md — POS-DEWAAN (Fashion Shines POS)

Guide for AI agents working on this codebase. Read this before making changes.

## Project Overview

A lightweight **Point of Sale + Inventory Management System** for a retail/wholesale business (Fashion Shines). Built to run locally on XAMPP with **no database server** — all data lives in CSV flat-files. The app runs in a browser as a desktop-style app.

- **Stack:** PHP 7.4+, vanilla JS, Tailwind CSS (CDN via `assets/js/tailwind.js`), Font Awesome
- **Storage:** CSV files in `data/` (file-locked reads/writes, no SQL)
- **Time zone:** `Asia/Karachi` (set in `includes/session.php`)
- **PHP binary:** `C:\xampp\php\php.exe` — NOT in PATH; always invoke explicitly when running PHP from the CLI. Use `C:\xampp\php\php.exe -l file.php` for syntax checks.
- **Local URL:** `http://localhost/pos-dewaan/`
- **Git:** repo on `master` branch; remote `origin` = `https://github.com/rafayqazi/POS-DEWAAN.git`. Push after each completed feature (user asks).
- **Environment note:** `includes/db.php` redirects to `contact_developer.php` if the `.git` folder is absent — pages cannot render without that folder.

## Directory Map

```
pos-dewaan/
├── index.php               Dashboard (sales trends, balances, alerts)
├── login.php / logout.php  Auth
├── heartbeat_check.php / verify_session.php   # maintenance/live-server helpers
├── includes/
│   ├── db.php              # THE DATA LAYER: CSV helpers, migrations
│   ├── functions.php       # helpers: auth/RBAC, currency, units, notifications
│   ├── session.php         # custom session mgmt (24h lifetime)
│   ├── header.php          # sidebar/nav (menu is defined here), showAlert/showConfirm
│   └── footer.php
├── pages/                  # UI pages (GET/page + inline POST handling)
│   ├── pos.php             # New sale / checkout (biggest file)
│   ├── inventory.php       # product CRUD, restock, stock filters, report printing
│   ├── sales_history.php customers.php customer_ledger.php dealers.php dealer_ledger.php
│   ├── restock_history.php return_product.php return_history.php expenses.php reports.php
│   ├── settings.php backup_restore.php categories.php units.php lockdown.php
│   └── print_*.php         # dedicated print/PDF views (printBill, print_sales, etc.)
├── actions/                # POST endpoints called from forms (redirect back after)
│   └── process_return.php, restock_process.php, save_expense.php, ...
├── data/                   # ALL persistent data as CSV (see schema below)
├── assets/                 # css/fonts/tailwind chart.js images
└── scratch/, migrations/   # one-off scripts (repair/backfill) — not part of app
```

## Data Layer (`includes/db.php`) — READ THIS FIRST

All CSVs live in `data/`. Each file has a header row. No SQL anywhere.

Key functions (all in `includes/db.php`):

| Function | Purpose |
|---|---|
| `readCSV($table)` | returns array of assoc rows (shared lock) |
| `insertCSV($table, $row)` | auto-assigns `id = max+1`, returns the new id |
| `updateCSV($table, $id, $new_data)` | merges fields; keys must exist in headers |
| `deleteCSV($table, $id)` | removes row by id |
| `writeCSV($table, $data, $headers)` | full-file replace — destructive, use only in migrations |
| `findCSV($table, $id)` / `findById` / `findCSV` | single row lookup |
| `processCSVTransaction($table, callback)` | **atomic update pattern**: exclusive-lock the file, callback receives all rows, return the new full array. Use for stock changes to avoid concurrent write corruption. |
| `getSetting($key)`, `updateSetting($key, $value)` | settings.csv key/value |
| `ensureCSVHeaders($table, $headers)` / `runMigrations()` | schema self-heal at bootstrap; **migrations run automatically** in db.php |
| `repairCSVIds($table)` | fixes scientific-notation/dup ids |

Conventions:
- Column set is per-CSV; never required columns beyond `id`.
- All money quantities stored as plain strings; cast with `(float)` when computing.
- CSV rows are matched by **string** id equality (`==`/`===` — use both loosely; ids in files are strings like `"48"`).
- **Never hard-code a path to a CSV** — always `getCSVPath()/readCSV()`.

## Key business/stock logic (read these before touching stock)

- **Units:** products have `unit` + `factor_level2`, `factor_level3` hierarchy; base unit is the SMALLEST. `getBaseMultiplier($unit_id_or_name, $product)` returns multipler of buying/selling unit descendants — see `includes/functions.php` `getUnitHierarchy`.
- **AVCO (avg_buy_price):** restocks compute moving average: `avg_buy_price` = `(old_stock*old_avg + new_qty*new_price) / (old_stock + new_qty)`. Stored as base-unit price.
- **Stock levels:** `stock_quantity` in base units. `formatStockHierarchy($qty, $product)` humanizes (e.g. `1 Ctn, 2 Box, 3 Piece`) — used everywhere.
- **Roles (RBAC):** users have `role` ∈ Admin / Viewer / Customer / Dealer; enforcement via `hasPermission($action)` in functions.php; `filterDataByRole('table', rows)` restricts CSV data per role. If a request hits a page without permission it falls through to a generic "Unauthorized Access" die().
- **Notifications:** `getGlobalNotifications()` in functions.php builds low-stock / expiry / debt alerts via session-dismissed ids (actions/dismiss_alert.php).

## Page flow / conventions for pages

- Each page starts:
  ```php
  require_once '../includes/db.php';
  require_once '../includes/functions.php';
  requireLogin();
  if (!hasPermission('add_sale')) die("Unauthorized Access");
  ```
  (`index.php` uses non-relative paths).
- Pages that handle POST (checkout, add, edit, delete, restock, return…) handle their POST in `pages/*.php` top-level **before** the `header.php` output, redirect to the same page after success (`redirect('..foo')`).
- `actions/` files may also exist — make sure to redirect back after success.
- Printing/PDF: `pages/print_*.php` render standalone HTML pages with inline CSS — no TCPDF; "PDF" = browser Print dialog. `inventory.php` builds a hidden `#printableArea` and `printReport()` clones/filters it in JS.

## Known conventions & gotchas you must respect

1. **CSV structure is the source of truth** — before writing to a table, `readCSV` to see real columns of that CSV (they drifted from the schemas below over migrations).
2. `id` fields in CSVs are **strings**; comparing with strict `===` will break. Use `==`.
3. When you modify stock/qty in two places, wrap both in a single `processCSVTransaction` instead of separate `readCSV`+`updateCSV` calls.
4. `formatCurrency()` no-decimal, `formatCurrencyJS()` used in JS mirrors.
5. Settings keys: `business_name`, `business_address`, `business_phone`, `expiry_notify_days`, `recovery_notify_days`, `db_schema_version`.
6. Header navigation lives in `includes/header.php`; the sidebar menu items are PHP-generated; if you rename a page, update header + the JS spotlight search list (line ~700).
7. Styling: tailwind CDN + custom CSS in header; keep the existing class-system (rounded-2xl/cards).
8. When adding columns to a CSV, do it via a migration version bump in `runMigrations()` (`db_schema_version`), not ad-hoc breaks.

## Commands

- Syntax check: `C:\xampp\php\php.exe -l file.php`
- Run an inline PHP script where php is required: `C:\xampp\php\php.exe -d error_reporting=E_ERROR some_script.php` — data CSVs must exist.
- Git: `git add . && git commit -m "..." && git push origin master` (only comment-style commit messages).

## Branching/devel naming

- Single branch `master` in use; keep commits small and focused.

## Maintaining this file

This document is the project's living knowledge base for AI agents.

**Whenever you change the codebase, also update AGENTS.md if any of these change:**
- New or renamed directories / pages / actions / data tables (update the Directory Map)
- New CSV files or changed columns/schema (update the Data Layer section)
- New key business rules, stock logic, roles/permissions, or settings keys
- New conventions (e.g., a new print pattern, a new helper function contract)
- New dependencies, assets, or dev/run commands
- Anything a fresh agent would need to know to avoid breaking the system

If you are unsure whether a change belongs in AGENTS.md, err on the side of adding it — it costs little and prevents future mistakes.