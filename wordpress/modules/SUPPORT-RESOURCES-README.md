# Bubba Hub Support & Guidance — staging integration

## Files
- `wordpress/modules/support-resources.php`: registers family resource post type, support topic taxonomy, shortcode and styles.
- `wp-content/mu-plugins/bubbahub-support-resources-loader.php`: optional MU loader. Loads module only if found at a known path.

## Deploy
1. Deploy both files, preserving the paths shown above.
2. Check WordPress admin for the missing-module warning. If present, correct the loader path before continuing.
3. In WordPress, create a page named **Family Resources** and add `[bh_support_resources]`.
4. Add a draft Family Resource with title, excerpt, image and topic. Publish a test resource.
5. In Settings > Permalinks, save the existing permalink settings once if the new single-resource route returns 404.
6. Test the resource page on desktop/mobile, keyword search, topic selection, pagination, single resource links, and REST access.
7. Check logged-out users see only published content.
8. Verify existing support questions, My Messages, Leader Pro blogs and login still work.

## Scope
This initial module uses native WordPress post editing and publishing permissions. It does **not** migrate old HTML guidance content, change private messages, or expose leader submission rights. The existing Leader Pro blog API uses a separate legacy session system and requires an authentication/security migration before integration.

## Rollback
Remove the MU loader to disable registration and shortcode. Existing WordPress resource records remain in the database; do not delete them. Restore the loader when ready.

## Known limitations
- The module depends on the deployment layout matching one of the loader's paths.
- Theme templates for archive and single resource pages are not yet customized.
- No migration of existing static support pages is included.
- The draft PR requires staging checks before merge.

## Support & Guidance homepage
Create a WordPress page containing `[bh_support_hub]`. This renders both the resources and articles libraries plus a website-help link. The website-help destination must be verified against the final WordPress routes.

## Articles and moderation
- `[bh_support_articles]` displays published WordPress posts assigned a Support Topic.
- The existing Leader Pro endpoint submits WordPress posts with `pending` status and the `_bh_leader_organiser_id` metadata.
- The new **Posts > Support Article Review** admin submenu lists pending posts with that metadata.
- Approve publishes a post; Return to draft makes it a draft. The administrator should assign a Support Topic before approval for it to appear in the public article library.
- The existing leader endpoint still relies on legacy session authentication. This PR does not claim to replace it with FluentAuth.

## Extra staging checks
- Confirm review menu is available only to authorised editors/admins.
- Confirm nonces and post-level permissions prevent unauthorised review.
- Confirm an article without a Support Topic does not appear in the article library.
- Confirm a reviewed article appears only after it is published and assigned a Support Topic.
- Confirm the combined homepage renders correctly and its website-help link resolves.

## Fluent Forms Free integration
The optional module `support-fluentforms-inbox.php` adds **Tools > Bubba Hub Enquiries** for WordPress administrators. Enter existing Fluent Forms IDs as a comma-separated list. The view queries only the submission ID, form ID, status and date from the plugin's own table. It does not copy or expose submitted answers in the overview, nor does it modify Fluent Forms entries.

Staging checks: verify the installed Fluent Forms version defines `FLUENTFORM` and uses the expected submissions table and column names; confirm only administrators can access the page; test with an enquiry form containing dummy (non-sensitive) information. The Fluent Forms status column is native submission status, not a custom support workflow. Do not advertise New/In Progress/Resolved or replies until implemented and tested.

## Enquiry workflow status (additional module update)
The admin-only enquiry overview now supports **New**, **In Progress**, and **Resolved**. A small `wp_prefix_bh_support_enquiry_status` table (actual prefix from `$wpdb->prefix`) stores entry ID, form ID, status, last update time and administrator ID. It does not store answers, names, emails or messages. The schema is created on the first authorised admin request. Updates use an admin nonce and verify that the entry belongs to a configured support form. The workflow does not send notifications or reply to users.

Staging checks: test fresh installation, status changes, cross-form entry rejection, non-admin access, plugin deactivation, and whether the status table is successfully created with the site's actual WordPress prefix. This implementation is not yet tested against the installed Fluent Forms version.

## Unified WordPress admin dashboard
The optional `support-admin-dashboard.php` module registers **BH Support** in the WordPress admin menu for administrators. It shows published resource count, pending leader article count, configured Fluent Forms support enquiry count, and workflow status totals. Quick links lead to the relevant WordPress and Fluent Forms screens. No private form answers are rendered.

Staging validation: verify that the dashboard counts match the source screens, the Fluent Forms unavailable/empty states render correctly, links resolve, and the admin menu is hidden from users without `manage_options`. Review layout on mobile. Do not merge until plugin load paths and PHP syntax are validated.

## Integration hardening
- The shared resource stylesheet is loaded for any singular page containing `[bh_support_resources]`, `[bh_support_articles]` or `[bh_support_hub]`.
- The dashboard treats entries without a workflow row (or explicitly marked New) as New, avoiding double-counting.
- The MU loader checks additional plugin layouts and supports the `bh_support_resources_module_paths` filter, which accepts an array of absolute paths to `support-resources.php`. Its sibling modules must be deployed in the same directory.

**Not yet validated:** actual staging deployment location, PHP syntax/lint, Fluent Forms schema compatibility, shortcode routes, and permission tests. Do not merge solely on these code commits.

## Consolidated admin navigation
- **BH Support** is the top-level dashboard.
- **BH Support > Bubba Hub Enquiries** provides Fluent Forms configuration and workflow statuses.
- **BH Support > Support Article Review** provides the pending leader article moderation queue.
- Dashboard buttons link to these submenu slugs using `admin.php?page=...`.

Before staging rollout, verify the admin menu loads without PHP errors, its links resolve, and all three pages deny unauthorised access. The change is committed to the feature branch only.

## Approval and schema safeguards
- Leader articles cannot be approved from the review queue unless a Support Topic is assigned. The queue displays assigned topics and disables Approve for unclassified posts. Server-side approval also enforces the rule.
- Enquiry workflow updates require the installed status schema version marker. If installation failed, an update cannot silently create an invalid workflow record.
- Staging tests: attempt approval with no topic (must fail), assign topic and approve (must succeed), attempt an unauthorised approval (must fail), and simulate a missing workflow table (must not update a status).

## Enquiry queue filters
The admin inbox now offers All, New, In Progress and Resolved filters. Filtering is applied at the database query level and pagination retains the selected workflow. An entry with no workflow record counts as New. If the workflow table is unavailable, the filter is reset to All to avoid invalid queries. No private response data is included.

Staging checks: create dummy entries for each state; compare filtered counts and pagination against the dashboard; confirm form IDs are restricted to the configured forms. Verify PHP syntax and SQL compatibility before deploying.

## Enquiry activity history
Schema version 2 adds `{$wpdb->prefix}bh_support_enquiry_history` to store status transitions (entry ID, form ID, old/new status, UTC timestamp, staff WordPress user ID). No names, email addresses, questions or submitted responses are copied. The admin-only inbox links enquiry numbers to a recent status-change history, limited to 50 entries per enquiry. Only entries belonging to configured support forms are eligible.

Staging checks: upgrade from schema v1, confirm both tables exist before updating statuses, verify history captures actual changes but not no-op saves, and confirm unauthorised staff cannot view the history. Existing status changes from before this update cannot be reconstructed. If history insert fails, status may already have changed; audit this before production deployment.
