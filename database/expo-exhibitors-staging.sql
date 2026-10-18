-- Apply once to STAGING database before enabling the exhibitor form.
CREATE TABLE IF NOT EXISTS bh_expo_tables (
 table_number TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO bh_expo_tables(table_number) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),
(14),(15),(16),(17),(18),(19),(20),(21),(22),(23),(24),(25),(26);
CREATE TABLE IF NOT EXISTS bh_expo_exhibitors (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_name VARCHAR(150) NOT NULL,
 contact_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(30) NOT NULL,
 description TEXT NOT NULL,
 table_number TINYINT UNSIGNED NOT NULL,
 status ENUM('approved','pending_review','rejected','cancelled') NOT NULL,
 details_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_status (status),
 INDEX idx_table_status (table_number,status),
 CONSTRAINT fk_expo_table FOREIGN KEY (table_number) REFERENCES bh_expo_tables(table_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- IMPORTANT: Table capacity is provisional pending venue floor plan.
-- Before going live, add DB-enforced uniqueness for active reservations or
-- a single row per table allocation; transaction row locks currently serialize writes.
