# Bubba Hub — Weekly Plan and Delivery Checklist

**Primary repository:** `bubbashub1/bubbaplugin`  
**Planning branch:** `plans-and-tracker`  
**Review cadence:** Nightly progress check; full weekly reset every Sunday.  
**Last updated:** 9 November 2026

## How to use this checklist

- Keep this as the operational checklist for the current week.
- Update statuses every evening: `[ ]` not started, `[~]` in progress, `[x]` complete and verified, `[!]` blocked/needs a decision.
- Do not mark a task complete because code exists or a PR merged. Confirm the required user journey, tests and staging QA.
- Record the PR/commit and evidence beside the task when available.
- Keep all implementation in BubbaPlugin. Bubba4 is a source of ideas, not a second implementation.
- The broader feature roadmap lives in [Future Developments](FUTURE_DEVELOPMENTS.md).

## This week's goal

Make the existing Bubba Hub platform dependable and joined-up for families, organisers, leaders and administrators. Improve what exists before adding new systems. Do not start a rebuild.

## 1. Main pages, dashboards and navigation

- [ ] Review homepage and activity discovery/results.
- [ ] Review activity detail page following merged PR #826; confirm deployment separately.
- [ ] Review organiser storefront/profile and agree the new flexible design.
- [ ] Review Family Hub and its eight-section navigation.
- [ ] Review Leader dashboard and the activity creation journey.
- [ ] Review Admin dashboard; simplify sections, navigation and duplicated destinations.
- [ ] Review mobile layout, accessibility, touch targets and consistency.
- [ ] Complete a sitemap check for missing, broken, duplicate or obsolete pages/routes.
- [ ] Record any page that should remain unchanged rather than redesigning for its own sake.

## 2. Activity listing wizard

- [ ] Confirm the seven-step wizard is accessible to the correct approved leader accounts.
- [ ] Identify and document all required fields.
- [ ] Add clear required indicators and field-level validation.
- [ ] Add helpful error messages and preserve entered data after validation errors.
- [ ] Confirm save/resume and draft behaviour.
- [ ] Verify organiser ownership is enforced server-side.
- [ ] Test the complete wizard on a phone-sized viewport and desktop.
- [ ] Verify successful submissions appear correctly in the admin review/listing area.

## 3. Duplicate listing alerts

- [ ] Check exact activity title + same venue matches server-side.
- [ ] Block exact duplicates by default.
- [ ] Show a useful warning for possible title-only matches.
- [ ] Provide the agreed exception/review route for genuinely different activities.
- [ ] Keep exception submissions private/unpublished and not bookable until reviewed.
- [ ] Verify leaders cannot bypass checks through an alternate dashboard or endpoint.
- [ ] Test punctuation/case/whitespace, shared venues, multiple classes at one venue and similar titles at different venues.
- [ ] Track the legacy BubbaWP PR #205 as a reference only; check for the equivalent work in BubbaPlugin and confirm current status, CI and staging acceptance.

## 4. Claim listing and organiser verification

- [ ] Review current claim invitation and request process.
- [ ] Confirm each listing is mapped to the correct organiser.
- [ ] Use verified ownership evidence; do not rely on display name or email match alone.
- [ ] Make claim statuses clear: not sent, invited, requested, approved, rejected/expired.
- [ ] Improve admin approval/rejection and multi-select review where safe.
- [ ] Prevent cross-organiser claims and unauthorised edits.
- [ ] Test invitation delivery, resend/expiry, duplicate requests and audit history.

## 5. Organiser storefront and venue page

- [ ] Approve a fresh organiser storefront design with flexible branding.
- [ ] Consider full branding as a Pro feature and a useful limited free profile.
- [ ] Add organiser-uploaded hero image with validation and fallback.
- [ ] Build front-end My Venues using the existing organiser-page layout.
- [ ] Connect the page to existing venue records and activity relationships.
- [ ] Support shared venues linked to multiple activities.
- [ ] Keep venue add/edit/delete/merge in the admin dashboard for this phase.
- [ ] Test empty states, multiple venues, permissions and mobile layout.

## 6. Booking system — feasibility and delivery

