-- Bubba Hub 2.0 native timetable extension, STAGING ONLY.
-- Preflight: backup database; confirm bh_sessions and bh_venues definitions match the repository.
-- Apply only to staging after schema inspection. Never run automatically on production.
-- Existing bh_sessions rows remain untouched.

CREATE TABLE IF NOT EXISTS bh_session_verifications (
 session_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 status ENUM('unverified','confirmed','needs_review') NOT NULL DEFAULT 'unverified',
 confirmed_at DATETIME NULL,
 confirmed_by_user_id BIGINT UNSIGNED NULL,
 next_review_at DATETIME NULL,
 source VARCHAR(40) NOT NULL DEFAULT 'manual',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_next_review (next_review_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_session_exceptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 occurrence_date DATE NOT NULL,
 exception_type ENUM('cancelled','rescheduled') NOT NULL,
 replacement_date DATE NULL,
 replacement_start_time TIME NULL,
 replacement_end_time TIME NULL,
 note VARCHAR(500) NULL,
 created_by_user_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_session_occurrence (session_id, occurrence_date),
 INDEX idx_occurrence_date (occurrence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_term_calendars (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(160) NOT NULL,
 region VARCHAR(120) NULL,
 organiser_id BIGINT UNSIGNED NULL,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_term_region (region,starts_on,ends_on),
 INDEX idx_term_organiser (organiser_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_session_term_links (
 session_id BIGINT UNSIGNED NOT NULL,
 term_calendar_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (session_id,term_calendar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
