-- Bubba Hub Listing Checker: structured search settings.
-- Run once AFTER database/listing-checker-schema.sql.
-- Safe for existing installations: adds only missing columns to the discovery terms table.
SET @bh_lc_add_source = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_listing_discovery_terms' AND COLUMN_NAME='source_platform')=0,
  'ALTER TABLE bh_listing_discovery_terms ADD COLUMN source_platform VARCHAR(20) NOT NULL DEFAULT ''web'' AFTER regions_json',
  'SELECT 1'
);
PREPARE bh_lc_stmt FROM @bh_lc_add_source;
EXECUTE bh_lc_stmt;
DEALLOCATE PREPARE bh_lc_stmt;

SET @bh_lc_add_keywords = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_listing_discovery_terms' AND COLUMN_NAME='search_keywords')=0,
  'ALTER TABLE bh_listing_discovery_terms ADD COLUMN search_keywords VARCHAR(255) NOT NULL DEFAULT '''' AFTER source_platform',
  'SELECT 1'
);
PREPARE bh_lc_stmt FROM @bh_lc_add_keywords;
EXECUTE bh_lc_stmt;
DEALLOCATE PREPARE bh_lc_stmt;

SET @bh_lc_add_frequency = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_listing_discovery_terms' AND COLUMN_NAME='frequency_hours')=0,
  'ALTER TABLE bh_listing_discovery_terms ADD COLUMN frequency_hours SMALLINT UNSIGNED NOT NULL DEFAULT 4 AFTER search_keywords',
  'SELECT 1'
);
PREPARE bh_lc_stmt FROM @bh_lc_add_frequency;
EXECUTE bh_lc_stmt;
DEALLOCATE PREPARE bh_lc_stmt;
