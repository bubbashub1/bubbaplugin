-- Disposable fixture for booking_integration_v0_2.sql CI verification.
CREATE TABLE bh_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organiser_id BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bh_booking_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  venue_id BIGINT UNSIGNED NULL,
  source_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  capacity INT UNSIGNED NULL,
  seats_remaining INT UNSIGNED NULL,
  price DECIMAL(10,2) NULL,
  booking_mode ENUM('bubba','external','schedule_only') NOT NULL DEFAULT 'bubba',
  external_url VARCHAR(1000) NULL,
  status ENUM('open','full','closed','cancelled') NOT NULL DEFAULT 'open',
  external_key VARCHAR(255) NULL,
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_slot_activity (activity_id,starts_at),
  INDEX idx_booking_slot_source (source_id,starts_at),
  UNIQUE KEY uq_external_slot (source_id,external_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bh_booking_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('reserved','confirmed','cancelled','attended') NOT NULL DEFAULT 'reserved',
  quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slot_user (slot_id,user_id),
  INDEX idx_reservation_slot (slot_id),
  INDEX idx_reservation_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bh_booking_consent_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  booking_id BIGINT UNSIGNED NULL,
  reservation_id BIGINT UNSIGNED NULL,
  consent_profile_id BIGINT UNSIGNED NULL,
  consent_version VARCHAR(30) NOT NULL,
  child_name VARCHAR(120) NOT NULL,
  consent_json LONGTEXT NOT NULL,
  consent_given_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_consent_snapshot_user (user_id),
  INDEX idx_consent_snapshot_booking (booking_id),
  INDEX idx_consent_snapshot_reservation (reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO bh_activities (organiser_id) VALUES (42);
INSERT INTO bh_booking_slots (activity_id,starts_at,capacity,seats_remaining,status)
VALUES (1,'2027-02-28 10:30:00',5,5,'open');
INSERT INTO bh_booking_reservations (slot_id,user_id,status,quantity) VALUES
  (1,10,'reserved',2),
  (1,11,'attended',1),
  (1,12,'cancelled',4);
