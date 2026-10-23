-- Staging only: one booking QR record per reservation, regardless of attendee count.
CREATE TABLE IF NOT EXISTS bh_expo_booking_qrs (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_booking_qr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Do not backfill with a random QR without arranging secure delivery to the booking email.
