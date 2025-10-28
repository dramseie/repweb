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
DROP TABLE IF EXISTS `accounting_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `kind` enum('bank','expense') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounting_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_entries` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `label` varchar(255) NOT NULL,
  `amount_cents` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_acc_cat` (`category_id`),
  CONSTRAINT `fk_acc_cat` FOREIGN KEY (`category_id`) REFERENCES `accounting_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appointment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `appointment_items_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) NOT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `real_start_at` datetime DEFAULT NULL,
  `real_end_at` datetime DEFAULT NULL,
  `status` enum('booked','done','no-show','cancelled') NOT NULL DEFAULT 'booked',
  `notes_public` text DEFAULT NULL,
  `google_event_id` varchar(255) DEFAULT NULL,
  `notes_private` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_appt_time` (`start_at`),
  KEY `ix_appt_customer` (`customer_id`),
  KEY `idx_appointments_start_at` (`start_at`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=858 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_counts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `count_date` date NOT NULL,
  `total_cents` int(11) NOT NULL,
  `expected_cash_cents` int(11) NOT NULL,
  `diff_cents` int(11) NOT NULL,
  `breakdown_json` longtext NOT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_CASH_COUNTS_DATE` (`count_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `notes_public` text DEFAULT NULL,
  `notes_private` text DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `gdpr_ok` tinyint(1) NOT NULL DEFAULT 0,
  `address` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_cust_email` (`email`),
  KEY `ix_cust_name` (`last_name`,`first_name`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('product','service') NOT NULL DEFAULT 'service',
  `price_cents` int(11) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `color_hex` char(7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `item_id` int(11) NOT NULL,
  `name_snapshot` varchar(255) NOT NULL,
  `unit_price_cents` int(11) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `line_total_cents` int(11) NOT NULL,
  `timer_status` enum('idle','running','paused','finished') NOT NULL DEFAULT 'idle',
  `timer_total_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `timer_last_started_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `total_cents` int(11) NOT NULL,
  `amount_received_cents` int(11) DEFAULT NULL,
  `tip_cents` int(11) DEFAULT NULL,
  `encaisse_at` datetime DEFAULT NULL,
  `elapsed_minutes` int(11) DEFAULT NULL,
  `total_tax_cents` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `realizations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`realizations_json`)),
  `custom_items_json` longtext DEFAULT NULL,
  `customer_id` bigint(20) DEFAULT NULL,
  `appointment_id` bigint(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(255) DEFAULT NULL,
  `replacement_appointment_id` int(11) DEFAULT NULL,
  `payments_json` longtext DEFAULT NULL CHECK (json_valid(`realizations_json`)),
  `payment_method` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_realisation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_realisation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `label` varchar(120) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `colour_code` varchar(20) DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_timer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_timer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `status` enum('idle','running','paused','finished') NOT NULL DEFAULT 'idle',
  `total_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `last_started_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timer_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_timer_interval`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_timer_interval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timer_id` bigint(20) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `seconds` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timer` (`timer_id`),
  CONSTRAINT `fk_interval_timer` FOREIGN KEY (`timer_id`) REFERENCES `pos_timer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_google`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_google` (
  `user_id` int(11) NOT NULL,
  `access_token` longtext DEFAULT NULL,
  `refresh_token` longtext DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `calendar_id` varchar(255) DEFAULT NULL,
  `sync_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_accounting_entries_month`;
/*!50001 DROP VIEW IF EXISTS `v_accounting_entries_month`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_accounting_entries_month` AS SELECT
 1 AS `id`,
  1 AS `date`,
  1 AS `label`,
  1 AS `amount_cents`,
  1 AS `category_id`,
  1 AS `notes`,
  1 AS `created_at`,
  1 AS `ym` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_orders_active`;
/*!50001 DROP VIEW IF EXISTS `v_orders_active`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_orders_active` AS SELECT
 1 AS `id`,
  1 AS `created_at`,
  1 AS `total_cents`,
  1 AS `amount_received_cents`,
  1 AS `tip_cents`,
  1 AS `encaisse_at`,
  1 AS `elapsed_minutes`,
  1 AS `total_tax_cents`,
  1 AS `note`,
  1 AS `realizations_json`,
  1 AS `customer_id`,
  1 AS `appointment_id`,
  1 AS `status`,
  1 AS `revoked_at`,
  1 AS `revoked_reason`,
  1 AS `replacement_appointment_id` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_orders_lines`;
/*!50001 DROP VIEW IF EXISTS `v_orders_lines`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_orders_lines` AS SELECT
 1 AS `order_id`,
  1 AS `order_created_at`,
  1 AS `encaisse_at`,
  1 AS `status`,
  1 AS `total_cents`,
  1 AS `amount_received_cents`,
  1 AS `tip_cents`,
  1 AS `payment_method`,
  1 AS `payments_json`,
  1 AS `order_note`,
  1 AS `customer_id`,
  1 AS `customer_first_name`,
  1 AS `customer_last_name`,
  1 AS `customer_email`,
  1 AS `customer_phone`,
  1 AS `line_id`,
  1 AS `line_kind`,
  1 AS `item_id`,
  1 AS `label`,
  1 AS `qty`,
  1 AS `unit_cents`,
  1 AS `tax_rate`,
  1 AS `line_cents`,
  1 AS `tax_cents` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_accounting_entries_month`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_accounting_entries_month` AS select `e`.`id` AS `id`,`e`.`date` AS `date`,`e`.`label` AS `label`,`e`.`amount_cents` AS `amount_cents`,`e`.`category_id` AS `category_id`,`e`.`notes` AS `notes`,`e`.`created_at` AS `created_at`,date_format(`e`.`date`,'%Y-%m') AS `ym` from `accounting_entries` `e` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_orders_active`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_orders_active` AS select `orders`.`id` AS `id`,`orders`.`created_at` AS `created_at`,`orders`.`total_cents` AS `total_cents`,`orders`.`amount_received_cents` AS `amount_received_cents`,`orders`.`tip_cents` AS `tip_cents`,`orders`.`encaisse_at` AS `encaisse_at`,`orders`.`elapsed_minutes` AS `elapsed_minutes`,`orders`.`total_tax_cents` AS `total_tax_cents`,`orders`.`note` AS `note`,`orders`.`realizations_json` AS `realizations_json`,`orders`.`customer_id` AS `customer_id`,`orders`.`appointment_id` AS `appointment_id`,`orders`.`status` AS `status`,`orders`.`revoked_at` AS `revoked_at`,`orders`.`revoked_reason` AS `revoked_reason`,`orders`.`replacement_appointment_id` AS `replacement_appointment_id` from `orders` where `orders`.`status` <> 'revoked' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_orders_lines`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_orders_lines` AS select `o`.`id` AS `order_id`,`o`.`created_at` AS `order_created_at`,`o`.`encaisse_at` AS `encaisse_at`,convert(`o`.`status` using utf8mb4) collate utf8mb4_unicode_ci AS `status`,`o`.`total_cents` AS `total_cents`,`o`.`amount_received_cents` AS `amount_received_cents`,`o`.`tip_cents` AS `tip_cents`,convert(`o`.`payment_method` using utf8mb4) collate utf8mb4_unicode_ci AS `payment_method`,convert(`o`.`payments_json` using utf8mb4) collate utf8mb4_unicode_ci AS `payments_json`,convert(`o`.`note` using utf8mb4) collate utf8mb4_unicode_ci AS `order_note`,`o`.`customer_id` AS `customer_id`,convert(`c`.`first_name` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_first_name`,convert(`c`.`last_name` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_last_name`,convert(`c`.`email` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_email`,convert(`c`.`phone` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_phone`,`oi`.`id` AS `line_id`,cast('catalog' as char charset utf8mb4) collate utf8mb4_unicode_ci AS `line_kind`,`oi`.`item_id` AS `item_id`,convert(`oi`.`name_snapshot` using utf8mb4) collate utf8mb4_unicode_ci AS `label`,`oi`.`qty` AS `qty`,`oi`.`unit_price_cents` AS `unit_cents`,`oi`.`tax_rate` AS `tax_rate`,`oi`.`line_total_cents` AS `line_cents`,NULL AS `tax_cents` from ((`ongleri`.`orders` `o` join `ongleri`.`order_items` `oi` on(`oi`.`order_id` = `o`.`id`)) left join `ongleri`.`customers` `c` on(`c`.`id` = `o`.`customer_id`)) union all select `o`.`id` AS `order_id`,`o`.`created_at` AS `order_created_at`,`o`.`encaisse_at` AS `encaisse_at`,convert(`o`.`status` using utf8mb4) collate utf8mb4_unicode_ci AS `status`,`o`.`total_cents` AS `total_cents`,`o`.`amount_received_cents` AS `amount_received_cents`,`o`.`tip_cents` AS `tip_cents`,convert(`o`.`payment_method` using utf8mb4) collate utf8mb4_unicode_ci AS `payment_method`,convert(`o`.`payments_json` using utf8mb4) collate utf8mb4_unicode_ci AS `payments_json`,convert(`o`.`note` using utf8mb4) collate utf8mb4_unicode_ci AS `order_note`,`o`.`customer_id` AS `customer_id`,convert(`c`.`first_name` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_first_name`,convert(`c`.`last_name` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_last_name`,convert(`c`.`email` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_email`,convert(`c`.`phone` using utf8mb4) collate utf8mb4_unicode_ci AS `customer_phone`,NULL AS `line_id`,cast('custom' as char charset utf8mb4) collate utf8mb4_unicode_ci AS `line_kind`,NULL AS `item_id`,convert(`jt`.`label` using utf8mb4) collate utf8mb4_unicode_ci AS `label`,coalesce(`jt`.`qty`,1) AS `qty`,coalesce(`jt`.`unit_cents`,0) AS `unit_cents`,coalesce(`jt`.`tax_rate`,0.00) AS `tax_rate`,coalesce(`jt`.`line_cents`,coalesce(`jt`.`qty`,1) * coalesce(`jt`.`unit_cents`,0)) AS `line_cents`,coalesce(`jt`.`tax_cents`,0) AS `tax_cents` from ((`ongleri`.`orders` `o` left join `ongleri`.`customers` `c` on(`c`.`id` = `o`.`customer_id`)) join JSON_TABLE(`o`.`custom_items_json`, '$[*]' COLUMNS (`label` varchar(255) PATH '$.label', `unit_cents` int(11) PATH '$.unit_cents', `qty` int(11) PATH '$.qty', `tax_rate` decimal(5,2) PATH '$.tax_rate', `line_cents` int(11) PATH '$.line_cents', `tax_cents` int(11) PATH '$.tax_cents')) `jt`) where json_valid(`o`.`custom_items_json`) */;
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

