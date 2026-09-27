-- Bubba Hub migration 005: align application roles with the live leader portal.
-- The PHP application uses role='leader' for class leader accounts.
-- Older schema versions omitted that enum value, which prevents leader registration/login.
ALTER TABLE bh_users MODIFY COLUMN role ENUM('family','leader','organiser','admin') NOT NULL DEFAULT 'family';