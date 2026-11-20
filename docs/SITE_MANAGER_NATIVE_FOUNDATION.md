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
- `assets/css/site-manager-design.css` defines safe baseline token mappings and is imported by `assets/css/styles.css`. After a published snapshot loads, primary colour maps to the shared green/brand aliases; accent maps to teal/yellow/blue aliases; surface maps to page/mist aliases; text maps to text/ink aliases. The radius control covers common activity, homepage, family hub, admin, subscription, pricing, support and calendar panels. These are deliberate shared-component mappings, not universal control of every legacy component.

## Important scope limitation
The published tokens currently provide a foundation, not complete visual control of every legacy component. Some site styles use hard-coded values and will not respond yet. The radius control now covers a defined set of shared panel/card classes; specialised page components may still retain their own shapes. Section spacing currently affects only `.section-gap`, not every section's padding. Do not claim that the entire site can already be restyled from this panel.

## Deployment and QA
Develop on `staging/site-manager-native-20261120`, based on `staging-site`. Do not merge to main or deploy live. After staging deployment, test admin authentication, setup, save draft, reload/discard, publish snapshot, invalid input rejection, CSRF rejection, phone layout and existing admin navigation. Then verify public pages load the published tokens and that existing login, booking, directory and other interactions remain unchanged.

## Homepage section controls (first implementation)
- The real homepage is `index.html`; it is a standalone page using `assets/js/explore.js`, not a WordPress template.
- Stable `data-bh-home-section` keys identify six optional sections: ticker, categories, quick links, latest activities, regions and family/leader CTA.
- The hero search and footer are deliberately protected and are not toggleable.
- Visibility is stored inside the existing draft/published Site Manager JSON setting. No additional table or migration is introduced.
- The admin controls default all six sections to visible, merge defaults for older settings snapshots, and save through the existing CSRF-protected draft/publish API.
- The homepage reads only the public published snapshot. Missing settings, endpoint errors or missing keys leave sections visible. Hidden sections are hidden without removing their DOM, so existing scripts and IDs remain intact.
- Section order is stored as a validated six-key array in the same draft/published settings JSON. The admin uses accessible up/down buttons with a live announcement; the public homepage applies order only after validating the complete key set. The hero and footer remain outside the reorderable set. Invalid or missing order data falls back to the default order.

## Next phases
1. Staging QA of homepage section visibility, including older settings snapshots, draft/publish, endpoint failure and phone layout.
2. Complete staging QA of section ordering and keyboard/mobile controls; verify hidden sections remain hidden after reordering and preserve existing page scripts.
3. Connect section spacing and card shape to the actual shared components; add component-specific controls and revision/restore.
4. Add navigation, banner/media and responsive settings.
5. Add click-to-edit visual editing only after stable component schemas exist.
