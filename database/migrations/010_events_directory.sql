-- Bubba Hub Events Directory foundation
-- Additive only. Apply to STAGING first after a verified backup.
-- Events remain separate from activities; junction table links a leader's own listings.

CREATE TABLE IF NOT EXISTS bh_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organiser_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  category VARCHAR(120) NULL,
  age_guidance VARCHAR(255) NULL,
  image_path VARCHAR(500) NULL,
  venue_id BIGINT UNSIGNED NULL,
  venue_name VARCHAR(190) NULL,
  address TEXT NULL,
  town VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postcode VARCHAR(20) NULL,
  price_from DECIMAL(10,2) NULL,
  booking_url VARCHAR(1000) NULL,
  ticketing_mode ENUM('free','paid','external','no_booking') NOT NULL DEFAULT 'no_booking',
  recurrence_type ENUM('one_off','recurring') NOT NULL DEFAULT 'one_off',
  recurrence_rule VARCHAR(500) NULL,
  status ENUM('draft','pending','published','cancelled','archived') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_event_slug (slug),
  KEY idx_bh_events_organiser (organiser_id),
  KEY idx_bh_events_status (status),
  KEY idx_bh_events_town (town),
  KEY idx_bh_events_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every public date is an occurrence. This supports one-off events, multi-day
-- events and recurring dates without trying to calculate dates in the browser.
CREATE TABLE IF NOT EXISTS bh_event_occurrences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bh_event_occurrence_date (starts_at),
  KEY idx_bh_event_occurrence_event (event_id),
  KEY idx_bh_event_occurrence_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An event may be associated with multiple activities; only the owning
-- organiser can create links, and the API must verify both records belong
-- to that organiser before inserting a relationship.
CREATE TABLE IF NOT EXISTS bh_event_activities (
  event_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id, activity_id),
  KEY idx_bh_event_activities_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
