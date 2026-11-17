-- Bubba Hub consolidated activity schema migration (replaces PRs #789, #790 and #791).
-- Run once against the intended Bubba Hub database during a controlled deployment.
-- Each column is checked independently so an existing field is preserved.

SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='facebook_url')=0,'ALTER TABLE bh_activities ADD COLUMN facebook_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='instagram_url')=0,'ALTER TABLE bh_activities ADD COLUMN instagram_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_venues' AND COLUMN_NAME='booking_url')=0,'ALTER TABLE bh_venues ADD COLUMN booking_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='block_length_weeks')=0,'ALTER TABLE bh_activities ADD COLUMN block_length_weeks SMALLINT UNSIGNED NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='is_free')=0,'ALTER TABLE bh_activities ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_sessions' AND COLUMN_NAME='class_name')=0,'ALTER TABLE bh_sessions ADD COLUMN class_name VARCHAR(160) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

-- Leader portal and organiser ownership fields consolidated from PRs #785 and #806.
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id')=0,'ALTER TABLE bh_organisers ADD COLUMN user_id BIGINT UNSIGNED NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='contact_name')=0,'ALTER TABLE bh_organisers ADD COLUMN contact_name VARCHAR(160) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility')=0,'ALTER TABLE bh_activities ADD COLUMN accessibility TEXT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='tags')=0,'ALTER TABLE bh_activities ADD COLUMN tags TEXT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='weekly_schedule')=0,'ALTER TABLE bh_activities ADD COLUMN weekly_schedule TEXT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='age_min_months')=0,'ALTER TABLE bh_activities ADD COLUMN age_min_months INT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='age_max_months')=0,'ALTER TABLE bh_activities ADD COLUMN age_max_months INT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='price_per_family')=0,'ALTER TABLE bh_activities ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='price_per_session')=0,'ALTER TABLE bh_activities ADD COLUMN price_per_session TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='booking_required')=0,'ALTER TABLE bh_activities ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='drop_in_welcome')=0,'ALTER TABLE bh_activities ADD COLUMN drop_in_welcome TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='trial_available')=0,'ALTER TABLE bh_activities ADD COLUMN trial_available TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='term_time_only')=0,'ALTER TABLE bh_activities ADD COLUMN term_time_only TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='holiday_sessions')=0,'ALTER TABLE bh_activities ADD COLUMN holiday_sessions TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='siblings_welcome')=0,'ALTER TABLE bh_activities ADD COLUMN siblings_welcome TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='what_to_bring')=0,'ALTER TABLE bh_activities ADD COLUMN what_to_bring VARCHAR(250) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='good_to_know')=0,'ALTER TABLE bh_activities ADD COLUMN good_to_know VARCHAR(250) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='gallery_images')=0,'ALTER TABLE bh_activities ADD COLUMN gallery_images TEXT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

-- Expertise storage is also defined in the base schema; create only if absent.
CREATE TABLE IF NOT EXISTS bh_leader_expertise (
  organiser_id BIGINT UNSIGNED NOT NULL,
  topic_key VARCHAR(80) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organiser_id, topic_key),
  INDEX idx_leader_expertise_topic (topic_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity feature flag from the featured-listing migration, folded into this canonical activity migration.
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='featured')=0,'ALTER TABLE bh_activities ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
