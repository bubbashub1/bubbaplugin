-- Bubba Hub migration 021: Planner Pro state storage.
-- The planner API expects this table to exist; public requests must not run DDL.
CREATE TABLE IF NOT EXISTS bh_planner_pro_state (
    user_id BIGINT UNSIGNED NOT NULL,
    state_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
