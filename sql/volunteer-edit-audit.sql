-- Apply to staging first, then live after validation.
-- Existing bh_volunteer_assignments table is managed by admin volunteer setup.
CREATE TABLE IF NOT EXISTS bh_volunteer_edit_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  before_json LONGTEXT NOT NULL,
  after_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_created (activity_id, created_at),
  KEY idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
