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
| `/childcare/` | Public childcare directory |
| `/organiser/[business_name]` | Public organiser storefront/profile |
| `/faq.html` | Frequently asked questions |
| `/about.html` | About Bubba Hub |
| `/contact.html` | Contact Bubba Hub |
| `/choose.html` | Choose family/leader journey |
| `/personalise.html` | New-family personalisation |
| `/privacy.html` | Legacy compatibility/privacy entry point |
| `/terms.html` | Legacy compatibility/terms entry point |

**Important distinction:** Schools are not a public directory section. Childcare is the public directory area for nurseries, pre-schools, childminders, before/after-school care, holiday childcare, SEND/specialist childcare and school nursery provision. School research/tracking lives inside My Hub at `/account/schools.html`.

## My Hub — family area

The visible product name for the family account area is **My Hub**. The `/account/` path is the technical route namespace.

| Route | Purpose |
|---|---|
| `/account/` | **My Hub dashboard** — family overview and entry point |
| `/account/profile.html` | Parent/member profile |
| `/account/family.html` | **Children and child profiles** |
| `/account/planner.html` | **Family planner** — calendar/list/day/week/month and family activities |
| `/account/saved-activities.html` | Saved/bookmarked activities |
| `/account/schools.html` | **School research/tracker**; separate from public Childcare |
| `/account/preferences.html` | Personalisation and directory preferences |
| `/account/notifications.html` | Notification preferences/history |
| `/account/privacy.html` | Privacy, consent and permissions |
| `/account/subscription.html` | Membership/subscription |
| `/account/payment-details.html` | Saved payment methods and billing details |
| `/account/logout.html` | Sign-out endpoint/page if required by auth flow |

The My Hub dashboard should make children, planner, saved activities and schools prominent rather than behaving like a generic settings page.

Child profiles may include name, nickname, photo, date of birth, gender, age, preferences, activity suitability and consent/permission settings as the relevant features are implemented.

## Booking system

| Route | Purpose |
|---|---|
| `/book/` | Booking journey entry |
| `/book/activity.html` | Activity/session selection |
| `/book/consent.html` | Child and organiser consent/permissions |
| `/book/confirmation.html` | Server-verified booking confirmation |
| `/book/manage.html` | Booking management |

The booking architecture supports both Bubba Hub checkout and external organiser booking. Payment is a step in the internal booking flow but does not need a separate public payment page.

## Authentication

| Route | Purpose |
|---|---|
| `/auth/login.html` | Family/leader sign in |
| `/auth/register.html` | Account registration |
| `/auth/forgot-password.html` | Password reset request |
| `/auth/reset-password.html` | Set new password |

Legacy `auth.html`, `forgot-password.html`, `reset-password.html` remain compatibility files until references are migrated.

## Family website help

These pages explain **how to use Bubba Hub**, and are separate from family-life Support.

| Route | Purpose |
|---|---|
| `/help/` | Help hub |
| `/help/families/` | Family user guide |
| `/help/families/getting-started.html` | Getting started |
| `/help/families/account.html` | Using My Hub/account |
| `/help/families/directory.html` | Finding activities |
| `/help/families/planner.html` | Using the planner |
| `/help/families/bookings.html` | Booking help |
| `/help/families/troubleshooting.html` | Troubleshooting |

## Leader website help

These pages explain **how leaders use Bubba Hub**, and are separate from the authenticated Leader Hub itself.

| Route | Purpose |
|---|---|
| `/help/leaders/` | Leader user guide |
| `/help/leaders/getting-started.html` | Getting started |
| `/help/leaders/listings.html` | Managing listings |
| `/help/leaders/schedules.html` | Managing schedules |
| `/help/leaders/bookings.html` | Booking help |
| `/help/leaders/payments.html` | Payment help |
| `/help/leaders/profile.html` | Business profile help |
| `/help/leaders/support.html` | Support/expertise help |

## Family Help & Support

This area is for **family life, wellbeing and support information**, not instructions for using the website.

| Route | Purpose |
|---|---|
| `/support/families/` | Family Support hub |
| `/support/families/pregnancy.html` | Pregnancy |
| `/support/families/baby-health.html` | Baby and child health |
| `/support/families/feeding.html` | Feeding |
| `/support/families/sleep.html` | Sleep |
| `/support/families/development.html` | Child development |
| `/support/families/parenting.html` | Parenting |
| `/support/families/wellbeing.html` | Family wellbeing |
| `/support/families/send.html` | SEND |
| `/support/families/financial-support.html` | Financial support |
| `/support/families/local-support.html` | Local support |
| `/support/families/urgent-help.html` | Urgent help |
| `/support/families/apps.html` | Suggested apps and digital resources |

## Canonical leader pages

