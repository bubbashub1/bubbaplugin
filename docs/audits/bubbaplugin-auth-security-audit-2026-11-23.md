# Bubba Hub authentication and security audit

**Date:** 2026-11-23  
**Repository reviewed:** `bubbashub1/bubbaplugin`, default branch `main`  
**Scope:** Static code review of account authentication, sessions, account recovery, admin authentication, WordPress login-bridge work, and representative protected APIs. This is not a live penetration test, a complete endpoint-by-endpoint audit, or a review of hosting configuration.

## Architecture decision

Retain Bubba Hub's custom directory, listings, venues, planner, bookings and leader/family experience. Do not make Directorist a platform dependency.

Use WordPress as the identity and authentication authority:
- WordPress users, passwords, login/logout and password recovery.
- WordPress sessions/cookies and authentication state.
- WordPress roles and capabilities, with custom least-privilege roles/capabilities for Bubba Hub family, leader, volunteer and admin actions.
- WordPress REST cookie authentication and REST nonces for same-origin browser requests.
- A small Bubba Hub integration layer maps the authenticated WordPress user to the existing Bubba Hub account/data identity. The browser must never choose its own user ID or role.

Keep Bubba Hub responsible for its specialist records and workflows. WordPress authentication does not automatically secure custom PHP endpoints: every protected endpoint must validate the current WordPress user, capability, account status and record ownership on the server.

## Findings

### High priority

1. **Separate static admin credential system — high**
   - Evidence: `admin-auth.php` reads one `admin_username` and `admin_password` from a server-side config file and compares them directly. It creates a separate `BUBBAHUB_ADMINSESSID` session.
   - The inspected login handler has no visible rate limit, lockout, per-admin identity, or second factor.
   - Risk: shared credentials are harder to attribute, revoke individually and protect against password guessing.
   - WordPress replacement: use named WordPress administrator accounts, strong passwords, MFA and a login-protection control. Check `current_user_can()` for admin actions; do not grant every leader administrator privileges.

2. **Two independent identity/session systems — high**
   - Evidence: `api/auth.php` uses `bh_users` and `BUBBAHUBSESSID`; `admin-auth.php` uses a separate static-config admin login and `BUBBAHUB_ADMINSESSID`. The WordPress login bridge MU plugin is explicitly documented as not yet wired into legacy API endpoints.
   - Risk: inconsistent login state, duplicate identities, role drift, confusing logout and access-control mistakes.
   - WordPress replacement: one WordPress user identity and session, with a unique, verified mapping to a legacy Bubba Hub user during migration.

3. **Session initialisation is inconsistent across endpoints — high reliability/security priority**
   - Evidence: `api/auth.php` explicitly names `BUBBAHUBSESSID`; `api/activate.php` starts a default PHP session without setting that name; `api/planner.php` also starts a default session without setting the Bubba Hub session name. Other endpoints initialise sessions differently.
   - Evidence: `api/admin-volunteers.php` and `api/admin-volunteer-assignments.php` start `BUBBAHUBSESSID` but check `bh_admin_authenticated`, while the primary admin login stores that flag in `BUBBAHUB_ADMINSESSID`.
   - Risk: successful sign-in may not be recognised by the next endpoint; admin-only actions may reject the legitimate admin session. Exact runtime impact depends on PHP session configuration and request cookies.
   - WordPress replacement: remove direct PHP session handling for human account authentication. Use `is_user_logged_in()`, `get_current_user_id()` and WordPress REST cookie auth.

4. **Custom leader portal writes have no visible CSRF token check — high**
   - Evidence: `api/leader-portal.php` requires POST writes to have an HTTPS same-origin Origin/Referer source and checks Fetch Metadata, but the inspected file has no general CSRF token validation before its write actions.
   - Risk: origin checks are useful defence-in-depth, but a per-user REST nonce should also protect cookie-authenticated mutations.
   - WordPress replacement: require a valid `X-WP-Nonce` on REST cookie-authenticated requests and separately enforce capabilities/ownership. A nonce is not a substitute for authorisation.

### Medium priority

5. **Password reset does not revoke existing sessions — medium**
   - Evidence: `api/auth.php?action=reset_password` consumes reset tokens and updates the password hash, but the inspected flow does not revoke other active sessions.
   - Risk: a previously stolen session may remain usable after a password reset.
   - WordPress replacement: use core password reset and session-token invalidation APIs; explicitly destroy other sessions when appropriate.

6. **Activation endpoint uses a different session name — medium**
   - Evidence: `api/activate.php` sets cookie parameters and calls `session_start()` without setting `BUBBAHUBSESSID`, then writes `bh_user_id`.
   - Risk: activation reports a signed-in session which the normal Bubba Hub APIs may not recognise.
   - WordPress replacement: activate/verify the WordPress account, then establish the normal WordPress login session.

