-- SMS has been removed from Bubba Hub notifications.
ALTER TABLE bh_user_preferences
  DROP COLUMN IF EXISTS sms_enabled,
  DROP COLUMN IF EXISTS phone,
  DROP COLUMN IF EXISTS sms_marketing;

CREATE TABLE IF NOT EXISTS bh_push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  content_encoding VARCHAR(30) NOT NULL DEFAULT 'aes128gcm',
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
  INDEX idx_push_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
