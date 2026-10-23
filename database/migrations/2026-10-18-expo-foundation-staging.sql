-- Bubba & Me Expo 2027: STAGING-ONLY foundation.
-- Review and execute manually against STAGING database; do not run against live.
-- Do not treat this migration as activating bookings or sending email.
CREATE TABLE IF NOT EXISTS bh_expo_reservations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 public_reference CHAR(24) NOT NULL,
 event_key VARCHAR(80) NOT NULL DEFAULT 'bubba-me-2027',
 contact_name VARCHAR(150) NOT NULL,
 contact_email VARCHAR(190) NOT NULL,
 adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
 children TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ticket_type ENUM('free','paid') NOT NULL DEFAULT 'free',
 amount_pence INT UNSIGNED NOT NULL DEFAULT 0,
 payment_status ENUM('not_required','pending','paid','refunded') NOT NULL DEFAULT 'not_required',
 booking_status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
 email_status ENUM('not_sent','sent','failed') NOT NULL DEFAULT 'not_sent',
 consent_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_reference (public_reference),
 KEY idx_expo_booking (event_key,booking_status,created_at),
 KEY idx_expo_email (contact_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_expo_tickets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reservation_id BIGINT UNSIGNED NOT NULL,
 ticket_token CHAR(64) NOT NULL,
 attendee_type ENUM('adult','child') NOT NULL,
 checked_in_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_ticket_token (ticket_token),
 KEY idx_expo_reservation (reservation_id),
 CONSTRAINT fk_expo_ticket_reservation FOREIGN KEY (reservation_id) REFERENCES bh_expo_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_expo_exhibitors (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL DEFAULT 'bubba-me-2027',
 business_name VARCHAR(150) NOT NULL,
 contact_name VARCHAR(120) NOT NULL,
 contact_email VARCHAR(190) NOT NULL,
 contact_phone VARCHAR(30) NOT NULL,
 website_url VARCHAR(500) NULL,
 logo_url VARCHAR(500) NULL,
 description TEXT NOT NULL,
 participation_json TEXT NULL,
 preferred_table SMALLINT UNSIGNED NULL,
 assigned_table SMALLINT UNSIGNED NULL,
 donation_interest ENUM('no','yes','maybe') NOT NULL DEFAULT 'no',
 donation_amount_pence INT UNSIGNED NOT NULL DEFAULT 0,
 donation_payment_status ENUM('not_required','pending','paid','refunded') NOT NULL DEFAULT 'not_required',
 application_status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
 public_visible TINYINT(1) NOT NULL DEFAULT 0,
 insurance_policy VARCHAR(120) NULL,
 insurance_expiry DATE NULL,
 requirements TEXT NULL,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 KEY idx_expo_exhibitor_status (event_key,application_status),
 KEY idx_expo_exhibitor_table (event_key,assigned_table),
 KEY idx_expo_exhibitor_email (contact_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_expo_tables (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL DEFAULT 'bubba-me-2027',
 table_number SMALLINT UNSIGNED NOT NULL,
 status ENUM('available','held','assigned','blocked') NOT NULL DEFAULT 'available',
 exhibitor_id BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_expo_event_table (event_key,table_number),
 KEY idx_expo_table_exhibitor (exhibitor_id),
 CONSTRAINT fk_expo_table_exhibitor FOREIGN KEY (exhibitor_id) REFERENCES bh_expo_exhibitors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provisional table numbers only; verify the floor plan before assigning any.
INSERT IGNORE INTO bh_expo_tables(event_key,table_number)
SELECT 'bubba-me-2027',n FROM (
 SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26
) numbers;
