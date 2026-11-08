-- Bubba Hub leader portal listing and profile storage (migration 021)
-- Apply once after checking each column/table is not already present.
-- This migration replaces request-time CREATE/ALTER TABLE statements in api/leader-portal.php.

ALTER TABLE bh_organisers ADD COLUMN contact_name VARCHAR(160) NULL;

ALTER TABLE bh_activities
  ADD COLUMN accessibility TEXT NULL,
  ADD COLUMN tags TEXT NULL,
  ADD COLUMN weekly_schedule TEXT NULL,
  ADD COLUMN age_min_months INT NULL,
  ADD COLUMN age_max_months INT NULL,
  ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN price_per_session TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN drop_in_welcome TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN trial_available TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN term_time_only TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN holiday_sessions TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN siblings_welcome TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN what_to_bring VARCHAR(250) NULL,
  ADD COLUMN good_to_know VARCHAR(250) NULL,
  ADD COLUMN facebook_url VARCHAR(500) NULL,
  ADD COLUMN instagram_url VARCHAR(500) NULL,
  ADD COLUMN gallery_images TEXT NULL;

CREATE TABLE IF NOT EXISTS bh_leader_expertise (
  organiser_id BIGINT UNSIGNED NOT NULL,
  topic_key VARCHAR(80) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organiser_id, topic_key),
  INDEX idx_leader_expertise_topic (topic_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
