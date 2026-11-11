# Bubba Hub — Weekly Plan and Delivery Checklist

**Primary repository:** `bubbashub1/bubbaplugin`  
**Planning branch:** `plans-and-tracker`  
**Review cadence:** Nightly progress check; full weekly reset every Sunday.  
**Last updated:** 11 November 2026

## How to use this checklist

- Keep this as the operational checklist for the current week.
- Update statuses every evening: `[ ]` not started, `[~]` in progress, `[x]` complete and verified, `[!]` blocked/needs a decision.
- Do not mark a task complete because code exists or a PR merged. Confirm the required user journey, tests and staging QA.
- Record the PR/commit and evidence beside the task when available.
- Keep all implementation in BubbaPlugin. BubbaWP and Bubba4 are archived historical/reference repositories only; consult them only when a checklist item explicitly requires origin verification, never as implementation targets.
- The broader feature roadmap lives in [Future Developments](FUTURE_DEVELOPMENTS.md).

## This week's goal

Make the existing Bubba Hub platform dependable and joined-up for families, organisers, leaders and administrators. Improve what exists before adding new systems. Do not start a rebuild.

## 1. Main pages, dashboards and navigation

- [ ] Review homepage and activity discovery/results.
- [ ] Review activity detail page following merged PR #826; confirm deployment separately.
- [~] Review organiser storefront/profile and agree the new flexible design. PR #845 is open and ready for review; the mini-site styling is present, but final design approval and browser/mobile QA remain outstanding.
- [~] Review Family Hub and its eight-section navigation. PRs #857–#860 merged the shared family portal shell, dedicated activity sections, shell parse fix and “My Bubba Hub” heading; browser/mobile navigation and post-deployment QA remain unverified.
- [~] Review Leader dashboard and the activity creation journey. New dashboard and portal-shell work is now on `main` through PRs #852–#856 (dashboard redesign, readability, leader name, sub-page shell and overlap fix); static CI passed on #852–#854, but authenticated Free/Pro browser/mobile QA is outstanding. PR #763 remains a draft and is now 120 commits ahead / 66 behind `main`; its wizard-specific work still needs isolation from the superseded dashboard shell.
- [~] Review Admin dashboard; simplify sections, navigation and duplicated destinations. PRs #835, #838 and #849 are merged to `main`, including the task-first six-section layout and mobile polish; deployment and staging/mobile visual QA are not evidenced, and the agreed simpler navigation still needs a visual check.
- [ ] Review mobile layout, accessibility, touch targets and consistency.
- [~] Complete a sitemap check for missing, broken, duplicate or obsolete pages/routes. Hub Demo routing fix PR #842 is merged; general clean-URL routing is proposed in open draft PR #851 (5 commits ahead / 10 behind `main`), with staging verification and CI still unverified.
- [ ] Record any page that should remain unchanged rather than redesigning for its own sake.

## 2. Activity listing wizard

- [~] Confirm the seven-step wizard is accessible to the correct approved leader accounts. PR #763 contains wizard/client integration work but remains draft and is 120 commits ahead / 66 behind `main`; its dashboard shell is superseded by merged PRs #852–#856, so isolate/reconcile only the required wizard work before authenticated leader access QA.
- [ ] Identify and document all required fields.
- [ ] Add clear required indicators and field-level validation.
- [ ] Add helpful error messages and preserve entered data after validation errors.
- [~] Confirm save/resume and draft behaviour. PR #763 adds organiser-scoped local draft keys, but the branch is now 120 commits ahead / 66 behind `main`; resume, cross-account behaviour and authenticated browser QA remain outstanding.
- [ ] Verify organiser ownership is enforced server-side.
- [ ] Test the complete wizard on a phone-sized viewport and desktop.
- [ ] Verify successful submissions appear correctly in the admin review/listing area.

## 3. Duplicate listing alerts

