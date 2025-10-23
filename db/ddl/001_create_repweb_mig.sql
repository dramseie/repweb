-- 001_create_repweb_mig.sql
CREATE SCHEMA IF NOT EXISTS `repweb_mig`;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_program` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('Active','Archived') DEFAULT 'Active',
  `created_at` DATETIME NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  INDEX (`tenant_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_wave` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `program_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `start_at` DATETIME NULL,
  `end_at` DATETIME NULL,
  `status` ENUM('Planned','InProgress','Completed','Archived') DEFAULT 'Planned',
  INDEX (`program_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_move_group` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `wave_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `priority` INT DEFAULT 100,
  `status` ENUM('Planned','InProgress','Completed') DEFAULT 'Planned',
  INDEX (`wave_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_migration` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `move_group_id` BIGINT UNSIGNED NOT NULL,
  `ci_entity_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `type` ENUM('App','DB','Infra','Network','Storage','Other') NOT NULL,
  `state` ENUM('Draft','Planned','Approved','Scheduled','InProgress','Completed','Validated','Closed','OnHold','Rejected','RolledBack') NOT NULL DEFAULT 'Draft',
  `planned_start` DATETIME NULL,
  `planned_end` DATETIME NULL,
  `actual_start` DATETIME NULL,
  `actual_end` DATETIME NULL,
  `backout_possible` TINYINT(1) DEFAULT 1,
  `risk_level` ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
  `owner_user_id` BIGINT UNSIGNED NOT NULL,
  `change_ref` VARCHAR(120) NULL,
  `notes` TEXT NULL,
  INDEX (`move_group_id`),
  INDEX (`ci_entity_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_dependency` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `from_migration_id` BIGINT UNSIGNED NOT NULL,
  `to_migration_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('blocks','requires','soft') DEFAULT 'requires',
  INDEX (`from_migration_id`),
  INDEX (`to_migration_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_runbook_step` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration_id` BIGINT UNSIGNED NOT NULL,
  `seq` INT NOT NULL,
  `phase` ENUM('Pre','Freeze','Cutover','Post','Backout') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `assignee_user_id` BIGINT UNSIGNED NULL,
  `est_minutes` INT DEFAULT 5,
  `status` ENUM('Pending','Ready','InProgress','Done','Skipped','Failed','BackedOut') DEFAULT 'Pending',
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `output` MEDIUMTEXT NULL,
  INDEX (`migration_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_checklist` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration_id` BIGINT UNSIGNED NOT NULL,
  `gate` ENUM('Readiness','CAB','PreCutover','PostCutover','Validation') NOT NULL,
  `item` VARCHAR(255) NOT NULL,
  `required` TINYINT(1) DEFAULT 1,
  `checked_by` BIGINT UNSIGNED NULL,
  `checked_at` DATETIME NULL,
  `status` ENUM('Open','Passed','Failed','N/A') DEFAULT 'Open',
  INDEX (`migration_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_approval` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('Owner','CAB','Security','Network','DBA','Business') NOT NULL,
  `approver_user_id` BIGINT UNSIGNED NOT NULL,
  `outcome` ENUM('Requested','Approved','Rejected') DEFAULT 'Requested',
  `comment` TEXT NULL,
  `decided_at` DATETIME NULL,
  INDEX (`migration_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_window` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `program_id` BIGINT UNSIGNED NOT NULL,
  `kind` ENUM('Maintenance','Freeze','Blackout') NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `capacity_total` INT DEFAULT 0,
  `resource_matrix` JSON NULL,
  `description` VARCHAR(255) NULL,
  INDEX (`program_id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_mig`.`mig_event` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration_id` BIGINT UNSIGNED NOT NULL,
  `etype` ENUM('Planned','Approved','Scheduled','Start','StepStart','StepDone','Rollback','Completed','Validated','Closed','Alert') NOT NULL,
  `payload` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX (`migration_id`),
  INDEX (`etype`)
) ENGINE=InnoDB;
