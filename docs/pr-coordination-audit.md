# PR coordination and gap review

## What was reviewed
GitHub search on 15 October 2026 returned 34 open PRs and 22 closed PRs. Most poster PRs #85–101 are **planning-only**, not deployable features. Open staging-release PRs #17, #32, #35 need reconciliation before unrelated work is merged.

## Source-of-truth rules
- Canonical event model: #65. Ticketing #66, organiser dashboard #67, recurrence #68, waitlists #69 and family integration #71 must extend it, not introduce new event identities.
- Canonical bookings and Stripe payment state: #66 and #77. Waitlists #69 only allocate against that model.
- Canonical venues: #76, consumed by business directory #86, accessibility #96, trails #90, guides #97 and rainy-day #85.
- Canonical communications: #79 and #98. Event notifications #68/#71, leader-first access #24 and ticket confirmations #66 use shared SMTP and consent rules.
- Canonical family preferences: #73 and #51, consumed by weekend #87, affordability #88, Planner #74 and newsletter #98.
- Canonical admin approval/claims: #80/#52, consumed by #64, #70, #86, #93 and #95.
- Canonical leader schedule: #75/#44, consumed by events #65 and holiday hub #89. Avoid duplicate schedule tables.

## Important overlap warnings
1. #64 exhibitor applications and #70 sponsor/exhibitor management: share business contacts and statuses, not two applicant records.
2. #66 paid tickets, #69 waitlists and #77 bookings: concurrency, webhook idempotency and seat capacity must have one owner.
3. #71 event newsletter integration and #98 personalised newsletter: shared preferences, unsubscribes and delivery queue.
4. #43 duplicate warnings and #44 listing foundations: preserve read-only duplicate checker and coordinate schema.
5. #56 auth audit and #57 auth recovery: audit is not a code fix; test changes against admin and leader sessions.
6. #32/#35 staging release PRs: reconcile outdated branch differences before merge, do not blindly merge into current main.
7. #84 master roadmap and poster PRs #85–101: planning documents overlap existing umbrella issues; do not count as shipped features.

## Potential missing features worth planning
- Data import reconciliation and provenance (65-sheet CSV merge, validation, duplicates, change history).
- Self-service data privacy and retention: family/leader export, account deletion, child profile minimisation, consent log.
- System observability: job queue, SMTP delivery monitoring, backup restore drills, error reporting and deployment health checks.
- Organiser support: onboarding walkthrough, help centre, notification inbox and support tickets.
- Content lifecycle: review dates, expired event cleanup, broken booking links, venue verification and seasonal archive.
- Event-day operations: staff check-in roles, scan fallback, emergency contact policy and accessibility check-in.
- PWA installability as a precursor to the Android app.

## Automated coordination limits
Workflow comments on PRs with likely related work based on a versioned ownership registry and changed file paths. It intentionally does not auto-edit other PR branches. Automatic branch-to-branch cherry-picking would be unsafe: code could conflict or silently duplicate database changes. Registry and workflow only activate after this PR is reviewed and merged into main. A stronger blocking CI gate can be added after registry quality is validated.

## Definition of done for every implementation PR
- Refer to the primary owning PR/issue in the description.
- Confirm shared tables/APIs are reused.
- Update registry when dependencies or ownership change.
- Add PHP lint, JS checks and staging validation; never treat a plan-only PR as production-ready.
- Require explicit review, migrations/rollback and approval before live release.
- Birthday Party Finder and party-space listings remain excluded.
