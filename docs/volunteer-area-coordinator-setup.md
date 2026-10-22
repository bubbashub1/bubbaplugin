# Bubba Hub Area Coordinator — Google Sheets setup

This is an additive, read-only-first companion to the existing **MASTER SHEET** and Apps Script import/export work. Never rename or alter existing MASTER SHEET columns or enable imports as part of setup.

## Sheet layout

Create a **separate private coordinator workbook**, owned by the Bubba Hub administrator. Do not share the master workbook with volunteers. Create these tabs:

1. `Assignments`: Area | Volunteer email | Active | Last assigned | Notes
2. `Review Queue`: Activity ID | Activity name | Area | Town | Source URL | Existing value | Suggested change | Evidence URL | Assigned email | Status | Checked date | Reviewer | Approved date | Notes
3. `Approval Log`: Timestamp | Activity ID | Field | Before | After | Volunteer | Approver | Decision | Evidence
4. `Dashboard`: area, total assigned, checked, awaiting approval, rejected, completed.

Statuses: Not started; In progress; Checked – correct; Change suggested; New activity suggested; Closed – verify; Approved; Rejected.

## Access and safety

- Admin-only MASTER SHEET and approval log. Volunteers must not see private family, leader, payment or authentication information.
- Separate area workbooks are safest; protect the header and identifiers. Google Sheets filter views are **not access controls**.
- Use immutable activity IDs from the existing export and record proposed changes in separate columns. Never edit source data directly.
- Each proposal requires an evidence URL and check date; do not auto-accept search snippets.
- A reviewer approves field-level changes before any staging import; no automatic production updates.
- Preserve the existing 25-row import batch limit, staging dry-run and backup/rollback gates. Do not expose the admin import endpoint to volunteers.

## Setup steps

1. Back up MASTER SHEET; verify the latest approved export against staging.
2. Create coordinator workbook and copy only public listing fields into review queue. Do not change source IDs.
3. Allocate Torbay, Teignbridge, Exeter, South Hams, Plymouth, North Devon, East Cornwall, Mid Cornwall and West Cornwall.
4. Create area-specific copies of review queue and share only each area's copy with its volunteer.
5. Collect proposals in a private admin queue; manually reconcile against source ID and current database values.
6. Test five staging records end to end before implementing a guarded Apps Script approval bridge.
7. Keep all search/API keys in server-side secrets or Apps Script Properties, never in a shared sheet.

## Google Search integration — follow-up

Verify which Google search API and quotas are active before integrating. Search results should provide leads and links, not authoritative replacement values. Prefer the organiser's official timetable or booking page, and require human verification.

## Acceptance criteria for future automation

- No volunteer has master-sheet or database write access.
- A rejected proposal cannot reach staging.
- Two simultaneous proposals for one activity create a conflict for manual review.
- Approval log records before/after and approver.
- Failed staging writes leave live untouched and can be rolled back.
- Repeat imports do not duplicate activities, organisers, venues or sessions.
