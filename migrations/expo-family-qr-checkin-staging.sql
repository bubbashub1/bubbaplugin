-- STAGING ONLY: apply to SCSTAGING-353034334ce5 before testing.
-- Independent of existing adult ticket check-ins and family attendance tables.
CREATE TABLE IF NOT EXISTS bh_expo_family_qr_checkins (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 checked_in_at DATETIME NOT NULL,
 INDEX idx_family_qr_checked_in_at (checked_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
