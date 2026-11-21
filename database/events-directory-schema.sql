-- Bubba Hub Events directory foundation
-- STAGING FIRST. This file is a reviewed schema proposal only; do not run automatically.
-- Existing Listings (bh_activities), venues, bookings and planner data are not altered.
-- No foreign keys are declared because legacy installations vary in their table definitions.

CREATE TABLE IF NOT EXISTS bh_directory_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organiser_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL,
  summary VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  category VARCHAR(120) NULL,
  age_min_months SMALLINT UNSIGNED NULL,
  age_max_months SMALLINT UNSIGNED NULL,
  price_from DECIMAL(10,2) NULL,
  booking_url VARCHAR(1000) NULL,
  image_path VARCHAR(500) NULL,
  venue_id BIGINT UNSIGNED NULL,
  venue_name VARCHAR(190) NULL,
  address VARCHAR(500) NULL,
  town VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postcode VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  review_note VARCHAR(2000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_directory_event_slug (slug),
  KEY idx_directory_event_owner (organiser_id,status),
  KEY idx_directory_event_status_date (status,submitted_at),
  KEY idx_directory_event_town (town),
  KEY idx_directory_event_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_directory_event_occurrences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/London',
  occurrence_status ENUM('scheduled','cancelled','rescheduled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_event_occurrence_start (starts_at,occurrence_status),
  KEY idx_event_occurrence_event (event_id,starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_directory_event_listings (
  event_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id,activity_id),
  KEY idx_event_listing_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review history is intentionally separate from the legacy Expo event-details table.
CREATE TABLE IF NOT EXISTS bh_directory_event_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NULL,
  decision ENUM('approved','rejected','returned_for_edits') NOT NULL,
  note VARCHAR(2000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_review_history (event_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Bubba Hub Directory sectors: Businesses, Childcare & Schools,
-- Places & Days Out, and School Clubs.
-- Together with the Events tables above, this is the consolidated five-sector
-- staging installation. Listings continue using the existing bh_activities.
-- All new records belong to an existing bh_organisers profile and are moderated.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS bh_directory_businesses (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL, created_by_user_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL, category VARCHAR(120) NOT NULL, summary VARCHAR(500) NULL,
 description MEDIUMTEXT NOT NULL, town VARCHAR(120) NOT NULL, region VARCHAR(120) NULL,
 address VARCHAR(500) NULL, postcode VARCHAR(20) NULL, website_url VARCHAR(1000) NULL,
 contact_email VARCHAR(190) NULL, contact_phone VARCHAR(60) NULL,
 status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft',
 submitted_at DATETIME NULL, reviewed_at DATETIME NULL, reviewed_by_user_id BIGINT UNSIGNED NULL,
 review_note VARCHAR(2000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_business_owner_status (organiser_id,status), KEY idx_business_town_category (town,category),
 KEY idx_business_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_directory_childcare (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL, created_by_user_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL, category VARCHAR(120) NOT NULL, summary VARCHAR(500) NULL,
 description MEDIUMTEXT NOT NULL, age_min SMALLINT UNSIGNED NULL, age_max SMALLINT UNSIGNED NULL,
 town VARCHAR(120) NOT NULL, region VARCHAR(120) NULL, address VARCHAR(500) NULL,
 postcode VARCHAR(20) NULL, website_url VARCHAR(1000) NULL, registration_body VARCHAR(120) NULL,
 registration_reference VARCHAR(120) NULL, verification_url VARCHAR(1000) NULL, verified_on DATE NULL,
 status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft',
 submitted_at DATETIME NULL, reviewed_at DATETIME NULL, reviewed_by_user_id BIGINT UNSIGNED NULL,
 review_note VARCHAR(2000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_childcare_owner_status (organiser_id,status), KEY idx_childcare_town_category (town,category),
 KEY idx_childcare_title (title), KEY idx_childcare_verification (verified_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_directory_places (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL, created_by_user_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL, category VARCHAR(120) NOT NULL, summary VARCHAR(500) NULL,
 description MEDIUMTEXT NOT NULL, town VARCHAR(120) NOT NULL, region VARCHAR(120) NULL,
 address VARCHAR(500) NULL, postcode VARCHAR(20) NULL, website_url VARCHAR(1000) NULL,
 facilities MEDIUMTEXT NULL,
 status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft',
 submitted_at DATETIME NULL, reviewed_at DATETIME NULL, reviewed_by_user_id BIGINT UNSIGNED NULL,
 review_note VARCHAR(2000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_places_owner_status (organiser_id,status), KEY idx_places_town_category (town,category),
 KEY idx_places_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_directory_school_clubs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL, created_by_user_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL, category VARCHAR(120) NOT NULL, summary VARCHAR(500) NULL,
 description MEDIUMTEXT NOT NULL, age_min TINYINT UNSIGNED NOT NULL DEFAULT 3,
 age_max TINYINT UNSIGNED NULL, school_name VARCHAR(190) NOT NULL,
 collection_arrangements MEDIUMTEXT NOT NULL, days_times MEDIUMTEXT NOT NULL,
 holiday_provision VARCHAR(500) NULL, price_details VARCHAR(500) NULL,
 town VARCHAR(120) NOT NULL, region VARCHAR(120) NULL, address VARCHAR(500) NULL,
 postcode VARCHAR(20) NULL, website_url VARCHAR(1000) NULL, registration_details MEDIUMTEXT NULL,
 verification_url VARCHAR(1000) NULL, verified_on DATE NULL,
 status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft',
 submitted_at DATETIME NULL, reviewed_at DATETIME NULL, reviewed_by_user_id BIGINT UNSIGNED NULL,
 review_note VARCHAR(2000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_school_clubs_owner_status (organiser_id,status), KEY idx_school_clubs_town_type (town,category),
 KEY idx_school_clubs_title (title), KEY idx_school_clubs_school (school_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared moderation history for the four specialist directory sections.
CREATE TABLE IF NOT EXISTS bh_directory_record_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 section_key ENUM('business','childcare','place','school_club') NOT NULL,
 record_id BIGINT UNSIGNED NOT NULL, reviewer_user_id BIGINT UNSIGNED NULL,
 decision ENUM('approved','rejected','returned_for_edits','revision_approved','revision_rejected','revision_returned_for_edits') NOT NULL,
 note VARCHAR(2000) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_directory_record_review (section_key,record_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Published-entry changes are staged separately and never overwrite live data until approved.
CREATE TABLE IF NOT EXISTS bh_directory_record_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 section_key ENUM('business','childcare','place','school_club') NOT NULL,
 record_id BIGINT UNSIGNED NOT NULL, organiser_id BIGINT UNSIGNED NOT NULL,
 submitted_by_user_id BIGINT UNSIGNED NOT NULL, payload MEDIUMTEXT NOT NULL,
 status ENUM('pending','approved','rejected','returned_for_edits') NOT NULL DEFAULT 'pending',
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, reviewed_at DATETIME NULL,
 reviewed_by_user_id BIGINT UNSIGNED NULL, review_note VARCHAR(2000) NULL,
 KEY idx_directory_revision_queue (status,submitted_at),
 KEY idx_directory_revision_record (section_key,record_id,status),
 KEY idx_directory_revision_owner (organiser_id,record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
