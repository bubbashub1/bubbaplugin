-- Bubba Hub native booking requests v0.1
-- Run once on STAGING after taking a database backup.
-- Keeps family contact/child snapshots separate from public activity data.

CREATE TABLE IF NOT EXISTS bh_booking_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_reference CHAR(12) NOT NULL,
  slot_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  organiser_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  parent_name VARCHAR(190) NOT NULL,
  parent_email VARCHAR(254) NOT NULL,
  parent_phone VARCHAR(80) NULL,
  child_id BIGINT UNSIGNED NULL,
  child_name VARCHAR(120) NOT NULL,
  child_age VARCHAR(30) NULL,
  quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  message TEXT NULL,
  status ENUM('pending','confirmed','cancelled','attended') NOT NULL DEFAULT 'pending',
  consent_snapshot LONGTEXT NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_booking_request_reference (public_reference),
  UNIQUE KEY uq_booking_request_slot_user (slot_id,user_id),
  KEY idx_booking_request_organiser_status (organiser_id,status,requested_at),
  KEY idx_booking_request_user (user_id,requested_at),
  KEY idx_booking_request_slot_status (slot_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
