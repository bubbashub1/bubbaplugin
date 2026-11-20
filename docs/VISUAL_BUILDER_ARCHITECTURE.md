# Bubba Hub Visual Website Builder — Architecture and Stage Plan

## Product decision

Build a native, Wix-style visual website builder inside the existing standalone PHP/JavaScript Bubba Hub application. Do not embed Wix, add WordPress/Gutenberg, or create a second page-management system.

The builder should progressively support visual selection and editing; adding, removing, duplicating and reordering supported sections and blocks; editable text, images, buttons, links, backgrounds, spacing and typography; desktop, tablet and mobile preview; page creation, templates, navigation, drafts, revisions and publishing; and Bubba Hub data-backed blocks using existing APIs.

## Existing systems to integrate

- admin/admin-site-manager.html and api/admin-site-settings.php already provide global design-token drafts and publishing, plus homepage visibility/order.
- admin/admin-page-manager.html, assets/js/admin-page-manager.js, api/admin-pages.php and page.php already manage ordinary content pages, hierarchy, draft/published status, preview, archive/restore and revisions.
- index.html with assets/js/explore.js is the real standalone homepage. Six optional homepage sections have stable data-bh-home-section keys. The hero/search and footer are protected.
- Bubba Hub is not a WordPress-rendered site. Keep the builder native to this app.

Do not duplicate the Page Manager or replace the existing homepage with a new generic page. Evolve these systems around one shared, versioned page-layout model.

## Core architecture

### 1. Component registry

Create a server/client shared allow-list of supported block types. Initial generic blocks: section/container; heading; rich text with restricted safe formatting; image; button/link; spacer/divider; two-column and three-column layout.

Later data-backed blocks: activity/category cards; upcoming activities; venue and regional explorers; events; family and class-leader calls to action.

Each component definition must specify editable properties, defaults, allowed children, responsive properties and renderer. Unknown component types or properties must be rejected or safely ignored. Never allow arbitrary JavaScript, event handlers, script tags or unrestricted CSS from editor content.

### 2. Page document format

Store a versioned JSON document for builder-managed layouts. Proposed shape:

```json
{
  "schema_version": 1,
  "page": { "title": "Example", "slug": "example" },
  "settings": { "max_width": "1200px", "background": "#ffffff" },
  "breakpoints": { "desktop": {}, "tablet": {}, "mobile": {} },
  "sections": [
    {
      "id": "stable-unique-id",
      "type": "section",
      "props": { "background": "#f4f8f7", "padding": 24 },
      "children": [
        { "id": "stable-unique-id", "type": "heading", "props": { "text": "Welcome", "level": 1 } }
      ]
    }
  ]
}
```

This is an illustrative contract, not a live migration. Validate schema version, node count, nesting depth, IDs, component types and every property server-side. Keep content values separate from executable markup.

### 3. Persistence and publishing

Extend the existing Page Manager data model only after a reviewed migration: add nullable builder document JSON to page records; preserve existing content_html pages and render them unchanged; snapshot the complete page document into revision history before every update; keep draft and published state distinct; publish atomically only after server-side validation; provide restore from a prior revision.

For the homepage, use an explicit adapter over the six existing section keys first. Do not attempt to convert the entire homepage DOM into arbitrary editable blocks in one operation. Protect the hero/search, shared header, footer, authentication, directory data and booking workflows.

### 4. Editor experience

The visual editor should have a top toolbar for page selection, save state, undo/redo, device preview and publish; a left panel for Add elements, Sections, Layers, Pages and Media; a centre canvas with clear selection outlines; and a right inspector for content, layout, style and responsive properties.

The canvas should edit a draft document, never mutate the published page. Selection and drag operations must be keyboard accessible; provide move up/down controls as a fallback to drag-and-drop.

### 5. Rendering and responsive behavior

Use one safe renderer for both editor preview and public output, with an editor-only selection layer. Reuse existing site CSS tokens and shared components. Define desktop/tablet/mobile breakpoints and inherit values unless a device override is explicitly set. Do not use arbitrary inline CSS as a substitute for a component design system.

### 6. Media

Integrate with existing upload/media handling only after permissions, storage paths, validation and image processing are audited. Initial image blocks may select existing approved Bubba Hub media. Validate MIME type, file size and image dimensions server-side before enabling uploads.

## Delivery sequence

