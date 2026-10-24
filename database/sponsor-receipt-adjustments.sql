-- Run after backing up the live database. Adjust wp_ to your WordPress prefix.
CREATE TABLE IF NOT EXISTS wp_bh_sponsor_receipt_adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  receipt_id BIGINT UNSIGNED NOT NULL,
  amount_pence BIGINT UNSIGNED NOT NULL,
  evidence_reference VARCHAR(190) NOT NULL,
  reason TEXT NOT NULL,
  recorded_by BIGINT UNSIGNED NOT NULL,
  recorded_at DATETIME NOT NULL,
  UNIQUE KEY unique_adjustment_reference (evidence_reference),
  KEY receipt_id (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
