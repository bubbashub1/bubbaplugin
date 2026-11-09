# Bubba Hub — Future Developments

**Planning home:** `bubbashub1/bubbaplugin`  
**Planning branch:** `plans-and-tracker`  
**Last consolidated:** 9 November 2026

## Purpose and repository direction

BubbaPlugin is the primary development repository and single source of truth and the single source of truth for future product development. Bubba4 is a reference/experimental repository only. Do not continue building a parallel product in Bubba4, copy whole modules blindly, or introduce duplicate data stores and dashboards.

The custom activity/listing directory remains bespoke because of Bubba Hub's data model and workflows. Reuse existing free/low-cost WordPress components only where they genuinely reduce work and do not compromise the bespoke directory, ownership rules, booking records or existing data.

All feature adoption must follow this sequence:

1. Identify the user problem and existing BubbaPlugin implementation.
2. Inspect the Bubba4 source/PR and confirm whether the idea is already present in BubbaPlugin.
3. Reuse or adapt only the missing, compatible part.
4. Add tests and staging verification.
5. Record the PR, evidence and deployment status in [Weekly Plan](WEEKLY_PLAN.md).

## Consolidated product roadmap

### A. Core pages, design and navigation
- Review the homepage, activity discovery/results, activity detail, organiser storefronts, venue pages and key content pages.
- Review and simplify Family, Leader and Admin dashboards; use one clear navigation structure per audience and remove duplicated destinations.
- Apply a consistent mobile-first design system, accessible controls and clear page actions.
- Keep the activity detail redesign PR #826 recorded as merged; deployment and regression QA remain separate checks until evidenced.
- Complete a sitemap audit: identify missing, duplicated, broken, inaccessible or obsolete pages and routes.

### B. Activity directory and listing wizard
- Keep activities, organisers and venues in the bespoke Bubba Hub directory.
- Complete the seven-step leader listing wizard: required-field rules, visible required indicators, validation, helpful errors, save/resume behaviour and mobile usability.
- Ensure approved leaders can create and manage only listings belonging to their verified organiser account.
- Add robust duplicate detection using activity title and venue; distinguish exact duplicates from possible matches.
- Provide a controlled exception/review path without allowing a duplicate to publish or become bookable before review.
- Improve listing completeness, timetable/session patterns, term-time rules, images, venue links and public listing presentation.
- Admin bulk actions, including “Mark as Term Time Only”, should be delivered as small, testable improvements.

**Current legacy BubbaWP GitHub reference:** PR #205, “Add activity duplicate checker and submission guard”. Its description records the intended server-side duplicate checks, private review queue and leader ownership enforcement. Confirm the current PR state and staging QA before treating it as delivered.

### C. Organiser ownership and claim listings
- Verify that each claim request maps to the correct organiser and the correct listings.
- Use evidence-based matching (contact email, existing organiser relationship and admin review); do not rely on a name or email match alone where ownership is uncertain.
- Make claim invitation/request/approval/rejection status visible.
- Provide multi-select admin review where safe, with an audit trail and clear reasons.
- Prevent cross-organiser claims and unauthorised listing edits.
- Test invitation email delivery, expired/resent invitations, duplicate requests and admin approval.

### D. Organiser storefronts and venues
- Create a modern organiser storefront with flexible branding.
- Consider enhanced/fully branded storefront customisation as a Leader Pro feature, while retaining a useful free profile.
- Allow an organiser to upload a hero image with file-type, size, permission and fallback validation.
- Build the front-end My Venues page by reusing the established organiser-page layout and existing venue records.
- Link venues to activities and support shared venues correctly.
- Keep venue add/edit/delete and merge operations in the admin area for the initial front-end release.

### E. Family Hub
- Continue from the approved eight-section direction: Overview, My Family, My Activity, My Planner, My Bookings, My Support, My Messages and Account Settings.
- Keep child/bump profiles private and parent-controlled.
- Join up saved activities, planner, preferences, visited activities and booking history.
- Reuse the private Hub Demo preview only for review; do not replace the live My Hub page until sign-off.
- Preserve ownership checks, privacy controls and existing family records.

**Legacy BubbaWP GitHub references:** PR #203 (Family Hub redesign/private demo) and PR #204 (Hub Demo routing fix). Verify current status and staging behaviour before rollout.

### F. Booking and payments
- Finish the existing BubbaPlugin booking journey rather than starting a second booking system:
  **Choose class → Select children → Enter details → Agree to terms → Pay once → Receive confirmation.**
