-- Bubba Hub Page Manager, Stage 1
-- Apply once to the standalone Bubba Hub database (the same database used by api/db.php).
CREATE TABLE IF NOT EXISTS bh_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  content_html MEDIUMTEXT NOT NULL,
  meta_description VARCHAR(300) NOT NULL DEFAULT '',
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_pages_slug (slug),
  KEY idx_bh_pages_status_updated (status, updated_at),
  KEY idx_bh_pages_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_page_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  content_html MEDIUMTEXT NOT NULL,
  meta_description VARCHAR(300) NOT NULL DEFAULT '',
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bh_page_revisions_page_created (page_id, created_at),
  CONSTRAINT fk_bh_page_revisions_page FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
