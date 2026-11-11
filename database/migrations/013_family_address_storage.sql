-- Bubba Hub migration 013: family home address storage.
-- Fresh installs: create the complete table with its unique address key.
CREATE TABLE IF NOT EXISTS bh_user_addresses (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 address_type VARCHAR(30) NOT NULL DEFAULT 'home',
 address_line1 VARCHAR(160) NULL,
 address_line2 VARCHAR(160) NULL,
 city VARCHAR(100) NULL,
 county VARCHAR(100) NULL,
 postcode VARCHAR(20) NULL,
 country VARCHAR(80) NOT NULL DEFAULT 'United Kingdom',
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_user_address_type(user_id,address_type),
 KEY idx_user_id(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations with an older/incomplete table:
-- inspect SHOW COLUMNS FROM bh_user_addresses and add only missing columns.
-- Before adding uq_user_address_type, check for duplicate (user_id,address_type)
-- pairs; resolve duplicates deliberately so profile data is not discarded.
-- Add the unique key only after duplicates are resolved:
-- ALTER TABLE bh_user_addresses ADD UNIQUE KEY uq_user_address_type(user_id,address_type);
-- Add idx_user_id only if SHOW INDEX does not already show it:
-- ALTER TABLE bh_user_addresses ADD KEY idx_user_id(user_id);