- Preserve existing booking, attendee, consent, checkout and Stripe records until a tested migration or replacement is explicitly approved.
- Resolve the current form-provider direction before implementation. Recent BubbaPlugin PR descriptions record a move toward Ninja Forms as the form interface, with BubbaPlugin retaining authority for booking records, permissions, consent and lifecycle; Events Manager is excluded from the core booking path in that proposal. This must be reconciled with older checklist notes that proposed Events Manager before any booking integration is changed.
- Complete read-only staging discovery of installed versions, form IDs/field keys/actions, existing booking tables, session/occurrence model and Stripe configuration.
- Test free bookings, capacity, duplicate prevention, sibling places, cancellation, confirmation, webhook idempotency and leader visibility.
- Keep paid processing disabled until staging end-to-end tests, fee disclosure, refund/payout reconciliation, legal checks and explicit release approval pass.

**Legacy BubbaWP GitHub references:** PRs #193 (booking checkout flow), #199–#200 (Ninja Forms discovery/migration direction), #195 (non-submittable booking preview), and #187–#192 (fee/checkout foundations). These are references to work described in PRs, not proof of production readiness.

### G. Admin and operational tools
- Simplify the admin dashboard into clearly named, colour-coded sections with stacked navigation and a sensible two-column layout where appropriate.
- Consolidate duplicate development tracker screens without losing saved statuses.
- Retain existing routes and saved data when hiding redundant menu entries.
- Provide a single admin overview that links to existing tools rather than rebuilding each tool.
- Improve activity search, status filtering, pagination and safe bulk actions.
- Add read-only diagnostics for plugin/form/booking compatibility where useful; diagnostics must not mutate production data.

### H. Sign-in, permissions and security
- Review family/leader sign-in and account linking.
- Never silently link accounts by matching email alone; require a verified linking journey.
- Keep new leader accounts pending approval until organiser ownership and permissions are verified.
- Test CSRF/nonces, session handling, role/capability checks, private pages, REST permissions and upload validation.
- Avoid implicit schema changes on page load; migrations must be explicit, backed up, versioned and reversible.

### I. Bubba4 ideas to selectively reuse

The Bubba4 repository history and PR descriptions show these useful ideas:

| Bubba4 idea | Decision for BubbaPlugin |
| --- | --- |
| Personalised Leader greeting | Adopt if not already present; use the signed-in account name safely. |
| Simpler admin navigation; hide redundant Workstreams menu but retain route/data | Adopt the navigation principle; verify the existing BubbaPlugin menu before changing it. |
| Link existing activity management rather than duplicate it | Adopt. BubbaPlugin remains the activity-management source of truth. |
| Read-only activity inventory with search, status filtering and pagination | Reuse the UX/approach only if BubbaPlugin's current activity admin lacks equivalent capability. |
| Passive plugin/membership/forms/booking compatibility inventory | Reuse the read-only diagnostic approach; no automatic migration or plugin changes. |
| Plugin collision/activation safeguards | Review and port compatible safeguards with tests. |
| Duplicate development tracker screens | Consolidate in BubbaPlugin while preserving both existing status stores until mapped and verified. |

Bubba4 evidence reviewed: repository main-branch commit history through 8 November 2026 and PR descriptions #1 and #2. This was a targeted review, not a full file-by-file code audit. No Bubba4 code has been assumed safe to copy or deploy.

## Technical and product guardrails

- One product, one primary implementation: BubbaPlugin.
- Do not create duplicate activity, organiser, venue, family or booking records.
- Preserve existing IDs, tables, submissions, URLs, APIs and permissions unless a reviewed migration explicitly changes them.
- No live changes during planning; staging/test fixtures first.
- No new paid services, paid plugins or AI APIs without explicit approval.
- Mobile-first and accessible for families, leaders and administrators.
- A merged PR is not automatically deployed or QA-passed.
- Every task must have a clear acceptance test and evidence before it is checked off.
- Keep the operational status in [Weekly Plan](WEEKLY_PLAN.md); use the existing technical master checklist and architecture records as supporting technical references.

## Suggested delivery order

1. Stabilise and simplify the existing pages, dashboards and navigation.
2. Finish the listing wizard, required fields and duplicate handling.
3. Strengthen organiser claims and ownership verification.
4. Deliver the organiser storefront and front-end venue page.
5. Audit missing/broken pages and close routing gaps.
6. Complete the existing booking journey and staging acceptance.
7. Review Bubba4 ideas one by one and selectively port only useful, non-duplicative improvements.
8. Complete end-to-end regression, security, privacy, mobile and release checks.

## Change log

| Date | Update |
| --- | --- |
| 2026-11-09 | Consolidated BubbaPlugin roadmap with selected Bubba4 ideas; BubbaPlugin confirmed as the single development home. |


## Repository consolidation note

This roadmap was consolidated from the former `bubbawp` planning branch and Bubba4 references into `bubbaplugin`. Older PR numbers explicitly described as BubbaWP references belong to that repository and must not be treated as BubbaPlugin PRs. Re-check equivalent work in BubbaPlugin before implementation to avoid duplication.
