-- Bubba Hub migration 006: persistent security review register.
-- Apply manually through the reviewed database migration process before enabling review updates.
CREATE TABLE IF NOT EXISTS bh_security_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    check_key VARCHAR(64) NOT NULL,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(500) NOT NULL,
    severity ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_progress','verified','accepted_risk') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    reviewed_by VARCHAR(120) NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bh_security_reviews_check_key (check_key),
    KEY idx_bh_security_reviews_status_severity (status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bh_security_reviews (check_key,title,description,severity,status) VALUES
('rate-limits','Login rate limiting','Confirm failed-login throttling is enforced even when the rate-limit store is unavailable.','high','open'),
('permissions','Role and record ownership','Verify family, child, organiser and leader ownership checks on every read and write endpoint.','critical','open'),
('csrf','CSRF protection','Review every state-changing request, including admin actions, for CSRF and origin validation.','high','open'),
('uploads','File uploads','Check file type, size, generated filenames, storage location and executable-file blocking.','high','open'),
('secrets','Secrets and configuration','Confirm credentials and private configuration are outside the public web root and never committed.','critical','open'),
('migrations','Database migrations','Confirm schema changes run through reviewed, versioned migrations rather than normal visitor requests.','medium','open'),
('backups','Backups and restore','Verify encrypted/off-site backups and periodically test a restore.','high','open'),
('deployment','Deployment SSH trust','Pin the hosting SSH host key; do not disable strict host-key verification.','high','open'),
('logging','Error handling and audit logs','Confirm public responses hide internals while private logs capture security-relevant events without secrets.','medium','open'),
('dependencies','Dependencies and patching','Review PHP, Composer and front-end dependencies for known vulnerabilities and supported versions.','medium','open')
ON DUPLICATE KEY UPDATE
    title=VALUES(title),
    description=VALUES(description);
