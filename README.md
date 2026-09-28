# BubbaPlugin

Standalone Bubba Hub family activity directory and planner.

This repository is intentionally independent of WordPress, Directorist, ACF, Ninja Forms and other CMS/plugin dependencies.

## Status

Standalone beta application with directory, activity detail, My Planner, My Hub, account/preferences, class leader and admin areas, backed by the current API layer.

## Deployment

GitHub Actions deployment workflow is included in `.github/workflows/deploy.yml` and publishes the standalone app to eWebAll over SFTP.

The beta currently uses the standalone API layer and is being prepared for full production database-backed family, organiser, booking and planner workflows.
## File Manager

The File Manager is available at `/library/` and manages the repository image folders:
- `images/listings/`
- `images/home/`
- `images/logos/`
- `images/general/`

Before using it, add a private key to the existing server-side `github-deploy-config.php`:

```php
'file_manager_key' => 'YOUR_LONG_RANDOM_PRIVATE_KEY',
```

The File Manager sends uploads through the existing server-side GitHub token, so the GitHub token is never exposed to the browser. Images are committed into the appropriate `images/` folder and the existing deployment workflow publishes the updated image folders to the server. Uploads are limited to JPG, JPEG, PNG, WebP and GIF, up to 8 MB each.


## Class Leader Portal

The standalone Class Leader Portal is at `/leader.html`.

The data model is organised as:

**Organiser → Activity → Venue → Session**

This allows one class leader to manage one listing with multiple venues and different sessions at each venue.

## MySQL connection

The repository includes:
- `api/db.php` — PDO connection using server-side configuration only.
- `api/health.php` — safe connection test endpoint; it does not expose database details.
- `database/schema.sql` — standalone `bh_*` table foundation.

Add these keys to the existing server-side `github-deploy-config.php` when ready:

```php
'mysql_host' => 'YOUR_MYSQL_HOST',
'mysql_database' => 'YOUR_DATABASE_NAME',
'mysql_username' => 'YOUR_DATABASE_USER',
'mysql_password' => 'YOUR_DATABASE_PASSWORD',
'mysql_charset' => 'utf8mb4',
```

Do **not** put database credentials in GitHub or any browser JavaScript.

The schema deliberately creates only `bh_*` tables so the existing WordPress `wp_*` tables are left untouched while the standalone application is tested.
