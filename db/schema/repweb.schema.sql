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
DROP TABLE IF EXISTS `app_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) DEFAULT NULL,
  `datatable_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datatable_columns`)),
  `grafana_dashboards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`grafana_dashboards`)),
  `grafana_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_88BDF3E9E7927C74` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currency` (
  `code` char(3) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distribution_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distribution_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distribution_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distribution_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list_id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `display_name` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `list_id` (`list_id`),
  KEY `email` (`email`),
  CONSTRAINT `fk_dm_list` FOREIGN KEY (`list_id`) REFERENCES `distribution_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dt_saved_filter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dt_saved_filter` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `table_key` varchar(128) NOT NULL,
  `name` varchar(160) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `owner_id` varchar(191) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`details_json`)),
  `state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`state_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table_public` (`table_key`,`is_public`),
  KEY `idx_table_owner` (`table_key`,`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ea_entity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ea_entity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ea_property` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `json` longtext NOT NULL,
  `tenant_code` varchar(64) DEFAULT NULL,
  `owner_email` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frw_catalog_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frw_catalog_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `unit` enum('vm','gb','device','flat','percent','none') NOT NULL DEFAULT 'none',
  `base_price_cents` bigint(20) NOT NULL DEFAULT 0,
  `formula_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`formula_json`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frw_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frw_lookup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `type` varchar(64) NOT NULL,
  `code` varchar(64) NOT NULL,
  `label` varchar(128) NOT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_frw_lookup` (`tenant_id`,`type`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frw_price_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frw_price_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rules_json`)),
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frw_run`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frw_run` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'draft',
  `answers_json` longtext NOT NULL,
  `pricing_breakdown_json` longtext DEFAULT NULL,
  `total_cents` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `fk_frw_run_template` FOREIGN KEY (`template_id`) REFERENCES `frw_template` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frw_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frw_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `version` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `schema_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fx_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fx_rate` (
  `as_of_date` date NOT NULL,
  `from_ccy` char(3) NOT NULL,
  `to_ccy` char(3) NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `source` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`as_of_date`,`from_ccy`,`to_ccy`),
  KEY `fk_fx_from` (`from_ccy`),
  KEY `fk_fx_to` (`to_ccy`),
  CONSTRAINT `fk_fx_from` FOREIGN KEY (`from_ccy`) REFERENCES `currency` (`code`),
  CONSTRAINT `fk_fx_to` FOREIGN KEY (`to_ccy`) REFERENCES `currency` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_events_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_events_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `calendar_id` varchar(255) NOT NULL DEFAULT 'primary',
  `google_event_id` varchar(255) NOT NULL,
  `etag` varchar(255) DEFAULT NULL,
  `updated_at_utc` datetime DEFAULT NULL,
  `appointment_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_cal_event` (`user_id`,`calendar_id`,`google_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=788 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_oauth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_oauth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` int(11) NOT NULL,
  `scope` text DEFAULT NULL,
  `token_type` varchar(32) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_google_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `type` enum('report','file') NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `format` enum('csv','excel','json') DEFAULT 'csv',
  `is_link` tinyint(1) NOT NULL DEFAULT 0,
  `is_public_link` tinyint(1) NOT NULL DEFAULT 0,
  `filename_override` varchar(190) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `fk_att_template` FOREIGN KEY (`template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_job`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_job` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('queued','running','sent','failed') NOT NULL DEFAULT 'queued',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_job_template` (`template_id`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`),
  CONSTRAINT `fk_job_template` FOREIGN KEY (`template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Europe/Paris',
  `years` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`years`)),
  `months` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`months`)),
  `month_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`month_days`)),
  `weekdays` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weekdays`)),
  `hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hours`)),
  `minute` int(11) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `next_run_at` datetime DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `enabled` (`enabled`),
  KEY `next_run_at` (`next_run_at`),
  CONSTRAINT `fk_sch_template` FOREIGN KEY (`template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `from_email` varchar(190) NOT NULL,
  `reply_to` varchar(190) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `to_addresses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`to_addresses`)),
  `cc_addresses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cc_addresses`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_template_distribution_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_template_distribution_list` (
  `template_id` int(11) NOT NULL,
  `list_id` int(11) NOT NULL,
  PRIMARY KEY (`template_id`,`list_id`),
  KEY `fk_tpl_list_list` (`list_id`),
  CONSTRAINT `fk_tpl_list_list` FOREIGN KEY (`list_id`) REFERENCES `distribution_list` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpl_list_template` FOREIGN KEY (`template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `route_params` longtext DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `divider_before` tinyint(1) NOT NULL DEFAULT 0,
  `mega_group` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `roles` longtext DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `badge` varchar(32) DEFAULT NULL,
  `external` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `IDX_D754D550727ACA70` (`parent_id`),
  CONSTRAINT `FK_D754D550727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `menu_item` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1029 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_quote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_quote` (
  `quote_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `quote_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`request_json`)),
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`result_json`)),
  `customer_ref` varchar(128) DEFAULT NULL,
  `tenant` varchar(64) DEFAULT NULL,
  `who` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `psr_project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `psr_project` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `weather_trend` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `rag_overall` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `progress_pct` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_psr_project_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `psr_project_snap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `psr_project_snap` (
  `version_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `weather_trend` tinyint(3) unsigned NOT NULL,
  `rag_overall` tinyint(3) unsigned NOT NULL,
  `progress_pct` tinyint(3) unsigned NOT NULL,
  `snap_at` datetime NOT NULL,
  PRIMARY KEY (`version_id`,`project_id`),
  KEY `idx_pps_project` (`project_id`),
  CONSTRAINT `fk_pps_version` FOREIGN KEY (`version_id`) REFERENCES `psr_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `psr_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `psr_task` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `wbs_code` varchar(64) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `rag` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `progress_pct` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task_project` (`project_id`),
  KEY `idx_task_parent` (`parent_id`),
  CONSTRAINT `fk_task_parent` FOREIGN KEY (`parent_id`) REFERENCES `psr_task` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_project` FOREIGN KEY (`project_id`) REFERENCES `psr_project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `psr_task_snap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `psr_task_snap` (
  `version_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `wbs_code` varchar(64) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `rag` tinyint(3) unsigned NOT NULL,
  `progress_pct` tinyint(3) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL,
  `snap_at` datetime NOT NULL,
  PRIMARY KEY (`version_id`,`task_id`),
  KEY `idx_pts_project` (`project_id`),
  KEY `idx_pts_parent` (`parent_id`),
  CONSTRAINT `fk_pts_version` FOREIGN KEY (`version_id`) REFERENCES `psr_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `psr_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `psr_version` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(120) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_version_label` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_answer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_answer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `response_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `field_id` bigint(20) unsigned DEFAULT NULL,
  `value_text` longtext DEFAULT NULL,
  `value_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resp_item` (`response_id`,`item_id`),
  KEY `fk_ans_item` (`item_id`),
  KEY `fk_ans_field` (`field_id`),
  CONSTRAINT `fk_ans_field` FOREIGN KEY (`field_id`) REFERENCES `qw_field` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ans_item` FOREIGN KEY (`item_id`) REFERENCES `qw_item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ans_resp` FOREIGN KEY (`response_id`) REFERENCES `qw_response` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_attachment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `response_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `field_id` bigint(20) unsigned DEFAULT NULL,
  `storage_path` varchar(512) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(127) DEFAULT NULL,
  `size_bytes` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resp` (`response_id`),
  CONSTRAINT `fk_att_resp` FOREIGN KEY (`response_id`) REFERENCES `qw_response` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_ci`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_ci` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ci_key` varchar(191) NOT NULL,
  `ci_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_ci` (`tenant_id`,`ci_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_field` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint(20) unsigned NOT NULL,
  `ui_type` enum('input','textarea','wysiwyg','select','multiselect','radio','checkbox','slider','color','date','time','daterange','integer','autocomplete','chainselect','image','file','voice','video','toggle') NOT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `default_value` text DEFAULT NULL,
  `min_value` double DEFAULT NULL,
  `max_value` double DEFAULT NULL,
  `step_value` double DEFAULT NULL,
  `validation_regex` varchar(255) DEFAULT NULL,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `options_sql` text DEFAULT NULL,
  `chain_sql` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`chain_sql`)),
  `accept_mime` varchar(255) DEFAULT NULL,
  `max_size_mb` int(11) DEFAULT NULL,
  `unique_key` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_type` (`ui_type`),
  CONSTRAINT `fk_field_item` FOREIGN KEY (`item_id`) REFERENCES `qw_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `questionnaire_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('header','question') NOT NULL,
  `title` varchar(255) NOT NULL,
  `help` text DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `outline` varchar(64) DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `visible_when` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visible_when`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_q_parent_sort` (`questionnaire_id`,`parent_id`,`sort`),
  KEY `idx_type` (`type`),
  KEY `fk_item_parent` (`parent_id`),
  CONSTRAINT `fk_item_parent` FOREIGN KEY (`parent_id`) REFERENCES `qw_item` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_q` FOREIGN KEY (`questionnaire_id`) REFERENCES `qw_questionnaire` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_questionnaire`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_questionnaire` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ci_id` bigint(20) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','in_review','approved','archived') NOT NULL DEFAULT 'draft',
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `approver_user_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_code` (`tenant_id`,`code`),
  KEY `idx_ci` (`ci_id`),
  CONSTRAINT `fk_qw_q_ci` FOREIGN KEY (`ci_id`) REFERENCES `qw_ci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_questionnaire_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_questionnaire_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `questionnaire_id` bigint(20) unsigned NOT NULL,
  `version` int(11) NOT NULL,
  `payload` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qv` (`questionnaire_id`,`version`),
  CONSTRAINT `fk_snap_q` FOREIGN KEY (`questionnaire_id`) REFERENCES `qw_questionnaire` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_response` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `questionnaire_id` bigint(20) unsigned NOT NULL,
  `submitted_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('in_progress','submitted','approved','rejected') NOT NULL DEFAULT 'in_progress',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qr_q` (`questionnaire_id`),
  KEY `idx_qr_status` (`status`),
  CONSTRAINT `fk_resp_q` FOREIGN KEY (`questionnaire_id`) REFERENCES `qw_questionnaire` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qw_workflow_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qw_workflow_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `questionnaire_id` bigint(20) unsigned NOT NULL,
  `response_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned NOT NULL,
  `action` enum('submit','request_changes','approve','reject','reopen','archive') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_q` (`questionnaire_id`),
  KEY `idx_r` (`response_id`),
  CONSTRAINT `fk_wf_q` FOREIGN KEY (`questionnaire_id`) REFERENCES `qw_questionnaire` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report` (
  `repid` int(11) NOT NULL AUTO_INCREMENT,
  `reptype` varchar(255) DEFAULT NULL,
  `repshort` varchar(50) DEFAULT NULL,
  `reptitle` varchar(255) DEFAULT NULL,
  `repdesc` longtext DEFAULT NULL,
  `repsql` longtext DEFAULT NULL,
  `repparam` longtext DEFAULT NULL,
  `repowner` varchar(255) DEFAULT NULL,
  `repts` datetime DEFAULT NULL,
  PRIMARY KEY (`repid`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `data` longtext NOT NULL,
  `owner` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_presets_unique` (`report_id`,`name`,`owner`),
  KEY `idx_report_presets_list` (`report_id`,`owner`,`updated_at`),
  KEY `idx_report_presets_name` (`report_id`,`name`),
  CONSTRAINT `fk_report_presets_report` FOREIGN KEY (`report_id`) REFERENCES `report` (`repid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_tile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_tile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `allowed_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_roles`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repweb_lucky_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repweb_lucky_numbers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `game` varchar(32) NOT NULL,
  `numbers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`numbers_json`)),
  `bonus_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bonus_json`)),
  `seed` varchar(64) DEFAULT NULL,
  `requester` varchar(128) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_game_created` (`game`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rest_connector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rest_connector` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `base_url` varchar(255) NOT NULL,
  `default_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_headers`)),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rest_endpoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rest_endpoint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connector_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL DEFAULT 'GET',
  `sample_query` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sample_query`)),
  `sample_body` longtext DEFAULT NULL,
  `json_path_bookmarks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`json_path_bookmarks`)),
  `label` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rest_endpoint_connector` (`connector_id`),
  CONSTRAINT `fk_rest_endpoint_connector` FOREIGN KEY (`connector_id`) REFERENCES `rest_connector` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `share_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `share_link` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `resource_type` varchar(64) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `format` enum('csv','excel','json') DEFAULT 'csv',
  `filename` varchar(190) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `token_2` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ui_widget_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ui_widget_instance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tab_id` int(11) NOT NULL,
  `widget_type` varchar(80) NOT NULL,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config_json`)),
  `position` int(11) NOT NULL DEFAULT 0,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `w` int(11) NOT NULL DEFAULT 4,
  `h` int(11) NOT NULL DEFAULT 3,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_widget_tab` (`tab_id`),
  CONSTRAINT `fk_widget_tab` FOREIGN KEY (`tab_id`) REFERENCES `ui_widget_tab` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ui_widget_tab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ui_widget_tab` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_user_id` int(11) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(120) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_json`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_owner_title` (`owner_user_id`,`title`),
  UNIQUE KEY `uniq_system_code` (`code`,`is_system`),
  KEY `idx_ui_widget_tab_owner_order` (`owner_user_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `widget_layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`widget_layout`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_rc_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_rc_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(64) NOT NULL,
  `room_name` varchar(120) NOT NULL,
  `room_type` char(1) NOT NULL DEFAULT 'c',
  `notify` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_room` (`user_id`,`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_tile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_tile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tile_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `pinned` tinyint(1) NOT NULL,
  `layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_C0EE5CDA76ED395638AF48B` (`user_id`,`tile_id`),
  KEY `IDX_C0EE5CD638AF48B` (`tile_id`),
  CONSTRAINT `FK_user_tile_report_tile` FOREIGN KEY (`tile_id`) REFERENCES `report_tile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_user_tile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_fx_rate_latest`;
/*!50001 DROP VIEW IF EXISTS `v_fx_rate_latest`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_fx_rate_latest` AS SELECT
 1 AS `from_ccy`,
  1 AS `to_ccy`,
  1 AS `rate`,
  1 AS `as_of_date`,
  1 AS `source` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vat_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vat_rate` (
  `country_code` char(2) NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'standard',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `rate_pct` decimal(6,3) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`country_code`,`category`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `psr_take_snapshot` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `psr_take_snapshot`(IN p_label VARCHAR(120), IN p_note VARCHAR(500), IN p_user VARCHAR(120))
BEGIN
  DECLARE v_version_id BIGINT UNSIGNED;

  INSERT INTO psr_version(label, note, created_by) VALUES (p_label, p_note, p_user);
  SET v_version_id = LAST_INSERT_ID();

  INSERT INTO psr_project_snap(version_id, project_id, name, description, weather_trend, rag_overall, progress_pct, snap_at)
  SELECT v_version_id, p.id, p.name, p.description, p.weather_trend, p.rag_overall, p.progress_pct, NOW()
  FROM psr_project p;

  INSERT INTO psr_task_snap(version_id, task_id, project_id, parent_id, wbs_code, name, rag, progress_pct, start_date, due_date, sort_order, snap_at)
  SELECT v_version_id, t.id, t.project_id, t.parent_id, t.wbs_code, t.name, t.rag, t.progress_pct, t.start_date, t.due_date, t.sort_order, NOW()
  FROM psr_task t;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_quote_service` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_quote_service`(
    IN  in_tenant_code    VARCHAR(64),
    IN  in_service_ci     VARCHAR(128),
    IN  in_country        CHAR(2),
    IN  in_level          VARCHAR(64),
    IN  in_usage          DECIMAL(18,6),
    IN  in_years          INT,
    IN  in_bill_ccy       CHAR(3),
    IN  in_as_of          DATE,
    IN  in_vat_included   BOOLEAN,
    IN  in_customer_ref   VARCHAR(128),
    IN  in_who            VARCHAR(128)
)
BEGIN
    /* ---------- Declarations ---------- */
    DECLARE v_err TEXT;

    -- tenant & attribute ids
    DECLARE v_tenant_id INT;
    DECLARE a_base_ccy INT; DECLARE a_base_amt INT; DECLARE a_unit INT; DECLARE a_vat_cat INT;
    DECLARE a_level_rule INT; DECLARE a_country_rule INT; DECLARE a_term_rule INT; DECLARE a_usage_rule INT;
    DECLARE a_rule_kind INT; DECLARE a_rule_payload INT;

    -- service values
    DECLARE v_base_ccy CHAR(3);
    DECLARE v_unit VARCHAR(64);
    DECLARE v_vat_cat VARCHAR(32);
    DECLARE v_base_amt DECIMAL(18,6);

    -- rule references
    DECLARE r_level_ci VARCHAR(128);
    DECLARE r_country_ci VARCHAR(128);
    DECLARE r_term_ci VARCHAR(128);
    DECLARE r_usage_ci VARCHAR(128);

    -- rule JSON payloads (as LONGTEXT)
    DECLARE j_level LONGTEXT;
    DECLARE j_country LONGTEXT;
    DECLARE j_term LONGTEXT;
    DECLARE j_usage LONGTEXT;

    -- inputs/defaults
    DECLARE v_country CHAR(2);
    DECLARE v_level   VARCHAR(64);
    DECLARE v_usage   DECIMAL(18,6);
    DECLARE v_years   INT;
    DECLARE v_bill_ccy CHAR(3);
    DECLARE v_as_of DATE;
    DECLARE v_vat_incl BOOLEAN;

    -- calc vars
    DECLARE i INT; DECLARE n INT;
    DECLARE v_level_mult DECIMAL(18,6);
    DECLARE v_country_uplift DECIMAL(18,6);
    DECLARE v_term_disc DECIMAL(18,6);
    DECLARE v_best_years INT;

    DECLARE v_remaining DECIMAL(18,6);
    DECLARE v_last_cap DECIMAL(18,6);
    DECLARE v_usage_cost DECIMAL(18,6);
    DECLARE t_cap_txt TEXT; DECLARE t_cap DECIMAL(18,6);
    DECLARE t_price DECIMAL(18,6); DECLARE v_band DECIMAL(18,6);

    DECLARE v_platform DECIMAL(18,6);
    DECLARE v_pre_uplift DECIMAL(18,6);
    DECLARE v_uplift_amt DECIMAL(18,6);
    DECLARE v_pre_disc DECIMAL(18,6);
    DECLARE v_disc_amt DECIMAL(18,6);
    DECLARE v_net_base DECIMAL(18,6);

    DECLARE v_fx DECIMAL(18,8);
    DECLARE v_net_bill DECIMAL(18,6);

    DECLARE v_vat_pct DECIMAL(6,3);
    DECLARE v_net DECIMAL(18,2);
    DECLARE v_vat DECIMAL(18,2);
    DECLARE v_gross DECIMAL(18,2);

    DECLARE j_req LONGTEXT;
    DECLARE j_res LONGTEXT;

    /* ---------- Resolve tenant & attributes (ext_eav) ---------- */
    SELECT id
      INTO v_tenant_id
      FROM ext_eav.tenants
     WHERE code = in_tenant_code
     LIMIT 1;

    IF v_tenant_id IS NULL THEN
        SET v_err = CONCAT('Unknown tenant_code: ', in_tenant_code);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_err;
    END IF;

    SELECT id INTO a_base_ccy
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.base_currency' LIMIT 1;

    SELECT id INTO a_base_amt
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.base_amount'   LIMIT 1;

    SELECT id INTO a_unit
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.unit'          LIMIT 1;

    SELECT id INTO a_vat_cat
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.vat_category'  LIMIT 1;

    SELECT id INTO a_level_rule
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.level_rule'    LIMIT 1;

    /* 🔧 the line below had the missing space before */
    SELECT id INTO a_country_rule
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.country_rule'  LIMIT 1;

    SELECT id INTO a_term_rule
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.term_rule'     LIMIT 1;

    SELECT id INTO a_usage_rule
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='pricing.usage_rule'    LIMIT 1;

    SELECT id INTO a_rule_kind
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='rule.kind'             LIMIT 1;

    SELECT id INTO a_rule_payload
      FROM ext_eav.attributes
     WHERE tenant_id=v_tenant_id AND code='rule.payload'          LIMIT 1;

    /* ---------- Load service values (ext_eav) ---------- */
    SELECT value INTO v_base_ccy
      FROM ext_eav.eav_values_string
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_base_ccy AND n=1
     LIMIT 1;

    SELECT value INTO v_unit
      FROM ext_eav.eav_values_string
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_unit AND n=1
     LIMIT 1;

    SELECT value INTO v_vat_cat
      FROM ext_eav.eav_values_string
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_vat_cat AND n=1
     LIMIT 1;

    SELECT value INTO v_base_amt
      FROM ext_eav.eav_values_decimal
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_base_amt AND n=1
     LIMIT 1;

    IF v_base_ccy IS NULL OR v_base_amt IS NULL THEN
        SET v_err = 'Service missing base currency or base amount';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_err;
    END IF;

    -- rule references
    SELECT target_ci INTO r_level_ci
      FROM ext_eav.eav_values_reference
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_level_rule AND n=1
     LIMIT 1;

    SELECT target_ci INTO r_country_ci
      FROM ext_eav.eav_values_reference
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_country_rule AND n=1
     LIMIT 1;

    SELECT target_ci INTO r_term_ci
      FROM ext_eav.eav_values_reference
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_term_rule AND n=1
     LIMIT 1;

    SELECT target_ci INTO r_usage_ci
      FROM ext_eav.eav_values_reference
     WHERE tenant_id=v_tenant_id AND entity_ci=in_service_ci
       AND attribute_id=a_usage_rule AND n=1
     LIMIT 1;

    /* ---------- Load rule payloads (ext_eav) ---------- */
    SELECT value INTO j_level
      FROM ext_eav.eav_values_json
     WHERE tenant_id=v_tenant_id AND entity_ci=r_level_ci
       AND attribute_id=a_rule_payload AND n=1
     LIMIT 1;

    SELECT value INTO j_country
      FROM ext_eav.eav_values_json
     WHERE tenant_id=v_tenant_id AND entity_ci=r_country_ci
       AND attribute_id=a_rule_payload AND n=1
     LIMIT 1;

    SELECT value INTO j_term
      FROM ext_eav.eav_values_json
     WHERE tenant_id=v_tenant_id AND entity_ci=r_term_ci
       AND attribute_id=a_rule_payload AND n=1
     LIMIT 1;

    SELECT value INTO j_usage
      FROM ext_eav.eav_values_json
     WHERE tenant_id=v_tenant_id AND entity_ci=r_usage_ci
       AND attribute_id=a_rule_payload AND n=1
     LIMIT 1;

    /* ---------- Inputs / defaults ---------- */
    SET v_country  = UPPER(in_country);
    SET v_level    = in_level;
    SET v_usage    = IFNULL(in_usage,0);
    SET v_years    = IFNULL(in_years,1);
    SET v_bill_ccy = IFNULL(NULLIF(in_bill_ccy,''), v_base_ccy);
    SET v_as_of    = in_as_of;
    SET v_vat_incl = IFNULL(in_vat_included, 0);
    IF v_vat_cat IS NULL OR v_vat_cat='' THEN SET v_vat_cat = 'standard'; END IF;

    /* ---------- Apply rules ---------- */
    -- level multiplier
    SET v_level_mult = 1.0;
    SET i = 0; SET n = IFNULL(JSON_LENGTH(j_level),0);
    WHILE i < n DO
      IF LOWER(JSON_UNQUOTE(JSON_EXTRACT(j_level, CONCAT('$[',i,'].level')))) = LOWER(v_level) THEN
         SET v_level_mult = CAST(JSON_UNQUOTE(JSON_EXTRACT(j_level, CONCAT('$[',i,'].multiplier'))) AS DECIMAL(18,6));
         SET i = n;
      ELSE
         SET i = i + 1;
      END IF;
    END WHILE;

    -- country uplift
    SET v_country_uplift = 0.0;
    SET i = 0; SET n = IFNULL(JSON_LENGTH(j_country),0);
    WHILE i < n DO
      IF UPPER(JSON_UNQUOTE(JSON_EXTRACT(j_country, CONCAT('$[',i,'].country')))) = v_country THEN
         SET v_country_uplift = CAST(JSON_UNQUOTE(JSON_EXTRACT(j_country, CONCAT('$[',i,'].uplift_pct'))) AS DECIMAL(18,6));
         SET i = n;
      ELSE
         SET i = i + 1;
      END IF;
    END WHILE;

    -- term discount
    SET v_term_disc = 0.0; SET v_best_years = 0;
    SET i = 0; SET n = IFNULL(JSON_LENGTH(j_term),0);
    WHILE i < n DO
      SET t_cap_txt = JSON_UNQUOTE(JSON_EXTRACT(j_term, CONCAT('$[',i,'].years')));
      IF t_cap_txt IS NOT NULL THEN
         SET t_cap = CAST(t_cap_txt AS SIGNED);
         SET t_price = CAST(JSON_UNQUOTE(JSON_EXTRACT(j_term, CONCAT('$[',i,'].discount_pct'))) AS DECIMAL(18,6));
         IF t_cap <= v_years AND t_cap >= v_best_years THEN
            SET v_best_years = t_cap; SET v_term_disc = t_price;
         END IF;
      END IF;
      SET i = i + 1;
    END WHILE;

    -- usage tiers
    SET v_remaining = v_usage; SET v_last_cap = 0.0; SET v_usage_cost = 0.0;
    SET i = 0; SET n = IFNULL(JSON_LENGTH(j_usage),0);
    WHILE i < n AND v_remaining > 0 DO
      SET t_cap_txt = JSON_UNQUOTE(JSON_EXTRACT(j_usage, CONCAT('$[',i,'].up_to')));
      SET t_price = CAST(JSON_UNQUOTE(JSON_EXTRACT(j_usage, CONCAT('$[',i,'].unit_price'))) AS DECIMAL(18,6));
      IF t_cap_txt IS NULL OR t_cap_txt = 'null' THEN
         SET v_band = v_remaining;
         SET v_usage_cost = v_usage_cost + v_band * t_price;
         SET v_remaining = 0;
      ELSE
         SET t_cap = CAST(t_cap_txt AS DECIMAL(18,6));
         SET v_band = LEAST(GREATEST(v_remaining,0), t_cap - v_last_cap);
         IF v_band > 0 THEN
            SET v_usage_cost = v_usage_cost + v_band * t_price;
            SET v_remaining = v_remaining - v_band;
            SET v_last_cap  = t_cap;
         END IF;
      END IF;
      SET i = i + 1;
    END WHILE;

    /* ---------- Subtotal ---------- */
    SET v_platform   = v_base_amt * v_level_mult;
    SET v_pre_uplift = v_platform + v_usage_cost;
    SET v_uplift_amt = v_pre_uplift * (v_country_uplift/100.0);
    SET v_pre_disc   = v_pre_uplift + v_uplift_amt;
    SET v_disc_amt   = v_pre_disc * (v_term_disc/100.0);
    SET v_net_base   = v_pre_disc - v_disc_amt;

    /* ---------- FX (repweb) ---------- */
    SET v_fx = 1.0;
    IF v_bill_ccy <> v_base_ccy THEN
        IF v_as_of IS NOT NULL THEN
            SELECT rate INTO v_fx
              FROM repweb.fx_rate
             WHERE as_of_date <= v_as_of AND from_ccy=v_base_ccy AND to_ccy=v_bill_ccy
             ORDER BY as_of_date DESC LIMIT 1;
        ELSE
            SELECT rate INTO v_fx
              FROM repweb.v_fx_rate_latest
             WHERE from_ccy=v_base_ccy AND to_ccy=v_bill_ccy
             LIMIT 1;
        END IF;

        IF v_fx IS NULL THEN
            SET v_err = CONCAT('FX rate missing ', v_base_ccy, '->', v_bill_ccy);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_err;
        END IF;
    END IF;

    SET v_net_bill = v_net_base * v_fx;

    /* ---------- VAT (repweb) ---------- */
    IF v_as_of IS NOT NULL THEN
        SELECT rate_pct INTO v_vat_pct
          FROM repweb.vat_rate
         WHERE country_code=v_country AND category=v_vat_cat
           AND valid_from<=v_as_of AND (valid_to IS NULL OR valid_to>=v_as_of)
         ORDER BY valid_from DESC LIMIT 1;
    ELSE
        SELECT rate_pct INTO v_vat_pct
          FROM repweb.v_vat_rate_latest
         WHERE country_code=v_country AND category=v_vat_cat
         LIMIT 1;
    END IF;

    IF v_vat_pct IS NULL THEN
        SET v_err = CONCAT('VAT rate missing for ', v_country, '/', v_vat_cat);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_err;
    END IF;

    IF v_vat_incl THEN
        SET v_gross = ROUND(v_net_bill,2);
        SET v_net   = ROUND(v_gross / (1.0 + v_vat_pct/100.0), 2);
        SET v_vat   = ROUND(v_gross - v_net, 2);
    ELSE
        SET v_net = ROUND(v_net_bill,2);
        SET v_vat = ROUND(v_net * (v_vat_pct/100.0), 2);
        SET v_gross = v_net + v_vat;
    END IF;

    /* ---------- Store & return JSON ---------- */
    SET j_req = JSON_OBJECT(
      'service_code', in_service_ci,
      'country', v_country,
      'level', v_level,
      'usage', v_usage,
      'years', v_years,
      'bill_ccy', v_bill_ccy,
      'as_of', v_as_of,
      'vat_included', v_vat_incl,
      'tenant_code', in_tenant_code,
      'customer_ref', in_customer_ref
    );

    SET j_res = JSON_OBJECT(
      'service', JSON_OBJECT('code', in_service_ci, 'unit', v_unit),
      'inputs', JSON_OBJECT('country', v_country, 'level', v_level, 'usage', v_usage, 'years', v_years,
                            'bill_ccy', v_bill_ccy, 'as_of', v_as_of, 'vat_included', v_vat_incl),
      'calc', JSON_OBJECT('base_ccy', v_base_ccy, 'base_amount', v_base_amt,
                          'level_multiplier', v_level_mult, 'usage_cost_base', v_usage_cost,
                          'country_uplift_pct', v_country_uplift, 'term_discount_pct', v_term_disc,
                          'fx_rate', v_fx, 'vat_pct', v_vat_pct),
      'totals', JSON_OBJECT(
        'ex_vat', JSON_OBJECT('currency', v_bill_ccy, 'amount', v_net),
        'vat',    JSON_OBJECT('currency', v_bill_ccy, 'amount', v_vat),
        'inc_vat',JSON_OBJECT('currency', v_bill_ccy, 'amount', v_gross)
      )
    );

    INSERT INTO repweb.price_quote(request_json, result_json, customer_ref, tenant, who)
    VALUES (j_req, j_res, in_customer_ref, in_tenant_code, in_who);

    SELECT j_res AS quote_json;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `v_fx_rate_latest`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_fx_rate_latest` AS select `t`.`from_ccy` AS `from_ccy`,`t`.`to_ccy` AS `to_ccy`,`t`.`rate` AS `rate`,`t`.`as_of_date` AS `as_of_date`,`t`.`source` AS `source` from (select `x`.`as_of_date` AS `as_of_date`,`x`.`from_ccy` AS `from_ccy`,`x`.`to_ccy` AS `to_ccy`,`x`.`rate` AS `rate`,`x`.`source` AS `source`,row_number() over ( partition by `x`.`from_ccy`,`x`.`to_ccy` order by `x`.`as_of_date` desc) AS `rn` from `fx_rate` `x`) `t` where `t`.`rn` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

