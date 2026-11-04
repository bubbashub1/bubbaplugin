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
