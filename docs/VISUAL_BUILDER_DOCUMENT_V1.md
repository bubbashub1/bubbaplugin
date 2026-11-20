# Visual Builder document contract — version 1

This is the first structured-content contract for the native Wix-style builder.

- Machine-readable schema: `schemas/page-document-v1.schema.json`
- Schema dialect: JSON Schema 2020-12
- Version: `schema_version: 1`
- Initial blocks: section/container, heading, paragraph and button
- Layout controls are deliberately constrained to approved values; no arbitrary HTML, JavaScript, event handlers, CSS declarations or unknown properties are accepted.
- Container nesting is intentionally flat in this stage: each page-level container holds heading, paragraph or button blocks. Containers can be added, reordered and deleted in the private draft. Each has independent background, padding, inner gap, width, minimum height, corner radius, border colour/thickness, horizontal alignment and spacing after.
- Page documents are data, not rendered HTML. The public renderer must escape text and validate link destinations before output.

## Persistence boundary

The schema is a contract only. It does not add a database column, change Page Manager records, or enable editing. Do not save documents or expose edit/publish controls until the server-side validator, draft persistence, revision snapshots, restore path and draft preview have all been implemented and tested.

Legacy pages with `content_html` must continue to render unchanged. A page becomes builder-managed only through an explicit, reviewed conversion or creation flow; never silently convert existing pages.

## Server-side validation requirements

The server must independently enforce the schema and also check:

1. The authenticated administrator has permission to edit the requested page.
2. The request carries the existing Page Manager CSRF token.
3. `schema_version` is supported; unknown versions are rejected.
4. All node IDs are unique across the complete document.
5. Total document size, section count, child count and text lengths remain within limits.
6. Every link destination is parsed and restricted to the approved protocols and same-origin relative paths; protocol-relative URLs and control characters are rejected.
7. The submitted page ID, slug and ownership are checked against the database; clients cannot select a different record by changing payload fields.
8. Draft saves never alter published output. Publishing is a separate explicit action and is atomic.
9. A complete prior document snapshot is written to revision history before every successful update.
10. Invalid documents return a useful validation error and make no partial database changes.

## Acceptance tests before enabling editing

- Accept a valid document containing each of the four v1 block types.
- Reject missing or unsupported schema versions.
- Reject unknown component types, unknown properties and arbitrary HTML/script fields.
- Reject duplicate or malformed IDs.
- Reject invalid heading levels, excessive text, excessive counts and out-of-range spacing.
- Reject `javascript:`, `data:`, protocol-relative URLs, control characters and malformed links.
- Confirm saving a draft does not change the public page.
- Confirm draft preview displays only the saved draft for the authorised admin session.
- Confirm each update creates a complete revision and restore returns the exact previous document.
- Confirm legacy `content_html` pages and existing Page Manager routes continue to work.

## Scope deliberately deferred

Images/media uploads, columns, drag-and-drop, per-device overrides, templates, navigation editing and Bubba Hub data-backed blocks are not part of schema v1. They require their own reviewed component definitions and validation rules.
