# Validator implementation

The pure validator is `api/page-document-validator-v1.php`. It has no database or session dependencies and can be exercised from CLI.

Run:

```sh
php -l api/page-document-validator-v1.php
php tests/page-document-validator-v1.php
```

The validator enforces the supported v1 document types and constrained properties, unique element IDs, text and node-size bounds, colour/spacing/heading/alignment constraints and link restrictions. It returns a structured `ok` and `errors` result and does not write to storage.

## Important

This validator is not yet wired into page-save endpoints. Do not treat the schema or pure tests as enabling builder editing. Before integrating it, add API-level authorization, CSRF, page ownership/slug protections, atomic draft persistence and revision snapshots, and test these against staging.


## Draft-storage API foundation

`api/admin-visual-page-drafts.php` adds a separate, administrator-authenticated draft-document storage endpoint. It requires the existing admin session guard and a session CSRF token, provides an explicit one-time `setup` action for its two isolated tables, validates documents server-side, anchors page title and slug to the existing Page Manager record, rejects archived pages, and snapshots the previous structured draft before replacing it. This storage is separate from legacy `content_html`; it does not publish, render structured documents publicly, convert legacy content, or enable visual editor controls. Staging testing and a reviewed safe renderer are still required.


## Link destination validation

The v1 validator accepts validated HTTP(S) URLs, valid email addresses with the `mailto:` scheme, and telephone numbers with the `tel:` scheme. Same-origin root-relative, fragment and query references are accepted. Protocol-relative URLs, control characters, backslashes, malformed email/telephone destinations, and executable/data schemes are rejected. The public renderer must still escape text and emit only validated href attributes.
