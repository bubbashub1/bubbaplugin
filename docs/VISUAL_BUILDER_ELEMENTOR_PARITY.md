# Visual Builder — Elementor reference audit and test-build parity

Audit target: `staging/visual-builder-columns-20261121`

Reference: the public Elementor repository. This is an architectural comparison, not a source-code port. The Bubba Hub builder remains a standalone PHP/JavaScript application and does not load Elementor or WordPress.

## Elementor architecture used as the reference

| Concern | Elementor reference pattern | Bubba Hub implementation |
|---|---|---|
| Widget registration | `Widgets_Manager` registers widget classes and exposes widget configuration | `api/admin-visual-widgets.php` seeds and returns the database-backed Widget Library; `assets/js/admin-visual-builder.js` builds the element palette |
| Control schema | `Controls_Manager` registers control types; `Controls_Stack` provides per-element settings | Inspector fields are currently hand-authored DOM controls, with generic settings generated from stored widget settings |
| Element/document model | Element instances carry type, ID, settings and child elements | Versioned JSON page document with sections and flat child blocks |
| Layout | Current Elementor uses nested Flexbox containers; legacy sections/columns remain relevant to older documents | Section/container CSS Grid with 1–4 tracks, editable track widths and per-block column placement |
| Responsive behavior | Device breakpoints and per-device controls/settings | Desktop/tablet/mobile canvas widths; mobile section overrides at <=600px; mobile stacks to one column |
| Drag and drop | Editor element model manages insertion and ordering | Native HTML drag/drop from palette, canvas and Layers; move-up/down controls are also present |
| Selection/state | Editor tracks active document and active element | Current page, draft, selected section/block and preview mode held in the builder JS; bounded undo/redo snapshots |
| Rendering | Element render methods and document/frontend rendering pipeline | Safe data-to-markup renderer in the editor iframe; public `page.php` still renders legacy `content_html` only |
| Persistence | Document save/load and revision/history lifecycle | `api/admin-visual-page-drafts.php` stores validated draft JSON separately and snapshots prior saved drafts |
| Media | Media controls and attachment management | Dedicated admin media endpoint with MIME/image checks, size/dimension limits and library browsing |

Useful Elementor source files:
- `includes/managers/widgets.php` — widget manager and registration hooks.
- `includes/managers/controls.php` — control types and registration.
- `includes/base/controls-stack.php` — control stacks and element settings.
- `includes/base/widget-base.php` and `includes/base/element-base.php` — element/widget lifecycle and rendering.
- `includes/elements/container.php` — current container element.
- `includes/widgets/heading.php`, `image.php`, `text-editor.php`, `button.php`, `divider.php`, `spacer.php` — core widget examples.
- `assets/dev/js/editor` — editor document, component and interaction code.

## Bubba Hub capability inventory

### Already present on the target branch

- Admin-only full-screen builder workspace with page selector, canvas, inspector and Layers panel.
- Published-page preview and explicit conversion into a separate structured draft.
- Version 1 JSON document and pure server-side validator.
- Draft storage isolated from Page Manager `content_html`; setup is an explicit one-time action.
- Transactional draft replacement and revision snapshots; revision restore.
- Section/container creation, presets, duplicate, delete and reorder.
- Container background, palette/gradient, image background, overlay, padding, gap, width, minimum height, border, radius and alignment controls.
- 1–4 container columns and editable column width ratios.
- Heading, paragraph, button, image and Bubba Hub directory-search blocks.
- Database-backed widget library including rich text, divider, spacer, icon, image/caption, form-field previews, accordion, tabs, notice, columns, feature box and reusable-section placeholder.
- Drag/drop from element palette and between canvas/Layers blocks.
- Direct text editing, duplicate/delete, move controls and undo/redo.
- Desktop/tablet/mobile preview sizing and constrained mobile container overrides.
- Admin-only media library and image upload endpoint.

### Added in this test-build commit

- Explicit per-element **Place in column** control in the inspector.
- Each supported block now receives a validated 1-based column placement within its parent container.
- The preview renderer assigns blocks to their chosen grid track, allowing several widgets to stack in the same column.
- Changing a container's column count refreshes the selected block's column options and clamps existing placements.
- Server validator rejects non-integer, zero, or out-of-range block column assignments.
- Regression tests cover valid placement and invalid column indices.

## Feature parity checklist

