-- Sponsor receipt ledger. Run after database backup.
-- Change wp_ to the actual WordPress table prefix if different.
CREATE TABLE IF NOT EXISTS wp_bh_sponsor_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entry_id BIGINT UNSIGNED NOT NULL,
  amount_pence BIGINT UNSIGNED NOT NULL,
  method VARCHAR(30) NOT NULL,
  evidence_reference VARCHAR(190) NOT NULL,
  verification_note TEXT NULL,
  verified_by BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME NOT NULL,
  UNIQUE KEY unique_method_reference (method, evidence_reference),
  KEY entry_id (entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
