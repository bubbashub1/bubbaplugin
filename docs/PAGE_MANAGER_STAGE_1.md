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

## Public rendering and clean URLs
Published pages render through `page.php?slug=...`; this legacy integration URL remains supported. Clean public URLs use the dedicated `/content/{slug}/` namespace, for example `/content/test-page/`. This separate namespace avoids collisions with root-level activity slugs and existing application routes.

The `/content/{slug}/` rewrite must appear before the generic root-level activity-slug fallback in `.htaccess`. Verify published pages load without an admin session, while draft and archived pages remain unavailable publicly. Do not change the live site's rewrite configuration until staging QA and release approval are complete.

## Security
- Uses the existing dedicated Bubba Hub admin session and server-only PDO configuration.
- Requires a session CSRF token for writes.
- Uses prepared SQL statements and validates slugs/status.
- Sanitises submitted HTML and rejects reserved system slugs.
- Keeps revisions before updates; archive is reversible.
