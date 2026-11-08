-- Bubba Hub migration 014: account activation token storage.
CREATE TABLE IF NOT EXISTS bh_account_activation_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 code_hash CHAR(64) NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_activation_token_hash(token_hash),
 KEY idx_activation_user(user_id),
 KEY idx_activation_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations: inspect SHOW COLUMNS and SHOW INDEX first.
-- Add code_hash only if missing:
-- ALTER TABLE bh_account_activation_tokens ADD COLUMN code_hash CHAR(64) NULL;
-- Preserve existing token_hash uniqueness and indexes; do not add duplicate keys.