| Capability | Status | Notes |
|---|---|---|
| Widget registry / palette | Partial | Database-backed registry exists; not equivalent to Elementor's class-based PHP widget lifecycle |
| Declarative control schema | Partial | Generic settings are generated for library widgets; core inspector controls are hand-coded |
| Section/container layout | Partial | CSS Grid-based sections, not Elementor's full Flexbox container model |
| Multiple columns and custom ratios | Implemented for test | 1–4 tracks; blocks can be assigned to individual tracks |
| Nested containers / inner sections | Missing | Document schema is intentionally flat; no arbitrary nested container tree |
| Core text/image/button blocks | Implemented for test | Safe restricted data model and renderer |
| Divider/spacer/icon/notice | Preview implementation | Library blocks render a visual representation |
| Accordion/tabs | Preview implementation | Editable item content; preview interactions are local to the iframe |
| Forms and submission | Missing | Form controls are display previews only; no form endpoint, validation workflow or submission storage |
| Reusable sections | Placeholder | No shared reusable content entity or synchronisation |
| Drag/drop | Partial | Palette/canvas/Layers drag-drop and reorder are present; no pointer-based resize handles |
| Undo/redo | Implemented for test | Client-side bounded snapshots; not a collaborative or server-persisted history |
| Responsive settings | Partial | Device preview plus mobile overrides; no independent tablet property overrides or general breakpoint system |
| Widget-specific frontend lifecycle | Missing | No class hooks equivalent to Elementor render/enqueue/frontend handlers |
| Draft save/load/revisions | Implemented for test | Separate structured draft tables; first save has no prior document to snapshot |
| Publish structured page | Missing | Draft save deliberately does not publish or alter the public page |
| Public structured renderer | Missing | `page.php` remains on legacy HTML rendering until a separately reviewed renderer/publish path is built |
| Global design tokens | Partial | Builder uses Bubba Hub palette values; not yet fully connected to Site Manager tokens |
| Dynamic/data widgets | Partial | Directory search is represented in editor preview; production rendering and runtime binding need separate integration |

## Architectural mismatches and test shortcuts

1. **Not a WordPress plugin runtime.** Bubba Hub is a standalone PHP/JS app. Elementor hooks, PHP widget subclasses, WordPress attachment IDs, WP REST/AJAX and document classes cannot be copied in directly.
2. **Flat document tree.** A section owns a flat list of blocks. Column membership is represented by a constrained block property, not nested column/container child nodes. This is simpler for a test but less expressive than Elementor's nested element tree.
3. **CSS Grid rather than Flexbox.** Track ratios and per-block placement are sufficient for the test template. Flex direction, wrapping, ordering, grow/shrink and full vertical alignment parity are not provided.
4. **Inspector controls are not fully schema-driven.** The current library settings are generated from stored settings, but core controls are still wired directly to HTML IDs and JavaScript handlers.
5. **Library widget definitions are not a complete renderer contract.** Some widgets are mock previews; forms, reusable sections and some advanced widgets have no live runtime.
6. **Preview and public rendering differ.** The iframe preview renderer is not yet the public renderer. Saved drafts remain private and cannot be published as structured output.
7. **Responsive support is constrained.** Tablet is a canvas viewport, not a separate set of tablet overrides. Mobile has a limited explicit override set and a single <=600px breakpoint.

## Staging test procedure

1. Open `/admin/admin-visual-builder.html` on staging and sign in as an administrator.
2. Select a non-critical published test page. Do not use a protected system page.
3. Click **Prepare draft**. Review the conversion notice; legacy layout and unsupported markup are not carried across.
4. If prompted, click **Set up draft storage** once. This creates only the isolated visual-draft and revision tables.
5. Add a **Container**. Select it and set **Columns** to 2. Set widths to `35, 65`.
6. Add two headings and two paragraphs. Select each block and set **Place in column** so the first heading/paragraph occupy column 1 and the second heading/paragraph occupy column 2.
7. Confirm each column stacks its own content and that the 35/65 ratio is visible.
8. Change to 3 columns, assign a block to column 3, then reduce to 2 columns. Confirm the selected control updates and no block references a missing track.
9. Drag a block to another position using the canvas and Layers panel. Also test duplicate, delete, undo and redo.
10. Save draft. Reload the page and confirm the same columns, widths, placements and content return.
11. Use the revision selector to restore an earlier saved draft. Confirm the public page remains unchanged.
12. Switch Desktop, Tablet and Mobile previews. Confirm mobile stacks the section to one column and no horizontal overflow is introduced.
13. Run the validator tests on a development checkout:
   ```sh
   php -l api/page-document-validator-v1.php
   php tests/page-document-validator-v1.php
   ```
14. Check the public page URL in a separate tab. It should still show the published legacy page; the draft must not appear publicly.

## Release boundary

This is a staging-only visual builder test. Do not merge or deploy to live. Before structured pages can be published, implement and test a shared safe renderer, explicit publish transaction, public cache invalidation, page routing compatibility, and a rollback path. Before claiming full Elementor parity, add nested columns/containers, declarative widget controls, tablet overrides, production widget renderers and widget-specific lifecycle hooks.
