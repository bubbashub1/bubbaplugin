-- Bubba Hub Daily / Weekly / Monthly Round Up newsletter preferences
ALTER TABLE bh_user_preferences
  ADD COLUMN IF NOT EXISTS newsletter_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS newsletter_frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  ADD COLUMN IF NOT EXISTS newsletter_last_sent_at DATETIME NULL;

