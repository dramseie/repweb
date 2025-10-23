-- 003_create_repweb_pi.sql
CREATE SCHEMA IF NOT EXISTS `repweb_pi`;
CREATE TABLE IF NOT EXISTS `repweb_pi`.`progress_indicator` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `program_id` BIGINT UNSIGNED NULL,
  `scope` ENUM('global','program','wave','move_group','migration') NOT NULL DEFAULT 'program',
  `scope_id` BIGINT UNSIGNED NULL,
  `indicator_key` VARCHAR(120) NOT NULL,
  `value_json` JSON NOT NULL,
  `window` VARCHAR(32) NULL,
  `computed_at` DATETIME NOT NULL,
  `version` INT DEFAULT 1,
  `notes` TEXT NULL,
  UNIQUE KEY `uq_idx` (`program_id`,`scope`,`scope_id`,`indicator_key`,`window`)
) ENGINE=InnoDB;