- [ ] Confirm the authoritative booking architecture and form-provider decision before changing code.
- [ ] Inspect current staging plugin/form versions, form IDs/field keys and booking/session schema read-only.
- [ ] Preserve existing booking, attendee, consent, checkout, Stripe and submission data.
- [ ] Confirm family child-profile ownership validation.
- [ ] Test the journey: choose class → select children → details → terms → pay → confirmation.
- [ ] Test free bookings, capacity, duplicates, sibling places, cancellation and confirmation.
- [ ] Test payment/webhook/refund/payout only in staging test mode.
- [ ] Keep live payment processing disabled until all acceptance gates and explicit approval are complete.
- [ ] If full implementation is not safe this week, produce a precise staged completion plan with blockers.

## 7. Bubba4 feature review

- [ ] Review personalised Leader greeting; port only if missing in BubbaPlugin.
- [ ] Review simpler admin navigation and redundant submenu hiding; preserve routes and saved data.
- [ ] Reuse existing activity-management links rather than creating a duplicate activity inventory.
- [ ] Compare read-only activity search/status filtering/pagination with current BubbaPlugin admin.
- [ ] Review passive compatibility diagnostics and activation/collision safeguards.
- [ ] Consolidate duplicate tracker screens without losing saved statuses.
- [ ] Record each feature as adopt, adapt, already exists or reject in Future Developments.

## 8. Regression and release checks

- [ ] Family journey: discover, view, save/plan and access private profile.
- [ ] Leader journey: sign in, claim organiser, create/edit listing and manage venues.
- [ ] Admin journey: review claims, duplicates, listings and venue records.
- [ ] Booking journey tested with safe staging accounts and test payment methods.
- [ ] Check permissions, privacy, CSRF/nonces, sessions, emails and uploads.
- [ ] Confirm no existing routes, records or dashboards were broken.
- [ ] Record deployment separately from merge and QA.
- [ ] Do not deploy to live without explicit approval, backup and rollback plan.

## Current known status / carry-forward

- [x] PR #826 activity detail redesign merged (9 November 2026). **Deployment and responsive/booking regression QA are not confirmed by this checklist.**
- [ ] Legacy BubbaWP PR #205: reference only; verify whether equivalent code exists in BubbaPlugin before deciding next steps.
- [ ] PR #844 My Venues in BubbaPlugin: previously reported in conversation; verify current status before relying on it.
- [ ] PR #819 session/CSRF issue in BubbaPlugin: previously identified as a possible blocker; verify current status.
- [ ] Hub Demo routing: PR #204 exists in BubbaPlugin search results; verify current status and actual route behaviour.
- [ ] Term Time Only bulk action: requested as a small admin improvement; not confirmed implemented.
- [ ] Organiser storefront redesign: design exploration only; approval/build outstanding.
- [ ] Booking: existing foundations and active architectural work exist; no claim of end-to-end production readiness.

## Nightly review template

At the end of each working day, record:

1. **Completed and verified:** checklist IDs, PR/commit, test evidence.
2. **In progress:** what changed and the next smallest action.
3. **Blocked:** exact blocker and decision/input required.
4. **Not started:** carry forward only if still a priority.
5. **Tomorrow's focus:** one main task and one fallback task.
6. **Scope check:** confirm no duplicate implementation, unapproved live change or unsafe data/schema change was introduced.

## Sunday weekly review

Every Sunday:
- Reconcile the checklist with actual GitHub PRs, commits and CI.
- Confirm staging/deployment/QA evidence separately.
- Close verified tasks and carry forward unfinished work.
- Review the Future Developments roadmap and Bubba4 candidates.
- Choose a realistic next week's top priorities based on available time.
- Keep one main active development workstream at a time.

## Change log

| Date | Change |
| --- | --- |
| 2026-11-09 | Created operational weekly checklist from the agreed Weekly Plan and consolidated development priorities. |


## Repository consolidation note

This operational checklist now lives in `bubbashub1/bubbaplugin`. PR references labelled legacy BubbaWP are historical source references only; verify equivalent work and current status in BubbaPlugin. BubbaPlugin is the single implementation target.
