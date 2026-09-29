# BUBBA HUB READ-ONLY REPOSITORY AUDIT

Repository: bubbashub1/bubbaplugin
Branch: main
Audit date: 29 September 2026
HEAD: 981120c2c79d6ce31aad26cfc9b0c0037e752491
HEAD commit: Fix admin bold theme stylesheet path and cache version

This is a read-only source audit. No application source files were changed as part of the audit.

## 1. Repository snapshot

- 170 tracked blob files
- 18 directories
- approximately 5.83 MB tracked content
- 69 HTML files
- 35 PHP files
- 35 JavaScript files
- 8 CSS files
- 5 SQL files
- 2 JSON files
- 1 YAML file
- 7 image files

## 2. Architecture inventory

Public and account pages include index.html, directory.html, activity.html, events.html, calendar.html, planner.html, my-hub.html, family.html, saved-activities.html, account.html, account-profile.html, preferences.html, notifications.html, consent.html, booking-manager.html, auth.html, forgot-password.html, reset-password.html, help-support.html, leader.html, leader-account.html, leader-auth.html, organiser.html, venues.html, venue.html, schools.html, subscription.html, privacy.html and terms.html.

The admin area contains approximately 35 pages covering the dashboard, activities/listings, imports/exports, sessions/times, venues, bookings, availability, content, navigation, notifications, organisers, class leaders, profiles, regions, towns, location mapping, search, accessibility, advanced filters, system settings and users/family accounts.

## 3. Important APIs

Key APIs include auth.php, db.php, activities.php, admin-activities.php, admin-import-export.php, my-hub.php, planner.php, preferences.php, reservations.php, push.php, leader-portal.php, contact.php, geocode.php, newsletter.php, onesignal-email.php, school-holidays.php, school-search.php, schools.php, search-settings.php, regions.php, menu.php, venues.php, venue.php, support.php, support-match.php, leader-support.php and leader-faqs.php.

## 4. Frontend and styling

Major JavaScript files include app.js, directory.js, admin.js, site-auth.js, auth.js, my-hub.js, family.js, personalise.js, planner.js, calendar.js, account-planner.js, organiser.js, leader-account.js, leader-auth.js, hero-search.js, menu-manager.js, preferences.js, account-dashboard.js, account-notifications.js, account-profile.js, account-privacy.js, account-subscription.js, booking-manager.js, consent.js and saved-activities.js.

styles.css is approximately 201 KB. bold-theme.css is approximately 8.7 KB. The bold theme adds stronger green/teal styling, accent colours, cards, buttons, hero styling, footer/mobile navigation and a centred modal system.

## 5. Findings

### F-01 API error disclosure
api/auth.php has a top-level exception path that can return the exception message to the client. Production responses should be generic while detailed errors are logged server-side.

### F-02 Runtime schema mutation
api/db.php can perform an ALTER TABLE operation during application setup. Schema changes should be handled by explicit migrations rather than normal requests.

### F-03 Runtime table creation
api/auth.php creates social-account/password-reset related tables from the request path. These should be moved into migrations.

### F-04 Admin export/session issue
api/admin-import-export.php has export logic that later accesses activity ID data even though the relevant SELECT omits a.id. This should be verified and corrected before relying on session exports.

### F-05 Large admin surface
The number of admin pages and the size of admin.js create a large regression surface. Admin testing should be performed by feature group.

### F-06 CSS layering
The large global stylesheet plus bold-theme overlay creates potential specificity and override debt. Future styling should favour clear component layers and shared design tokens.

### F-07 Permission matrix
Every protected API should be tested as anonymous, family user, leader, organiser and administrator. Pay particular attention to admin, leader, reservations, My Hub and account APIs.

### F-08 Sensitive family data
My Hub, profile, preferences, consent and reservation functionality can contain child/family and potentially medical or consent information. Ownership checks, minimal API responses, safe logging and retention/deletion rules should be verified.

### F-09 External integrations
Google/Apple authentication, Resend, OneSignal, geocoding and school-related integrations should be checked for credential handling, failure behaviour, rate limits and timeouts.

### F-10 Deployment drift
Deployment spans deploy.php, GitHub Actions and hosting configuration. The exact production path, permissions, configuration location, workflow result and deployed commit should be documented and tested.

## 6. Database

Database files include schema.sql plus migrations 002_user_preferences.sql, 003_newsletter_roundup.sql, 004_remove_sms_add_push.sql and 005_add_leader_role.sql.

