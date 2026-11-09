-- Bubba Hub booking integration v0.2
-- Run on STAGING only after taking a database backup.
-- Reuses the existing bh_booking_reservations and bh_booking_consent_snapshots tables.
-- Does not create a parallel booking request table or recreate bh_booking_slots.

ALTER TABLE bh_booking_reservations
  ADD COLUMN IF NOT EXISTS public_reference CHAR(12) NULL,
  ADD COLUMN IF NOT EXISTS organiser_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS parent_name VARCHAR(190) NULL,
  ADD COLUMN IF NOT EXISTS parent_email VARCHAR(254) NULL,
  ADD COLUMN IF NOT EXISTS parent_phone VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS child_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS child_name VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS child_age VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS message TEXT NULL;

-- Backfill organiser ownership for existing reservations from the activity.
UPDATE bh_booking_reservations br
JOIN bh_booking_slots bs ON bs.id = br.slot_id
JOIN bh_activities a ON a.id = bs.activity_id
SET br.organiser_id = a.organiser_id
WHERE br.organiser_id IS NULL;

-- Recalculate remaining places from the canonical reservation rows.
UPDATE bh_booking_slots s
LEFT JOIN (
  SELECT slot_id, SUM(quantity) AS booked
  FROM bh_booking_reservations
  WHERE status IN ('reserved','confirmed','attended')
  GROUP BY slot_id
) r ON r.slot_id = s.id
SET
  s.seats_remaining = CASE
    WHEN s.capacity IS NULL THEN NULL
    ELSE GREATEST(s.capacity - COALESCE(r.booked,0),0)
  END,
  s.status = CASE
    WHEN s.status IN ('open','full') AND s.capacity IS NOT NULL AND s.capacity - COALESCE(r.booked,0) <= 0 THEN 'full'
    WHEN s.status = 'full' AND (s.capacity IS NULL OR s.capacity - COALESCE(r.booked,0) > 0) THEN 'open'
    ELSE s.status
  END;

-- The existing reservation status enum is retained:
-- reserved = family request awaiting organiser action
-- confirmed = organiser accepted
-- cancelled = cancelled
-- attended = attended

-- bh_booking_consent_snapshots already supports reservation_id and consent_profile_id.
-- Add an index only if it is not already present.
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'bh_booking_reservations'
    AND INDEX_NAME = 'uq_reservation_public_reference'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE UNIQUE INDEX uq_reservation_public_reference ON bh_booking_reservations (public_reference)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
