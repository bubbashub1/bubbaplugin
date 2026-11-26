# Site Manager integration audit — 25 November 2026

## Application and page ownership

Bubba Hub is a standalone PHP/JavaScript application, not a WordPress-rendered site. The Page Manager remains authoritative for ordinary page records, hierarchy, legacy content and publishing. The Visual Builder stores isolated structured drafts and revisions; it must not create a second page registry or update legacy `content_html`.

## Existing image/File Manager

- UI: `/library/` (`library/index.html` and `library/app.js`).
- API: `library/api.php`.
- Existing folders: `images/general`, `images/home`, `images/listings`, `images/logos`.
- The API uses GitHub Contents API URLs hard-coded to repository `bubbashub1/bubbaplugin` and branch `main`. The raw image base also points at `main`.
- Upload writes to `main`; delete calls the GitHub Contents DELETE endpoint on `main`. These operations mutate the production source branch and must not be reused by the staging Site Manager.
- This run adds `api/admin-visual-media.php`, a GET-only endpoint protected by `admin-session-guard.php`. It lists raster images from the current deployment's local `images` directory, checks real paths and image MIME types, excludes symlinks, allows only four folders and caps results at 250. It has no upload, overwrite or delete action.
- The Visual Builder's Media tab can add one of these approved images to a private draft container. The image source is checked by the client and the PHP document validator; alternative text is editable and length-limited. No public page is changed.

## Existing navigation/menu system — secured and reused on 26 November 2026

- The existing store remains `bh_site_menus`; no parallel menu table or page registry was introduced.
- The existing `api/menu.php` is now split into a public read route and authenticated admin routes. Public GET is read-only, does not start an administrator session, and falls back to safe defaults if the table is absent or the database is temporarily unavailable.
- Table creation has moved to the explicit administrator-only POST setup action. A GET request no longer creates or changes database structures.
- Admin reads and writes use the shared `admin-session-guard.php`, a session-bound CSRF token, same-origin/Fetch Metadata checks, allow-listed menu groups and item fields, bounded labels/URLs/item counts, safe URL schemes, and a transaction for all four menu groups. Error responses no longer expose database exception messages.
- The existing Navigation & menus page is now a working editor for the main header and three footer groups. Reordering uses accessible Move up / Move down controls; no non-functional drag handle is presented.
- `assets/js/site-auth.js` already consumed the public footer menu data; it now also uses the configured main menu for the public header, with the existing default links as a fallback. The account link retains leader-account routing for authenticated leaders, and the existing logout control remains appended for authenticated users.
- The old individual navigation subpages remain legacy placeholders and are no longer the entry point. The admin dashboard continues to link to the consolidated Navigation & menus page.
- The Navigation Manager is separate from Visual Builder drafts. Saving changes the current deployment's menu configuration; deploy this code to staging only for QA. No page content, booking, directory, family or leader data is written by this feature.

## Navigation verification boundary

- Added pure PHP tests for allowed URL forms, unsafe schemes, path traversal, normalisation, group/item allow-lists, visibility, label length and item-count limits.
- GitHub Actions checks PHP syntax, menu validation tests, and JavaScript syntax for the manager and public auth/menu consumer.
- Repository tests do not prove browser behaviour or database permissions. Staging QA must verify unauthenticated public menu reads, administrator-only setup/save, CSRF rejection, all four menu groups, header/footer rendering, and regression behaviour before any live deployment.

## Safe next steps

1. Test the local media inventory on staging; confirm only staging-deployed files are listed.
2. Decide on a staging-safe upload/approval model before adding upload or delete capabilities. Do not use the existing File Manager write actions from staging.
3. Harden menu API setup and writes: shared admin guard, explicit setup, CSRF, allow-listed menu keys and destinations, generic errors.
4. Identify public navigation/footer consumers, then build a working editor in the existing Navigation area rather than creating a duplicate menu store.
5. Only expose menu editing in the Visual Builder after regression tests confirm login, booking, directory, family and leader navigation remain intact.

## Verification boundary

Repository code and PR metadata were reviewed. This change has not been deployed to staging and has not been tested in a browser or against a server filesystem. The new inventory endpoint lists only files present on the deployment where it runs.
