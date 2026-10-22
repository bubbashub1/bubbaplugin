-- Bubba Hub listing improvements — additive schema, review before staging migration.
-- All activity, organiser and session IDs remain unchanged.
CREATE TABLE IF NOT EXISTS bh_listing_claims (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 requester_user_id BIGINT UNSIGNED NOT NULL,
 proposed_organiser_id BIGINT UNSIGNED DEFAULT NULL,
 evidence TEXT DEFAULT NULL,
 status ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED DEFAULT NULL,
 reviewed_at DATETIME DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_claim_activity_status(activity_id,status),
 INDEX idx_claim_requester(requester_user_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_listing_health_findings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 rule_key VARCHAR(80) NOT NULL,
 severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
 status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
 details VARCHAR(1000) DEFAULT NULL,
 checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME DEFAULT NULL,
 UNIQUE KEY uq_activity_rule(activity_id,rule_key),
 INDEX idx_health_status(status,severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_session_exceptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 schedule_id BIGINT UNSIGNED NOT NULL,
 exception_date DATE NOT NULL,
 exception_type ENUM('cancelled','time_changed','venue_changed','replacement') NOT NULL,
 replacement_start_time TIME DEFAULT NULL,
 replacement_end_time TIME DEFAULT NULL,
 replacement_venue_id BIGINT UNSIGNED DEFAULT NULL,
 note VARCHAR(500) DEFAULT NULL,
 created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_schedule_date(schedule_id,exception_date),
 INDEX idx_exception_date(exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_timetable_change_batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 operation VARCHAR(50) NOT NULL,
 request_json LONGTEXT NOT NULL,
 before_json LONGTEXT NOT NULL,
 result_json LONGTEXT DEFAULT NULL,
 status ENUM('preview','applied','rolled_back','failed') NOT NULL DEFAULT 'preview',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 applied_at DATETIME DEFAULT NULL,
 INDEX idx_batch_organiser(organiser_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
