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


## Stage 3 — first draft editing workflow

The visual-builder screen now connects to the isolated draft API for a deliberately limited first editing path:

- The page selector uses published records from the existing Page Manager.
- An administrator explicitly prepares a structured conversion preview from the selected page; it is not automatic.
- The initial converter extracts up to 80 headings, paragraphs and eligible links from the page's main content. It reports how many supported blocks were prepared and warns that images, complex layout, original styling and unsupported elements are not carried into this first simplified draft.
- The draft canvas renders the structured document as escaped text and validated link destinations inside the sandboxed preview. It does not execute page scripts.
- Headings, paragraphs and button/link text and destinations can be edited in the draft inspector.
- Save sends the v1 document to the authenticated draft API. Previous draft snapshots are listed and can be restored after confirmation.
- The editor has no publish control and does not write to Page Manager `content_html` or the public renderer.

This is a controlled first editing slice, not yet a full Wix-style page editor. Do not use the conversion workflow for complex or visually sensitive pages until the user has reviewed its simplified preview. Staging browser QA must verify session behaviour, API setup, selection/edit/save/restore, desktop and mobile canvas, and unchanged public output before this stage is considered ready.


## Stage 3 — constrained section styling controls

The draft inspector now supports selecting a converted section and changing its background colour, inner padding (0–96px) and spacing between child blocks (0–64px). The controls write only allow-listed section properties already enforced by the v1 server validator. The draft iframe updates in place; saving still uses the isolated draft API and does not publish or change legacy page content. This is a first constrained layout control, not arbitrary CSS editing.


## Stage 3 — independent page containers

The Visual Builder treats each top-level section as an editable container. Administrators can add containers, add headings/text/buttons to the selected container, move containers up/down, and delete a container (a page must retain at least one). Each container has constrained settings for background, padding, inner gap, width percentage, minimum height, corner radius, border thickness/colour, horizontal alignment and spacing after. Values are stored in the structured draft and independently validated by the server. This remains draft-only; it does not change the public renderer or Page Manager content.


## Responsive container overrides — first mobile slice

The structured v1 draft model now permits an optional `mobile` property on a container. It supports constrained width, padding, inner gap, minimum height, corner radius, border thickness, horizontal alignment and space after. The editor exposes a separate toggle and controls; values are validated server-side and apply only inside the draft preview at 600px or narrower. Existing documents remain valid and inherit their desktop settings until the override is explicitly enabled. This does not affect public rendering, legacy Page Manager content or any live page. Tablet overrides and public renderer integration remain deferred.


## Stage 3 — duplicate containers

The editor now duplicates a selected draft container immediately after the original. The copy retains layout styling, optional mobile overrides and block content, but remaps the container and every child block ID to prevent collisions. The copy is reselected in the draft canvas. Container and block counts are capped at 40 sections and 80 children per section in the editor; the v1 server validator remains the final save-time authority. A small pure helper is covered by Node tests. Duplication remains private-draft only.


## Branch synchronization audit — 22 November 2026

The `staging-site` base now diverges from this feature branch. The current compare reports 136 commits ahead and 130 behind, with the common ancestor still `dd7dc6c2e9a5e5fc72e2f5573b547ca85d12ee8b`. GitHub's pull-request mergeability indicator has fluctuated after updates. No merge, force-update or automatic synchronization was attempted. Before staging deployment, reconcile this drift through a reviewed merge commit and re-run the full checks; avoid blindly rebasing or overwriting either branch.


## Stage 3 — local draft undo and redo

The Visual Builder now provides a bounded, in-memory history for unsaved structured-draft edits. Undo and redo cover text/link edits, container styling, mobile overrides, adding blocks or containers, moving, duplicating and deleting containers. The history is limited to the most recent 50 checkpoints; text and control edits are grouped from focus until the user moves to another control. The toolbar buttons expose the actions, and Ctrl/Cmd+Z, Ctrl/Cmd+Y and Ctrl/Cmd+Shift+Z are supported outside text-entry controls. History is reset when a page/draft is loaded or a save/restore completes. Undo and redo only change the private in-memory draft; an explicit Save draft request is still required, and no publish action exists. The pure history helper has Node regression coverage for no-op checkpoints, undo/redo, redo invalidation and the history limit.

## Branch synchronization audit — 23 November 2026

The latest comparison with `staging-site` reports 138 feature commits ahead and 156 base commits behind, diverged from common ancestor `dd7dc6c2e9a5e5fc72e2f5573b547ca85d12ee8b`. The current `staging-site` head is `c02d7191b8a9096316bbbf5747f259f7a2c210ca`; the PR still points at the older base SHA and GitHub currently reports `mergeable: false`. Several paths overlap between the two lines of work, including `admin.html`, `index.html` and `assets/css/styles.css`. No branch synchronization, rebase, merge, force-update or deployment was attempted. Continue isolated builder work on the feature branch, then reconcile the overlapping staging changes with a reviewed merge commit and full regression checks before any staging deployment.


## Stage 3 — direct inline text editing (24 November 2026)

The private draft canvas now supports direct text editing for the three existing v1 text-bearing block types: headings, paragraphs and buttons. A single click still selects an element for inspection; a double-click on a marked draft block enters inline editing. Enter commits the edit, Escape cancels it, and Shift+Enter inserts a line break in paragraphs. Pasted content is inserted as plain text. The editor stores only the element's text content, not user-supplied markup; button labels cannot be emptied. Client-side limits mirror the v1 block limits, while the server-side document validator remains authoritative at save time.

Inline editing is available only in the structured private draft preview. Published-page preview remains read-only. It does not add images, arbitrary rich text, HTML, CSS, scripts or public-renderer integration. The inspector remains available for text and link destinations, and the existing draft history captures the pre-edit document so Undo can reverse a committed inline edit.

## Branch synchronization status — 24 November 2026

The feature branch was reconciled with the current `staging-site` head on 23 November using a normal GitHub-generated merge commit. The verified compare from staging commit `c02d7191b8a9096316bbbf5747f259f7a2c210ca` to feature head `06118ad8db7f55fb215136774710e0ec00659447` was 142 commits ahead and 0 behind, with the merge base equal to the staging head. This supersedes the earlier 22–23 November drift notes above. No rebase, force update or PR merge was performed.
