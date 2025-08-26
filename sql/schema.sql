CREATE TABLE report_tile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  type VARCHAR(50) NOT NULL,
  config JSON NULL,
  thumbnail_url VARCHAR(255) NULL,
  allowed_roles JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  tile_id INT NOT NULL,
  position INT NOT NULL DEFAULT 0,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  layout JSON NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT FK_user_tile_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
  CONSTRAINT FK_user_tile_report_tile FOREIGN KEY (tile_id) REFERENCES report_tile (id) ON DELETE CASCADE,
  CONSTRAINT UNQ_user_tile UNIQUE (user_id, tile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
