# Bubba Hub Site Manager — native foundation

## Architecture decision
Bubba Hub is a standalone PHP/JavaScript application, not a WordPress theme/plugin. The Site Manager therefore lives in the existing admin area and uses the existing admin session guard and PDO database connection. Do not add WordPress MU-plugins or depend on WordPress APIs.

## Current foundation
- `admin/admin-site-manager.html`: admin UI for palette, typography, card/button shape and section spacing, with a sample preview and draft/publish controls.
- `assets/js/admin-site-manager.js`: authenticated same-origin API client and admin preview/draft/publish interactions.
- `api/admin-site-settings.php`: admin-session-protected JSON endpoint with session CSRF token, validated allow-listed settings and explicit one-time table setup.
- The endpoint creates only `bh_site_settings` after an authenticated admin explicitly selects setup. It does not alter existing tables.
- Draft and published settings are stored separately.
- `api/site-design.php`: public read-only endpoint exposing only the published allow-listed settings.
- The shared `assets/js/header.js` loader loads published settings and sets validated CSS custom properties. It is used by the shared public-page loader in `assets/js/app.js` and by pages that include the header script directly. It fails silently when no published settings exist.
- `assets/css/site-manager-design.css` defines safe baseline token mappings and is imported by `assets/css/styles.css`.

## Important scope limitation
The published tokens currently provide a foundation, not complete visual control of every legacy component. Some site styles use hard-coded values and will not respond yet. In particular, section spacing, card radius and other component styling need to be connected deliberately to real components. Do not claim that the entire site can already be restyled from this panel.

## Deployment and QA
Develop on `staging/site-manager-native-20261120`, based on `staging-site`. Do not merge to main or deploy live. After staging deployment, test admin authentication, setup, save draft, reload/discard, publish snapshot, invalid input rejection, CSRF rejection, phone layout and existing admin navigation. Then verify public pages load the published tokens and that existing login, booking, directory and other interactions remain unchanged.

## Next phases
1. Audit existing shared CSS variables and replace only safe, repeated hard-coded design values with shared tokens.
2. Connect section spacing and card shape to the actual shared components; add component-specific controls and revision/restore.
3. Extend the page manager with safe editable section blocks and ordering.
4. Add navigation, banner/media and responsive settings.
5. Add click-to-edit visual editing only after stable component schemas exist.
