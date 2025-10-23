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
DROP TABLE IF EXISTS `attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attribute` (
  `attribute_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `data_type` enum('string','integer','decimal','boolean','datetime','json') NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`attribute_id`),
  UNIQUE KEY `tenant_id` (`tenant_id`,`name`),
  CONSTRAINT `attribute_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_relation` (
  `rel_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) NOT NULL,
  `rel_type_id` bigint(20) NOT NULL,
  `parent_entity_id` bigint(20) NOT NULL,
  `child_entity_id` bigint(20) NOT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_to` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`rel_id`),
  UNIQUE KEY `tenant_id` (`tenant_id`,`rel_type_id`,`parent_entity_id`,`child_entity_id`),
  KEY `ix_rel_parent` (`parent_entity_id`),
  KEY `ix_rel_child` (`child_entity_id`),
  KEY `ix_rel_type` (`rel_type_id`),
  CONSTRAINT `fk_rel_child` FOREIGN KEY (`child_entity_id`) REFERENCES `entity` (`entity_id`),
  CONSTRAINT `fk_rel_parent` FOREIGN KEY (`parent_entity_id`) REFERENCES `entity` (`entity_id`),
  CONSTRAINT `fk_rel_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`),
  CONSTRAINT `fk_rel_type` FOREIGN KEY (`rel_type_id`) REFERENCES `eav_relation_type` (`rel_type_id`),
  CONSTRAINT `chk_rel_not_self` CHECK (`parent_entity_id` <> `child_entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_relation_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_relation_type` (
  `rel_type_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_directed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`rel_type_id`),
  UNIQUE KEY `tenant_id` (`tenant_id`,`name`),
  CONSTRAINT `fk_reltype_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_value` (
  `value_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `entity_id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `value_string` text DEFAULT NULL,
  `value_integer` bigint(20) DEFAULT NULL,
  `value_decimal` decimal(20,6) DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_datetime` datetime DEFAULT NULL,
  `value_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`value_id`),
  UNIQUE KEY `entity_id` (`entity_id`,`attribute_id`),
  KEY `ix_eav_value_entity` (`entity_id`),
  KEY `ix_eav_value_attr` (`attribute_id`),
  CONSTRAINT `eav_value_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`entity_id`),
  CONSTRAINT `eav_value_ibfk_2` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`attribute_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity` (
  `entity_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) NOT NULL,
  `type_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`entity_id`),
  KEY `type_id` (`type_id`),
  KEY `ix_entity_tenant_type` (`tenant_id`,`type_id`),
  CONSTRAINT `entity_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`),
  CONSTRAINT `entity_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `entity_type` (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entity_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_type` (
  `type_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `tenant_id` (`tenant_id`,`name`),
  CONSTRAINT `entity_type_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant` (
  `tenant_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `type_attribute` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type_id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_type_attribute_type_attr` (`type_id`,`attribute_id`),
  KEY `attribute_id` (`attribute_id`),
  CONSTRAINT `type_attribute_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `entity_type` (`type_id`),
  CONSTRAINT `type_attribute_ibfk_2` FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`attribute_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_cmdb_server`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb_server`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb_server` AS SELECT
 1 AS `entity_id`,
  1 AS `tenant_id`,
  1 AS `type_id`,
  1 AS `entity_name`,
  1 AS `hostname`,
  1 AS `ip_address`,
  1 AS `serial_num`,
  1 AS `cpu_count`,
  1 AS `ram_gb`,
  1 AS `os_version` */;
SET character_set_client = @saved_cs_client;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_create_views` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_create_views`(IN p_tenant VARCHAR(255))
BEGIN
  DECLARE v_tenant_id   BIGINT;
  DECLARE v_type_id     BIGINT;
  DECLARE v_tenant_name VARCHAR(255);
  DECLARE v_type_name   VARCHAR(255);
  DECLARE v_tenant_slug VARCHAR(255);
  DECLARE v_type_slug   VARCHAR(255);
  DECLARE v_view_name   VARCHAR(255);
  DECLARE v_i INT DEFAULT 0;
  DECLARE v_n INT DEFAULT 0;

  -- Build a worklist of (tenant_id, type_id, tenant_name, type_name)
  DROP TEMPORARY TABLE IF EXISTS work_types;
  CREATE TEMPORARY TABLE work_types (
    tenant_id   BIGINT NOT NULL,
    type_id     BIGINT NOT NULL,
    tenant_name VARCHAR(255) NOT NULL,
    type_name   VARCHAR(255) NOT NULL,
    PRIMARY KEY (tenant_id, type_id)
  );

  IF LOWER(p_tenant) = 'all' THEN
    INSERT INTO work_types
      SELECT t.tenant_id, et.type_id, t.name, et.name
      FROM entity_type et
      JOIN tenant t ON t.tenant_id = et.tenant_id;
  ELSE
    INSERT INTO work_types
      SELECT t.tenant_id, et.type_id, t.name, et.name
      FROM entity_type et
      JOIN tenant t ON t.tenant_id = et.tenant_id
      WHERE LOWER(t.name) = LOWER(p_tenant);
  END IF;

  SELECT COUNT(*) INTO v_n FROM work_types;

  WHILE v_n > 0 AND v_i < v_n DO
    -- Fetch the i-th row of the worklist
    SELECT tenant_id, type_id, tenant_name, type_name
      INTO v_tenant_id, v_type_id, v_tenant_name, v_type_name
    FROM work_types
    ORDER BY tenant_id, type_id
    LIMIT v_i, 1;

    -- Slugify tenant/type names for a safe view name
    SET v_tenant_slug = LOWER(v_tenant_name);
    SET v_tenant_slug = REPLACE(v_tenant_slug,' ','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'-','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'/','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'\\','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'.','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'(','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,')','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,'&','and');
    SET v_tenant_slug = REPLACE(v_tenant_slug,':','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,',','_');
    SET v_tenant_slug = REPLACE(v_tenant_slug,';','_');
    SET v_tenant_slug = TRIM(BOTH '_' FROM v_tenant_slug);

    SET v_type_slug = LOWER(v_type_name);
    SET v_type_slug = REPLACE(v_type_slug,' ','_');
    SET v_type_slug = REPLACE(v_type_slug,'-','_');
    SET v_type_slug = REPLACE(v_type_slug,'/','_');
    SET v_type_slug = REPLACE(v_type_slug,'\\','_');
    SET v_type_slug = REPLACE(v_type_slug,'.','_');
    SET v_type_slug = REPLACE(v_type_slug,'(','_');
    SET v_type_slug = REPLACE(v_type_slug,')','_');
    SET v_type_slug = REPLACE(v_type_slug,'&','and');
    SET v_type_slug = REPLACE(v_type_slug,':','_');
    SET v_type_slug = REPLACE(v_type_slug,',','_');
    SET v_type_slug = REPLACE(v_type_slug,';','_');
    SET v_type_slug = TRIM(BOTH '_' FROM v_type_slug);

    SET v_view_name = CONCAT('v_', v_tenant_slug, '_', v_type_slug);

    -- Build pivot columns for this type (choose correct value_* by data_type)
    SELECT GROUP_CONCAT(
             CONCAT(
               'MAX(CASE WHEN v.attribute_id = ', a.attribute_id, ' THEN ',
               CASE a.data_type
                 WHEN 'string'   THEN 'v.value_string'
                 WHEN 'integer'  THEN 'v.value_integer'
                 WHEN 'decimal'  THEN 'v.value_decimal'
                 WHEN 'boolean'  THEN 'v.value_boolean'
                 WHEN 'datetime' THEN 'v.value_datetime'
                 WHEN 'json'     THEN 'v.value_json'
                 ELSE 'v.value_string'
               END,
               ' END) AS `',
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                 a.name,' ','_'),'-','_'),'/','_'),'.','_'),'(','_'),')','_'),'&','and'),':','_'),
               '`'
             )
             ORDER BY COALESCE(ta.sort_order,0), a.name
             SEPARATOR ', '
           )
      INTO @cols
      FROM type_attribute ta
      JOIN attribute a ON a.attribute_id = ta.attribute_id
      WHERE ta.type_id = v_type_id;

    SET @cols = IFNULL(@cols, '');

    -- Create/replace the view
    SET @sql = CONCAT(
      'CREATE OR REPLACE VIEW `', v_view_name, '` AS ',
      'SELECT e.entity_id, e.tenant_id, e.type_id, e.name AS entity_name',
      IF(@cols <> '', CONCAT(', ', @cols), ''),
      ' FROM entity e ',
      'LEFT JOIN eav_value v ON v.entity_id = e.entity_id ',
      'WHERE e.tenant_id = ', v_tenant_id, ' AND e.type_id = ', v_type_id, ' ',
      'GROUP BY e.entity_id, e.tenant_id, e.type_id, e.name'
    );

    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    SET v_i = v_i + 1;
  END WHILE;

  DROP TEMPORARY TABLE IF EXISTS work_types;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_show_table` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_show_table`(IN p_tenant VARCHAR(255), IN p_type VARCHAR(255))