- [~] Check exact activity title + same venue matches server-side. Draft PR #843 implements a server-side title/venue preflight and is 27 commits ahead / 11 behind `main`. Latest visible run #60 passed PHP syntax and organiser-profile DB migration/update checks; duplicate-specific behaviour tests and staging acceptance remain outstanding.
- [~] Block exact duplicates by default. PR #843 implements the default server-side block on its draft branch; direct production behaviour is not verified.
- [~] Show a useful warning for possible title-only matches. PR #843 warns on same-title/different-venue matches; browser QA is outstanding.
- [~] Provide the agreed exception/review route for genuinely different activities. PR #843 adds a leader review request and admin decision flow; staging QA is outstanding.
- [~] Keep exception submissions private/unpublished and not bookable until reviewed. PR #843 forces review overrides to pending/unpublished; this still needs end-to-end verification.
- [~] Verify leaders cannot bypass checks through an alternate dashboard or endpoint. PR #843 applies the check to direct API submissions; alternate-path and authenticated browser tests remain outstanding.
- [ ] Test punctuation/case/whitespace, shared venues, multiple classes at one venue and similar titles at different venues.
- [~] Track the legacy BubbaWP PR #205 as a reference only; equivalent work is now in BubbaPlugin PR #843 (open draft). No duplicate-specific CI or staging acceptance has been evidenced.

## 4. Claim listing and organiser verification

- [ ] Review current claim invitation and request process.
- [ ] Confirm each listing is mapped to the correct organiser.
- [ ] Use verified ownership evidence; do not rely on display name or email match alone.
- [ ] Make claim statuses clear: not sent, invited, requested, approved, rejected/expired.
- [ ] Improve admin approval/rejection and multi-select review where safe.
- [ ] Prevent cross-organiser claims and unauthorised edits.
- [ ] Test invitation delivery, resend/expiry, duplicate requests and audit history.

## 5. Organiser storefront and venue page

- [~] Approve a fresh organiser storefront design with flexible branding. PR #845 is open and ready for review, 2 commits ahead / 11 behind `main`; it proposes a Pro mini-site storefront, but the exact design still needs approval.
- [ ] Consider full branding as a Pro feature and a useful limited free profile.
- [ ] Add organiser-uploaded hero image with validation and fallback.
- [~] Build front-end My Venues using the existing organiser-page layout. PR #844 is open as a draft, 3 commits ahead / 11 behind `main`, and uses the existing leader portal API and venue records.
- [~] Connect the page to existing venue records and activity relationships. PR #844 groups matching venue locations and links their activities; cross-organiser and staging QA remain outstanding.
- [~] Support shared venues linked to multiple activities. PR #844 includes grouped locations; verify real multi-activity venue behaviour in staging.
- [ ] Keep venue add/edit/delete/merge in the admin dashboard for this phase.
- [ ] Test empty states, multiple venues, permissions and mobile layout.

## 6. Booking system — feasibility and delivery

- [!] Confirm the authoritative booking architecture and form-provider decision before changing code. PR #817 is a native booking prototype, 69 commits ahead / 49 behind `main`; CI syntax and disposable MariaDB migration tests pass, but the final checkout/form-provider approach remains unresolved. Do not add a second booking path.
- [ ] Inspect current staging plugin/form versions, form IDs/field keys and booking/session schema read-only.
- [~] Preserve existing booking, attendee, consent, checkout, Stripe and submission data. PR #817 uses canonical reservations and consent snapshots in its prototype; full data-preservation, checkout and Stripe acceptance is not verified.
- [ ] Confirm family child-profile ownership validation.
- [~] Test the journey: choose class → select children → details → terms → pay → confirmation. PR #817 contains a partial native prototype for slots/capacity, consent and family/leader booking lists. CI run #23 passed PHP/JavaScript syntax and disposable MariaDB migration tests; the prototype explicitly does not collect payments, and staging E2E/pay/confirmation remain outstanding.
- [ ] Test free bookings, capacity, duplicates, sibling places, cancellation and confirmation.
- [ ] Test payment/webhook/refund/payout only in staging test mode.
- [ ] Keep live payment processing disabled until all acceptance gates and explicit approval are complete.
- [ ] If full implementation is not safe this week, produce a precise staged completion plan with blockers.

## 7. Bubba4 feature review

Bubba4 and BubbaWP are archived. This is a historical source review only; do not restart either repository or create parallel implementation work.

- [~] Review personalised Leader greeting; PR #854 now shows the signed-in leader name above “My workspace” in BubbaPlugin and is merged, but confirm whether a fuller personalised greeting is still required before considering this historical feature review complete.
- [~] Review simpler admin navigation and redundant submenu hiding; PRs #835, #838 and #849 provide the task-first six-section admin layout and mobile polish on `main`; verify redundant submenu handling, routes and saved data in staging.
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

- [x] Repository consolidation: user confirmed BubbaWP and Bubba4 are archived; BubbaPlugin is the sole active implementation repository.

