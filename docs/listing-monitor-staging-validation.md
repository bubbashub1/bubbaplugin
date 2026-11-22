# Listing Monitor — controlled staging validation

**Status:** an earlier version of this branch was deployed to staging. The database-reuse refactor is now on the feature branch and must pass CI and be redeployed to staging before browser validation. Migrations remain unapplied.

## Safety boundaries

- Target staging only: `https://staging.bubbahub.co.uk/`.
- Never run these migrations against live or production.
- Take a fresh staging files/database backup before deployment.
- Confirm the staging database configuration points to the staging database before applying either migration.
- Existing Listing Checker remains available; do not remove its tables or settings.
- Keep website monitoring disabled and unscheduled throughout this test.
- No public listing is edited, closed, hidden, archived or deleted by a website check.
- Only the explicit approval action may write approved fields to `bh_activities`.

## Before deployment

- [ ] Confirm the feature branch and draft PR are still the intended source.
- [ ] Confirm PHP lint, security guards, offline rule tests, MySQL integration and API integration are green on the exact code commit.
- [ ] Confirm a fresh staging backup exists and can be restored.
- [ ] Confirm the staging environment's private DB configuration resolves to the staging DB, not the live DB.
- [ ] Confirm the two revised migration files have not already been applied; check table existence first.
- [ ] Agree a disposable staging activity for approval and stale-snapshot tests. Do not use a real listing unless its original values are recorded and it is safe to restore.

## Deployment steps (staging-only approval already given)

1. Deploy the feature branch to staging using the normal staging deployment workflow.
2. Apply only the two revised, additive migrations to the confirmed staging database:
   - `database/migrations/20261121_create_listing_monitor_drafts.sql`
   - `database/migrations/20261122_create_listing_monitor_website_checks.sql`
3. Verify the two new private tables exist:
   - `bh_listing_monitor_drafts`
   - `bh_listing_monitor_website_history`
   - the two `website_monitor_*` disabled settings in `bh_listing_checker_settings`
4. Keep `website_monitor_enabled` at `0`. Do not add or run a cron schedule for `api/listing-monitor-website-cron.php`.
5. Do not turn on existing health settings as part of this validation.

## Admin browser checks

- [ ] Admin can open Activities & Listings → Listing Monitor.
- [ ] Signed-out visitors are redirected to admin login.
- [ ] Expired admin sessions are rejected.
- [ ] Activity totals and existing review findings load without changing the old Listing Health page.
- [ ] A non-website finding opens the suggested-field comparison.
- [ ] A website finding opens evidence only and cannot create an empty draft.
- [ ] Source URL, checked time, HTTP status and evidence are readable.
- [ ] Unticking a field excludes it from the draft.
- [ ] Editing proposed text is retained.
- [ ] Saving creates a private draft and the public listing remains unchanged.
- [ ] Saving again updates the same draft rather than creating duplicates.
- [ ] Full listing review shows the activity details, linked venues and sessions where available.
- [ ] Approval requires the browser confirmation and updates only the highlighted fields.
- [ ] Approved draft retains the admin identity, before/after values, source and approval time as its approval record.
- [ ] If a live field is changed after draft creation, approval returns a conflict and does not overwrite it.
- [ ] Rejected CSRF and cross-origin requests do not create drafts or update listings.
- [ ] A website finding cannot be mistaken for proof that the business has closed.
- [ ] Existing admin pages, listing review, venues and timetable editor still work.

## Post-test checks

- [ ] Verify the public test activity shows only the explicitly approved field changes.
- [ ] Verify all other activity fields are unchanged.
- [ ] Verify the approved draft retains the exact before/after values and reviewer details.
- [ ] Verify the worker remains disabled and unscheduled.
- [ ] Verify no live database, live files or public listing were touched.
- [ ] Record test results and any defects in the PR before considering merge.

## Rollback

If the staging UI or migration fails, stop testing. Do not attempt ad-hoc live fixes. Restore staging from the pre-test backup or use a reviewed, tested rollback plan. Do not drop new tables manually while any test data or audit evidence is still needed.


## Database reuse decisions

- Reuse `bh_listing_check_findings` for private alerts and suggested evidence.
- Reuse `bh_listing_checker_settings` for the separate, default-disabled website-monitor switches and existing batch/interval settings.
- Do not reuse `bh_listing_check_queue` or `bh_listing_check_runs` for website checks: the legacy health worker relies on them for its own due calculations and last-run interval. Sharing them would cause the two workers to interfere.
- Do not reuse `bh_listing_check_results`: it stores discovery/search result snapshots rather than a chronological website-check log.
- Do not reuse `bh_directory_record_revisions`: it requires organiser-submission ownership fields and is not an admin research draft.
- The approved row in `bh_listing_monitor_drafts` is the audit record; no separate audit table is created.
- `bh_listing_monitor_website_history` is the only additional website-check table. Its latest matching URL timestamp is also the scheduling state, so no separate state table is required.
