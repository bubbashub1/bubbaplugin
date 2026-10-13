-- Bubba Hub Events: leader-first access and exhibitor applications
-- Apply to staging database only after backup and review.
-- Existing bh_events table is managed by api/events.php.
CREATE TABLE IF NOT EXISTS bh_event_early_access (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 opens_at DATETIME NOT NULL,
 public_at DATETIME NOT NULL,
 application_url VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_event (event_id),
 KEY idx_release (enabled,public_at),
 CONSTRAINT fk_bh_early_event FOREIGN KEY (event_id) REFERENCES bh_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_event_leader_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 leader_id BIGINT UNSIGNED NOT NULL,
 channel ENUM('email','portal') NOT NULL,
 state ENUM('queued','sent','failed','read','cancelled') NOT NULL DEFAULT 'queued',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_error VARCHAR(255) DEFAULT NULL,
 sent_at DATETIME DEFAULT NULL,
 read_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_event_leader_channel (event_id,leader_id,channel),
 KEY idx_dispatch (state,channel,created_at),
 CONSTRAINT fk_bh_notice_event FOREIGN KEY (event_id) REFERENCES bh_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_event_exhibitor_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 leader_id BIGINT UNSIGNED DEFAULT NULL,
 business_name VARCHAR(190) NOT NULL,
 contact_name VARCHAR(190) NOT NULL,
 contact_email VARCHAR(190) NOT NULL,
 contact_phone VARCHAR(60) NOT NULL DEFAULT '',
 website VARCHAR(1000) NOT NULL DEFAULT '',
 business_description TEXT NOT NULL,
 stall_requirements TEXT,
 participation_notes TEXT,
 status ENUM('pending','approved','declined','withdrawn') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_event_status (event_id,status),
 KEY idx_email (contact_email),
 CONSTRAINT fk_bh_exhibitor_event FOREIGN KEY (event_id) REFERENCES bh_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
