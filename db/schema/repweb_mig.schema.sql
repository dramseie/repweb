/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `mig_approval`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_approval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_id` bigint(20) unsigned NOT NULL,
  `role` enum('Owner','CAB','Security','Network','DBA','Business') NOT NULL,
  `approver_user_id` bigint(20) unsigned NOT NULL,
  `outcome` enum('Requested','Approved','Rejected') DEFAULT 'Requested',
  `comment` text DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `migration_id` (`migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_checklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_checklist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_id` bigint(20) unsigned NOT NULL,
  `gate` enum('Readiness','CAB','PreCutover','PostCutover','Validation') NOT NULL,
  `item` varchar(255) NOT NULL,
  `required` tinyint(1) DEFAULT 1,
  `checked_by` bigint(20) unsigned DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `status` enum('Open','Passed','Failed','N/A') DEFAULT 'Open',
  PRIMARY KEY (`id`),
  KEY `migration_id` (`migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_container`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_container` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wave_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wave_id` (`wave_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_dependency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_dependency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_migration_id` bigint(20) unsigned NOT NULL,
  `to_migration_id` bigint(20) unsigned NOT NULL,
  `type` enum('blocks','requires','soft') DEFAULT 'requires',
  PRIMARY KEY (`id`),
  KEY `from_migration_id` (`from_migration_id`),
  KEY `to_migration_id` (`to_migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_id` bigint(20) unsigned NOT NULL,
  `etype` enum('Planned','Approved','Scheduled','Start','StepStart','StepDone','Rollback','Completed','Validated','Closed','Alert') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `migration_id` (`migration_id`),
  KEY `etype` (`etype`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_migration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_migration` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `move_group_id` bigint(20) unsigned NOT NULL,
  `ci_entity_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `type` enum('App','DB','Infra','Network','Storage','Other') NOT NULL,
  `state` enum('Draft','Planned','Approved','Scheduled','InProgress','Completed','Validated','Closed','OnHold','Rejected','RolledBack') NOT NULL DEFAULT 'Draft',
  `planned_start` datetime DEFAULT NULL,
  `planned_end` datetime DEFAULT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_end` datetime DEFAULT NULL,
  `backout_possible` tinyint(1) DEFAULT 1,
  `risk_level` enum('Low','Medium','High','Critical') DEFAULT 'Low',
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `change_ref` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `move_group_id` (`move_group_id`),
  KEY `ci_entity_id` (`ci_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_move_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_move_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wave_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `priority` int(11) DEFAULT 100,
  `status` enum('Planned','InProgress','Completed') DEFAULT 'Planned',
  PRIMARY KEY (`id`),
  KEY `wave_id` (`wave_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_program`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_program` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Archived') DEFAULT 'Active',
  `created_at` datetime NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_project` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Archived') DEFAULT 'Active',
  `created_at` datetime NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_runbook_step`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_runbook_step` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_id` bigint(20) unsigned NOT NULL,
  `seq` int(11) NOT NULL,
  `phase` enum('Pre','Freeze','Cutover','Post','Backout') NOT NULL,
  `title` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `assignee_user_id` bigint(20) unsigned DEFAULT NULL,
  `est_minutes` int(11) DEFAULT 5,
  `status` enum('Pending','Ready','InProgress','Done','Skipped','Failed','BackedOut') DEFAULT 'Pending',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `output` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `migration_id` (`migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_runbook_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_runbook_template` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `method` enum('LiftShift','Reinstall','P2V','V2V','vMotion','Decomm') NOT NULL,
  `description` text DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`version`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_runbook_template_step`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_runbook_template_step` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint(20) unsigned NOT NULL,
  `seq` int(11) NOT NULL,
  `phase` enum('Pre','Freeze','Cutover','Post','Backout') NOT NULL,
  `title` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `est_minutes` int(11) DEFAULT 5,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_server`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_server` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `container_id` bigint(20) unsigned NOT NULL,
  `hostname` varchar(200) NOT NULL,
  `application` varchar(200) DEFAULT NULL,
  `method` enum('LiftShift','Reinstall','P2V','V2V','vMotion','Decomm') NOT NULL DEFAULT 'LiftShift',
  `ci_entity_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_container_host` (`container_id`,`hostname`),
  KEY `application` (`application`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_slot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `window_id` bigint(20) unsigned NOT NULL,
  `label` varchar(120) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `capacity` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_slot_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_slot_assignment` (
  `slot_id` bigint(20) unsigned NOT NULL,
  `server_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`slot_id`,`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_wave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_wave` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('Planned','InProgress','Completed','Archived') DEFAULT 'Planned',
  PRIMARY KEY (`id`),
  KEY `program_id` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_wave2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_wave2` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('Planned','InProgress','Completed','Archived') DEFAULT 'Planned',
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mig_window`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mig_window` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `kind` enum('Maintenance','Freeze','Blackout') NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `capacity_total` int(11) DEFAULT 0,
  `resource_matrix` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resource_matrix`)),
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `program_id` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

