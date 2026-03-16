# Fashion Shines POS

## Overview
A modern, lightweight Point of Sale (POS) & Inventory Management System built with PHP. Uses CSV flat-files for data storage — no SQL database required.

## Tech Stack
- **Language**: PHP 8.2
- **Frontend**: HTML5, Tailwind CSS (CDN), Vanilla JavaScript, Font Awesome 6
- **Storage**: CSV flat-files in `/data/` directory
- **Sessions**: File-based sessions stored in `/data/sessions/`

## Project Structure
- `index.php` — Main dashboard (requires login)
- `login.php` / `logout.php` — Authentication
- `pages/` — Feature pages (inventory, POS, reports, customers, dealers, etc.)
- `actions/` — Backend action handlers (form submissions, CRUD operations)
- `includes/` — Shared PHP includes (db.php, functions.php, header.php, footer.php, session.php)
- `assets/` — Static assets (CSS, JS, images, fonts)
- `data/` — CSV data files (auto-created on first run, gitignored)
- `uploads/` — Uploaded files

## Running the App
- **Workflow**: `php -S 0.0.0.0:5000` (port 5000)
- The PHP built-in server serves the entire project root

## Default Login
- **Username**: Deewan
- **Password**: admin
(Created automatically on first run via `includes/install.php`)

## Data Storage
All data is stored as CSV files in `/data/`:
- `products.csv`, `sales.csv`, `sale_items.csv`, `customers.csv`, `dealers.csv`
- `dealer_transactions.csv`, `customer_payments.csv`, `expenses.csv`, etc.
- Auto-migration system updates CSV headers as new features are added

## Deployment
- Target: VM (persistent file storage needed for CSV files)
- Run: `php -S 0.0.0.0:5000`
