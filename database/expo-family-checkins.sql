-- Staging-only family check-in counts, per reservation.
CREATE TABLE IF NOT EXISTS bh_expo_family_checkins (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 adults_checked_in INT UNSIGNED NOT NULL DEFAULT 0,
 children_checked_in INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