### Stage 0 — prerequisite audit and recovery
- Confirm current feature branch and PR state.
- Resolve and verify the Site Manager API connection issue on staging; identify whether failure is database connection or table check.
- Complete Page Manager API, preview, revision and routing audit.
- Confirm admin authorization, CSRF, database conventions, CSS loading and current staging deployment.
- Do not merge or deploy live.

### Stage 1 — visual builder shell and read-only canvas
- Add a dedicated Visual Builder entry point from existing Website & Content admin navigation.
- Reuse Page Manager page records and page hierarchy; no duplicate page list.
- Provide page selection and a desktop/tablet/mobile canvas.
- Render an existing managed page in a sandboxed same-origin preview or a safe renderer.
- Add a layers tree and component selection for supported, explicitly registered elements. The first prototype now lists page headings, paragraphs, links, buttons, images and section/article elements; choosing a layer selects the corresponding item in the preview.
- At this stage, selection is read-only; do not offer save/publish for unsupported page content.
- Test mobile usability, admin session behavior and isolation from live/published content.

### Stage 2 — safe text and link editing
- Use the v1 data contract in `schemas/page-document-v1.schema.json` (documented in `docs/VISUAL_BUILDER_DOCUMENT_V1.md`).
- Enable heading, paragraph and button text/link properties only after server-side validation independently enforces the contract, including unique IDs, size limits, safe links and allowed properties.
- Validate and save a draft document without changing the published page.
- Preview draft without publishing; verify authorisation and CSRF protection.
- Snapshot the complete prior document before each update and verify revision restore.

### Stage 3 — sections and layout editing
- Add supported sections and blocks.
- Reorder, duplicate and delete with undo/redo.
- Add constrained columns and spacing controls.
- Preserve protected system components.

### Stage 4 — images and media library
- Select existing media; then implement secure upload if appropriate.
- Add alt text, crop/fit and focal point controls.

### Stage 5 — responsive editing and design controls
- Add per-breakpoint overrides and preview.
- Connect components to global Site Manager design tokens.
- Ensure no horizontal overflow and no destructive layout shifts.

### Stage 6 — page templates, navigation and data blocks
- Save reusable templates.
- Add navigation management.
- Add Bubba Hub data-backed blocks using existing APIs, never copied directory data.

### Stage 7 — production hardening
- Revision history and restore UI.
- Permissions, CSRF, validation and output escaping tests.
- Accessibility and keyboard testing.
- Staging regression suite for homepage, family/leader login, bookings, search, directory and page routing.
- Live release only after explicit user approval.

## Non-negotiable safety rules

- Staging first; never merge or deploy live without explicit approval.
- No WordPress dependency or paid builder dependency.
- No arbitrary HTML/JS editing in the visual canvas.
- Never delete or rewrite protected system functionality.
- Draft previews must not affect public output.
- Preserve old content_html pages and existing routes.
- Every persisted change must be validated and revisioned.
- A passing syntax workflow is not evidence of successful browser/staging behavior.

## First acceptance milestone

The first prototype now provides the editor shell, published managed-page selector, responsive-width preview, clickable element selection, a layers list, and a read-only properties inspector. It has not yet passed staging browser QA. The next milestone should add a validated structured page-document model and safe text/link editing against drafts, while preserving existing legacy HTML pages and revision history.

## Stage 2 backend progress — draft persistence boundary

The existing `api/admin-visual-page-drafts.php` endpoint now provides the first isolated persistence path for structured visual-builder drafts:

- It uses the existing administrator session guard and a session CSRF token.
- Its one-time setup creates only the dedicated `bh_visual_page_documents` and `bh_visual_page_document_revisions` tables, linked to existing Page Manager records. It does not alter `bh_pages`, `bh_page_revisions`, or legacy `content_html`.
- Draft saves validate the v1 document server-side, lock the Page Manager record, reject archived pages, and take page title/slug identity from the database rather than trusting the canvas payload.
- Each replacement of an existing builder draft snapshots the prior JSON document in the builder revision table in the same transaction.
- The API returns the current draft and the latest 30 revision identifiers/timestamps.
- A revision can be restored only to the same page; the current draft is snapshotted before the restore.
- There is deliberately no publish action and no public-renderer integration in this stage. Saving or restoring a builder draft cannot change what visitors see.
- Legacy Page Manager pages are not silently converted. A later editor step must provide an explicit conversion/initialisation preview before any existing page is treated as builder-managed.

The API is backend groundwork only. The current visual-builder screen remains read-only until the editor UI, safe draft preview renderer, and staging tests are connected. A successful PHP syntax workflow alone is not staging sign-off.
