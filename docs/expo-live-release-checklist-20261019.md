# Expo exhibitor applications — LIVE release gate (19 October 2026)

This branch is based on production main, NOT staging-site or the divergent Expo feature branch. **DO NOT MERGE OR DEPLOY** until every gate below is verified.

## Included now
- Public exhibitor application page (currently displays launch-pending copy).
- Read-only public approved exhibitor API (requires live bh_expo_exhibitors table; fails closed).

## Missing before release
- Public POST application API. Existing feature-branch api/expo-exhibitors.php is staging-admin-only and must NOT be copied to production. Build and test public endpoint with server-side validation, origin/CSRF controls, anti-spam/rate limiting, all records pending by default, no public contact data, and admin notification.
- Confirm and apply live DB schema non-destructively (bh_expo_exhibitors; ensure event key, statuses, public visibility, contact and insurance fields). Back up DB first.
- Add protected admin review/approval UI and API, verify dedicated admin session, CSRF, and approval-before-publication. Isolated staging PR #225 contains initial work, not yet verified.
- Verify expo.html and links from events pages, ensure no accidental broken routes. Avoid replacing existing main event pages without diff review.
- End-to-end staging test: anonymous submission, invalid submission, duplicate/spam attempts, admin approval, public listing, notification mail; verify no sensitive information exposed.
- Confirm production GitHub Actions deploy from main to public_html; PR merge triggers deployment. Do not overwrite manually deployed ticket or QR check-in files. Verify those flows after deployment.

## Release acceptance
- Anonymous exhibitor can submit successfully on live, sees confirmation.
- Admin receives and approves pending application.
- Only approved + public_visible exhibitor shown on Expo page.
- Existing family tickets and QR remain operational.

## Notes
- Current form says applications are not open; change only when backend is ready.
- Do not publish test exhibitor gibberish from staging.
