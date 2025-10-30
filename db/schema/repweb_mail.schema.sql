-- Schema for mail client (repweb_mig schema)

CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_mail` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` VARCHAR(255) DEFAULT NULL,
  `mailbox` VARCHAR(16) NOT NULL DEFAULT 'inbound',
  `subject` VARCHAR(500) DEFAULT NULL,
  `from_address` VARCHAR(255) DEFAULT NULL,
  `to_addresses` JSON NOT NULL,
  `cc_addresses` JSON DEFAULT NULL,
  `bcc_addresses` JSON DEFAULT NULL,
  `body_html` MEDIUMTEXT DEFAULT NULL,
  `body_text` MEDIUMTEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `template_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'received',
  `error` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_id` (`message_id`),
  INDEX `idx_mailbox_created` (`mailbox`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_mail_attachment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mail_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) DEFAULT NULL,
  `content_type` VARCHAR(255) DEFAULT NULL,
  `size` INT UNSIGNED DEFAULT NULL,
  `data` LONGBLOB DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_mail_id` (`mail_id`),
  CONSTRAINT `fk_mail_attachment_mail` FOREIGN KEY (`mail_id`) REFERENCES `repweb_mig`.`mig_mail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_mail_template` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body_html` MEDIUMTEXT DEFAULT NULL,
  `body_text` MEDIUMTEXT DEFAULT NULL,
  `default_from` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_template_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_mail_sendlog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mail_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_sendlog_mail` (`mail_id`),
  CONSTRAINT `fk_sendlog_mail` FOREIGN KEY (`mail_id`) REFERENCES `repweb_mig`.`mig_mail` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - `to_addresses`, `cc_addresses`, `bcc_addresses`, and `tags` are JSON columns and can be queried with JSON_EXTRACT.
-- - Attachments are stored as LONGBLOB in `mig_mail_attachment.data`.
-- - `message_id` is stored when available (helps deduplicate IMAP fetches).
-- - Use appropriate permissions and ensure `php-imap` is available for IMAP polling.
