# Expo floor plan — safe release checklist

This feature remains **private by default**. Do not merge/deploy until staging checks pass.

## Staging migration
1. Back up the staging database.
2. Apply `docs/sql/expo-floor-plan-publication.sql` to **staging only**.
3. Confirm `bh_expo_floor_plans` has `event_key,layout_json,revision`, and `bh_expo_exhibitors` has `event_key,preferred_table,application_status`. Verify InnoDB engines.
4. Confirm `SELECT * FROM bh_expo_floor_plan_publication` is empty, or the event's `published` value is 0.
5. PHP lint `api/expo-floor-plan-admin.php` and `api/expo-floor-plan-public.php`.

## Access and publication tests
- Anonymous GET `/api/expo-floor-plan-admin.php` → 401; anonymous POST → 401.
- Anonymous GET `/api/expo-floor-plan-public.php` before publish → 404 (no geometry).
- Signed-in admin editor shows **Public plan: hidden** and **Publish floor plan**.
- Save a valid layout, click Publish, confirm public API returns **only table geometry** (no names, contact information, exhibitor assignments).
- Click Unpublish; anonymous public API returns 404 again.
- Ensure no public Events directory or Expo page embeds this API until a separate explicit release.

## Booking and edit safety
- Submit exhibitor application selecting table A; confirm pending application stores table A.
- Submit another application for table A; expect 409 and no second booking.
- Admin attempts to delete/renumber A; expect 409 `reserved_table_identity_locked`.
- Admin moves A without changing its ID or label; save should succeed.
- Test two simultaneous submissions for A, and simultaneous admin save versus application.
- Confirm rejected/cancelled statuses free table A and that the UI refreshes.
- Confirm the admin editor's draft `exhibitor` text does **not** expose personal details through the public endpoint.

## Production
After staging sign-off, back up production, apply the migration with `published=0`, deploy the reviewed code, verify private default, and publish **only** when Bubba Hub explicitly approves release. A Publish button does not automatically add the plan to the Events directory.
