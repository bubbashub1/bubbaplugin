# Free & Under £5 Finder

**Status:** Planning-only draft PR. No executable feature delivered.

## Product scope
Inclusive affordability filter with per-person/per-family units, free activities, price confidence and updated dates; reuse directory price fields.

## Related roadmap
#25

## Required implementation breakdown
1. Inspect existing repository routes, PHP APIs, WordPress integrations, bh_* schema and design components before adding new code.
2. Document user journeys for families, leaders and admins; specify empty, error and mobile states.
3. Define canonical entities, API contracts, ownership, permissions, and Free/Pro access; avoid duplicate records and breaking changes.
4. Specify input validation, rate limits, consent, privacy, accessibility, logging and moderation where applicable.
5. Define database migration and rollback steps with no automatic live schema modifications.
6. Add staging acceptance tests: happy path, permissions, invalid input, duplicates, mobile usability, regressions and edge cases.
7. Require explicit approval before merging or deploying live.

## Definition of ready for coding
- Dependencies identified and canonical existing sources verified.
- Mocked UI and API contract agreed.
- Staging test data and expected outcomes documented.
- Work split into independently testable code commits if the scope is large.

## Exclusions
Birthday Party Finder, party-space listings and party-venue discovery are explicitly excluded from the Bubba Hub poster roadmap.
