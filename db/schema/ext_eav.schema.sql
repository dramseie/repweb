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
DROP TABLE IF EXISTS `attribute_acl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attribute_acl` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `principal_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `permission` enum('read','write','admin') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aacl_attr` (`tenant_id`,`attribute_id`),
  KEY `idx_aacl_principal` (`tenant_id`,`principal_id`),
  KEY `idx_aacl_role` (`tenant_id`,`role_id`),
  KEY `fk_aacl_attr` (`attribute_id`),
  KEY `fk_aacl_princip` (`principal_id`),
  KEY `fk_aacl_role` (`role_id`),
  CONSTRAINT `fk_aacl_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aacl_princip` FOREIGN KEY (`principal_id`) REFERENCES `rbac_principals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aacl_role` FOREIGN KEY (`role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aacl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `label` varchar(190) NOT NULL,
  `data_type` enum('string','text','integer','decimal','boolean','datetime','json','reference','ip','cidr') NOT NULL,
  `unit` varchar(32) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_searchable` tinyint(1) NOT NULL DEFAULT 1,
  `is_indexed` tinyint(1) NOT NULL DEFAULT 0,
  `rbac_visibility` enum('public','tenant','restricted') NOT NULL DEFAULT 'tenant',
  `owner_role_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attr_tenant_code` (`tenant_id`,`code`),
  KEY `idx_attr_owner_role` (`tenant_id`,`owner_role_id`),
  KEY `fk_attr_owner_role` (`owner_role_id`),
  CONSTRAINT `fk_attr_owner_role` FOREIGN KEY (`owner_role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canvas_layouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_ref` varchar(190) NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT 'default',
  `nodes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_canvas` (`tenant_id`,`user_ref`,`name`),
  CONSTRAINT `fk_canvas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_boolean`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_boolean` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` tinyint(1) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vb` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vb_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vb_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vb_entity` (`entity_ci`),
  KEY `fk_vb_attr` (`attribute_id`),
  CONSTRAINT `fk_vb_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vb_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_cidr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_cidr` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` varchar(64) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vcidr` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vcidr_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vcidr_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vcidr_entity` (`entity_ci`),
  KEY `fk_vcidr_attr` (`attribute_id`),
  CONSTRAINT `fk_vcidr_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vcidr_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vcidr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_datetime`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_datetime` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vdt` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vdt_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vdt_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vdt_entity` (`entity_ci`),
  KEY `fk_vdt_attr` (`attribute_id`),
  CONSTRAINT `fk_vdt_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vdt_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vdt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_decimal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_decimal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` decimal(24,8) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vd` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vd_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vd_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vd_entity` (`entity_ci`),
  KEY `fk_vd_attr` (`attribute_id`),
  CONSTRAINT `fk_vd_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vd_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vd_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_integer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_integer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` bigint(20) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vi` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vi_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vi_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vi_entity` (`entity_ci`),
  KEY `fk_vi_attr` (`attribute_id`),
  CONSTRAINT `fk_vi_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vi_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_ip` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` varchar(64) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vip` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vip_lookup` (`tenant_id`,`attribute_id`,`value`),
  KEY `idx_vip_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vip_entity` (`entity_ci`),
  KEY `fk_vip_attr` (`attribute_id`),
  CONSTRAINT `fk_vip_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vip_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vip_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_json`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_json` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vjson` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vjson_attr` (`tenant_id`,`attribute_id`),
  KEY `idx_vjson_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vjson_entity` (`entity_ci`),
  KEY `fk_vjson_attr` (`attribute_id`),
  CONSTRAINT `fk_vjson_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vjson_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vjson_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_reference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_reference` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `target_ci` varchar(255) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vr` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vr_lookup` (`tenant_id`,`attribute_id`,`target_ci`),
  KEY `idx_vr_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vr_entity` (`entity_ci`),
  KEY `fk_vr_attr` (`attribute_id`),
  KEY `fk_vr_target` (`target_ci`),
  CONSTRAINT `fk_vr_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vr_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vr_target` FOREIGN KEY (`target_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_string`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_string` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` varchar(1024) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vs` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vs_lookup` (`tenant_id`,`attribute_id`,`value`(255)),
  KEY `idx_vs_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vs_entity` (`entity_ci`),
  KEY `fk_vs_attr` (`attribute_id`),
  CONSTRAINT `fk_vs_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vs_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eav_values_text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eav_values_text` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_ci` varchar(255) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `n` int(11) NOT NULL DEFAULT 1,
  `value` longtext NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vt` (`tenant_id`,`entity_ci`,`attribute_id`,`n`),
  KEY `idx_vt_attr` (`tenant_id`,`attribute_id`),
  KEY `idx_vt_entity` (`tenant_id`,`entity_ci`),
  KEY `fk_vt_entity` (`entity_ci`),
  KEY `fk_vt_attr` (`attribute_id`),
  CONSTRAINT `fk_vt_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vt_entity` FOREIGN KEY (`entity_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_vt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entities` (
  `ci` varchar(255) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_type_id` bigint(20) unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL,
  `rbac_visibility` enum('public','tenant','restricted') NOT NULL DEFAULT 'tenant',
  `owner_role_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`ci`),
  UNIQUE KEY `uq_ent_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_ent_tenant_type` (`tenant_id`,`entity_type_id`),
  KEY `idx_ent_tenant_name` (`tenant_id`,`name`),
  KEY `idx_ent_owner_role` (`tenant_id`,`owner_role_id`),
  KEY `fk_ent_type` (`entity_type_id`),
  KEY `fk_ent_owner_role` (`owner_role_id`),
  CONSTRAINT `fk_ent_owner_role` FOREIGN KEY (`owner_role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ent_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ent_type` FOREIGN KEY (`entity_type_id`) REFERENCES `entity_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entity_acl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_acl` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ci` varchar(255) NOT NULL,
  `principal_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `permission` enum('read','write','admin') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eacl_ci` (`tenant_id`,`ci`),
  KEY `idx_eacl_principal` (`tenant_id`,`principal_id`),
  KEY `idx_eacl_role` (`tenant_id`,`role_id`),
  KEY `fk_eacl_entity` (`ci`),
  KEY `fk_eacl_princip` (`principal_id`),
  KEY `fk_eacl_role` (`role_id`),
  CONSTRAINT `fk_eacl_entity` FOREIGN KEY (`ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_eacl_princip` FOREIGN KEY (`principal_id`) REFERENCES `rbac_principals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eacl_role` FOREIGN KEY (`role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eacl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `code` varchar(64) NOT NULL,
  `icon` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uq_type_tenant_name` (`tenant_id`,`name`),
  CONSTRAINT `fk_type_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `principal_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `principal_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `principal_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_role` (`tenant_id`,`principal_id`,`role_id`),
  KEY `fk_pr_principal` (`principal_id`),
  KEY `fk_pr_role` (`role_id`),
  CONSTRAINT `fk_pr_principal` FOREIGN KEY (`principal_id`) REFERENCES `rbac_principals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_role` FOREIGN KEY (`role_id`) REFERENCES `rbac_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rbac_principals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rbac_principals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type` enum('user','group','service') NOT NULL DEFAULT 'user',
  `name` varchar(190) NOT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_principal_tenant_name` (`tenant_id`,`type`,`name`),
  CONSTRAINT `fk_principals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rbac_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rbac_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `code` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_tenant_code` (`tenant_id`,`code`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `relation_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `relation_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `label` varchar(190) NOT NULL,
  `directed` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reltype` (`tenant_id`,`code`),
  CONSTRAINT `fk_reltype_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `relations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `relation_type_id` bigint(20) unsigned NOT NULL,
  `src_ci` varchar(255) NOT NULL,
  `dst_ci` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rel` (`tenant_id`,`relation_type_id`,`src_ci`,`dst_ci`),
  KEY `idx_rel_src` (`tenant_id`,`src_ci`),
  KEY `idx_rel_dst` (`tenant_id`,`dst_ci`),
  KEY `fk_rel_type` (`relation_type_id`),
  KEY `fk_rel_src` (`src_ci`),
  KEY `fk_rel_dst` (`dst_ci`),
  CONSTRAINT `fk_rel_dst` FOREIGN KEY (`dst_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_rel_src` FOREIGN KEY (`src_ci`) REFERENCES `entities` (`ci`) ON DELETE CASCADE,
  CONSTRAINT `fk_rel_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rel_type` FOREIGN KEY (`relation_type_id`) REFERENCES `relation_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `code` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `type_attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `entity_type_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `unique_per_type` tinyint(1) NOT NULL DEFAULT 0,
  `cardinality` enum('one','many') NOT NULL DEFAULT 'one',
  `default_value` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ta` (`tenant_id`,`entity_type_id`,`attribute_id`),
  KEY `idx_ta_type` (`tenant_id`,`entity_type_id`),
  KEY `idx_ta_attr` (`tenant_id`,`attribute_id`),
  KEY `fk_ta_type` (`entity_type_id`),
  KEY `fk_ta_attr` (`attribute_id`),
  CONSTRAINT `fk_ta_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_type` FOREIGN KEY (`entity_type_id`) REFERENCES `entity_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_cmdb-app_instance`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-app_instance`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-app_instance` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `ip_address`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-application`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-application`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-application` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-backup_device`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-backup_device`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-backup_device` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-environment`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-environment`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-environment` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `env_code` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-esx_host`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-esx_host`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-esx_host` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `cpu_count`,
  1 AS `ip_address`,
  1 AS `memory_gb`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-nas_storage`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-nas_storage`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-nas_storage` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-network_device`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-network_device`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-network_device` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `ip_address`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-physical_server`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-physical_server`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-physical_server` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `cpu_count`,
  1 AS `ip_address`,
  1 AS `memory_gb`,
  1 AS `model`,
  1 AS `os`,
  1 AS `serial_number`,
  1 AS `vendor` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-san_storage`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-san_storage`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-san_storage` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-san_switch`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-san_switch`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-san_switch` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `ip_address`,
  1 AS `model`,
  1 AS `serial_number`,
  1 AS `vendor`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_cmdb-vm`;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-vm`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_cmdb-vm` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `cpu_count`,
  1 AS `ip_address`,
  1 AS `memory_gb`,
  1 AS `os`,
  1 AS `version` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_loc-country`;
/*!50001 DROP VIEW IF EXISTS `v_loc-country`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_loc-country` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `country.continent`,
  1 AS `country.currency`,
  1 AS `country.eu_member`,
  1 AS `country.iso2`,
  1 AS `country.iso3`,
  1 AS `country.languages`,
  1 AS `country.name`,
  1 AS `country.timezone`,
  1 AS `country.vat_category_default` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_loc-fx_rate`;
/*!50001 DROP VIEW IF EXISTS `v_loc-fx_rate`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_loc-fx_rate` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `fx.as_of`,
  1 AS `fx.from_ccy`,
  1 AS `fx.rate`,
  1 AS `fx.source`,
  1 AS `fx.to_ccy` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_loc-geoloc`;
/*!50001 DROP VIEW IF EXISTS `v_loc-geoloc`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_loc-geoloc` AS SELECT
 1 AS `ci`,
  1 AS `name`,
  1 AS `status`,
  1 AS `address`,
  1 AS `city`,
  1 AS `country`,
  1 AS `desc`,
  1 AS `icon`,
  1 AS `lat`,
  1 AS `long` */;
SET character_set_client = @saved_cs_client;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_create_views` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_create_views`(IN p_tenant_code VARCHAR(64))
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_type_id BIGINT;
  DECLARE v_type_code VARCHAR(64);

  DECLARE cur CURSOR FOR
    SELECT et.id, et.code
    FROM entity_types et
    JOIN tenants t ON t.id=et.tenant_id
    WHERE t.code = p_tenant_code;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;

  OPEN cur;

  read_loop: LOOP
    FETCH cur INTO v_type_id, v_type_code;
    IF done THEN
      LEAVE read_loop;
    END IF;

    -- Build dynamic select for attributes of this type
    SET @sql := (
      SELECT CONCAT(
        'CREATE OR REPLACE VIEW `v_', p_tenant_code, '-', v_type_code, '` AS ',
        'SELECT e.ci, e.name, e.status, ',
        GROUP_CONCAT(
          DISTINCT
          CONCAT(
            'MAX(CASE WHEN a.code = ''', a.code, ''' THEN ev.value END) AS `', a.code, '`'
          )
          ORDER BY a.code SEPARATOR ', '
        ),
        ' FROM entities e ',
        'JOIN type_attributes ta ON ta.entity_type_id = ', v_type_id, ' AND ta.tenant_id=e.tenant_id ',
        'JOIN attributes a ON a.id=ta.attribute_id ',
        'LEFT JOIN eav_values_string ev ON ev.entity_ci=e.ci AND ev.attribute_id=a.id AND ev.tenant_id=e.tenant_id ',
        'WHERE e.entity_type_id = ', v_type_id, ' ',
        'GROUP BY e.ci, e.name, e.status'
      )
      FROM type_attributes ta
      JOIN attributes a ON a.id=ta.attribute_id
      WHERE ta.entity_type_id = v_type_id
        AND ta.tenant_id = (SELECT id FROM tenants WHERE code=p_tenant_code)
    );

    -- Execute it if we have any attributes
    IF @sql IS NOT NULL THEN
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;

  END LOOP;

  CLOSE cur;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_select_view` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_select_view`(
  IN p_tenant_code VARCHAR(64),
  IN p_entity_type_code VARCHAR(64)
)
main: BEGIN
  DECLARE v_tenant_id BIGINT UNSIGNED;
  DECLARE v_type_id   BIGINT UNSIGNED;
  DECLARE v_attr_ids  TEXT;
  DECLARE v_col_exprs TEXT;

  -- Resolve tenant & type
  SET v_tenant_id := (SELECT `id` FROM `tenants` WHERE `code`=p_tenant_code LIMIT 1);
  IF v_tenant_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown tenant code';
  END IF;

  SET v_type_id := (
    SELECT et.`id`
    FROM `entity_types` et
    WHERE et.`tenant_id`=v_tenant_id AND et.`code`=p_entity_type_code
    LIMIT 1
  );
  IF v_type_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown entity_type code for tenant';
  END IF;

  -- Build list of attribute ids and pivot expressions
  SET v_attr_ids := (
    SELECT GROUP_CONCAT(a.`id` ORDER BY a.`code`)
    FROM `type_attributes` ta
    JOIN `attributes` a ON a.`id`=ta.`attribute_id`
    WHERE ta.`tenant_id`=v_tenant_id AND ta.`entity_type_id`=v_type_id
  );

  SET v_col_exprs := (
    SELECT GROUP_CONCAT(
             CONCAT('MAX(CASE WHEN v.attribute_id=', a.`id`,
                    ' THEN v.value END) AS `', a.`code`, '`')
             ORDER BY a.`code` SEPARATOR ', '
           )
    FROM `type_attributes` ta
    JOIN `attributes` a ON a.`id`=ta.`attribute_id`
    WHERE ta.`tenant_id`=v_tenant_id AND ta.`entity_type_id`=v_type_id
  );

  -- If no attributes mapped, just return the base CI list and exit
  IF v_attr_ids IS NULL OR v_attr_ids='' THEN
    SET @sql := CONCAT(
      'SELECT e.`ci`, e.`name`, e.`status` ',
      'FROM `entities` e ',
      'WHERE e.`tenant_id`=', v_tenant_id, ' AND e.`entity_type_id`=', v_type_id, ' ',
      'ORDER BY e.`ci`'
    );
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    LEAVE main;
  END IF;

  -- UNION of all EAV value tables, normalized to (tenant_id, entity_ci, attribute_id, value)
  SET @val_sql := CONCAT(
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_string` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_text` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_integer` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_decimal` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_boolean` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, DATE_FORMAT(`value`, ''%Y-%m-%d %H:%i:%s'') ',
    'FROM `eav_values_datetime` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_json` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `target_ci` ',
    'FROM `eav_values_reference` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_ip` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_cidr` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') '
  );

  -- Final SELECT: pivot into columns (same shape as the views)
  SET @sql := CONCAT(
    'SELECT e.`ci`, e.`name`, e.`status`, ',
    v_col_exprs, ' ',
    'FROM `entities` e ',
    'LEFT JOIN (', @val_sql, ') v ',
    '  ON v.`entity_ci`=e.`ci` AND v.`tenant_id`=e.`tenant_id` ',
    'WHERE e.`tenant_id`=', v_tenant_id, ' AND e.`entity_type_id`=', v_type_id, ' ',
    'GROUP BY e.`ci`, e.`name`, e.`status` ',
    'ORDER BY e.`ci`'
  );

  PREPARE s FROM @sql;
  EXECUTE s;
  DEALLOCATE PREPARE s;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_show_sql` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_show_sql`(
  IN p_tenant_code VARCHAR(64),
  IN p_entity_type_code VARCHAR(64)
)
main: BEGIN
  DECLARE v_tenant_id BIGINT UNSIGNED;
  DECLARE v_type_id   BIGINT UNSIGNED;
  DECLARE v_attr_ids  TEXT;
  DECLARE v_col_exprs TEXT;

  /* avoid truncation of large generated SQL */
  SET SESSION group_concat_max_len = 1024 * 1024;

  -- Resolve tenant & type
  SET v_tenant_id := (SELECT `id` FROM `tenants` WHERE `code`=p_tenant_code LIMIT 1);
  IF v_tenant_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown tenant code';
  END IF;

  SET v_type_id := (
    SELECT et.`id`
    FROM `entity_types` et
    WHERE et.`tenant_id`=v_tenant_id AND et.`code`=p_entity_type_code
    LIMIT 1
  );
  IF v_type_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown entity_type code for tenant';
  END IF;

  -- Build list of attribute ids and pivot expressions
  SET v_attr_ids := (
    SELECT GROUP_CONCAT(a.`id` ORDER BY a.`code`)
    FROM `type_attributes` ta
    JOIN `attributes` a ON a.`id`=ta.`attribute_id`
    WHERE ta.`tenant_id`=v_tenant_id AND ta.`entity_type_id`=v_type_id
  );

  SET v_col_exprs := (
    SELECT GROUP_CONCAT(
             CONCAT('MAX(CASE WHEN v.attribute_id=', a.`id`,
                    ' THEN v.value END) AS `', a.`code`, '`')
             ORDER BY a.`code` SEPARATOR ', '
           )
    FROM `type_attributes` ta
    JOIN `attributes` a ON a.`id`=ta.`attribute_id`
    WHERE ta.`tenant_id`=v_tenant_id AND ta.`entity_type_id`=v_type_id
  );

  -- If no attributes mapped, return the base CI select and exit
  IF v_attr_ids IS NULL OR v_attr_ids='' THEN
    SELECT CONCAT(
      'SELECT e.`ci`, e.`name`, e.`status` ',
      'FROM `entities` e ',
      'WHERE e.`tenant_id`=', v_tenant_id,
      ' AND e.`entity_type_id`=', v_type_id,
      ' ORDER BY e.`ci`'
    ) AS generated_sql;
    LEAVE main;
  END IF;

  -- UNION of all EAV value tables
  SET @val_sql := CONCAT(
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_string` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_text` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_integer` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_decimal` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_boolean` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, DATE_FORMAT(`value`, ''%Y-%m-%d %H:%i:%s'') ',
    'FROM `eav_values_datetime` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, CAST(`value` AS CHAR) ',
    'FROM `eav_values_json` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `target_ci` ',
    'FROM `eav_values_reference` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_ip` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') ',
    'UNION ALL ',
    'SELECT `tenant_id`,`entity_ci`,`attribute_id`, `value` ',
    'FROM `eav_values_cidr` WHERE `tenant_id`=', v_tenant_id, ' AND `attribute_id` IN (', v_attr_ids, ') '
  );

  -- Final SELECT text
  SET @sql := CONCAT(
    'SELECT e.`ci`, e.`name`, e.`status`, ',
    v_col_exprs, ' ',
    'FROM `entities` e ',
    'LEFT JOIN (', @val_sql, ') v ',
    '  ON v.`entity_ci`=e.`ci` AND v.`tenant_id`=e.`tenant_id` ',
    'WHERE e.`tenant_id`=', v_tenant_id, ' AND e.`entity_type_id`=', v_type_id, ' ',
    'GROUP BY e.`ci`, e.`name`, e.`status` ',
    'ORDER BY e.`ci`'
  );

  -- Return it as text
  SELECT @sql AS generated_sql;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_show_tables` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_show_tables`(IN p_tenant_code VARCHAR(64), IN p_entity_type_code VARCHAR(64))
BEGIN
  DECLARE v_tenant_id BIGINT UNSIGNED;
  DECLARE v_type_id   BIGINT UNSIGNED;

  -- Resolve tenant and type
  SET v_tenant_id := (SELECT `id` FROM `tenants` WHERE `code` = p_tenant_code LIMIT 1);
  IF v_tenant_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown tenant code';
  END IF;

  SET v_type_id := (
    SELECT et.`id`
    FROM `entity_types` et
    WHERE et.`tenant_id` = v_tenant_id AND et.`code` = p_entity_type_code
    LIMIT 1
  );
  IF v_type_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown entity_type code for tenant';
  END IF;

  -- Temp table with the attribute→table mapping for this type
  DROP TEMPORARY TABLE IF EXISTS tmp_attr_map;
  CREATE TEMPORARY TABLE tmp_attr_map (
    attribute_id BIGINT UNSIGNED NOT NULL,
    code         VARCHAR(64)     NOT NULL,
    data_type    ENUM('string','text','integer','decimal','boolean','datetime','json','reference','ip','cidr') NOT NULL,
    eav_table    VARCHAR(64)     NOT NULL
  ) ENGINE=MEMORY;

  INSERT INTO tmp_attr_map (attribute_id, code, data_type, eav_table)
  SELECT
    a.`id`,
    a.`code`,
    a.`data_type`,
    CASE a.`data_type`
      WHEN 'string'   THEN 'eav_values_string'
      WHEN 'text'     THEN 'eav_values_text'
      WHEN 'integer'  THEN 'eav_values_integer'
      WHEN 'decimal'  THEN 'eav_values_decimal'
      WHEN 'boolean'  THEN 'eav_values_boolean'
      WHEN 'datetime' THEN 'eav_values_datetime'
      WHEN 'json'     THEN 'eav_values_json'
      WHEN 'reference'THEN 'eav_values_reference'
      WHEN 'ip'       THEN 'eav_values_ip'
      WHEN 'cidr'     THEN 'eav_values_cidr'
    END AS eav_table
  FROM `type_attributes` ta
  JOIN `attributes` a
    ON a.`id` = ta.`attribute_id`
   AND a.`tenant_id` = ta.`tenant_id`
  WHERE ta.`tenant_id` = v_tenant_id
    AND ta.`entity_type_id` = v_type_id
  ORDER BY a.`code`;

  /* -----------------------------
     Result set #1: detailed mapping
     ----------------------------- */
  SELECT
    m.`code`       AS attribute_code,
    m.`data_type`,
    m.`eav_table`,
    m.`attribute_id`
  FROM tmp_attr_map m
  ORDER BY m.`code`;

  /* ------------------------------------------
     Result set #2: distinct tables and counts
     ------------------------------------------ */
  SELECT
    m.`eav_table`,
    COUNT(*) AS attribute_count
  FROM tmp_attr_map m
  GROUP BY m.`eav_table`
  ORDER BY m.`eav_table`;

  /* ----------------------------------------------------
     Result set #3: sample SQL that flattens all values
     (returns one textual SQL string you can copy/run)
     ---------------------------------------------------- */

  -- Collect attribute_ids per data_type for dynamic UNION
  SET @ids_string    := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='string');
  SET @ids_text      := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='text');
  SET @ids_integer   := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='integer');
  SET @ids_decimal   := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='decimal');
  SET @ids_boolean   := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='boolean');
  SET @ids_datetime  := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='datetime');
  SET @ids_json      := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='json');
  SET @ids_reference := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='reference');
  SET @ids_ip        := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='ip');
  SET @ids_cidr      := (SELECT GROUP_CONCAT(attribute_id) FROM tmp_attr_map WHERE data_type='cidr');

  SET @union_sql := '';

  -- Helper to append UNION block
  IF @ids_string IS NOT NULL AND @ids_string <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      'SELECT e.ci, e.name, e.status, a.code AS attribute_code, ''string'' AS data_type, s.value AS value, s.updated_at, s.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_string s ON s.entity_ci=e.ci AND s.tenant_id=e.tenant_id AND s.attribute_id IN (', @ids_string, ')',
      ' JOIN attributes a ON a.id=s.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_text IS NOT NULL AND @ids_text <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''text'', t.value, t.updated_at, t.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_text t ON t.entity_ci=e.ci AND t.tenant_id=e.tenant_id AND t.attribute_id IN (', @ids_text, ')',
      ' JOIN attributes a ON a.id=t.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_integer IS NOT NULL AND @ids_integer <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''integer'', CAST(i.value AS CHAR), i.updated_at, i.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_integer i ON i.entity_ci=e.ci AND i.tenant_id=e.tenant_id AND i.attribute_id IN (', @ids_integer, ')',
      ' JOIN attributes a ON a.id=i.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_decimal IS NOT NULL AND @ids_decimal <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''decimal'', CAST(d.value AS CHAR), d.updated_at, d.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_decimal d ON d.entity_ci=e.ci AND d.tenant_id=e.tenant_id AND d.attribute_id IN (', @ids_decimal, ')',
      ' JOIN attributes a ON a.id=d.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_boolean IS NOT NULL AND @ids_boolean <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''boolean'', CAST(b.value AS CHAR), b.updated_at, b.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_boolean b ON b.entity_ci=e.ci AND b.tenant_id=e.tenant_id AND b.attribute_id IN (', @ids_boolean, ')',
      ' JOIN attributes a ON a.id=b.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_datetime IS NOT NULL AND @ids_datetime <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''datetime'', DATE_FORMAT(dt.value, ''%Y-%m-%d %H:%i:%s''), dt.updated_at, dt.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_datetime dt ON dt.entity_ci=e.ci AND dt.tenant_id=e.tenant_id AND dt.attribute_id IN (', @ids_datetime, ')',
      ' JOIN attributes a ON a.id=dt.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_json IS NOT NULL AND @ids_json <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''json'', CAST(j.value AS CHAR), j.updated_at, j.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_json j ON j.entity_ci=e.ci AND j.tenant_id=e.tenant_id AND j.attribute_id IN (', @ids_json, ')',
      ' JOIN attributes a ON a.id=j.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_reference IS NOT NULL AND @ids_reference <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''reference'', ref.target_ci, ref.updated_at, ref.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_reference ref ON ref.entity_ci=e.ci AND ref.tenant_id=e.tenant_id AND ref.attribute_id IN (', @ids_reference, ')',
      ' JOIN attributes a ON a.id=ref.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_ip IS NOT NULL AND @ids_ip <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''ip'', ip.value, ip.updated_at, ip.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_ip ip ON ip.entity_ci=e.ci AND ip.tenant_id=e.tenant_id AND ip.attribute_id IN (', @ids_ip, ')',
      ' JOIN attributes a ON a.id=ip.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  IF @ids_cidr IS NOT NULL AND @ids_cidr <> '' THEN
    SET @union_sql := CONCAT(@union_sql,
      CASE WHEN @union_sql<>'' THEN ' UNION ALL ' ELSE '' END,
      'SELECT e.ci, e.name, e.status, a.code, ''cidr'', c.value, c.updated_at, c.updated_by',
      ' FROM entities e',
      ' JOIN eav_values_cidr c ON c.entity_ci=e.ci AND c.tenant_id=e.tenant_id AND c.attribute_id IN (', @ids_cidr, ')',
      ' JOIN attributes a ON a.id=c.attribute_id',
      ' WHERE e.tenant_id=', v_tenant_id, ' AND e.entity_type_id=', v_type_id, ' ');
  END IF;

  -- Provide the sample SQL as a single-row result (or helpful note if no attributes)
  IF @union_sql IS NULL OR @union_sql = '' THEN
    SELECT CONCAT('/* No attributes mapped for type ', p_entity_type_code, ' in tenant ', p_tenant_code, ' */') AS sample_sql;
  ELSE
    SELECT @union_sql AS sample_sql;
  END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `eav_upsert` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `eav_upsert`(
    IN p_tenant_code        VARCHAR(64),
    IN p_entity_type_code   VARCHAR(64),
    IN p_ci                 VARCHAR(255),
    IN p_name               VARCHAR(255),
    IN p_status             VARCHAR(32),
    IN p_json_attributes    LONGTEXT,     -- JSON object like {"icon":"fa-solid fa-building"}
    IN p_updated_by         VARCHAR(255)  -- optional; pass NULL to default
)
main: BEGIN
    DECLARE v_tenant_id      BIGINT UNSIGNED;
    DECLARE v_type_id        BIGINT UNSIGNED;
    DECLARE v_keys           JSON;
    DECLARE v_len            INT;
    DECLARE i                INT DEFAULT 0;

    DECLARE v_key            VARCHAR(64);
    DECLARE v_attr_id        BIGINT UNSIGNED;
    DECLARE v_dtype          VARCHAR(16);  -- data_type from attributes
    DECLARE v_str            LONGTEXT;
    DECLARE v_int            BIGINT;
    DECLARE v_dec            DECIMAL(24,8);
    DECLARE v_bool           TINYINT(1);
    DECLARE v_dt             DATETIME;
    DECLARE v_json           LONGTEXT;
    DECLARE v_ref            VARCHAR(255);

    IF p_json_attributes IS NULL OR p_json_attributes = '' THEN
        SET p_json_attributes = '{}';
    END IF;
    IF NOT JSON_VALID(p_json_attributes) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid JSON in p_json_attributes';
    END IF;

    -- Resolve tenant & type
    SET v_tenant_id := (SELECT id FROM tenants WHERE code = p_tenant_code LIMIT 1);
    IF v_tenant_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown tenant code';
    END IF;

    SET v_type_id := (
      SELECT et.id
      FROM entity_types et
      WHERE et.tenant_id = v_tenant_id AND et.code = p_entity_type_code
      LIMIT 1
    );
    IF v_type_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown entity_type code for tenant';
    END IF;

    START TRANSACTION;

    /* ---------- Upsert base entity ---------- */
    INSERT INTO entities (ci, tenant_id, entity_type_id, name, status, created_at, updated_at, updated_by)
    VALUES (p_ci, v_tenant_id, v_type_id, p_name, p_status, NOW(), NOW(), COALESCE(p_updated_by,'eav_upsert'))
    ON DUPLICATE KEY UPDATE
        tenant_id      = VALUES(tenant_id),
        entity_type_id = VALUES(entity_type_id),
        name           = VALUES(name),
        status         = VALUES(status),
        updated_at     = NOW(),
        updated_by     = COALESCE(p_updated_by,'eav_upsert');

    /* ---------- Iterate JSON attributes ---------- */
    SET v_keys = JSON_KEYS(p_json_attributes);
    SET v_len  = JSON_LENGTH(v_keys);

    attrs_loop: WHILE i < v_len DO
        SET v_key = JSON_UNQUOTE(JSON_EXTRACT(v_keys, CONCAT('$[', i, ']')));

        -- Find attribute and data type for this tenant
        SET v_attr_id = NULL; SET v_dtype = NULL;
        SELECT a.id, a.data_type
          INTO v_attr_id, v_dtype
        FROM attributes a
        WHERE a.tenant_id = v_tenant_id AND a.code = v_key
        LIMIT 1;

        -- If attribute unknown for this tenant, skip gracefully
        IF v_attr_id IS NULL THEN
            SET i = i + 1;
            ITERATE attrs_loop;
        END IF;

        -- Get value as string once
        SET v_str = JSON_UNQUOTE(JSON_EXTRACT(p_json_attributes, CONCAT('$.', v_key)));

        CASE
            WHEN v_dtype IN ('string','text') THEN
                INSERT INTO eav_values_string (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_str, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'integer' THEN
                SET v_int = CAST(v_str AS SIGNED);
                INSERT INTO eav_values_integer (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_int, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'decimal' THEN
                SET v_dec = CAST(v_str AS DECIMAL(24,8));
                INSERT INTO eav_values_decimal (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_dec, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'boolean' THEN
                SET v_bool = CASE
                               WHEN LOWER(v_str) IN ('1','true','yes','y','on')  THEN 1
                               WHEN LOWER(v_str) IN ('0','false','no','n','off') THEN 0
                               ELSE CAST(v_str AS SIGNED)
                             END;
                INSERT INTO eav_values_boolean (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_bool, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'datetime' THEN
                SET v_dt = STR_TO_DATE(v_str, '%Y-%m-%d %H:%i:%s');
                IF v_dt IS NULL THEN
                    SET v_dt = STR_TO_DATE(v_str, '%Y-%m-%d');
                END IF;
                INSERT INTO eav_values_datetime (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_dt, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'json' THEN
                SET v_json = JSON_EXTRACT(p_json_attributes, CONCAT('$.', v_key));
                INSERT INTO eav_values_json (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, CAST(v_json AS CHAR), NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'reference' THEN
                SET v_ref = v_str;
                INSERT INTO eav_values_reference (tenant_id, entity_ci, attribute_id, n, target_ci, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_ref, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    target_ci = VALUES(target_ci),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'ip' THEN
                INSERT INTO eav_values_ip (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_str, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);

            WHEN v_dtype = 'cidr' THEN
                INSERT INTO eav_values_cidr (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                VALUES (v_tenant_id, p_ci, v_attr_id, 1, v_str, NOW(), COALESCE(p_updated_by,'eav_upsert'))
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by);
        END CASE;

        SET i = i + 1;
    END WHILE attrs_loop;

    COMMIT;

    SELECT p_ci AS ci, p_name AS name, p_status AS status, p_tenant_code AS tenant, p_entity_type_code AS entity_type;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-app_instance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-app_instance` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 10 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 10 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-application`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-application` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 9 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 9 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-backup_device`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-backup_device` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 8 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 8 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-environment`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-environment` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'env_code' then `ev`.`value` end) AS `env_code` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 11 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 11 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-esx_host`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-esx_host` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'cpu_count' then `ev`.`value` end) AS `cpu_count`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'memory_gb' then `ev`.`value` end) AS `memory_gb`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 2 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 2 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-nas_storage`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-nas_storage` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 7 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 7 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-network_device`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-network_device` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 4 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 4 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-physical_server`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-physical_server` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'cpu_count' then `ev`.`value` end) AS `cpu_count`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'memory_gb' then `ev`.`value` end) AS `memory_gb`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'os' then `ev`.`value` end) AS `os`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 1 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 1 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-san_storage`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-san_storage` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 6 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 6 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-san_switch`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-san_switch` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'model' then `ev`.`value` end) AS `model`,max(case when `a`.`code` = 'serial_number' then `ev`.`value` end) AS `serial_number`,max(case when `a`.`code` = 'vendor' then `ev`.`value` end) AS `vendor`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 5 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 5 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_cmdb-vm`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_cmdb-vm` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'cpu_count' then `ev`.`value` end) AS `cpu_count`,max(case when `a`.`code` = 'ip_address' then `ev`.`value` end) AS `ip_address`,max(case when `a`.`code` = 'memory_gb' then `ev`.`value` end) AS `memory_gb`,max(case when `a`.`code` = 'os' then `ev`.`value` end) AS `os`,max(case when `a`.`code` = 'version' then `ev`.`value` end) AS `version` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 3 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 3 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_loc-country`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_loc-country` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'country.continent' then `ev`.`value` end) AS `country.continent`,max(case when `a`.`code` = 'country.currency' then `ev`.`value` end) AS `country.currency`,max(case when `a`.`code` = 'country.eu_member' then `ev`.`value` end) AS `country.eu_member`,max(case when `a`.`code` = 'country.iso2' then `ev`.`value` end) AS `country.iso2`,max(case when `a`.`code` = 'country.iso3' then `ev`.`value` end) AS `country.iso3`,max(case when `a`.`code` = 'country.languages' then `ev`.`value` end) AS `country.languages`,max(case when `a`.`code` = 'country.name' then `ev`.`value` end) AS `country.name`,max(case when `a`.`code` = 'country.timezone' then `ev`.`value` end) AS `country.timezone`,max(case when `a`.`code` = 'country.vat_category_default' then `ev`.`value` end) AS `country.vat_category_default` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 13 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 13 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_loc-fx_rate`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_loc-fx_rate` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'fx.as_of' then `ev`.`value` end) AS `fx.as_of`,max(case when `a`.`code` = 'fx.from_ccy' then `ev`.`value` end) AS `fx.from_ccy`,max(case when `a`.`code` = 'fx.rate' then `ev`.`value` end) AS `fx.rate`,max(case when `a`.`code` = 'fx.source' then `ev`.`value` end) AS `fx.source`,max(case when `a`.`code` = 'fx.to_ccy' then `ev`.`value` end) AS `fx.to_ccy` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 14 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 14 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_loc-geoloc`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY DEFINER */
/*!50001 VIEW `v_loc-geoloc` AS select `e`.`ci` AS `ci`,`e`.`name` AS `name`,`e`.`status` AS `status`,max(case when `a`.`code` = 'address' then `ev`.`value` end) AS `address`,max(case when `a`.`code` = 'city' then `ev`.`value` end) AS `city`,max(case when `a`.`code` = 'country' then `ev`.`value` end) AS `country`,max(case when `a`.`code` = 'desc' then `ev`.`value` end) AS `desc`,max(case when `a`.`code` = 'icon' then `ev`.`value` end) AS `icon`,max(case when `a`.`code` = 'lat' then `ev`.`value` end) AS `lat`,max(case when `a`.`code` = 'long' then `ev`.`value` end) AS `long` from (((`entities` `e` join `type_attributes` `ta` on(`ta`.`entity_type_id` = 12 and `ta`.`tenant_id` = `e`.`tenant_id`)) join `attributes` `a` on(`a`.`id` = `ta`.`attribute_id`)) left join `eav_values_string` `ev` on(`ev`.`entity_ci` = `e`.`ci` and `ev`.`attribute_id` = `a`.`id` and `ev`.`tenant_id` = `e`.`tenant_id`)) where `e`.`entity_type_id` = 12 group by `e`.`ci`,`e`.`name`,`e`.`status` */;
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

