# Google sign-in release checklist

This checklist is for the native Bubba Hub authentication system in `bubbaplugin`. It does not use WordPress users or Simple Membership.

## 1. Google Cloud configuration (server owner)

- [ ] In Google Cloud Console, create or select an OAuth 2.0 Client ID of type **Web application**.
- [ ] Add `https://bubbahub.co.uk` to **Authorised JavaScript origins**.
- [ ] Add any exact callback/redirect URI only if the chosen Google Identity Services setup requires one. The current One Tap/button ID-token flow posts the credential to Bubba Hub and does not use a Google redirect callback.
- [ ] Copy the **Client ID** only. Do not put a client secret in browser code or commit it to Git.
- [ ] Add the client ID to the private server-side `api/config.php` file under `google.client_id`, e.g. `'google' => ['client_id' => 'YOUR-WEB-CLIENT-ID']`.
- [ ] Keep `api/config.php` private and outside version control. The existing `BH_GOOGLE_CLIENT_ID` constant in `wp-config.php` remains an override if already used.
- [ ] Confirm the Google OAuth consent screen has the correct Bubba Hub app name, support email, authorised domains and published/test-user status.

## 2. Pre-release code checks

- [ ] Review the draft PR against the latest `main`; confirm no unrelated changes are included.
- [ ] Run `php -l api/auth.php` and `php -l api/google-config.php` on a PHP 8-compatible runner/server.
- [ ] Run a JavaScript syntax check on `assets/js/auth-page.js` (e.g. `node --check assets/js/auth-page.js`).
- [ ] Confirm `api/config.php` is not committed and contains no credentials in Git history.
- [ ] Confirm the deployed `auth.html` loads `api/google-config.php` and Google Identity Services over HTTPS.

## 3. Functional tests (use test accounts first)

- [ ] Family: create a new account with Google; ensure terms are required and first/last names are saved where the schema supports them.
- [ ] Family: link an existing password account using the same verified Google email; ensure no duplicate account is created.
- [ ] Family: reject a Google email already used by a different role/context where the native role policy requires it.
- [ ] Leader: create a new account with organisation name and organiser terms; confirm one `bh_users` row, one linked `bh_social_accounts` row and one pending `bh_organisers` row.
- [ ] Leader: link an existing active leader account by verified email; confirm its verification timestamp is set.
- [ ] Leader: sign in again using the linked Google identity.
- [ ] Reject missing leader organisation name or terms without creating partial account/organiser rows.
- [ ] Reject invalid audience, issuer, expiry, issued-at, nonce and unverified-email tokens.
- [ ] Confirm nonce expires after five minutes and cannot be replayed.
- [ ] Confirm Volunteer/Team context cannot use Google.
- [ ] Confirm email/password Family and Leader sign-in, registration, verification and password reset still work.
- [ ] Test mobile modal, Family/Leader switching, Create account mode, password visibility and safe `next` redirects.

## 4. Live release (only after tests pass)

- [ ] Confirm a fresh live backup exists.
- [ ] Merge the reviewed PR to `main`.
- [ ] Deploy through the normal Bubba Hub deployment route.
- [ ] Add/verify the private Google client ID on the live server.
- [ ] Test one controlled Family login and one controlled Leader login on live.
- [ ] Check PHP/server logs and confirm no duplicate accounts or unexpected organiser records.
- [ ] If any critical issue appears, disable the Google client ID/configuration and roll back the code release.

## Important

The draft implementation is **not live**. Do not merge or deploy until the pre-release and functional checks have passed. Never share the Google client secret or database credentials in chat or GitHub.
