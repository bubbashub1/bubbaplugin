# Admin Team & Permissions — draft specification

## Goal
Main admin dashboard section for owner to assign granular per-user access to modules and actions.

## Modules
Events & Expo (exhibitors, sponsors, floor plan, raffle, tickets), Directory & Listings, Sheets Sync, Volunteer Team, Support & Messages, Payments & Finance, Site Settings, Users & Permissions.

## Permissions
None, View, Edit, Approve/Publish, Manage; use separate action flags where needed (e.g. event check-in vs refund, prize editing vs raffle drawing). Default deny; only owner/superadmin may manage permissions and sensitive financial actions. Support multiple roles and per-user overrides with explicit effective-permission display. Regional scope applies to volunteers.

## Admin UI
Search users, invite or disable users, assign role template (Events helper, Directory volunteer, Regional coordinator, Admin), set granular module permissions, review effective access and audit changes. Protect owner account from accidental lockout.

## Security
Enforce on every PHP API and admin page route, not just hidden navigation. Require authentication, CSRF protection, role and resource scope validation, audit logs, safe migrations, deny access to direct URLs, no privilege escalation. Keep existing admin authentication working and test compatibility with leader and family roles.

## Rollout and tests
Feature flag; staging first. Test events-only account against direct links/API requests for payments, listings, user management; verify revocation immediately blocks access; ensure owner retains full access. No live enablement before approval.
