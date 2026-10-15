# Bubba & Me Expo — ticketing and QR check-in

Target: 9 January 2027, Acorn Centre, Torquay, 10:30–14:30. Use the existing event workflow under #21–23; do not create duplicate event or reservation records.

## Ticket types and workflow
- Free entry and optional paid ticket tiers configured by admin.
- Server-side reservation with transaction-safe capacity, idempotent checkout and unique non-sequential ticket identifiers.
- Stripe webhook is the source of truth for paid ticket confirmation; never mark paid on browser redirect alone.
- Send confirmation via existing SMTP only after reservation is valid/payment confirmed.
- QR code carries an opaque signed or securely random identifier, never personal details.
- Admin mobile check-in endpoint enforces admin role, CSRF and idempotent single-use scan, while allowing explicit audited override.
- Guest list and export are admin-only; minimise retained personal data.
- Refund, cancellation and reissue rules documented before launch.

## Staging acceptance
- Free reservation and paid webhook confirmation.
- Duplicate checkout/webhook and double-scan do not double-book or double-check-in.
- QR code cannot be guessed or forged; cancelled ticket cannot check in.
- Offline check-in policy is explicit (no unsafe fake success).
- Real email deliverability and Android camera scan tested.
- Production keys and live ticket sales remain disabled until approved.