7. **Account-context semantics are easy to confuse — medium**
   - Evidence: `bh_check_account_context()` permits the `leader` role in family context. `bh_login_session()` stores family and leader context in separate flags. The `me` response's `authenticated` field reflects family context, while leader state is returned separately.
   - Risk: front-end components that only inspect `authenticated` can misinterpret a valid leader session.
   - WordPress replacement: use one authenticated user ID and explicit role/capability checks; dashboards can redirect by role without inventing separate login identities.

8. **Custom database schema is modified during normal requests — medium**
   - Evidence: account helpers create tables; `api/profile.php` attempts multiple `ALTER TABLE` operations during profile requests; `api/my-hub.php` creates and upgrades tables at request time.
   - Risk: runtime database privileges are broader than necessary, concurrent requests can race, and schema changes are difficult to review/roll back.
   - WordPress replacement: WordPress does not replace Bubba Hub's specialist schema. Move schema changes into versioned, reviewed migrations and remove DDL from normal user requests.

9. **WordPress bridge is a preflight, not a completed integration — medium**
   - Evidence: `bubbahub-login-bridge-foundation.php` says it resolves identity without changing sessions; preflight says legacy API sessions remain unchanged. It maps only family and leader roles and requires a unique legacy link plus matching email and active legacy account.
   - Risk: existing users are not yet centrally authenticated by WordPress; role coverage (e.g. volunteers/admins) and account reconciliation are incomplete.
   - Recommendation: extend the mapping deliberately, audit duplicates/unlinked accounts, then migrate in controlled stages. Do not enable a partial bridge on production.

10. **Privacy/GDPR work is still marked draft — medium**
    - Evidence: `docs/GDPR-REVIEW-CHECKLIST.md` labels itself a draft and leaves data inventory, children's data, lawful basis, retention, cookies/consent and processor/transfer review incomplete.
    - Risk: Bubba Hub handles family and child-related information; security is not only login protection.
    - Recommendation: complete the data inventory, retention/deletion rules, access model, cookie/consent audit and DPIA screening before treating the privacy work as complete.

### Existing controls worth retaining

- `api/auth.php` uses password hashing, dummy-hash verification for unknown emails, rate limiting, generic login/reset responses, CSRF checks, one-time hashed email-verification/reset tokens and short expiry windows.
- Leader password registration requires email verification before password login.
- Session regeneration is used on password login and selected account transitions.
- The leader portal checks that requested activities belong to the current organiser for the inspected image-upload and listing-import paths.
- Several endpoints use prepared statements, server-side role/status checks and upload validation.
- The login bridge preflight checks unique legacy links, role mapping, email match and active status.

These controls are useful foundations; they do not cancel out the higher-priority inconsistencies.

## What WordPress can take over

| Responsibility | WordPress can provide | Bubba Hub still needs |
|---|---|---|
| User accounts | User records and account lifecycle | One-time legacy ID mapping and migration |
| Passwords/login/logout | Core authentication and session cookies | Front-end integration and role-aware redirects |
| Password recovery | Core lost-password flow and session management | Branded emails/pages if desired |
| Roles/permissions | Roles, capabilities and `current_user_can()` | Custom Bubba Hub capabilities and per-record ownership checks |
| CSRF | REST cookie authentication and REST nonces | Require nonce on every cookie-authenticated write |
| Admin accounts | Individual WP administrator accounts | Separate least-privilege admin capabilities for custom tools |
| MFA/rate limiting | Usually a security plugin or hosting control | Configure, test and monitor it |
| Google/Facebook sign-in | Supported social-login plugin or reviewed OAuth integration | Provider configuration and account-linking policy |
| Email verification | Not a complete core single-site feature by itself | Add a suitable plugin or small reviewed extension |
| Specialist data | Not a replacement | Keep Bubba Hub's custom listings, venues, planner, bookings and dashboards |

## Recommended order of work

1. **Freeze feature changes to authentication** while the migration design is agreed; do not disable the current login yet.
2. Inventory current accounts and roles; reconcile duplicate, unlinked, inactive and unverified accounts.
3. Define WordPress roles/capabilities: family, leader, volunteer and administrator, with least privilege.
4. Build and test the WordPress-to-legacy identity bridge on staging. Require exactly one mapping and matching verified email; never accept a user ID from the browser.
5. Convert one low-risk read-only API first, then one write endpoint with REST nonce, capability and ownership checks.
6. Migrate login, registration, verification, password reset and logout in stages. Preserve user IDs and planner/listing ownership.
7. Test anonymous, wrong-role, wrong-owner, inactive, expired-session, CSRF, duplicate-link and account-recovery cases.
8. Only after all endpoints are migrated and verified, retire the legacy password/session system and static admin login.

## Audit limitations

This review used source code on the repository's default branch. It did not execute the PHP, query production databases, inspect private server configuration, verify actual plugin versions, test live endpoints, run a full static-analysis tool, or perform penetration testing. Findings are code-review observations and should be validated on staging before changes are made.
