-- Bubba Hub migration 010: venue booking URL.
-- Check SHOW COLUMNS FROM bh_venues LIKE 'booking_url' before applying.
-- Run only when the column is absent.
ALTER TABLE bh_venues
  ADD COLUMN booking_url VARCHAR(2048) NULL DEFAULT NULL;
