# BubbaPlugin Master Development, Review & Release Checklist

**Purpose:** Use this checklist for every meaningful BubbaPlugin change: bug fixes, new features, database work, security changes, UI changes, integrations and releases.

**Master repository:** https://github.com/bubbashub1/bubbaplugin  
**Rule:** BubbaPlugin is the source of truth. Other repositories may be inspected for ideas or reusable approaches, but code must not be copied blindly. Check compatibility with the master repository first.

## 1. Before starting
- [ ] Confirm the request and the user-visible outcome in plain English.
- [ ] Identify affected pages, APIs, JavaScript, PHP, database tables, scheduled jobs, emails and integrations.
- [ ] Review the current implementation in BubbaPlugin before editing.
- [ ] Check recent commits, open pull requests and existing work to avoid duplicating or overwriting changes.
- [ ] Check related repositories only where useful; record useful ideas and verify compatibility.
- [ ] Identify existing features that could be affected (login, family accounts, leader portal, activities, sessions, venues, planner, bookings, admin, etc.).
- [ ] Identify whether the work changes personal data, child data, consent, payments, permissions or account access.
- [ ] Confirm the target branch and use a feature branch unless it is a small, documentation-only change.
- [ ] Agree what is in scope and what is explicitly not being built.

## 2. Design and compatibility
- [ ] Follow existing BubbaPlugin structure, naming, coding style and authentication patterns.
- [ ] Reuse existing tables, APIs and shared components where appropriate; do not create parallel systems without an approved reason.
- [ ] Check how existing pages and APIs read and write the same information.
- [ ] Keep data ownership and permissions consistent across family, organiser/leader and admin roles.
- [ ] Make the interface clear, compact, responsive and consistent with Bubba Hub.
- [ ] Include loading, empty, success, validation and error states.
- [ ] Make forms accessible: labels, keyboard use, focus states and understandable validation.
- [ ] Avoid hard-coded demo data, credentials, secrets, production IDs and environment-specific URLs.
- [ ] Keep scope controlled; do not silently change unrelated features.

## 3. Security and privacy review
- [ ] Require authentication for protected actions.
- [ ] Check account status and role on the server, not just in the browser.
- [ ] Enforce record ownership on every read, update, delete and status change.
- [ ] Validate and normalise all input on the server.
- [ ] Use parameterised database queries; no unsafe SQL concatenation.
- [ ] Protect state-changing requests against CSRF using the established project pattern.
- [ ] Escape output appropriately to prevent XSS.
- [ ] Check file upload type, size, storage location and access controls where uploads are involved.
- [ ] Apply sensible rate limits to login, password reset, enquiry, booking and other abuse-prone endpoints.
- [ ] Do not trust localStorage, hidden form fields or browser-supplied role, price, consent, owner or status values.
- [ ] Derive sensitive facts (user identity, ownership, permissions, consent and authoritative prices) from trusted server-side records.
- [ ] Do not expose passwords, tokens, private contact details or unnecessary child information in logs or responses.
- [ ] Check data minimisation, retention, deletion and access rules for personal and child-related data.
- [ ] Review session cookie settings, expiry, logout and session regeneration where authentication changes.
- [ ] Test anonymous, inactive, wrong-role, wrong-owner and tampered-request scenarios.
- [ ] Record any security issue that remains open; do not describe it as fixed until tested.

## 4. Database and migration review
- [ ] Inspect the current master schema and existing migrations before writing SQL.
- [ ] Check whether the required table, column, index or relationship already exists.
- [ ] Prefer additive, non-destructive migrations.
- [ ] Do not drop, rename or overwrite existing tables/columns without an explicit migration and rollback plan.
- [ ] Avoid creating duplicate tables or a second source of truth for existing data.
- [ ] Include appropriate primary keys, foreign keys where supported, indexes, constraints and storage engine.
- [ ] Make migrations safe to run more than once where practical.
- [ ] Consider existing rows, NULL values, legacy accounts and partially completed installations.
- [ ] Check character sets, collations, date/time handling and timezone assumptions.
- [ ] Provide a clear SQL file and explain exactly when and where it should be run.
- [ ] Test the migration on a disposable database copy, including a repeat run.
- [ ] Verify the application works both before and after the migration where compatibility is required.
- [ ] Provide a rollback or recovery procedure; take a backup before any production database change.
- [ ] Never run a production migration or alter live data without explicit approval.

## 5. Code quality and automated checks
- [ ] Review every changed file and the complete diff, not just the headline feature.
- [ ] Run PHP syntax checks on every changed PHP file.
- [ ] Run available project tests, static checks, linting and build checks.
- [ ] Check JavaScript syntax and browser-console errors.
- [ ] Check API response codes, response shape and error handling.
- [ ] Check database queries, transaction handling and failure paths.
- [ ] Check concurrency/race conditions for capacity, bookings, payments or other limited resources.
- [ ] Check duplicate submissions and idempotency where relevant.
- [ ] Check dates, timezones, daylight-saving changes, recurring schedules and boundary conditions where relevant.
- [ ] Check mobile, tablet and desktop layouts.
- [ ] Check links, navigation, buttons, modals, form validation and keyboard interaction.
- [ ] Confirm no debug output, temporary test code or accidental secrets remain.
- [ ] Do not claim a test passed unless it was actually run and its result inspected.
- [ ] If a test cannot be run, record it clearly as NOT RUN and explain what is needed.

