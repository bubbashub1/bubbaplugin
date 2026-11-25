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

## Existing navigation/menu system

- The admin dashboard links to `admin/admin-navigation.html`; its three menu subpages are currently placeholders.
- `assets/js/menu-manager.js` expects `#menuManagerLists`, `#saveMenus` and `#menuManagerStatus`, which are not present in the current admin dashboard markup; it does not initialise as a complete editor.
- `api/menu.php` creates `bh_site_menus` during GET and has no CSRF validation for writes. It should not be connected to the Site Manager until hardened.
- Keep current public header/footer navigation and family/leader application navigation unchanged.

## Safe next steps

1. Test the local media inventory on staging; confirm only staging-deployed files are listed.
2. Decide on a staging-safe upload/approval model before adding upload or delete capabilities. Do not use the existing File Manager write actions from staging.
3. Harden menu API setup and writes: shared admin guard, explicit setup, CSRF, allow-listed menu keys and destinations, generic errors.
4. Identify public navigation/footer consumers, then build a working editor in the existing Navigation area rather than creating a duplicate menu store.
5. Only expose menu editing in the Visual Builder after regression tests confirm login, booking, directory, family and leader navigation remain intact.

## Verification boundary

Repository code and PR metadata were reviewed. This change has not been deployed to staging and has not been tested in a browser or against a server filesystem. The new inventory endpoint lists only files present on the deployment where it runs.
