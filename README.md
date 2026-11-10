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


## Listing Checker setup (PR #846)

The Listing Checker admin settings and discovery-term editor use the standalone `bh_*` database. Before opening the settings page on a deployed environment, run the one-time migration against the Bubba Hub database:

```sh
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < database/listing-checker-schema.sql
```

This creates checker configuration, editable discovery terms, run/queue tracking and review-finding tables. It does not modify activity, organiser or venue records. The admin page is available at `/admin/admin-listing-checker-settings.html`.

The settings page stores the desired schedule (default every 4 hours, batch size 30, Europe/London) and discovery terms. **It does not itself start scheduled work**: the CLI workers and hosting cron entries must be implemented and configured before those saved schedules take effect.


### Existing-listing CLI worker

After applying the migration, the existing-listing batch worker can be invoked from a server-side cron (never expose it as a public URL):

```sh
php /path/to/bubbaplugin/api/listing-checker-health-cron.php
```

Configure the hosting cron to invoke it every four hours. It uses the saved enable flag, UK timezone/run-window/day settings, batch size and maximum runtime; it uses a MySQL advisory lock to prevent overlapping runs. Each run handles up to the configured batch size (default 30), saves findings in the separate review table and never updates live activity records. The worker exits safely with a migration-required message until the SQL migration has been applied.


### Listing Checker review queue

The admin review queue is available at `/admin/admin-listing-review.html`. It reads persisted findings from `bh_listing_check_findings` and allows an administrator to record a review status (new, follow-up, approved, rejected, confirmed issue or not an issue). These actions update only the finding's review metadata; they never publish, edit, remove or contact a listing. Source links are limited to HTTP(S).
