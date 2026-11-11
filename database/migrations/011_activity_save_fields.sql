-- Bubba Hub migration 011: activity pricing and session class fields.
-- Inspect each column first and run only the ALTER statements for missing columns.
-- Do not rerun an ALTER statement when its column already exists.
ALTER TABLE bh_activities
  ADD COLUMN block_length_weeks SMALLINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE bh_activities
  ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE bh_sessions
  ADD COLUMN class_name VARCHAR(160) NULL DEFAULT NULL;
