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
DROP TABLE IF EXISTS `FAT_CMDB`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `FAT_CMDB` (
  `CI_Type_Short` text DEFAULT NULL,
  `CI_Subtype_Short_Name` text DEFAULT NULL,
  `CI_Product` text DEFAULT NULL,
  `CI_Mandant` text DEFAULT NULL,
  `CI_CHM_Controlled` text DEFAULT NULL,
  `CI_UCMDB_ID` text DEFAULT NULL,
  `CI_UCMDB_Ext_ID` text DEFAULT NULL,
  `CI_ID` text DEFAULT NULL,
  `CI_Name` text DEFAULT NULL,
  `CI_Search_Code` text DEFAULT NULL,
  `CI_Status` text DEFAULT NULL,
  `CI_Group` text DEFAULT NULL,
  `CI_Main_Service` text DEFAULT NULL,
  `CI_Provider_Organisation` text DEFAULT NULL,
  `CI_Old_Search_Code` text DEFAULT NULL,
  `CI_OS` text DEFAULT NULL,
  `CI_Qualified` text DEFAULT NULL,
  `CI_DMZ` text DEFAULT NULL,
  `CI_Server_Type` text DEFAULT NULL,
  `CI_Version` text DEFAULT NULL,
  `CI_Created_Date_Time` datetime DEFAULT NULL,
  `CI_Created_By_ID` text DEFAULT NULL,
  `CI_Created_By_Name_CI` text DEFAULT NULL,
  `CI_Created_By_Name_Contacts` text DEFAULT NULL,
  `CI_Modification_Date_Time` datetime DEFAULT NULL,
  `CI_Modified_By` text DEFAULT NULL,
  `CI_Technical_Owner_Name` text DEFAULT NULL,
  `CI_Technical_Owner_Deputy_Name` text DEFAULT NULL,
  `CI_Location` text DEFAULT NULL,
  `CI_Site` text DEFAULT NULL,
  `CI_Building` text DEFAULT NULL,
  `CI_Room` text DEFAULT NULL,
  `CI_Default_Assignment_Group_Active_Flag` text DEFAULT NULL,
  `CI_Change_Owner_Group` text DEFAULT NULL,
  `CI_Change_Owner_Group_Active_Flag` text DEFAULT NULL,
  `CI_IT_Quality_Group` text DEFAULT NULL,
  `CI_IT_Quality_Group_Active_Flag` text DEFAULT NULL,
  `CI_Admin_Group` text DEFAULT NULL,
  `CI_Admin_Group_Active_Flag` text DEFAULT NULL,
  `CI_Default_Assignment_Group` text DEFAULT NULL,
  `CI_Business_Quality_Group` text DEFAULT NULL,
  `CI_Coordinator_Group` text DEFAULT NULL,
  `CI_Coordinator_Group_Active_Flag` text DEFAULT NULL,
  `CI_Task_Implementor_Group` text DEFAULT NULL,
  `CI_Task_Implementor_Group_Active_Flag` text DEFAULT NULL,
  `CI_Business_Approval_Group` text DEFAULT NULL,
  `CI_Business_Approval_Group_Active_Flag` text DEFAULT NULL,
  `CI_Infrastructure_CAB_Group` text DEFAULT NULL,
  `CI_Infrastructure_CAB_Group_Active_Flag` text DEFAULT NULL,
  `CI_Global_CAB_Group` text DEFAULT NULL,
  `CI_Global_CAB_Group_Active_Flag` text DEFAULT NULL,
  `CI_Visibility_Type` smallint(6) DEFAULT NULL,
  `CI_Usage` text DEFAULT NULL,
  `CI_Support_Remarks` longtext DEFAULT NULL,
  `CI_Serial_Number` text DEFAULT NULL,
  `CI_Console_IP_Address` text DEFAULT NULL,
  `CI_Purchase_Date` datetime DEFAULT NULL,
  `CI_Order_Number` text DEFAULT NULL,
  `CI_Deployment_Site` text DEFAULT NULL,
  `CI_License_Type` text DEFAULT NULL,
  `CI_License_Owner` text DEFAULT NULL,
  `CI_License_Information` text DEFAULT NULL,
  `CI_Install_Mode` text DEFAULT NULL,
  `CI_Synchronized_Rollout` text DEFAULT NULL,
  `CI_Roaming` text DEFAULT NULL,
  `CI_Distribution_Technology` text DEFAULT NULL,
  `CI_Package_Category` text DEFAULT NULL,
  `CI_Range_Enabled` text DEFAULT NULL,
  `CI_Requesting_Site` text DEFAULT NULL,
  `CI_Pilot_Site` text DEFAULT NULL,
  `CI_OQ_Sites` text DEFAULT NULL,
  `CI_Package_Reboot` text DEFAULT NULL,
  `CI_Package_Status` text DEFAULT NULL,
  `CI_Win7_Bit_Version` text DEFAULT NULL,
  `CI_Description` longtext DEFAULT NULL,
  `CI_Gxp_Relevance` text DEFAULT NULL,
  `CI_Gxp_Relevant_Flg` text DEFAULT NULL,
  `CI_Personal_Data_Flg` text DEFAULT NULL,
  `CI_Compliance_Comments` longtext DEFAULT NULL,
  `CI_Risk_Factor` text DEFAULT NULL,
  `CI_Unit_Responsible_ID` text DEFAULT NULL,
  `CI_Unit_Responsible_Deputy_ID` text DEFAULT NULL,
  `CI_Application_Type` text DEFAULT NULL,
  `CI_Business_Criticality` smallint(6) DEFAULT NULL,
  `CI_Time_Zone` text DEFAULT NULL,
  `CI_Operational_Service_Level` text DEFAULT NULL,
  `CI_Service_Availability_Hours` text DEFAULT NULL,
  `CI_Environment` text DEFAULT NULL,
  `CI_Complexity` text DEFAULT NULL,
  `CI_Lifecycle` text DEFAULT NULL,
  `CI_Fully_Qualified_Name` text DEFAULT NULL,
  `CI_Model` text DEFAULT NULL,
  `CI_Friendly_Name` text DEFAULT NULL,
  `CI_Rack_ID` text DEFAULT NULL,
  `CI_Primary_IP_Address` text DEFAULT NULL,
  `CI_Reporting_Field1` text DEFAULT NULL,
  `CI_Reporting_Field2` text DEFAULT NULL,
  `CI_Vendor` text DEFAULT NULL,
  `CI_Patching_Wave` text DEFAULT NULL,
  `CI_Patching_Comments` text DEFAULT NULL,
  `CI_Reboot_Comments` text DEFAULT NULL,
  `CI_Managed_By_Name` text DEFAULT NULL,
  `CI_Managed_By_Departament` text DEFAULT NULL,
  `CI_Managed_By_ID` text DEFAULT NULL,
  `CI_Hardware_Model_Manufacturer` text DEFAULT NULL,
  `CI_Hardware_Model_Name_External` text DEFAULT NULL,
  `CI_Hardware_Model_Name_Internal` text DEFAULT NULL,
  `CI_Roche_Hardware_Model` text DEFAULT NULL,
  `CI_Software_Model_Manufacturer` text DEFAULT NULL,
  `CI_Software_Model_Name_External` text DEFAULT NULL,
  `CI_Software_Model_Name_Internal` text DEFAULT NULL,
  `CI_Roche_Software_Model` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `FAT_CMDBPath`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `FAT_CMDBPath` (
  `ci_search_code_path_start` text DEFAULT NULL,
  `ci_type_path_start` text DEFAULT NULL,
  `ci_status_path_start` text DEFAULT NULL,
  `ci_search_code_path_end` text DEFAULT NULL,
  `ci_type_path_end` text DEFAULT NULL,
  `ci_status_path_end` text DEFAULT NULL,
  `ci_path` text DEFAULT NULL,
  `ci_path_direction_flag` text DEFAULT NULL,
  `ci_path_level` text DEFAULT NULL,
  `path_status` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `FAT_CMDBRelAll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `FAT_CMDBRelAll` (
  `ParentCI` varchar(510) DEFAULT NULL,
  `ParentType` varchar(510) DEFAULT NULL,
  `ParentStatus` varchar(510) DEFAULT NULL,
  `Relation` varchar(510) DEFAULT NULL,
  `ChildCI` varchar(510) DEFAULT NULL,
  `ChildType` varchar(510) DEFAULT NULL,
  `ChildStatus` varchar(510) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cmk_hosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cmk_hosts` (
  `name` varchar(128) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `plugin_output` text DEFAULT NULL,
  `last_check` int(10) unsigned DEFAULT NULL,
  `acknowledged` tinyint(4) DEFAULT 0,
  `in_downtime` tinyint(4) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`),
  KEY `idx_cmk_hosts_state` (`state`),
  KEY `idx_cmk_hosts_last_check` (`last_check`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cmk_hosts_v`;
/*!50001 DROP VIEW IF EXISTS `cmk_hosts_v`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `cmk_hosts_v` AS SELECT
 1 AS `host_name`,
  1 AS `state`,
  1 AS `plugin_output`,
  1 AS `last_check`,
  1 AS `acknowledged`,
  1 AS `in_downtime`,
  1 AS `updated_at`,
  1 AS `last_check_dt` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `cmk_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cmk_notifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ts` datetime NOT NULL,
  `site` varchar(64) NOT NULL,
  `notification_type` varchar(32) DEFAULT NULL,
  `host` varchar(128) DEFAULT NULL,
  `service` varchar(255) DEFAULT NULL,
  `host_state` varchar(16) DEFAULT NULL,
  `host_state_id` tinyint(4) DEFAULT NULL,
  `service_state` varchar(16) DEFAULT NULL,
  `service_state_id` tinyint(4) DEFAULT NULL,
  `output` text DEFAULT NULL,
  `long_output` text DEFAULT NULL,
  `contact` varchar(128) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notification_author` varchar(128) DEFAULT NULL,
  `notification_comment` text DEFAULT NULL,
  `what` varchar(16) DEFAULT NULL,
  `host_address` varchar(64) DEFAULT NULL,
  `attempt` smallint(6) DEFAULT NULL,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_json`)),
  PRIMARY KEY (`id`),
  KEY `idx_cmk_notif_ts` (`ts`),
  KEY `idx_cmk_notif_host` (`host`),
  KEY `idx_cmk_notif_service` (`service`),
  KEY `idx_cmk_notif_type` (`notification_type`),
  KEY `idx_cmk_notif_what` (`what`),
  KEY `idx_cmk_notif_attempt` (`attempt`),
  KEY `idx_cmk_notif_hostaddr` (`host_address`)
) ENGINE=InnoDB AUTO_INCREMENT=1663 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cmk_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cmk_services` (
  `host_name` varchar(128) NOT NULL,
  `description` varchar(255) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `plugin_output` text DEFAULT NULL,
  `last_check` int(10) unsigned DEFAULT NULL,
  `acknowledged` tinyint(4) DEFAULT 0,
  `in_downtime` tinyint(4) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`host_name`,`description`),
  KEY `idx_cmk_services_state` (`state`),
  KEY `idx_cmk_services_last_check` (`last_check`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cmk_services_v`;
/*!50001 DROP VIEW IF EXISTS `cmk_services_v`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `cmk_services_v` AS SELECT
 1 AS `host_name`,
  1 AS `description`,
  1 AS `state`,
  1 AS `plugin_output`,
  1 AS `last_check`,
  1 AS `acknowledged`,
  1 AS `in_downtime`,
  1 AS `updated_at`,
  1 AS `last_check_dt` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `importLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `importLog` (
  `ilid` int(11) NOT NULL AUTO_INCREMENT,
  `ilprocessgroup` varchar(255) DEFAULT NULL,
  `ilcount` text DEFAULT NULL,
  `ilts` datetime DEFAULT NULL,
  PRIMARY KEY (`ilid`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `json_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `json_import` (
  `ji_source` varchar(255) NOT NULL,
  `ji_key` varchar(255) NOT NULL,
  `ji_json` longblob DEFAULT NULL,
  `ji_ts` datetime DEFAULT NULL,
  PRIMARY KEY (`ji_source`,`ji_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `servicenow_incident`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicenow_incident` (
  `sys_id` varchar(32) NOT NULL,
  `number` varchar(50) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `sys_created_on` datetime DEFAULT NULL,
  `sys_updated_on` datetime DEFAULT NULL,
  `opened_by` varchar(80) DEFAULT NULL,
  `caller_id` varchar(80) DEFAULT NULL,
  `assignment_group` varchar(120) DEFAULT NULL,
  `assigned_to` varchar(80) DEFAULT NULL,
  `priority` varchar(10) DEFAULT NULL,
  `impact` varchar(10) DEFAULT NULL,
  `urgency` varchar(10) DEFAULT NULL,
  `state` varchar(10) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `subcategory` varchar(80) DEFAULT NULL,
  `close_code` varchar(80) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`sys_id`),
  KEY `idx_servicenow_incident_sys_updated_on` (`sys_updated_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sn_cmdb_ci_computer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sn_cmdb_ci_computer` (
  `sys_id` char(32) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `sys_class_name` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `asset_tag` varchar(255) DEFAULT NULL,
  `install_status` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `operational_status` int(11) DEFAULT NULL,
  `environment` varchar(100) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `sys_created_on` datetime DEFAULT NULL,
  `sys_updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`sys_id`),
  KEY `idx_cmdb_name` (`name`),
  KEY `idx_cmdb_updated` (`sys_updated_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `thermoBBQ`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thermoBBQ` (
  `DATE` datetime NOT NULL,
  `ITU` double DEFAULT NULL,
  `IHU` double DEFAULT NULL,
  `source_file` varchar(255) NOT NULL,
  PRIMARY KEY (`DATE`,`source_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weather_hourly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `weather_hourly` (
  `provider` varchar(32) NOT NULL,
  `latitude` decimal(8,5) NOT NULL,
  `longitude` decimal(8,5) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `ts_utc` datetime NOT NULL,
  `temperature_c` decimal(5,2) DEFAULT NULL,
  `rel_humidity` decimal(5,2) DEFAULT NULL,
  `wind_speed_kmh` decimal(6,2) DEFAULT NULL,
  `wind_gust_kmh` decimal(6,2) DEFAULT NULL,
  `wind_speed_ms` decimal(5,2) DEFAULT NULL,
  `wind_gust_ms` decimal(5,2) DEFAULT NULL,
  `wind_dir_deg` decimal(6,2) DEFAULT NULL,
  `precip_mm` decimal(6,2) DEFAULT NULL,
  `weathercode` int(11) DEFAULT NULL,
  `source_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`provider`,`latitude`,`longitude`,`ts_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `apply_roche_anon` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER FUNCTION `apply_roche_anon`(s LONGTEXT) RETURNS longtext CHARSET latin1 COLLATE latin1_swedish_ci
    DETERMINISTIC
BEGIN
  /* Order matters: do the most specific first */
  -- Explicit compounds
  SET s = REGEXP_REPLACE(s, '(?i)\\bROCHEINTERACT\\b', 'OrgInteract');
  SET s = REGEXP_REPLACE(s, '(?i)\\bINSTITUTO\\s+ROCHE\\b', 'Instituto Helix');
  SET s = REGEXP_REPLACE(s, '(?i)\\bROCHE\\s+TURKEY\\b', 'Helix Turkey');
  SET s = REGEXP_REPLACE(s, '(?i)\\bWARSAW\\s+DIA\\s+INTERNET\\s+ROCHE\\.PL\\b', 'Warsaw DX Internet org.pl');

  -- Core brand tokens
  SET s = REGEXP_REPLACE(s, '(?i)\\bROCHE\\b', 'Helix');

  -- Common brand/product family hints you likely want neutralized
  SET s = REGEXP_REPLACE(s, '(?i)\\bACCU[- ]?CHEK\\b', 'GlucoCheck');
  SET s = REGEXP_REPLACE(s, '(?i)\\bVENTANA\\b', 'Vistana');
  SET s = REGEXP_REPLACE(s, '(?i)\\bGENENTECH\\b', 'GenTech');
  SET s = REGEXP_REPLACE(s, '(?i)\\bGNE\\b', 'GNT');

  -- Domains / URLs
  SET s = REGEXP_REPLACE(s, '(?i)roche\\.pl', 'org.pl');

  /* Add more lines here if you spot further “Roche-smelling” tokens
     e.g.: locations (Basel, Penzberg) or business-unit keywords.
     Example (uncomment if desired):
     -- SET s = REGEXP_REPLACE(s, '(?i)\\bBASEL\\b', 'City-1');
     -- SET s = REGEXP_REPLACE(s, '(?i)\\bPENZBERG\\b', 'City-2');
  */

  RETURN s;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `cmk_hosts_v`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `cmk_hosts_v` AS select `h`.`name` AS `host_name`,`h`.`state` AS `state`,`h`.`plugin_output` AS `plugin_output`,`h`.`last_check` AS `last_check`,`h`.`acknowledged` AS `acknowledged`,`h`.`in_downtime` AS `in_downtime`,`h`.`updated_at` AS `updated_at`,from_unixtime(`h`.`last_check`) AS `last_check_dt` from `cmk_hosts` `h` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `cmk_services_v`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `cmk_services_v` AS select `s`.`host_name` AS `host_name`,`s`.`description` AS `description`,`s`.`state` AS `state`,`s`.`plugin_output` AS `plugin_output`,`s`.`last_check` AS `last_check`,`s`.`acknowledged` AS `acknowledged`,`s`.`in_downtime` AS `in_downtime`,`s`.`updated_at` AS `updated_at`,from_unixtime(`s`.`last_check`) AS `last_check_dt` from `cmk_services` `s` */;
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

