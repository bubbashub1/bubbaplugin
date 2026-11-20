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
