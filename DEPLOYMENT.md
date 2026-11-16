# Deployment notes

## Requirements

- PHP 8.1 or newer with `SQLite3` and a working session handler.
- HTTPS in production.
- A writable, persistent directory for the SQLite database, outside the public document root.
- Writable `Images/companyDP` only if business logo uploads are enabled.

## Database setup

Set `STOCK_KEEPER_DB_PATH` in the PHP-FPM/Apache environment to an **absolute path** outside the web root, for example `/var/lib/stock-keeper/mysqlitedb.db`. Give the PHP worker read/write access to the database and write access to its parent directory; keep those permissions unavailable to other users. Do not put the database on a network filesystem.

The project-local `mysqlitedb.db` fallback is retained for existing local installations. Production should set the environment variable. The included `.htaccess` blocks direct access to that fallback database and its SQLite journal files when Apache honors per-directory rules. For Nginx or another server, add equivalent deny rules for database files and the CLI setup scripts; `.htaccess` is not read by Nginx.

For Nginx, apply rules like these in the site server block (adjust the database filename if it is stored below the document root):

```nginx
location ~* ^/(mysqlitedb\.db(-wal|-shm)?|.*\.(sqlite|sqlite3|db)(-wal|-shm)?)$ { deny all; }
location ~ ^/(exe|migrate_product_units)\.php$ { deny all; }
```

Back up the database before changing application files or running setup/migration commands. Run `php exe.php` from the project directory to initialize an empty database or apply the current schema extensions, including product units. It is CLI-only and no longer creates a placeholder business account; create the first real account through the sign-up page. `migrate_product_units.php` remains available for older installations and is also CLI-only. Run migrations against a backup or staging copy first.

## Web server and secrets

- Point the web root at this application directory only if the server denies database and backup files; preferably keep persistent data elsewhere.
- Disable directory listing, enable HTTPS redirects, and configure trusted proxy HTTPS handling if TLS terminates before PHP.
- If TLS terminates at a trusted reverse proxy and PHP does not see HTTPS, set `STOCK_KEEPER_HTTPS=1` in the PHP runtime so session cookies remain Secure. Do not use client supplied forwarded headers as the trust signal.
- Keep PHP error display disabled and send errors to private logs.
- Apply upload limits consistent with the application (logo/contact files are limited to 2 MB).
- Use a regular, tested backup schedule and verify restoration before production use.
- Configure edge/server rate limits for login and password recovery. Recovery currently uses security questions rather than email-based recovery; treat this as a limited recovery method.

## First deployment checklist

1. Copy the app and set the database path and filesystem permissions.
2. Run the CLI schema initializer against an empty database or a staging copy.
3. Configure HTTPS and web-server deny rules; confirm the database URL and setup-script URLs return 403/404.
4. Create the initial account, configure a logo if desired, and test a full stock movement and backup/restore using staging data.
5. Confirm your production PHP upload and request-size settings allow the documented 2 MB uploads.
