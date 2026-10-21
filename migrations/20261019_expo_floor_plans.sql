-- Back up LIVE database before applying this additive migration.
CREATE TABLE IF NOT EXISTS bh_expo_floor_plans (
 event_key VARCHAR(80) NOT NULL PRIMARY KEY,
 layout_json LONGTEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
