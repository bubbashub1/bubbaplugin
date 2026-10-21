-- STAGING ONLY. Back up staging database before applying.
-- Admin login currently uses a configured username, not a numeric admin account ID.
-- Preserve reviewer identity without fabricating reviewed_by_admin_id.
ALTER TABLE bh_sheets_sync_review_queue
  ADD COLUMN reviewed_by_admin_username VARCHAR(190) NULL AFTER reviewed_by_admin_id,
  ADD KEY idx_reviewed_by_username (reviewed_by_admin_username);
