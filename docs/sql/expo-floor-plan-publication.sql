-- Run on staging first; do not expose public plan before admin approval.
CREATE TABLE IF NOT EXISTS bh_expo_floor_plan_publication (
 event_key VARCHAR(80) NOT NULL PRIMARY KEY,
 published TINYINT(1) NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
