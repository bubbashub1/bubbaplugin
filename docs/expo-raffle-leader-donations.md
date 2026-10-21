# Expo raffle — registered class leader prize donations

Status: implementation specification for PR #307; **not enabled**.

## Agreed rules
- One combined Expo raffle; planned £2 per entry, subject to UK lottery compliance and payment-provider approval.
- One prize maximum per person, regardless of number of tickets.
- Paid ticket checkout and winner draws stay disabled.

## Leader submission
- Show **Donate a Prize** in the authenticated Class Leader portal.
- Authenticate with the existing BUBBAHUBSESSID leader session, verify an active `bh_users.role='leader'` account and its linked `bh_organisers` record server-side; do not accept a client-supplied organiser ID.
- Form: prize title, description, estimated value, image, expiry/redemption terms, contact for fulfilment, consent to public donor attribution.
- Use the site's established CSRF checks, input limits and secure media upload rules. Images must be validated server-side; do not enable uploads until implemented.
- Submit as **pending review**, never directly to the public gallery.
- Allow the submitting leader to see their submission status and edit or withdraw it before approval.

## Admin review
- Queue pending donations separately from draft and published prizes.
- Show leader/organiser, prize details, terms and image; approve, reject with reason, or request changes.
- Approval may create/update a draft prize; public publication requires an explicit admin action.
- Store the original submitting user and organiser IDs, timestamps, decision actor and status transitions for audit.
- Keep public `api/expo-raffle-prizes.php` limited to approved, published prizes; ensure pending submissions never leak.
- Do not use the existing `claimed` status to represent a rejected donation.

## Data and testing
- Prefer a separate `bh_expo_raffle_submissions` table with status enum `pending/changes_requested/approved/rejected/withdrawn`, organiser and user foreign keys, and optional approved prize ID; retain existing prize table.
- Test unauthenticated/family/leader/admin permissions, CSRF failures, ID spoofing, duplicate submissions, draft privacy, mobile layouts, rejection and publication.
- Run migration on **staging only** first. Do not merge to live or enable paid sales as part of this work.
