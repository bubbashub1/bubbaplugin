-- Bubba Hub migration 007: authentication rate-limit storage.
-- Apply before deploying the fail-closed authentication rate limiter.
CREATE TABLE IF NOT EXISTS bh_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bucket_time (bucket, created_at),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
