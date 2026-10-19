# Expo floor plan integration — staging acceptance checks

Do not enable public bookings or merge this PR until the following checks pass on staging.

1. Export a JSON backup from the current admin floor plan. Confirm the saved server layout and revision load correctly.
2. Open the editor while signed out. Confirm admin login is required.
3. Create an approved test exhibitor with no table assigned. Choose an unreserved table and save the assignment. Reload and confirm it remains assigned and is grey.
4. Open the public exhibitor form in another browser. Confirm the assigned table is grey and cannot be selected.
5. Create a pending application choosing a different available table. Confirm it becomes unavailable on a fresh browser and its application remains pending.
6. Try assigning that pending applicant's reserved table to a different approved exhibitor. Confirm the server rejects the request with 409.
7. Release an approved exhibitor's assigned table. Confirm the assignment is cleared; if another pending/approved applicant still holds it as preferred, it must remain unavailable.
8. Simulate two concurrent requests to claim the same table. Confirm only one succeeds.
9. Test stale CSRF, expired admin session, invalid origin, and a non-admin request. All must fail.
10. Test adding, moving, rotating, saving, exporting, and reimporting layout items. Check assignments after any table renumbering or deletion.

## Known follow-up gates
- The public application is deliberately closed by default behind BH_EXPO_APPLICATIONS_OPEN and Turnstile configuration. Do not turn it on as part of this PR.
- A public-facing preview of the full floor plan must not be published without explicit admin release.
- Table IDs must be stable. Deleting a table with a reservation or assignment needs a server-side safeguard before allowing live editing.
- Assignment changes must not send exhibitor confirmation emails or imply automatic application approval.
- Confirm admin database migration is installed in staging and production separately before deploying.

**Status:** untested. Keep the PR in draft until these tests are executed and the known follow-up gates are resolved.