- [x] PR #826 activity detail redesign merged (9 November 2026). **Deployment and responsive/booking regression QA are not confirmed.**
- [~] Admin dashboard: PR #838 (colour-coded panels) and PR #849 (mobile polish) are merged to main. No deployment or staging/mobile visual QA evidence is available.
- [~] PR #763 Leader Dashboard remains open as a draft and is now 120 commits ahead / 66 behind `main`; reconciliation PR #850 was closed unmerged. Its older dashboard shell has been superseded by merged PRs #852–#856; do not merge the whole stale branch. Isolate the wizard work still needed.
- [~] Duplicate checking: implementation is in draft PR #843 (27 ahead / 11 behind `main`). Latest visible workflow #60 passed PHP syntax and organiser-profile database migration/update checks; no dedicated duplicate-match/review test or staging acceptance is evidenced.
- [~] PR #844 My Venues remains open as a draft (3 ahead / 11 behind `main`). It uses existing organiser-scoped venue/activity data; authenticated multi-venue, cross-organiser and mobile QA remain outstanding.
- [~] PR #819 school-holiday save/session-CSRF fix merged to main on 9 November (merge commit 6f0515b). Deployment and runtime/staging verification are not confirmed.
- [~] Hub Demo routing: commit 6594ed2 (“Route Hub Demo to WordPress”) is included on main. PR #204 is unrelated (“Repair staging Events layout”), closed unmerged against an Expo feature branch. Actual route behaviour/deployment remain unverified.
- [ ] Term Time Only bulk action: requested as a small admin improvement; not confirmed implemented.
- [~] Organiser storefront: PR #845 is open and ready for review (2 ahead / 11 behind `main`). The Pro mini-site styling is present; hero-image upload/brand controls are explicitly not included, and browser/mobile QA remains outstanding.
- [!] Booking architecture: PR #817 is an open draft native booking prototype (69 ahead / 49 behind `main`). Syntax and disposable migration CI jobs passed, but the branch needs reconciliation and the final form/checkout decision plus staging E2E remain open.
- [~] Listing Checker: PR #846 is an open draft, 111 commits ahead / 11 behind `main`. Latest workflow run #118 is still queued with no jobs reported yet; PHP/security checks therefore remain unverified in this run. Schema/cron configuration and staging data checks are also outstanding.
- [!] My Hub storage migration: PR #802 is open, 4 commits ahead / 55 behind `main`. No staging backup, migration execution or data-preservation QA is evidenced; reconcile and inspect staging before any SQL.
- [~] Family session/profile fix PR #847 and subscription session fix PR #848 remain open drafts, 4 and 1 commits ahead respectively and both 11 commits behind `main`; no relevant PR-triggered CI or staging verification is evidenced.
- [ ] No live deployment, database change or staging QA was performed during this review.

- [~] Family portal shell and sections: PRs #857–#860 are merged to `main`, including the shared shell, dedicated activity sections, the JavaScript parse fix and the “My Bubba Hub” heading. No PR-triggered CI was returned for these commits, and deployment/browser/mobile acceptance is not evidenced.
- [~] Leader portal redesign: PRs #852–#856 are merged to `main`. CI runs #9–#11 passed JavaScript syntax and dashboard structure/navigation/CSS checks for #852–#854; #855/#856 had no PR-triggered workflow result returned. These are static checks, not authenticated browser QA.
- [~] General static-page routing: PR #851 is an open draft (5 ahead / 10 behind `main`), limited to `.htaccess`; no PR-triggered CI result or staging route test is evidenced.
- [~] Deployment workflow reliability: PR #861 merged to `main` with bounded SSH/SCP timeouts and extraction progress. This is a workflow-code change only; no successful production deployment or post-deploy QA is evidenced.
- [ ] No live deployment, database change or staging QA was performed during this review.

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
| 2026-11-11 | Reconciled recent BubbaPlugin merges #852–#861, open PR states, branch divergence and available CI. Updated Family/Leader portal progress and recorded that deployment and browser QA remain unverified. |


## Repository consolidation note

This operational checklist now lives in `bubbashub1/bubbaplugin`. BubbaWP and Bubba4 are archived historical/reference repositories only; consult them only where a checklist item explicitly requires origin verification. PR references from those repositories are not BubbaPlugin PRs. BubbaPlugin is the single implementation target.
