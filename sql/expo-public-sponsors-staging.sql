-- STAGING ONLY. Public sponsor showcase is manually curated and opt-in.
CREATE TABLE IF NOT EXISTS bh_expo_public_sponsors (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(100) NOT NULL DEFAULT 'bubba-me-2027',
 business_name VARCHAR(160) NOT NULL,
 website_url VARCHAR(500) NULL,
 logo_url VARCHAR(500) NULL,
 approved TINYINT(1) NOT NULL DEFAULT 0,
 public_visible TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_public_sponsors (event_key,approved,public_visible,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Populate only with confirmed sponsors who consent to public display.
-- No automatic import from payment or exhibitor applications.
