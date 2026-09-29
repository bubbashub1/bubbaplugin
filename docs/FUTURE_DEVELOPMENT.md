# Bubba Hub — Future Development Backlog

This is the working backlog for future Bubba Hub development.

## Workflow

### “create the next future development request”
When this command is used, Chat should:
1. Read this backlog.
2. Find the next suitable OPEN request.
3. Turn it into a clear development request with goal, scope, affected areas, acceptance criteria and dependencies.
4. Present it for user approval/changes.
5. Do not implement anything until the user approves it.
6. After implementation, update the request with status, notes and commit.

### “close X request”
Identify request X and, once the user confirms it is complete, mark it CLOSED and record the completion date and commit.

Partially completed work should remain IN PROGRESS, not closed.

## Statuses
- OPEN — planned
- IN PROGRESS — being worked on
- BLOCKED — waiting on dependency/decision
- ON HOLD — deliberately paused
- CLOSED — completed and accepted

---

# Future Development Requests

## FD-001 — [OPEN] Paid Advertising Zones for Leaders

**Goal:**  
Create a paid advertising system that allows Bubba Hub leaders/organisers to purchase advertising placements on the website for a 30-day advertising period.

**Why:**  
Give leaders an additional paid promotion option while creating a sustainable revenue stream for Bubba Hub.

**Advertising zones:**
1. **Site Wide** — advertisement can appear throughout the site.
2. **My Hub Only** — advertisement appears within the My Hub/family experience.
3. **My Hub & Directory** — advertisement appears in My Hub and directory areas.
4. **Home Page Only** — advertisement appears only on the home page.

**Campaign duration:**
- Standard campaign length: 30 days.
- One-off campaign — runs for 30 days and then ends.
- Recurring campaign — automatically renews for another 30-day period, subject to successful payment and approval rules.

**Advertiser content options:**
- **Existing Bubba Hub listing** — use a selected listing as the advertised content/destination.
- **Leader storefront** — advertise the leader's storefront/organisation instead of a specific activity listing.

The setup should clearly show the chosen zone, content, duration and cost before payment.

**Scope:**
- Add advertising-zone management to admin.
- Add leader-facing advertising purchase and campaign management.
- Define the four placement/display rules.
- Add 30-day campaign lifecycle.
- Add one-off vs recurring billing.
- Allow campaign to use an existing listing or storefront.
- Add campaign status handling.
- Integrate with the project's chosen payment provider.
- Add admin approval/control.
- Add basic campaign statistics where practical.
- Automatically stop expired campaigns.
- Handle cancelled/failed recurring payments safely.
- Prevent adverts displaying before required approval/payment conditions are met.

**Likely files/areas:**
- admin/ — advertising management
- api/ — advertising, campaign and payment endpoints
- assets/js/ — leader/admin advertising interfaces
- assets/css/ — advertising UI and placement styling
- database schema/migrations — campaigns, zones, billing and relationships
- leader/account pages — purchase and campaign management
- directory, My Hub and home page rendering — placement
- existing payment/subscription infrastructure — reuse where appropriate

**Acceptance criteria:**
- [ ] Admin can create/manage the four advertising zones.
- [ ] Leader can select one of the four zones.
- [ ] Leader can choose a listing or storefront.
- [ ] Leader sees campaign duration and cost before purchasing.
- [ ] Campaign runs for exactly 30 days from its defined start point.
- [ ] Leader can select one-off or recurring billing.
- [ ] Recurring campaigns renew for another 30 days only after successful renewal/payment.
- [ ] Expired/cancelled campaigns stop displaying automatically.
- [ ] Admin can approve, pause, reject, cancel and manage campaigns.
- [ ] Advertising displays only in the zone purchased.
- [ ] Leader permissions prevent one leader managing another leader's campaigns.
- [ ] Payment and campaign status remain synchronised.
- [ ] Campaign start/end dates and billing state are recorded.
- [ ] Mobile and desktop placements fit the Bubba Hub theme.
- [ ] Basic campaign reporting is available, or limitations are clearly documented.

**Dependencies / questions:**
- Advertising prices for each zone need to be decided.
- Confirm the payment provider and current recurring-payment capability.
- Decide whether recurring campaigns renew automatically or require re-approval.
- Define maximum simultaneous adverts per zone.
- Define advert rotation when multiple leaders purchase the same zone.
- Define whether Site Wide includes account/auth pages or public pages only.
- Define exact storefront content used for storefront campaigns.
- Decide whether leaders upload custom artwork or Bubba Hub generates the advert from listing/storefront data.
- Decide whether approval occurs before payment, after payment, or both.
- Establish refund/cancellation rules.

**Implementation notes:**
- Do not start coding until the user approves this request and key pricing/payment/display decisions are resolved.
- Reuse existing authentication, leader permissions, listing relationships and payment infrastructure where practical.
- Build advertising as a configurable system rather than hard-coded placements.
- Use a date-driven campaign lifecycle so expired advertising does not require manual removal.

**Completion:**
- Status: OPEN
- Completed: —
- Commit: —

---

# Development History

_No requests have been closed yet._
