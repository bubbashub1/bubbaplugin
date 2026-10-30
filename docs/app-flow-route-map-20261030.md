# Bubba Hub app-flow route and UI map
Updated: 2026-10-30. Planning and QA document; does not change live routes.

## Primary family navigation
| Label | Canonical page | Role |
|---|---|---|
| Home | / | Public landing and entry to search |
| Find | /directory.html | Search, filters and results; map/calendar are views, not parallel navigation destinations |
| Plan | /planner.html | Personal planner (signed-in sync where supported) |
| Saved | /account/saved-activities.html | Saved activity collection |
| My Hub | /my-hub.html | Family dashboard |

## Secondary public navigation
| Page | Route | Purpose |
|---|---|---|
| Activity detail | /activity/{slug} | Read, save, plan and book |
| Organiser storefront | /organiser/{slug}/ | Organiser and related classes |
| Events | /events.html | Events discovery |
| Venues | /venues.html | Location discovery |
| Public calendar | /calendar.html | All available sessions; NOT the personal planner |
| Expo | /expo.html | Event tickets, exhibitors and raffle |
| Help | /help-support.html | Guidance and contact |
| Account | /account.html | Account management entry |
| Sign in | /auth.html | Shared family/leader authentication |
| Subscriptions | /pro.html | Family/Leader Pro |
| Class leader | /leader.html | Leader workspace |

## Account settings (reachable from My Hub > Account)
- /account/profile.html — identity/contact
- /account/preferences.html — interests, ages, region
- /account/notifications.html — notification choices
- /account/privacy.html — consent and data
- /account/planner.html — currently overlaps /planner.html; evaluate redirect or bookings-only role before changing

## Design rules
1. One global header and footer; family mobile bottom navigation is supplemental, not a second desktop menu.
2. One activity card component across homepage, directory, saved, organiser and recommendations.
3. One primary action per card; Save/Plan/Book actions retain consistent wording.
4. Public calendar and private planner must be clearly named.
5. Responsive filter drawer on mobile; list/map/calendar use mutually exclusive views.
6. Shared design tokens and component CSS; avoid per-page override accumulation.
7. Keep keyboard focus, reduced-motion support, 44px touch targets and meaningful empty/loading states.
8. Do not rewrite booking/import/admin behavior as part of visual migrations.

## Concrete defects to investigate before broad rollout
- directory.html currently includes two elements with id="results" (activity list and embedded planner); scope selectors or rename with coordinated JS updates.
- Directory contains five view buttons (Activities, Calendar, Planner, Map, Events); simplify user journey without removing functionality.
- Header implementation varies: some pages use siteHeaderMount and others hard-code header markup.
- Account and My Hub share overlapping destinations; avoid competing dashboards.
- Multiple CSS generations remain loaded on directory, calendar and My Hub.
- Validate legacy WordPress routes, canonical activity URLs and redirect behavior.
- Check postcode/region mapping, venue geocoding accuracy, map pin click-through, and missing coordinates with live data. Do not assume coordinates are correct solely because present.

## Rollout and sign-off
1. Visual baseline screenshots at 390px, 768px and 1440px.
2. Apply global component system; smoke test header/footer and auth.
3. Migrate discovery pages, then family pages, then leader/admin.
4. Run browser-based performance/accessibility tests and compare before/after.
5. Confirm live deployments and real-user flows before marking complete.
