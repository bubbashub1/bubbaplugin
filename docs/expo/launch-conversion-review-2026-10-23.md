# Bubba & Me Expo — conversion and launch fixes (review draft)

**Event:** 9 January 2027, Acorn Centre, Torquay, 10:30–14:30. **Exhibitor deadline:** 30 November 2026.

## Baseline to preserve
- 25 spaces total; 10 confirmed (3 paying at £20, 7 complimentary); 15 available.
- Venue hire £200; no other costs reported yet. Do not display these internal numbers publicly without approval.
- Existing Forminator integrations, payment forms, admin controls and popups must be preserved; review open PRs #385, #388, #389, #390, #393 and #398 before changing overlapping files.

## Draft A — visitor booking conversion (highest priority)
- Replace 'Coming Soon' ONLY after the real free-ticket reservation flow has been verified end to end.
- Confirm adult QR tickets, children counted, booking confirmation email, duplicate protection, and admin check-in.
- Make primary CTA visible above fold and on mobile; one clear 'Get free tickets' action.
- Do not invent attendance numbers or claim tickets are live before verification.

## Draft B — exhibitor conversion
- Review all Expo exhibitor CTAs against the current Forminator page/modal and remove stale links to old preview forms.
- Confirm whether £20 is mandatory for new commercial exhibitors or optional; preserve previously agreed complimentary arrangements.
- Show application deadline 30 November prominently, a short 'what is included' section and accurate availability without revealing private admin data.
- Validate mobile logo upload and thank-you/confirmation flow.

## Draft C — sponsor, banners, raffle
- Use a single working Forminator sponsor enquiry CTA, with transparent sponsorship benefits and no unverified audience claims.
- Make the 1m × 50cm promotional banner option easy to find; verify price and payment configuration before publishing.
- Keep raffle paid-entry links disabled until the legal basis, beneficiary, payment method and terms are checked; do not assume paid online raffle proceeds can fund a private business.
- Clearly separate donations of prizes from purchasing entries.

## Draft D — confidence and clarity
- Confirm dynamic exhibitor logos/names load on mobile and desktop; add sensible loading/empty/error states.
- Add practical FAQs for accessibility, parking, pushchairs, changing facilities and ticket arrangements, only where verified.
- Show timetable as provisional until speakers/times are confirmed.
- Ensure popups have keyboard close, focus handling, no overflow and accessible button labels.

## Acceptance tests before merge
1. Desktop + Android/mobile CTA navigation and popup close.
2. Forminator sponsor, exhibitor, banner and raffle forms reach intended destination.
3. Stripe payment and confirmation flows tested separately where applicable.
4. Free ticket QR generated and email delivered; admin check-in works.
5. No 'Coming Soon' on live ticket CTAs after verified launch; no duplicate CTAs or conflicting prices.
6. No live database schema changes or deployment without explicit review.

**Status:** Planning draft only; implementation, legal review, QA and deployment still pending.
