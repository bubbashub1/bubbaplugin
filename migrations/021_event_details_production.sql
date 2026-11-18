-- Production migration for the authenticated Bubba & Me event editor.
-- Run only after a verified live database backup and staging validation.
-- This metadata table is separate from tickets, exhibitor applications,
-- sponsor payments, QR check-in and attendee records.
--
-- Safe to re-run: CREATE TABLE IF NOT EXISTS and INSERT IGNORE preserve
-- any existing event record. This migration never updates or deletes data.

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
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only create the starter record when it does not already exist.
-- Current planned event details: Sunday 28 February 2027, 10:00–13:00.
-- Existing live data always takes precedence and is never overwritten.
INSERT IGNORE INTO bh_event_details
    (event_key, title, description, starts_at, ends_at, venue)
VALUES
    ('bubba-me-2027',
     'Bubba & Me Baby & Pregnancy Expo',
     'Discover local pregnancy, baby and family businesses and support services.',
     '2027-02-28 10:00:00',
     '2027-02-28 13:00:00',
     'Acorn Centre, Torquay');