The migration system is useful, but runtime schema mutation should be consolidated into migrations.

## 7. Deployment

deploy.php is POST-only, checks the authenticated Bubba Hub admin session, locates github-deploy-config.php, reads the GitHub token, dispatches the deploy.yml workflow against main and reports success/failure.

Verify CSRF protection for the deployment POST and make sure the deployment result clearly identifies the deployed commit.

## 8. Current file-size hotspots

- assets/css/styles.css — 201,362 bytes
- api/auth.php — 33,705 bytes
- assets/js/admin.js — 33,256 bytes
- assets/js/directory.js — 32,998 bytes
- index.html — 22,985 bytes
- activity.html — 21,324 bytes
- api/admin-import-export.php — 15,855 bytes
- api/my-hub.php — 15,692 bytes
- database/schema.sql — 15,579 bytes
- assets/css/bold-theme.css — 8,669 bytes

## 9. Recent commit history

- 981120c — 2026-09-29 08:21 UTC — Fix admin bold theme stylesheet path and cache version
- 6c6cfb2 — 2026-09-29 08:21 UTC — Fix modal styling and centering in bold theme
- 662d25a — 2026-09-29 08:11 UTC — Remove redundant bold theme automation
- d4a1dbe — 2026-09-29 08:11 UTC — Remove redundant bold theme automation
- 034409b — 2026-09-29 07:52 UTC — Bold Bubba Hub visual theme — approved visual refresh
- 2649a50 — 2026-09-28 20:33 UTC — Fix central admin dashboard URL
- 55b5a76 — 2026-09-28 20:33 UTC — Fix central admin dashboard URL
- e827791 — 2026-09-28 20:33 UTC — Fix central admin dashboard URL
- 9f2d4f7 — 2026-09-28 20:33 UTC — Fix central admin dashboard URL
- 90b01b8 — 2026-09-28 20:32 UTC — Fix admin dashboard link path
- 3f0c8f5 — 2026-09-28 20:32 UTC — Fix admin dashboard link path
- c7f4fb2 — 2026-09-28 20:32 UTC — Fix admin dashboard links in admin pages
- 1cd6936 — 2026-09-28 20:32 UTC — Fix admin dashboard links in admin pages
- 4a805d4 — 2026-09-28 20:23 UTC — Fix all admin subpage links for admin structure
- a4772c5 — 2026-09-28 20:22 UTC — Fix Activities admin subpage links
- fcbf9c9 — 2026-09-28 20:20 UTC — Simplify Activities and listings admin section
- 15b4bf3 — 2026-09-28 20:13 UTC — Fix admin dashboard links after folder move
- dada782 — 2026-09-28 20:12 UTC — Correct links between admin folder pages
- 919cb84 — 2026-09-28 20:12 UTC — Fix admin folder routing and authentication redirects
- acb5811 — 2026-09-28 20:12 UTC — Remove admin pages from repository root after move

Recent history shows three main phases: admin restructuring, the bold visual refresh, and cleanup of redundant theme automation.

## 10. Recommended audit sequence

1. Core public journey: index, directory, activity, events, leader, help/support.
2. Authentication/account: auth, account, profile, preferences, notifications, consent and password reset.
3. My Hub/planner: my-hub, family, personalise, planner, calendar and saved activities.
4. Booking/leader: booking manager, leader auth/account, organiser and venues.
5. Admin: test by feature group.
6. Backend/database: trace each page to APIs, permissions, validation and database queries.

For each page record its purpose, HTML/CSS/JS dependencies, API calls, authentication and roles, database tables, forms, modals, external services, desktop/mobile behaviour, known bugs and console/network errors.

## 11. Audit limitations

This report is based on repository/source inspection. It does not claim that every page has been manually exercised in a browser or that every API has been tested against production data. Browser/runtime testing should therefore be a separate stage.

## 12. Overall technical picture

Bubba Hub is now a substantial standalone application covering public directory/search, activity pages, family accounts, My Hub, planner/calendar, saved activities, preferences/notifications, consent, bookings/reservations, leader/organiser tooling, venues, administration, import/export, migrations, integrations, scheduled jobs and GitHub Actions deployment.

The main next step is controlled consolidation: understand each page and dependency, remove runtime schema work, tighten permissions and sensitive-data handling, reduce CSS/JS duplication, and systematically test the key user journeys.