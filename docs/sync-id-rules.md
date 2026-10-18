# Bidirectional activity IDs

- `MASTER SHEET` is the editable sheet; `id` is the permanent `bh_activities.id`.
- A sheet row with a specified unused positive integer ID creates a website record with exactly that ID after validation and approval; collisions are blocked.
- A sheet row with no ID gets a database-generated ID, written back to the sheet only after the database commit.
- A website listing missing from the sheet is appended with its existing ID, with no duplicate append on retries.
- Never match records by title or row number; use immutable ID and persisted mapping.
- No deletes from missing rows; conflicting concurrent changes are held for review.
- Explicit-ID inserts must be transactional, with unique constraint and retry-safe processing; avoid guessing MAX(id)+1.
- Start with one staging test listing and a separate copy of the sheet. Do not enable live writes or schedules until tested and approved.
