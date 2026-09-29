-- Leader support expertise
-- Leaders can opt in to private family support topics. Public FAQs remain separate.
CREATE TABLE IF NOT EXISTS bh_leader_expertise (
  organiser_id BIGINT UNSIGNED NOT NULL,
  topic_key VARCHAR(80) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organiser_id, topic_key),
  INDEX idx_leader_expertise_topic (topic_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
