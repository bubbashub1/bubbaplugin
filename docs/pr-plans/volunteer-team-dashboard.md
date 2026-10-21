# Volunteer Team Dashboard — draft specification

## Goal
A mobile-first volunteer portal matching Bubba Hub design, with a separate admin team overview.

## Volunteer features
- Volunteer login with scoped role and assigned region(s)
- Dashboard: assigned listings, unchecked listings, proposed updates, approved changes
- Search/filter local activities and inspect source links
- Submit field-level corrections and new listing suggestions to moderation queue
- Review Google Sheets sync discrepancies and original class websites without direct database write permissions
- Progress history, onboarding instructions, support and contact admin

## Admin features
- Invite, suspend and assign coordinators to Torbay, Teignbridge, South Hams, Exeter and other Devon/Cornwall areas
- View workload, coverage, aging tasks and pending corrections by region
- Approve/reject submissions with audit trail

## Security and rollout
- Never expose Google API credentials to browser
- Enforce region scope and roles server-side for every endpoint
- Avoid exposing family account data and private contact details
- CSRF, rate limits, change audit, duplicate checks
- Integrate with existing Sheets sync; staging first, feature flag off by default

## Acceptance
- Volunteer sees only assigned region records
- Proposed changes do not auto-publish
- Admin can review and approve with audit records
- Works on mobile and desktop
- No production rollout until tested
