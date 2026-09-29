# Bubba Hub — HTML Page Architecture

Branch: `rebuild-html-foundation-2026-09-29`
Purpose: establish the canonical HTML page map before rebuilding CSS, JavaScript or backend wiring.

## Principles

1. One canonical HTML page per user-facing route.
2. Folder names describe the product area: `account/`, `leader/`, `admin/`.
3. Root HTML is reserved for public-facing pages and authentication/legal entry points.
4. Admin navigation is hard-coded for now; the old dynamic main-menu editor is not part of the first rebuild.
5. Existing working pages are treated as source material only. We rebuild the page shell/content deliberately rather than carrying forward accumulated overrides.
6. CSS and JavaScript cleanup happens after the HTML/page structure is agreed and stable.
7. Old duplicate files are not deleted until all links/references have been moved to their canonical route.

## Canonical public pages

| Route | Purpose |
|---|---|
| `/` | Homepage |
| `/directory.html` | Family activity directory/search |
| `/activity.html?id=...` | Activity/listing detail |
| `/events.html` | Events |
| `/venues.html` | Venue directory |
| `/venue.html?id=...` | Venue detail |
| `/organiser/[business_name]` | Public organiser profile |
| `/schools/` | School information/search |
| `/help-support.html` | Family support and guidance |
| `/auth.html` | Family sign in / sign up |
| `/forgot-password.html` | Password reset request |
| `/reset-password.html` | Set new password |
| `/choose.html` | Choose family/leader journey |
| `/personalise.html` | New-family personalisation |
| `/consent.html` | Booking/child consent |
| `/privacy.html` | Privacy |
| `/terms.html` | Terms |

## Canonical family/account pages

| Route | Purpose |
|---|---|
| `/account/` | Family account dashboard |
| `/account/profile.html` | Parent/member profile |
| `/account/family.html` | Children and family details |
| `/account/planner.html` | Family planner |
| `/account/saved-activities.html` | Saved activities |
| `/account/preferences.html` | Directory/personalisation preferences |
| `/account/notifications.html` | Notification preferences/history |
| `/account/privacy.html` | Privacy, consent and permissions |
| `/account/subscription.html` | Membership/subscription |
| `/account/logout.html` | Sign-out endpoint/page if required by auth flow |

The account area is the single source of truth. Root duplicates such as `account.html`, `account-profile.html` and `account-planner.html` are legacy compatibility files and will be removed/redirected only after references are migrated.

## Canonical leader pages

| Route | Purpose |
|---|---|
| `/leader/` | Leader dashboard |
| `/leader/profile.html` | Business/leader profile |
| `/leader/classes.html` | Classes/listings |
| `/leader/schedule.html` | Sessions and availability |
| `/leader/venues.html` | Venues |
| `/leader/bookings.html` | Bookings/reservations |
| `/leader/payments.html` | Payments and fees |
| `/leader/statistics.html` | Statistics |
| `/leader/faqs.html` | Public FAQs |
| `/leader/support.html` | Leader support and expertise |
| `/leader/blog.html` | Leader news/blog |

Public organiser profiles are separate from the authenticated leader portal.

## Canonical admin pages

The current 30+ thin admin shells are reduced to a smaller feature set. Each page owns a feature area instead of having one HTML file per tiny setting.

| Route | Purpose |
|---|---|
| `/admin/` | Admin dashboard |
| `/admin/activities.html` | Listings, add/edit, sessions and venues |
| `/admin/bookings.html` | Bookings, availability and booking settings |
| `/admin/organisers.html` | Leader accounts, profiles and listings |
| `/admin/users.html` | Family accounts and test users |
| `/admin/search.html` | Search/filter behaviour and accessibility |
| `/admin/regions.html` | Counties, regions, towns and location mapping |
| `/admin/content.html` | Site content and media |
| `/admin/notifications.html` | Newsletter, email and push |
| `/admin/system.html` | Health, deployment and system tools |
| `/admin/import-export.html` | CSV/import/export tools |

The old `admin-navigation*.html` pages are deliberately excluded from the first rebuild because main navigation is hard-coded during this phase.

## Pages being merged/retired after migration

- `account.html` → `account/`
- `account-profile.html` → `account/profile.html`
- `account-planner.html` → `account/planner.html`
- `leader-account.html` → `leader/profile.html` or leader dashboard as appropriate
- `leader-auth.html` → shared authentication flow
- `admin.html` → `admin/`
- `admin/admin-activities-*.html` → `admin/activities.html`
- `admin/admin-bookings-*.html` → `admin/bookings.html`
- `admin/admin-organisers-*.html` → `admin/organisers.html`
- `admin/admin-users-*.html` → `admin/users.html`
- `admin/admin-search-*.html` → `admin/search.html`
- `admin/admin-regions-*.html` → `admin/regions.html`
- `admin/admin-navigation-*.html` → not rebuilt initially
- `my-hub.html`, `planner.html`, `calendar.html` → consolidate into the account planner/My Hub experience after the new account HTML is stable
- `booking-manager.html` → leader/admin booking area after ownership is clarified
- `organiser.html` → public organiser route
- `library/index.html` → internal/legacy library area; not part of the family-facing product
- `schools.html` → consolidate with `schools/`

## Rebuild order

### Phase 1 — HTML only
1. Public shell and homepage
2. Directory
3. Activity
4. Events / venues
5. Public organiser
6. Authentication
7. Family account
8. Planner/My Hub
9. Leader portal
10. Admin portal
11. Legal/support pages

### Phase 2 — shared CSS
- One global design system
- One component layer
- Page-specific CSS only where genuinely necessary
- Remove the current 200KB+ global CSS accumulation

### Phase 3 — JavaScript
- Shared app/session/navigation code
- Directory/search
- Account/planner
- Leader
- Admin
- Remove duplicate/obsolete scripts

### Phase 4 — API/data
- Preserve working endpoints
- Consolidate runtime schema changes into migrations
- Verify family/leader/admin permissions
- Verify listing, booking and consent ownership

### Phase 5 — retirement
Only after route/reference testing:
- remove legacy duplicate HTML
- remove unused CSS/JS
- remove obsolete menu manager
- remove dead pages and dead APIs
- verify deployed `/beta` site against this architecture

## Definition of done for the HTML foundation

Every canonical route exists, has a consistent semantic structure, uses the same header/footer conventions, has working internal links, is mobile-safe at the markup level, and contains no page-specific styling hacks. Functionality can remain mocked/placeholder until the page structure is approved.
