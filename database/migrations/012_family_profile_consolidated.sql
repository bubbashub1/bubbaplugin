-- Bubba Hub consolidated family profile migration (replaces PRs #792 and #793).
-- Existing data is preserved. Run against the intended Bubba Hub database.

SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='first_name')=0,'ALTER TABLE bh_users ADD COLUMN first_name VARCHAR(80) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='last_name')=0,'ALTER TABLE bh_users ADD COLUMN last_name VARCHAR(80) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='phone')=0,'ALTER TABLE bh_users ADD COLUMN phone VARCHAR(40) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='date_of_birth')=0,'ALTER TABLE bh_users ADD COLUMN date_of_birth DATE NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

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

-- Upgrade older address tables without replacing existing rows.
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='address_type')=0,"ALTER TABLE bh_user_addresses ADD COLUMN address_type VARCHAR(30) NOT NULL DEFAULT 'home'","SELECT 1");
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='address_line1')=0,'ALTER TABLE bh_user_addresses ADD COLUMN address_line1 VARCHAR(160) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='address_line2')=0,'ALTER TABLE bh_user_addresses ADD COLUMN address_line2 VARCHAR(160) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='city')=0,'ALTER TABLE bh_user_addresses ADD COLUMN city VARCHAR(100) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='county')=0,'ALTER TABLE bh_user_addresses ADD COLUMN county VARCHAR(100) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='postcode')=0,'ALTER TABLE bh_user_addresses ADD COLUMN postcode VARCHAR(20) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='country')=0,"ALTER TABLE bh_user_addresses ADD COLUMN country VARCHAR(80) NOT NULL DEFAULT 'United Kingdom'","SELECT 1");
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='latitude')=0,'ALTER TABLE bh_user_addresses ADD COLUMN latitude DECIMAL(10,7) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND COLUMN_NAME='longitude')=0,'ALTER TABLE bh_user_addresses ADD COLUMN longitude DECIMAL(10,7) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

-- Add the unique key only when existing data has no duplicate address types.
SET @bh_dupes = (SELECT COUNT(*) FROM (SELECT user_id,address_type FROM bh_user_addresses GROUP BY user_id,address_type HAVING COUNT(*)>1) AS duplicate_addresses);
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND INDEX_NAME='uq_user_address_type')=0 AND @bh_dupes=0,'ALTER TABLE bh_user_addresses ADD UNIQUE KEY uq_user_address_type(user_id,address_type)','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_addresses' AND INDEX_NAME='idx_user_id')=0,'ALTER TABLE bh_user_addresses ADD KEY idx_user_id(user_id)','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
