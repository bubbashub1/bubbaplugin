-- STAGING ONLY. Apply after staging backup.
-- Immutable record of an attempted approved change, for future rollback.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_apply_audit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 review_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(32) NOT NULL,
 prior_value LONGTEXT NULL,
 proposed_value LONGTEXT NULL,
 prior_fingerprint CHAR(64) NOT NULL,
 proposed_fingerprint CHAR(64) NOT NULL,
 outcome ENUM('applied','skipped','conflict','rolled_back') NOT NULL,
 actor_username VARCHAR(190) NOT NULL,
 recorded_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_review_outcome (review_id,outcome),
 KEY idx_activity_time (activity_id,recorded_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
