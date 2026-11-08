-- Bubba Hub organiser ownership link.
-- Apply once during a controlled maintenance window before enabling claim approval.
-- Preflight:
--   SHOW COLUMNS FROM bh_organisers LIKE 'user_id';
-- If the column already exists, do not run the ALTER statement again.
-- This is intentionally a deployment-time migration, not request-time DDL.
ALTER TABLE bh_organisers
  ADD COLUMN user_id BIGINT UNSIGNED NULL DEFAULT NULL;
