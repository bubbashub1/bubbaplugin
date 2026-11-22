# Bubba Hub Leader account and Directory ownership standard
> **Historical design reference — not current staging status.** This document records an earlier Directory design. For the installed tables, current admin tools, schema-upgrade step, public API wiring and verified status, use [`DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md`](DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md). Do not assume every workflow or schema described here is already enabled on staging.

## Product language
- Use **Leader** as the umbrella account type for people and organisations providing family-facing activities, events, services, childcare, places or clubs.
- Use **Leader Hub** for the authenticated workspace and **Leader** in navigation and user-facing account labels.
- Avoid using **Class Leader** as the generic account label. It remains appropriate when referring specifically to a person running a class or activity.
- Family accounts remain a separate account type and experience.

## One account, one organisation identity
Every Leader-facing Directory feature must use the existing authenticated Leader account and its linked organiser/organisation profile. Do not create separate logins or duplicate organiser records for Events, Businesses, Childcare & Schools, Places & Days Out or School Clubs.

The relationship is:
1. Authenticated bh_users Leader account (role='leader').
2. Linked bh_organisers organisation profile (user_id is the preferred durable link).
3. One or more owned Directory records, with their own section-specific data and permissions.

Resolve ownership on the server from the authenticated session and the linked organiser profile. Never trust a submitted user ID, organiser ID, owner email or record ID as proof of ownership. Email matching may be used only as a controlled legacy account-linking/reconciliation path, not as the normal ownership mechanism.

## Permissions and section access
- A Leader can manage only records owned by their linked organisation, subject to record-specific permissions.
- The same Leader account may have access to multiple Directory sections; do not assume every Leader is a class provider.
- Show section navigation and create/manage actions only when the account has the relevant capability or an approved record in that section.
- Keep the existing Listings, planner, booking and class-management workflows working.
- Events may link to Listings owned by the same organiser; linking must not duplicate the Listing.
- Admin moderation and audit history remain separate from Leader ownership.

## Build checklist (mandatory for every new section)
- [ ] Uses the existing Leader sign-in/session and role='leader' account.
- [ ] Loads the linked organiser profile from the authenticated user ID.
- [ ] Creates records against that organiser ID server-side.
- [ ] Checks ownership on every read, edit, delete, submit and relationship operation.
- [ ] Does not create a second login or duplicate organiser profile.
- [ ] Includes the appropriate section capability and admin approval workflow.
- [ ] Has a clear route back to the Leader Hub.
- [ ] Family account access remains separate and unaffected.
- [ ] Tests cover a Leader with more than one Directory section and attempts to access another organisation's records.

## Naming rollout
The current role value and database structure are not being renamed in this UI terminology change. Keep the existing leader role and organiser links stable. Any later account/role migration must be separately audited and tested before changing stored role values or authentication logic.
