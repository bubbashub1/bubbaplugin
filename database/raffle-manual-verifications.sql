-- Apply to the live WordPress database after backup, before enabling manual raffle verification.
-- Use the actual WordPress prefix if it is not wp_.
CREATE TABLE IF NOT EXISTS wp_bh_raffle_manual_verifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 entry_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 amount_pence INT UNSIGNED NOT NULL,
 method VARCHAR(16) NOT NULL,
 reference VARCHAR(160) NOT NULL,
 note VARCHAR(500) NOT NULL DEFAULT '',
 verified_at DATETIME NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_raffle_entry (entry_id),
 UNIQUE KEY uq_raffle_payment_reference (method,reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