## 6. Feature-specific regression checks
Select the areas relevant to the change and test nearby features as well.
- [ ] Login, logout, password visibility, password reset and rate limiting.
- [ ] Family registration, profile, children and consent.
- [ ] Organiser/leader login, profile and permissions.
- [ ] Activity listing, search, filters, categories and activity detail pages.
- [ ] Sessions, calendar, recurring dates, school holidays and timetable rules.
- [ ] Venue selection, venue ownership, location search and map data.
- [ ] Family planner, saved items and clash detection.
- [ ] Enquiries, contact routing and organiser/contact email fallback.
- [ ] Booking requests, capacity, duplicate requests, cancellations and attendance.
- [ ] Leader portal, booking management and activity management.
- [ ] Admin dashboard, user management, listing management and audit history.
- [ ] Emails, notifications, scheduled tasks and external links.
- [ ] Existing URLs, redirects and mobile navigation.

## 7. Staging verification
- [ ] Confirm staging is a known, working clone of the intended code and database state.
- [ ] Confirm staging is not accidentally connected to production email, payment or data services.
- [ ] Back up staging before applying migrations or test data.
- [ ] Apply the SQL migration to staging first.
- [ ] Test the full user journey from beginning to end for each affected role.
- [ ] Test valid, invalid, missing, expired, duplicate and unauthorised scenarios.
- [ ] Test with realistic test accounts and clearly marked test records.
- [ ] Check the browser console, PHP/server logs and database for errors.
- [ ] Check the affected feature on mobile and desktop.
- [ ] Run the relevant regression checks from section 6.
- [ ] Record evidence/results and list any known limitations.
- [ ] Obtain user approval before production deployment.
- [ ] If staging is unavailable or unreliable, stop before production release and restore/verify staging first.

## 8. Pull request and merge
- [ ] PR targets the correct base branch (main unless explicitly agreed otherwise).
- [ ] PR explains the problem, solution, affected files and user-visible changes.
- [ ] PR lists database changes and exact migration instructions.
- [ ] PR lists security/privacy considerations.
- [ ] PR lists tests actually run and their results.
- [ ] PR lists tests not run and remaining risks.
- [ ] Review the full diff for accidental changes, duplicate code and unrelated edits.
- [ ] Confirm no unresolved critical/high security, data integrity or compatibility issue remains.
- [ ] Confirm required staging checks are complete.
- [ ] Confirm the branch is up to date with the target branch and resolve conflicts carefully.
- [ ] Merge only after checks and approvals are complete.
- [ ] Verify the merge commit and resulting main contents.
- [ ] Keep unfinished work clearly marked as draft/not ready; do not merge merely because GitHub says it is mergeable.

## 9. Production deployment
- [ ] Confirm the exact commit being deployed is the approved merge commit.
- [ ] Confirm a current backup exists for files and database.
- [ ] Confirm the deployment method, affected files and any downtime/maintenance needs.
- [ ] Apply only the approved production SQL migration, in the documented order.
- [ ] Deploy the approved code; do not include unrelated local or staging files.
- [ ] Verify key pages and APIs after deployment.
- [ ] Run a small end-to-end smoke test for the affected feature.
- [ ] Check PHP/server logs, browser console and database errors.
- [ ] Confirm existing login and critical user journeys still work.
- [ ] Record deployment date, commit SHA, migration status and outcome.
- [ ] If serious errors occur, stop further changes and follow the rollback/recovery plan.

## 10. Close-out and ongoing maintenance
- [ ] Update relevant documentation, setup instructions and database notes.
- [ ] Record any manual hosting steps the user must complete.
- [ ] Record outstanding issues and who/what is needed to resolve them.
- [ ] Remove temporary test data and test accounts where safe.
- [ ] Confirm no test mode, debug switch or placeholder content is left enabled.
- [ ] Confirm the feature is visible and usable for the intended account roles.
- [ ] Add follow-up work to an issue or clearly named task rather than relying on chat history.
- [ ] Keep this checklist updated when the project process changes.

## Release decision
A change is **READY TO MERGE** only when code review, relevant tests, security review, data compatibility and required staging checks are complete, with no unresolved critical/high issue.

A change is **READY TO DEPLOY** only when it is merged to main, the exact commit is identified, backups and migration instructions are ready, and the user has approved deployment.

Use one of these statuses in every review:
- **BLOCKED** — a critical issue, unsafe migration, broken compatibility or missing essential verification remains.
- **NOT READY** — work is in progress or important checks are still outstanding.
- **READY TO MERGE** — required review and verification are complete.
- **READY TO DEPLOY** — merged, approved and deployment preparation is complete.
- **DEPLOYED – VERIFIED** — deployed and post-deployment checks passed.

## Review summary template
For each substantial change, report:
1. **What changed:** plain-English summary.
2. **Where:** PR, branch, files and affected pages/APIs.
3. **Database:** whether SQL is required, what it changes and whether it has been run.
4. **Security/privacy:** checks performed and any remaining concerns.
5. **Tests:** passed, failed and not run (be specific).
6. **Regression:** existing features checked.
7. **Staging:** environment used and result.
8. **Release status:** one of the statuses above.
9. **Next action:** the single safest next step.

**Standing instruction:** Apply this checklist to every future BubbaPlugin code review, implementation, merge and deployment. If a section is not relevant, mark it N/A rather than silently skipping it.