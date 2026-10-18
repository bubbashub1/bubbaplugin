-- Staging-only: persistent family QR email delivery state.
-- The QR secret must be retained securely by the controlled confirmation workflow;
-- this table deliberately stores neither the QR payload nor plaintext secret.
CREATE TABLE IF NOT EXISTS bh_expo_family_email_delivery (
 reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
 attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
 lease_until DATETIME NULL,
 last_attempt_at DATETIME NULL,
 sent_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_expo_family_delivery_retry (status,lease_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
