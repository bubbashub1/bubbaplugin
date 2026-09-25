-- Bubba Hub standalone database foundation
-- Safe design: ONLY bh_* tables. Do not modify existing WordPress wp_* tables.
-- Run this after confirming the existing MySQL database is the one intended for Bubba Hub.

CREATE TABLE IF NOT EXISTS bh_organisers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 organisation_name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 description TEXT NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(80) NULL,
 website VARCHAR(255) NULL,
 status ENUM('draft','pending','published','suspended') NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 description TEXT NULL,
 category VARCHAR(120) NULL,
 age_range VARCHAR(255) NULL,
 price_from DECIMAL(10,2) NULL,
 booking_url VARCHAR(500) NULL,
 image_path VARCHAR(500) NULL,
 status ENUM('draft','pending','published','archived') NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_activity_organiser (organiser_id),
 INDEX idx_activity_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_venues (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 venue_name VARCHAR(190) NOT NULL,
 address TEXT NULL,
 town VARCHAR(120) NULL,
 region VARCHAR(120) NULL,
 postcode VARCHAR(20) NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_venue_activity (activity_id),
 INDEX idx_venue_town (town)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 venue_id BIGINT UNSIGNED NOT NULL,
 day_of_week TINYINT UNSIGNED NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NULL,
 duration_minutes SMALLINT UNSIGNED NULL,
 price DECIMAL(10,2) NULL,
 term_time_only TINYINT(1) NOT NULL DEFAULT 0,
 frequency VARCHAR(80) NULL DEFAULT 'weekly',
 start_date DATE NULL,
 end_date DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_session_venue (venue_id),
 INDEX idx_session_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('family','organiser','admin') NOT NULL DEFAULT 'family',
 status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_children (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 gender VARCHAR(40) NULL,
 photo_path VARCHAR(500) NULL,
 date_of_birth DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_child_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_bumps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 nickname VARCHAR(120) NULL,
 photo_path VARCHAR(500) NULL,
 due_date DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_bump_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_saved_activities (
 user_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_planner (
 user_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 visited TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_hidden_planner_days (
 user_id BIGINT UNSIGNED NOT NULL,
 day_of_week TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY (user_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 session_id BIGINT UNSIGNED NULL,
 status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
 consent_given TINYINT(1) NOT NULL DEFAULT 0,
 amount DECIMAL(10,2) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_booking_user (user_id),
 INDEX idx_booking_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Private family Help & Support. These tables are separate from public leader FAQs.
CREATE TABLE IF NOT EXISTS bh_guidance_topics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 description VARCHAR(500) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_guidance_offers (
 user_id BIGINT UNSIGNED NOT NULL,
 topic_id BIGINT UNSIGNED NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY (user_id, topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_support_questions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 topic_id BIGINT UNSIGNED NULL,
 subject VARCHAR(190) NULL,
 question TEXT NOT NULL,
 status ENUM('submitted','assigned','awaiting_user','answered','closed') NOT NULL DEFAULT 'submitted',
 assigned_user_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_support_user (user_id),
 INDEX idx_support_assigned (assigned_user_id),
 INDEX idx_support_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_support_answers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 question_id BIGINT UNSIGNED NOT NULL,
 responder_user_id BIGINT UNSIGNED NOT NULL,
 answer TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_support_answer_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_leader_faqs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NULL,
 question VARCHAR(500) NOT NULL,
 answer TEXT NOT NULL,
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_faq_organiser (organiser_id),
 INDEX idx_faq_activity (activity_id),
 INDEX idx_faq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
