-- Bubba Hub migration 019: My Hub family, saved activity and planner storage.
-- CREATE TABLE IF NOT EXISTS supports fresh installations. Conditional column
-- additions upgrade older child/bump tables without touching existing records.

CREATE TABLE IF NOT EXISTS bh_children (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    gender VARCHAR(40) NULL,
    photo_path VARCHAR(500) NULL,
    date_of_birth DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_child_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_bumps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    nickname VARCHAR(120) NULL,
    photo_path VARCHAR(500) NULL,
    due_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_bump_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_saved_activities (
    user_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_planner (
    user_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    visited TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The planner API also stores each family's hidden weekdays.
CREATE TABLE IF NOT EXISTS bh_hidden_planner_days (
    user_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='name')=0,'ALTER TABLE bh_children ADD COLUMN name VARCHAR(120) NOT NULL DEFAULT \'\'','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='gender')=0,'ALTER TABLE bh_children ADD COLUMN gender VARCHAR(40) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='photo_path')=0,'ALTER TABLE bh_children ADD COLUMN photo_path VARCHAR(500) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='date_of_birth')=0,'ALTER TABLE bh_children ADD COLUMN date_of_birth DATE NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='created_at')=0,'ALTER TABLE bh_children ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_children' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE bh_children ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_bumps' AND COLUMN_NAME='nickname')=0,'ALTER TABLE bh_bumps ADD COLUMN nickname VARCHAR(120) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_bumps' AND COLUMN_NAME='photo_path')=0,'ALTER TABLE bh_bumps ADD COLUMN photo_path VARCHAR(500) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_bumps' AND COLUMN_NAME='due_date')=0,'ALTER TABLE bh_bumps ADD COLUMN due_date DATE NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_bumps' AND COLUMN_NAME='created_at')=0,'ALTER TABLE bh_bumps ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_add_column = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_bumps' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE bh_bumps ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','SELECT 1');
PREPARE bh_stmt FROM @bh_add_column; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
