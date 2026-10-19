-- Apply to STAGING only after a database backup.
-- Event metadata is separate from Expo tickets, applications and payments.
CREATE TABLE IF NOT EXISTS bh_event_details (
 event_key VARCHAR(80) NOT NULL PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 description TEXT NOT NULL,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL,
 venue VARCHAR(250) NOT NULL,
 image_url VARCHAR(1000) NOT NULL DEFAULT '',
 image_alt VARCHAR(200) NOT NULL DEFAULT '',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO bh_event_details(event_key,title,description,starts_at,ends_at,venue)
VALUES('bubba-me-2027','Bubba & Me Baby & Pregnancy Expo','Discover local pregnancy, baby and family businesses and support services.','2027-01-09 10:30:00','2027-01-09 14:30:00','Acorn Centre, Torquay');
