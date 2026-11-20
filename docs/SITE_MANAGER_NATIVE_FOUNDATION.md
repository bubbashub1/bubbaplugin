# Bubba Hub Site Manager — native foundation

## Architecture decision
Bubba Hub is a standalone PHP/JavaScript application, not a WordPress theme/plugin. The Site Manager therefore lives in the existing admin area and uses the existing admin session guard and PDO database connection. Do not add WordPress MU-plugins or depend on WordPress APIs.

## This phase
- `admin/admin-site-manager.html`: admin UI for palette, typography, card/button shape and section spacing.
- `assets/js/admin-site-manager.js`: authenticated same-origin API client, live sample preview, desktop/mobile preview frame, draft/publish controls.
- `api/admin-site-settings.php`: admin-session-protected JSON endpoint with session CSRF token, validated allow-listed settings and explicit one-time table setup.
- `database`: the endpoint creates `bh_site_settings` only after an authenticated admin explicitly selects setup. It does not alter existing tables.
- Published settings are stored separately from drafts. The UI explicitly states that public page styles are not yet connected; no site-wide CSS is injected in this phase.

## Deployment and QA
Develop on `staging/site-manager-native-20261120`, based on `staging-site`. Do not merge to main or deploy live. After staging deployment, test admin authentication, setup, save draft, reload/discard, publish snapshot, invalid input rejection, CSRF rejection, phone layout and all existing admin navigation. Verify public pages remain visually unchanged in this phase.

## Next phases
1. Connect approved design tokens to shared site styles without broad selectors or page-specific regressions.
2. Add component-specific controls and revision/restore.
3. Extend page manager with safe editable section blocks and ordering.
4. Add navigation, banner/media and responsive settings.
5. Add click-to-edit visual editing only after stable component schemas exist.
