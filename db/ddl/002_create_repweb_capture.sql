-- 002_create_repweb_capture.sql
CREATE SCHEMA IF NOT EXISTS `repweb_capture`;
CREATE TABLE IF NOT EXISTS `repweb_capture`.`landing_vcenter_vm` (
  `source_ts` DATETIME NOT NULL,
  `raw` JSON NOT NULL,
  KEY (`source_ts`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `repweb_capture`.`cur_ci` (
  `ci_key` VARCHAR(200) PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `env` VARCHAR(50) NULL,
  `owner_hint` VARCHAR(200) NULL,
  `confidence` DECIMAL(5,4) DEFAULT 0.0,
  `last_seen` DATETIME NULL,
  `source_facts` JSON NULL
) ENGINE=InnoDB;
