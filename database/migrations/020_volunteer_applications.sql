-- Bubba Hub migration 020: regional volunteer application storage.
CREATE TABLE IF NOT EXISTS bh_volunteer_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    town VARCHAR(120) NOT NULL,
    region VARCHAR(120) NOT NULL,
    preferred_role VARCHAR(100) NOT NULL,
    availability TEXT NOT NULL,
    motivation TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    consent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
