-- 004_optional_runbook_templates.sql
-- Optional: create runbook template tables (apply only if you want DB-backed templates)
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_runbook_template` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `method` ENUM('LiftShift','Reinstall','P2V','V2V','vMotion','Decomm') NOT NULL,
  `description` TEXT NULL,
  `version` INT DEFAULT 1,
  UNIQUE KEY (`name`,`version`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_runbook_template_step` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `seq` INT NOT NULL,
  `phase` ENUM('Pre','Freeze','Cutover','Post','Backout') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `est_minutes` INT DEFAULT 5,
  INDEX (`template_id`)
) ENGINE=InnoDB;
