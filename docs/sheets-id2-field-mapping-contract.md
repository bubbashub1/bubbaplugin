# ID 2 full field mapping — approval contract

This is an **inventory only**. It does not insert an activity, organiser, venue, image, session or sync trace. Run `bhAuditId2FullFieldMapping` from the Apps Script file.

## Proposed handling
- `bh_activities`: preserve supported activity fields only after checking each staging column type. Force `status=draft` regardless of the sheet status. Do not publish.
- `bh_organisers`: organisation name and contact fields are **candidate** values, not proof of identity. Never auto-link or create without duplicate matching and approval.
- `bh_venues`: venue name/address/location fields are **candidate** values, not proof of identity. Never auto-link or create without duplicate matching and approval.
- `county`: not present in the previously audited staging activity schema; requires an explicit region/county decision.
- `price_free`: staging activity schema does not have this exact field. Needs reviewed conversion to price/price_from or other supported field.
- `status`: always override with draft on import.
- `image_1`, `image_2`, `image_3`: staging image table was absent; requires storage strategy.
- `sessions`: blank for ID 2; do not invent.
- `tags`: stage has activity.tags, but no tags relationship tables; verify format before use.
- `category`: staging has activity.category plus category relationship tables; avoid duplicate categorisation.
- `featured`, boolean flags, age months, price, coordinates: verify normalization, bounds and null handling.
- `slug`: generated as `cornwall-libraries` and checked for collision; enforce recheck immediately before write.

## Gate
Backup reported downloaded by operator; verify file and restore procedure before writing. Public API and both direct activity pages were checked with existing draft ID 999999. Do not interpret this as permission to write. A follow-up server-side schema validation and manual mapping approval are required. Keep `BH_SHEETS_WRITE_ENABLED=0`; live site and MASTER SHEET remain unchanged.
