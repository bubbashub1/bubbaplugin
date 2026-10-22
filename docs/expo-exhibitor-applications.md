# Bubba & Me Expo — Exhibitor applications

Draft feature specification for 9 January 2027 at Acorn Centre, Torquay. Builds on existing event workflows (#21–#24) and the unified admin approval centre (#52).

## Application journey
1. Exhibitor opens the Expo event page and selects **Apply for a stall**.
2. Mobile-friendly form: business/organisation name, named contact, email, phone (optional), website/social link (optional), service category, short description, stall requirements, accessibility needs (optional), and agreement to exhibitor terms/privacy.
3. On submission, show a reference number and send an acknowledgement through the existing SMTP service. Do not promise a confirmed place until approval.
4. Admin reviews **pending / approved / declined / withdrawn** applications, with internal notes and date-stamped decisions.
5. On approval, send a confirmation with event details and any payment instructions; do not send payment links before approval unless explicitly configured.
6. Existing Bubba Hub leaders can prefill their business details with permission; never require a leader account to apply.

## Implementation and safety checks
- First inspect existing event and admin approval tables/routes to reuse them; avoid a second event database.
- Require CSRF for logged-in requests; rate-limit public form submissions and validate inputs server-side.
- Store only necessary contact details, restrict admin access, prevent XSS, and avoid sending sensitive internal notes by email.
- Keep email delivery failure visible to admin, with retry support, without creating duplicate applications.
- Provide CSV export for authorised admins; no public applicant list.
- Ensure accessible mobile UI, clear errors and no duplicate submissions.
- Add migrations separately and do not execute on live without backup and approval.
- Staging tests: successful application, duplicate submission, invalid email, SMTP failure, approval/decline, permissions, mobile layout.

## Acceptance criteria
Exhibitors can apply without leaving Bubba Hub; admins can review and respond; applicants can distinguish application received from stall confirmed. No live deployment until staging tests pass.
