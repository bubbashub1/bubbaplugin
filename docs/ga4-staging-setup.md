# Bubba Hub GA4 staging foundation

This branch does **not** deploy to live or staging automatically. The production deployment workflow watches `main`.

## Configuration
- Set `window.BH_ANALYTICS_ID = 'G-XXXXXXXXXX'` before loading `/assets/js/bh-analytics.js`.
- No measurement ID is included yet: tracking is disabled.
- Staging hostnames are excluded even if configured.
- The script requires `localStorage.bh_analytics_consent = 'granted'`. A compliant consent UI and withdrawal handling must be wired up before production use.
- Install this script **once** on each custom HTML page, preferably in a common shared header. Do not install GA4 through another plugin/tag manager on the same page.
- For WordPress, add a single enqueue in the active theme or an MU plugin, but first audit Site Kit and other tag plugins to avoid duplication.
- Do not send names, emails, child information, search text containing personal information, or other PII as events.
- Confirm page views with GA4 DebugView / Tag Assistant after consent. Verify no duplicate page_view events.
