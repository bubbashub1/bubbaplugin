# Claim invitation controlled test — activity 36

Status: PREPARED, NOT EXECUTED. No staging deployment or emails sent.

## Preconditions
- Confirm staging activity ID 36 exists and `bh_activities.contact_email` contains the tester-controlled address.
- Confirm activity 36 is unclaimed and no pending claim exists.
- Confirm SMTP configuration uses noreply@bubbahub.co.uk; sender display name Bubba Hub South West; no Reply-to.
- Deploy the feature branch to staging only after authentication, schema migration, and claim flow are reviewed.
- Keep `BH_CLAIM_AUTO_INVITES_ENABLED` disabled.
- Keep `BH_CLAIM_INVITES_ENABLED` disabled until a human verifies the recipient and the manual-send action.

## Test cases
1. Unauthenticated admin invitation endpoint must return 401.
2. Admin GET shows record 36 and the expected recipient, not organiser email.
3. Invalid CSRF on manual send returns 403.
4. With sending disabled, POST for 36 returns 409 and sends nothing.
5. Enable manual sending only in staging; send to record 36 once.
6. Verify From, subject, HTML/plaintext, claim URL and email delivery.
7. Repeated send within seven days must return 409.
8. Tampered/expired invitation token must be rejected.
9. Wrong signed-in leader email must be rejected.
10. Correct leader email + valid token creates pending claim; admin reviews.
11. Ensure no other activity is emailed and live remains unchanged.

## Release blockers
- Verify actual admin session conventions and CSRF.
- Database migration should be separate from public/admin request handlers.
- Wire invitation token through sign-in UI to claim submission.
- Verify approval workflow and test on staging before enabling automatic triggers.


## Required staging database preparation

Before opening the admin invitations page, ensure both `bh_listing_claims` (as defined in `api/claim-listing.php`) and `bh_claim_invitations` (as defined in the feature branch history) exist. The admin endpoint no longer creates tables on request. Do not enable `BH_CLAIM_INVITES_ENABLED` until the migration has been applied and verified. During the first test, only listing 36 can be emailed unless `BH_CLAIM_INVITES_ALL_ENABLED=1` is explicitly set.