| Route | Purpose |
|---|---|
| `/leader/` | Leader Hub dashboard |
| `/leader/profile.html` | Business/leader profile, including Business Name |
| `/leader/classes.html` | Classes/listings; add/edit listing uses a modal/wizard |
| `/leader/schedule.html` | Sessions and availability; add/edit session uses a modal |
| `/leader/venues.html` | Venues; add/edit venue uses a modal |
| `/leader/bookings.html` | Bookings/reservations |
| `/leader/payments.html` | Payments and fees |
| `/leader/statistics.html` | Statistics |
| `/leader/faqs.html` | Public FAQs |
| `/leader/support.html` | Leader support and private family-support expertise |
| `/leader/blog.html` | Leader news/blog |
| `/leader/advertising.html` | Leader advertising campaigns |

Public organiser profiles are separate from the authenticated Leader Hub and use `/organiser/[business_name]`.

## Canonical admin pages

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
| `/admin/listing-watch.html` | Directory monitoring/discovery, potential listings, duplicates and review queue |
| `/admin/advertising.html` | Advertising campaign management and approval |
| `/admin/system.html` | Health, deployment and system tools |
| `/admin/import-export.html` | CSV/import/export tools |

Admin navigation is hard-coded during this rebuild. The old dynamic main-menu editor is not part of the first rebuild.

## Legal and policy pages

The legal area reserves the full policy set identified in `FUTURE_DEVELOPMENT.md`.

| Route | Purpose |
|---|---|
| `/legal/` | Legal/policy hub |
| `/legal/privacy.html` | Privacy Notice |
| `/legal/terms.html` | Terms & Conditions |
| `/legal/booking-terms.html` | Booking Terms |
| `/legal/refunds.html` | Refund & Cancellation Policy |
| `/legal/payment-terms.html` | Payment Terms |
| `/legal/leader-terms.html` | Leader Terms |
| `/legal/pro-terms.html` | Pro Terms |
| `/legal/advertising-terms.html` | Advertising Terms |
| `/legal/cookies.html` | Cookie Policy |
| `/legal/acceptable-use.html` | Acceptable Use |
| `/legal/community-guidelines.html` | Community/Content Guidelines |
| `/legal/complaints.html` | Complaints |
| `/legal/data-retention.html` | Data Retention |
| `/legal/data-rights.html` | Data Subject Rights/privacy requests |
| `/legal/child-family-data.html` | Child & Family Data |
| `/legal/consent.html` | Consent and Permissions |
| `/legal/accessibility.html` | Accessibility |
| `/legal/disclaimer.html` | Disclaimer |

The legal pages should receive professional legal/data-protection review before final publication.

## Future feature architecture already captured

### Advertising — FD-001
Four campaign zones:
1. Site Wide
2. My Hub Only
3. My Hub & Directory
4. Home Page Only

Campaigns may use an existing Bubba Hub listing or leader storefront. One-off and recurring 30-day campaigns are planned. Admin approval, lifecycle, payment state, expiry, cancellation and display/rotation rules belong to the advertising implementation.

### Central payments — FD-002
A reusable payment abstraction supports bookings, advertising and Pro subscriptions, with planned PayPal, Stripe and bank transfer support. It tracks pending, successful, failed, cancelled and refunded states without storing raw card details.

### Booking payments + 2% Bubba Hub fee — FD-003
Internal booking checkout will support the agreed customer-paid 2% Bubba Hub booking fee, organiser proceeds/payouts, payment status, refunds, webhooks, reconciliation and duplicate prevention. Financial success must be verified server-side.

### Legal/privacy/policy — FD-004
The full policy set above is reserved in the architecture. Final wording and data/payment details require appropriate legal/data-protection review.

## Operational systems

### Listing Watch
`/admin/listing-watch.html` is the directory operations centre. It can later connect to Google/web monitoring or alert sources and should support:
- potential new listings
- possible duplicates
- needs checking
- recently checked
- ignored
- create/update listing
- monitoring groups

Monitoring groups can include childcare, baby/toddler groups, children's activities, sports/swimming, classes, new businesses and other agreed directory categories.

### My Hub Schools
`/account/schools.html` is a family research/tracking utility. It is not a public school directory and is not part of Childcare. It can later support Google Custom Search-style discovery, saved schools, location/name search, notes and tracking.

## Pro

No separate `/pro/` portal is required at this stage.

- Family subscription management → `/account/subscription.html`
- Pro legal terms → `/legal/pro-terms.html`
- Admin/payment handling → relevant admin/payment systems

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
- `how-it-works.html` → replaced by `faq.html`
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


## Interaction rule — modals vs pages

Leader actions that are short create/edit workflows should use modals or multi-step modals rather than creating extra HTML pages.

- Add/edit listing → modal wizard launched from `leader/classes.html`
- Add/edit session → modal launched from `leader/schedule.html`
- Add/edit venue → modal launched from `leader/venues.html`
- Add/edit FAQ → modal launched from `leader/faqs.html`
- Add payment method → modal launched from `account/payment-details.html`

A dedicated page is used when the user needs a persistent workspace, dashboard, history, settings area or substantial workflow.
