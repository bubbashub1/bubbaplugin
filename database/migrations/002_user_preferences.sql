-- Bubba Hub account-backed discovery preferences
CREATE TABLE IF NOT EXISTS bh_user_preferences (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 email_enabled TINYINT(1) NOT NULL DEFAULT 1,
 sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
 push_enabled TINYINT(1) NOT NULL DEFAULT 0,
 phone VARCHAR(80) NULL,
 sms_marketing TINYINT(1) NOT NULL DEFAULT 0,
 planner_reminders TINYINT(1) NOT NULL DEFAULT 1,
 booking_updates TINYINT(1) NOT NULL DEFAULT 1,
 saved_searches TINYINT(1) NOT NULL DEFAULT 0,
 support_replies TINYINT(1) NOT NULL DEFAULT 1,
 event_reminders TINYINT(1) NOT NULL DEFAULT 1,
 region VARCHAR(120) NULL,
 town VARCHAR(120) NULL,
 preferred_day VARCHAR(20) NULL,
 categories_json TEXT NULL,
 free_activities TINYINT(1) NOT NULL DEFAULT 0,
 term_time TINYINT(1) NOT NULL DEFAULT 0,
 max_price DECIMAL(10,2) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