BEGIN
  DECLARE v_tenant_id BIGINT;
  DECLARE v_type_id   BIGINT;

  -- Resolve tenant/type -> IDs
  SELECT t.tenant_id INTO v_tenant_id
  FROM tenant t WHERE LOWER(t.name) = LOWER(p_tenant)
  LIMIT 1;

  IF v_tenant_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tenant not found';
  END IF;

  SELECT et.type_id INTO v_type_id
  FROM entity_type et
  WHERE et.tenant_id = v_tenant_id AND LOWER(et.name) = LOWER(p_type)
  LIMIT 1;

  IF v_type_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Type not found for this tenant';
  END IF;

  -- Build dynamic pivot columns (choose correct value_* by attribute.data_type)
  SELECT GROUP_CONCAT(
           CONCAT(
             'MAX(CASE WHEN v.attribute_id = ', a.attribute_id, ' THEN ',
             CASE a.data_type
               WHEN 'string'   THEN 'v.value_string'
               WHEN 'integer'  THEN 'v.value_integer'
               WHEN 'decimal'  THEN 'v.value_decimal'
               WHEN 'boolean'  THEN 'v.value_boolean'
               WHEN 'datetime' THEN 'v.value_datetime'
               WHEN 'json'     THEN 'v.value_json'
               ELSE 'v.value_string'
             END,
             ' END) AS `',
             REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
               a.name,' ','_'),'-','_'),'/','_'),'.','_'),'(','_'),')','_'),'&','and'),':','_'),
             '`'
           )
           ORDER BY COALESCE(ta.sort_order,0), a.name
           SEPARATOR ', '
         )
    INTO @cols
    FROM type_attribute ta
    JOIN attribute a ON a.attribute_id = ta.attribute_id
    WHERE ta.type_id = v_type_id;

  SET @cols = IFNULL(@cols, '');

  -- Compose and execute the dynamic SELECT (returns a result set)
  SET @sql = CONCAT(
    'SELECT e.entity_id, e.tenant_id, e.type_id, e.name AS entity_name',
    IF(@cols <> '', CONCAT(', ', @cols), ''),
    ' FROM entity e',
    ' LEFT JOIN eav_value v ON v.entity_id = e.entity_id',
    ' WHERE e.tenant_id = ', v_tenant_id, ' AND e.type_id = ', v_type_id,
    ' GROUP BY e.entity_id, e.tenant_id, e.type_id, e.name',
    ' ORDER BY e.entity_id'
  );

  PREPARE stmt FROM @sql;
  EXECUTE stmt;
  DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `v_cmdb_server`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb_server` AS select `e`.`entity_id` AS `entity_id`,`e`.`tenant_id` AS `tenant_id`,`e`.`type_id` AS `type_id`,`e`.`name` AS `entity_name`,max(case when `v`.`attribute_id` = 1 then `v`.`value_string` end) AS `hostname`,max(case when `v`.`attribute_id` = 2 then `v`.`value_string` end) AS `ip_address`,max(case when `v`.`attribute_id` = 3 then `v`.`value_string` end) AS `serial_num`,max(case when `v`.`attribute_id` = 4 then `v`.`value_integer` end) AS `cpu_count`,max(case when `v`.`attribute_id` = 5 then `v`.`value_decimal` end) AS `ram_gb`,max(case when `v`.`attribute_id` = 6 then `v`.`value_string` end) AS `os_version` from (`entity` `e` left join `eav_value` `v` on(`v`.`entity_id` = `e`.`entity_id`)) where `e`.`tenant_id` = 1 and `e`.`type_id` = 1 group by `e`.`entity_id`,`e`.`tenant_id`,`e`.`type_id`,`e`.`name` */;
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

