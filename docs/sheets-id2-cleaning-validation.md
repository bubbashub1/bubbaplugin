# PR #223 — Cornwall Libraries ID 2 cleaning checks

Run `bhValidateId2Cleaning` in Apps Script. It reads only row 2 of MASTER SHEET and logs a compact report. It makes **no external requests**, no database changes, and no spreadsheet changes.

- HTML: detect potentially dangerous elements and event-handler attributes. Count generated markup attributes. Do **not** strip tags with regex or silently rewrite content; sanitise server-side using a reviewed HTML allowlist before future import. Preserve tables and meaningful formatting.
- Images: unwrap simple Markdown links to a URL candidate; check HTTP(S) syntax only. **Not** a remote existence, licensing or safety check.
- Booleans: interpret true/false, yes/no, 1/0, flag other values.
- Price and ages: flag free-price inconsistencies and invalid age ranges.
- Coordinates: flag missing pairs, out-of-range and nonnumeric values.
- Drop-in flag: warn if description contradicts source flag.
- Organiser and venue: report names only; no identity approval or auto-linking.
- Sessions: empty source sessions remain empty; timetable in description is not automatically a verified session schedule.
- Source `status` never authorises publication; force future draft.

Before any real staging write, confirm full mapping, actual organiser and venue identity, HTML sanitisation, image strategy, backup and rollback, and explicit approval. Keep `BH_SHEETS_WRITE_ENABLED=0`.
