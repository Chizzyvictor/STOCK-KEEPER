<div align="center">
  <img src="Images/logo.png" alt="Stock Keeper logo" width="112">
  <h1>Stock Keeper</h1>
  <p><strong>A clear view of your inventory, from receiving stock to settling partner accounts.</strong></p>
  <p>
    <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white">
    <img alt="SQLite" src="https://img.shields.io/badge/Database-SQLite-003B57?logo=sqlite&logoColor=white">
    <img alt="Bootstrap 5" src="https://img.shields.io/badge/UI-Bootstrap%205-7952B3?logo=bootstrap&logoColor=white">
  </p>
</div>

Stock Keeper is a browser-based inventory and stock management application for businesses. Keep product quantities, stock activity, partner balances, and operational reports together in one place.

## At a glance

- **Dashboard** — monitor inventory value, movement totals, low-stock alerts, and recent activity.
- **Product catalogue** — organize products by category and unit; track SKUs, cost and selling prices, reorder levels, and active status.
- **Stock control** — record stock in and out, review a searchable movement ledger, and reconcile physical counts with stock-take history.
- **Partner accounts** — manage suppliers and customers, record payments and advances, and review or export account statements.
- **Reports** — inspect date-filtered purchasing, sales, product and partner activity; export report data as CSV.
- **Business settings** — manage business details, categories, measurement units, and contact imports and exports.
- **Business accounts** — create an account and sign in to keep each business's records separate.

## Getting started

### Requirements

- PHP 8.1 or newer
- PHP `SQLite3` extension
- A web server with PHP support, or PHP's built-in development server

### Run locally

1. Clone the repository and change into the project directory.
2. Set `STOCK_KEEPER_DB_PATH` to an **absolute path outside the public web root**. Keep this setting active in the terminal you use to run the app.

   **PowerShell**

   ```powershell
   $env:STOCK_KEEPER_DB_PATH = 'C:\path\to\data\mysqlitedb.db'
   ```

   **Linux / macOS**

   ```sh
   export STOCK_KEEPER_DB_PATH=/path/to/data/mysqlitedb.db
   ```

3. Initialize the database schema from that terminal:

   ```sh
   php exe.php
   ```

4. Start the development server from the same terminal:

   ```sh
   php -S localhost:8000
   ```

5. Open [http://localhost:8000/login.php](http://localhost:8000/login.php) and choose **Sign Up** to create your business account.

The schema initializer is **CLI-only**. Do not expose it as a web setup page. If you use the project-local `mysqlitedb.db` fallback instead, make sure your local web server blocks direct access to database files; PHP's built-in server does not apply Apache `.htaccess` rules.

## Tests

Run the standalone PHP tests from the project root:

```sh
php tests/DatabaseBootstrapTest.php
php tests/PartnerContactImporterTest.php
php tests/ProductCatalogueInstallerTest.php
```

The database bootstrap test uses a temporary database path; the other tests use isolated SQLite fixtures.

## Project layout

```text
├── INC/        Shared layout, session, database, and schema helpers
├── scripts/    Browser-side application and API interactions
├── styles/     Application styles
├── tests/      Standalone PHP tests
├── assets/     Locally bundled front-end libraries
├── Images/     Application and upload image assets
├── api*.php    JSON endpoints for application workflows
├── exe.php     CLI database initializer and schema updater
└── *.php       Application pages
```

## Production deployment

For production requirements—including HTTPS, filesystem permissions, database backups, and web-server deny rules—see [DEPLOYMENT.md](DEPLOYMENT.md). In particular, configure `STOCK_KEEPER_DB_PATH` to an absolute, persistent location **outside the public web root**, and ensure only the PHP worker can access it.

## Technology

- PHP 8.1+
- SQLite with the PHP `SQLite3` extension
- Bootstrap 5, vanilla JavaScript, and locally bundled vendor assets
