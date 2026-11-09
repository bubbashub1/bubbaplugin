# Google sign-in release checklist

This checklist is for the native Bubba Hub authentication system in `bubbaplugin`. It does not use WordPress users or Simple Membership.

## 1. Google Cloud configuration

- [ ] Create or select an OAuth 2.0 Client ID of type **Web application** in Google Cloud Console.
- [ ] Add `https://bubbahub.co.uk` to **Authorised JavaScript origins**.
- [ ] The current Google Identity Services button/ID-token flow posts credentials to Bubba Hub; it does not require a Google redirect callback URI.
- [ ] Copy the **Client ID** only. Never put a client secret in browser code or commit it to Git.
- [ ] Add the client ID to the private server-side `api/config.php` under `google.client_id`, e.g. `'google' => ['client_id' => 'YOUR-WEB-CLIENT-ID']`.
- [ ] Keep `api/config.php` private and outside version control. The existing `BH_GOOGLE_CLIENT_ID` constant in `wp-config.php` remains an override.
- [ ] Check the Google OAuth consent screen app name, support email, authorised domains and test-user/publishing status.

## 2. Pre-release code checks

- [ ] Review this draft PR against the latest `main`; confirm only the intended auth files and this checklist are changed.
- [ ] Run `php -l api/auth.php` and `php -l api/google-config.php` on a PHP 8-compatible runner/server.
- [ ] Run `node --check assets/js/auth-page.js`.
- [ ] Confirm `api/config.php` is not committed and no credentials are in Git.
- [ ] Confirm `auth.html` loads `api/google-config.php` and Google Identity Services over HTTPS.

## 3. Functional tests (use test accounts first)

- [ ] Family: create a new Google account; confirm terms are required and Google first/last names are saved where schema columns exist.
- [ ] Family: link an existing password account using the same verified Google email; confirm no duplicate account is created.
- [ ] Leader: create a new Google account with organisation name and organiser terms; confirm one `bh_users` row, one `bh_social_accounts` row and one pending `bh_organisers` row.
- [ ] Leader: link an existing active leader account by verified email; confirm email verification is recorded.
- [ ] Leader: sign in again with the linked Google identity.
- [ ] Missing leader organisation name or terms are rejected without partial account/organiser records.
- [ ] Invalid audience, issuer, expiry, issued-at, nonce and unverified-email tokens are rejected.
- [ ] Nonce expires after five minutes and cannot be replayed.
- [ ] Volunteer/Team context cannot use Google.
- [ ] Existing Facebook social sign-in remains unchanged.
- [ ] Email/password Family and Leader sign-in, registration, email verification and password reset still work.
- [ ] Test mobile modal, Family/Leader switching, Create account mode, password visibility and safe `next` redirects.

## 4. Live release (only after tests pass)

- [ ] Confirm a fresh live backup exists.
- [ ] Merge the reviewed PR to `main`.
- [ ] Deploy through the normal Bubba Hub deployment route.
- [ ] Add/verify the private Google client ID on the live server.
- [ ] Test one controlled Family login and one controlled Leader login on live.
- [ ] Check server logs and confirm no duplicate accounts or unexpected organiser records.
- [ ] If a critical issue appears, remove/disable the Google client ID configuration and roll back the code release.

## Important

This implementation is **not live**. Do not merge or deploy until pre-release and functional checks have passed. Never share the Google client secret or database credentials in chat or GitHub.
