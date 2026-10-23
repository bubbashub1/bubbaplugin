-- Bubba & Me 2027 visitor admission only. Live database: SCWORDPRESS-35303433c4eb
-- Additive migration; does not alter existing Expo raffle, sponsor, exhibitor or Forminator tables.
-- MySQL/MariaDB, InnoDB, utf8mb4. Run once in phpMyAdmin after a database backup.
CREATE TABLE IF NOT EXISTS bh_expo_reservations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 public_reference CHAR(24) NOT NULL,
 event_key VARCHAR(80) NOT NULL,
 contact_name VARCHAR(150) NOT NULL,
 contact_email VARCHAR(190) NOT NULL,
 adults SMALLINT UNSIGNED NOT NULL,
 children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 ticket_type VARCHAR(32) NOT NULL DEFAULT 'free',
 amount_pence INT UNSIGNED NOT NULL DEFAULT 0,
 payment_status VARCHAR(32) NOT NULL DEFAULT 'not_required',
 booking_status VARCHAR(32) NOT NULL DEFAULT 'pending',
 email_status VARCHAR(32) NOT NULL DEFAULT 'not_sent',
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_public_reference (public_reference),
 KEY idx_expo_event_status (event_key, booking_status),
 KEY idx_expo_email (contact_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_tickets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reservation_id BIGINT UNSIGNED NOT NULL,
 ticket_token CHAR(64) NOT NULL,
 attendee_type VARCHAR(16) NOT NULL DEFAULT 'adult',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_ticket_token (ticket_token),
 KEY idx_expo_ticket_reservation (reservation_id),
 CONSTRAINT fk_expo_ticket_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_booking_qrs (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 token_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_qr_hash (token_hash),
 CONSTRAINT fk_expo_qr_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_family_checkins (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 adults_checked_in SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 children_checked_in SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_expo_family_checkin_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_family_qr_checkins (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 checked_in_at DATETIME NOT NULL,
 KEY idx_family_qr_checked_in_at (checked_in_at),
 CONSTRAINT fk_expo_family_qr_checkin_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_family_email_delivery (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
 last_attempt_at DATETIME NULL DEFAULT NULL,
 lease_until DATETIME NULL DEFAULT NULL,
 sent_at DATETIME NULL DEFAULT NULL,
 CONSTRAINT fk_expo_email_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_expo_giveaway_claims (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reservation_id BIGINT UNSIGNED NOT NULL,
 giveaway_key VARCHAR(80) NOT NULL,
 claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_giveaway_reservation (reservation_id, giveaway_key),
 CONSTRAINT fk_expo_giveaway_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Read-only verification
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
('bh_expo_reservations','bh_expo_tickets','bh_expo_booking_qrs','bh_expo_family_checkins','bh_expo_family_qr_checkins','bh_expo_family_email_delivery','bh_expo_giveaway_claims')
ORDER BY TABLE_NAME;
