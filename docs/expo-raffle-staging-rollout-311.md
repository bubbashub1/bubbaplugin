# PR #311 — staging rollout checklist

**Target:** `staging-site` only. **Do not merge into `main`**.

## Before merge
- [x] Selectively copied raffle-specific files from PR #307, avoiding unrelated changes on divergent branches.
- [x] Confirmed staging admin login uses `BUBBAHUB_ADMINSESSID`, `bh_admin_authenticated` and `bh_admin_last_activity`, matching raffle admin API.
- [x] Public raffle API exposes only `status='published'` prizes.
- [x] Ticket checkout and winner draw functionality are absent/disabled.
- [ ] Review the Expo layout and admin navigation in the staging PR diff.
- [ ] Check PHP syntax in CI or locally: `php -l api/expo-raffle-admin.php && php -l api/expo-raffle-prizes.php`.
- [ ] Review UK lottery compliance and payment-provider requirements **before any future paid ticket sales**.

## After merge and staging deployment
1. Confirm the GitHub **Deploy Bubba Hub Staging** workflow succeeds for `staging-site`. The workflow targets `staging_html`, not `public_html`.
2. Back up the **staging database**. Confirm the selected DB is staging, never live.
3. Apply `sql/expo-raffle-prizes-2027.sql` **to staging only**; do not run against production.
4. Sign in to staging admin and visit `/admin/expo-raffle-prizes.html`.
5. Create a **draft** prize; confirm it saves and does **not** appear on `/expo.html#win`.
6. Publish the test prize; confirm it appears on the Expo page with donor attribution. Change it to claimed; confirm it disappears.
7. Test invalid/expired admin session, invalid CSRF, mobile navigation, and missing-table error handling.
8. Confirm existing exhibitor registration, visitor tickets, and Expo layout still work.

## Not included yet
- Registered Class Leader **Donate a Prize** form and approval queue: documented in `docs/expo-raffle-leader-donations.md`, **not implemented**.
- Direct phone photo upload, ticket purchases, and winner draws: **not enabled**.

If the staging deployment or migration fails, do not deploy to live. Fix on the staging PR branch and re-test.
