# Listing Health Scheduled Task import and approval

## Purpose

Scheduled Task research is imported into a persistent, server-side approval queue. It is not a direct database sync. Findings remain pending until an authenticated administrator explicitly approves each change.

## Workflow

1. A recurring ChatGPT task reviews public Bubba Hub directory pages and publicly available organiser/venue sources.
2. It reports evidence-backed findings with the exact listing ID, proposed field/value, source URL, evidence and confidence.
3. Copy the JSON block from the task report.
4. In Admin → Listing Health Check, choose **Import Scheduled Task JSON** and paste it, or select a listing and use **Import from public webpage** to preview a public source and manually enter a proposed field value.
5. Open **Review imported findings**. Compare the current value with the proposed value and inspect the source/evidence.
6. Optionally edit the proposal, then separately choose **Approve and update listing** or **Reject**.
7. The server checks the admin session and CSRF token, validates the field/value/source, and verifies that the live field has not changed since import. If it has changed, the finding is marked stale and is not applied.

ChatGPT Scheduled Tasks do not have access to Bubba Hub's private admin database. They cannot approve, publish, edit or remove listings. Importing a report only creates pending review records.

## Manual public webpage preview

- Select a listing in the Listing Health table, choose **Import from public webpage**, and enter its public page URL.
- The server fetches only a public HTTP(S) HTML/plain-text page, with DNS resolution pinned to a validated public IPv4 address, no redirects, a short timeout and a 1 MB response limit. Private/local IPs, non-standard ports, credential-bearing URLs, non-web content and redirect responses are rejected.
- The preview displays a page title and a short text excerpt. It does not crawl linked pages or automatically change a field.
- Enter the proposed value and add it to the same pending approval queue. The source URL and preview excerpt are retained as evidence. The normal individual approval and stale-value safeguards still apply.
- Page content is untrusted. Check the source yourself before approving any proposed value.

## JSON contract

```json
{
  "schema_version": 1,
  "generated_at": "2026-11-16T09:00:00+00:00",
  "findings": [
    {
      "activity_id": 123,
      "title": "Example activity",
      "field": "description",
      "finding": "The public listing has no description.",
      "suggested_value": "A proposed factual description...",
      "source_url": "https://example.org/",
      "evidence": "The organiser's public page describes the activity...",
      "source_platform": "website",
      "confidence": 82
    }
  ]
}
```

Each actionable finding must include a positive integer `activity_id`, a supported `field`, a string `suggested_value` (or `proposed_value` / `suggested_description` for descriptions), and an HTTP(S) `source_url`. Confidence is optional and is stored as a percentage from 0–100.

Supported activity fields are `title`, `description`, `short_description`, `category`, `age_range`, `website`, `booking_url`, `contact_email`, `contact_phone`, `facebook_url`, `instagram_url`, `town`, `region`, and `postcode`, provided the field exists in the connected activity schema. Venue and organiser fields are not silently written to activity columns; those require the appropriate venue or organiser editor.

New discovery candidates without an existing `activity_id` are not imported as edits. They must be reviewed and created as a separate draft workflow.

## Approval and audit safeguards

- Queue records are stored in the database, not browser local storage, and are available to authorised admins across devices.
- Import never changes an activity record.
- Each item is approved individually; there is no automatic or bulk approval.
- The server stores the original value, proposed value, source URL, evidence, confidence, import time, review status, reviewer label and review time.
- Approval uses a database transaction and row locks.
- If the live value differs from the value captured at import, the item becomes stale and no update is made.
- Only allowlisted fields that exist in the current `bh_activities` schema can be applied.
- Invalid URLs and email addresses are rejected.
- Rejected and approved records remain in the approval table as an audit trail.

## Safety boundaries

- Never invent a listing ID, source, phone number, email, address, price, session time or age range.
- Distinguish verified facts from suggestions and low-confidence matches.
- Do not infer that a listing is closed because a website is temporarily unavailable.
- Description suggestions must be factual, neutral and based on sourced information.
- Do not include personal data that is not already publicly displayed for the listing.
- Potential duplicates are not automatically merged. Activity merges require relationship-aware handling of sessions, venues, organiser ownership, bookings, saved/planned references and other linked records.
- Staging browser QA and schema verification are required before any deployment. No live deployment is included in this change.
