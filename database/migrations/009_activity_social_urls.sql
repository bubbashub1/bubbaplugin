-- Bubba Hub migration 009: activity-specific social URLs.
-- Check each column first; only add columns that are not already present.
-- Run against the Bubba Hub application database during a controlled deployment.
ALTER TABLE bh_activities
  ADD COLUMN facebook_url VARCHAR(2048) NULL DEFAULT NULL,
  ADD COLUMN instagram_url VARCHAR(2048) NULL DEFAULT NULL;
