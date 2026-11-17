# Bubba Hub Page Manager — Stage 1

## Scope
The first release adds an authenticated admin page list and basic page editor for ordinary content pages. It supports draft/published status, archive/restore, unique slugs, search/filter, and a revision snapshot before each edit.

System/application pages remain protected and are not imported into or overwritten by this content system.

## Files
- `admin/admin-page-manager.html` — admin interface
- `assets/js/admin-page-manager.js` — browser interactions
- `api/admin-pages.php` — admin-only JSON API
- `database/migrations/20261112_create_bh_pages.sql` — one-time schema migration

## Deployment
1. Deploy the feature branch through the normal review and release process.
2. Open `/admin/admin-page-manager.html` while signed in to the admin account.
3. If prompted, select **Set up Page Manager**. This explicit, authenticated admin action creates the two Page Manager tables in the standalone Bubba Hub database used by `api/db.php`.
4. Confirm that a new draft can be created, edited, previewed, published, archived and restored.

The setup action is idempotent and is not exposed publicly. The SQL migration is retained as a manual fallback for controlled database administration.

## Public rendering
The admin editor's preview currently opens the reserved integration endpoint `/page.php?slug=...`. Public page rendering and clean URL routing are a separate follow-on task and must be completed before published pages are promoted as live website pages. Do not add rewrite rules until existing standalone routes and the host's current rewrite configuration have been reviewed.

## Security
- Uses the existing dedicated Bubba Hub admin session and server-only PDO configuration.
- Requires a session CSRF token for writes.
- Uses prepared SQL statements and validates slugs/status.
- Sanitises submitted HTML and rejects reserved system slugs.
- Keeps revisions before updates; archive is reversible.
