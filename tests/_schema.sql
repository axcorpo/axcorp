-- MySQL dump 10.13  Distrib 5.7.33, for Win64 (x86_64)
--
-- Host: localhost    Database: masteranunturi
-- ------------------------------------------------------
-- Server version	5.7.33

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `workspace`
--

DROP TABLE IF EXISTS `workspace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workspace` (
  `id` binary(16) NOT NULL,
  `subscription_id` binary(16) DEFAULT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `api_token` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_workspace_subscription1_idx` (`subscription_id`),
  CONSTRAINT `fk_workspace_subscription1` FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription`
--

DROP TABLE IF EXISTS `subscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `subscriber_id` binary(16) NOT NULL,
  `package_id` binary(16) DEFAULT NULL,
  `company_id` binary(16) DEFAULT NULL,
  `external_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `trial_period` smallint(6) DEFAULT NULL,
  `trial_cycle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `billing_period` smallint(6) DEFAULT NULL,
  `billing_cycle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_UNIQUE` (`code`),
  KEY `deleted` (`deleted`),
  KEY `fk_subscription_company1_idx` (`company_id`),
  KEY `fk_subscription_package1_idx` (`package_id`),
  KEY `fk_subscription_subscriber1_idx` (`subscriber_id`),
  KEY `fk_subscription_subscription1_idx` (`parent_id`),
  CONSTRAINT `fk_subscription_company1` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_package1` FOREIGN KEY (`package_id`) REFERENCES `package` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_subscriber1` FOREIGN KEY (`subscriber_id`) REFERENCES `subscriber` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_subscription1` FOREIGN KEY (`parent_id`) REFERENCES `subscription` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriber`
--

DROP TABLE IF EXISTS `subscriber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriber` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `user_id` binary(16) NOT NULL,
  `affiliate_id` binary(16) DEFAULT NULL,
  `external_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `pin` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `date_of_birth` datetime DEFAULT NULL,
  `address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `street_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `street_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `staircase` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `block` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `floor` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `apartment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `locality` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `zip_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `county` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_UNIQUE` (`code`),
  KEY `deleted` (`deleted`),
  KEY `fk_subscriber_subscriber1_idx` (`parent_id`),
  KEY `fk_subscriber_user1_idx` (`user_id`),
  KEY `fk_subscriber_affiliate1_idx` (`affiliate_id`),
  CONSTRAINT `fk_subscriber_affiliate1` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscriber_subscriber1` FOREIGN KEY (`parent_id`) REFERENCES `subscriber` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscriber_user1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-21 18:05:32



SET FOREIGN_KEY_CHECKS=1;

-- Signup-flow tables (user, company, feature tree, package + features, workspace links) — added for MasterSignupFlowTest
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `auth_key` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `password_reset_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `signup_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `login_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `gender` tinyint DEFAULT NULL,
  `signature` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `last_activity` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `password_reset_token` (`password_reset_token`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `signup_token_UNIQUE` (`signup_token`),
  KEY `deleted` (`deleted`),
  KEY `fk_user_user1_idx` (`parent_id`),
  CONSTRAINT `fk_user_user1` FOREIGN KEY (`parent_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tin` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `registration_number` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `street_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `street_number` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `staircase` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `block` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `floor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `apartment` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `locality` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `zip_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `county` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nace_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fax` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bank_account` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bank` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_first_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_middle_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_last_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_function` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `representative_email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `taxation` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `reverse_taxation` tinyint DEFAULT NULL,
  `split_vat` tinyint DEFAULT NULL,
  `vat_at_collection` tinyint DEFAULT NULL,
  `net_turnover` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `social_capital` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fiscal_status` tinyint DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_company_user1_idx` (`user_id`),
  CONSTRAINT `fk_company_user1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature_module` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_feature_module_feature_module1_idx` (`parent_id`),
  CONSTRAINT `fk_feature_module_feature_module1` FOREIGN KEY (`parent_id`) REFERENCES `feature_module` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `feature_module_id` binary(16) DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `price` decimal(15,4) DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `renewable` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`),
  KEY `deleted` (`deleted`),
  KEY `fk_feature_feature1_idx` (`parent_id`),
  KEY `fk_feature_feature_module1_idx` (`feature_module_id`),
  CONSTRAINT `fk_feature_feature1` FOREIGN KEY (`parent_id`) REFERENCES `feature` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_feature_feature_module1` FOREIGN KEY (`feature_module_id`) REFERENCES `feature_module` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `trial_period` smallint DEFAULT NULL,
  `trial_cycle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `billing_period` smallint DEFAULT NULL,
  `billing_cycle` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_package_package1_idx` (`parent_id`),
  CONSTRAINT `fk_package_package1` FOREIGN KEY (`parent_id`) REFERENCES `package` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_feature` (
  `id` binary(16) NOT NULL,
  `package_id` binary(16) NOT NULL,
  `feature_id` binary(16) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `price` decimal(15,4) DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `renewable` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_package_feature_feature1_idx` (`feature_id`),
  KEY `fk_package_feature_package1_idx` (`package_id`),
  CONSTRAINT `fk_package_feature_feature1` FOREIGN KEY (`feature_id`) REFERENCES `feature` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_package_feature_package1` FOREIGN KEY (`package_id`) REFERENCES `package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_feature` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `subscription_id` binary(16) NOT NULL,
  `feature_id` binary(16) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `price` decimal(15,4) DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `renewable` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_subscription_feature_feature1_idx` (`feature_id`),
  KEY `fk_subscription_feature_subscription1_idx` (`subscription_id`),
  KEY `fk_subscription_feature_subscription_feature1_idx` (`parent_id`),
  CONSTRAINT `fk_subscription_feature_feature1` FOREIGN KEY (`feature_id`) REFERENCES `feature` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_feature_subscription1` FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_feature_subscription_feature1` FOREIGN KEY (`parent_id`) REFERENCES `subscription_feature` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_has_user` (
  `workspace_id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `auth_key` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `password_reset_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `signup_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `login_token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `gender` tinyint DEFAULT NULL,
  `signature` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `last_activity` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`workspace_id`,`user_id`),
  KEY `fk_workspace_has_user_user1_idx` (`user_id`),
  KEY `fk_workspace_has_user_workspace1_idx` (`workspace_id`),
  CONSTRAINT `fk_workspace_has_user_user1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_workspace_has_user_workspace1` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_has_subscription_feature` (
  `workspace_id` binary(16) NOT NULL,
  `subscription_feature_id` binary(16) NOT NULL,
  `quota` int NOT NULL DEFAULT '0',
  `renewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`workspace_id`,`subscription_feature_id`),
  KEY `fk_workspace_has_subscription_feature_subscription_featur_idx` (`subscription_feature_id`),
  KEY `fk_workspace_has_subscription_feature_workspace1_idx` (`workspace_id`),
  CONSTRAINT `fk_workspace_has_subscription_feature_subscription_feature1` FOREIGN KEY (`subscription_feature_id`) REFERENCES `subscription_feature` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_workspace_has_subscription_feature_workspace1` FOREIGN KEY (`workspace_id`) REFERENCES `workspace` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;



--
-- RBAC. `auth_assignment.user_id` is BINARY(16) with a foreign key to `user.id`, the
-- shape m260713_120000_convert_auth_assignment_user_id_to_binary leaves in production;
-- common\rbac\DbManager converts the canonical UUID strings the identity exposes, and
-- the tests need to exercise that conversion rather than a varchar stand-in.
--
DROP TABLE IF EXISTS `auth_rule`;
CREATE TABLE `auth_rule` (
  `name` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `data` blob,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `auth_item`;
CREATE TABLE `auth_item` (
  `name` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `type` smallint(6) NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `rule_name` varchar(64) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `data` blob,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`name`),
  KEY `rule_name` (`rule_name`),
  KEY `idx-auth_item-type` (`type`),
  CONSTRAINT `auth_item_ibfk_1` FOREIGN KEY (`rule_name`) REFERENCES `auth_rule` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `auth_item_child`;
CREATE TABLE `auth_item_child` (
  `parent` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `child` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`parent`,`child`),
  KEY `child` (`child`),
  CONSTRAINT `auth_item_child_ibfk_1` FOREIGN KEY (`parent`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `auth_item_child_ibfk_2` FOREIGN KEY (`child`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `auth_assignment`;
CREATE TABLE `auth_assignment` (
  `item_name` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `user_id` binary(16) NOT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`item_name`,`user_id`),
  KEY `auth_assignment_user_id_idx` (`user_id`),
  CONSTRAINT `auth_assignment_ibfk_1` FOREIGN KEY (`item_name`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- support_ticket. Present so the form models can resolve their attributes; the foreign
-- keys are dropped because most of their targets (the department / priority / status
-- nomenclatures) are not part of this schema, and the suite disables FK checks anyway.
--
DROP TABLE IF EXISTS `support_ticket`;
CREATE TABLE `support_ticket` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `support_ticket_department_id` binary(16) DEFAULT NULL,
  `support_ticket_priority_id` binary(16) DEFAULT NULL,
  `support_ticket_status_id` binary(16) DEFAULT NULL,
  `subscription_id` binary(16) DEFAULT NULL,
  `series` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `number` int(11) DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `attachment` mediumtext COLLATE utf8mb3_unicode_ci,
  `type` tinyint(4) DEFAULT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- support_ticket_comment. Same treatment as support_ticket: present so the reply form
-- model resolves its attributes; foreign keys dropped.
--
DROP TABLE IF EXISTS `support_ticket_comment`;
CREATE TABLE `support_ticket_comment` (
  `id` binary(16) NOT NULL,
  `support_ticket_id` binary(16) NOT NULL,
  `content` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `attachment` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_support_ticket_comment_support_ticket1_idx` (`support_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


--
-- Invoicing tables (invoice, item, package_translation) — added for OblioInvoicePayloadTest.
-- Same shape as storedo_master; the foreign keys are irrelevant to the tests (checks are off).
--

CREATE TABLE `invoice` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `subscriber_id` binary(16) DEFAULT NULL,
  `company_id` binary(16) DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `document_series` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `document_number` int DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` tinyint DEFAULT NULL,
  `payment_processor` tinyint DEFAULT NULL,
  `amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `vat` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `exchange_rate` decimal(15,4) NOT NULL DEFAULT '1.0000',
  `multiplier` int NOT NULL DEFAULT '1',
  `e_invoice_upload_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `e_invoice_download_id` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `e_invoice_status` tinyint(1) DEFAULT '0',
  `e_invoice_error` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `e_invoice_sent_at` datetime DEFAULT NULL,
  `details` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `type` tinyint DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_invoice_company1_idx` (`company_id`),
  KEY `fk_invoice_invoice1_idx` (`parent_id`),
  KEY `fk_invoice_subscriber1_idx` (`subscriber_id`),
  CONSTRAINT `fk_invoice_company1` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoice_invoice1` FOREIGN KEY (`parent_id`) REFERENCES `invoice` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoice_subscriber1` FOREIGN KEY (`subscriber_id`) REFERENCES `subscriber` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `item` (
  `id` binary(16) NOT NULL,
  `invoice_id` binary(16) NOT NULL,
  `item_id` binary(16) DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `exchange_rate` decimal(15,4) NOT NULL DEFAULT '1.0000',
  `multiplier` int NOT NULL DEFAULT '1',
  `details` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_item_invoice1_idx` (`invoice_id`),
  CONSTRAINT `fk_item_invoice1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `language` (
  `language_id` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `language` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `country` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `name_ascii` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `status` smallint NOT NULL,
  PRIMARY KEY (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `package_translation` (
  `package_id` binary(16) NOT NULL,
  `language_id` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`package_id`,`language_id`),
  KEY `deleted` (`deleted`),
  KEY `fk_package_translation_language1_idx` (`language_id`),
  CONSTRAINT `fk_package_translation_language1` FOREIGN KEY (`language_id`) REFERENCES `language` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_package_translation_package1` FOREIGN KEY (`package_id`) REFERENCES `package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Affiliate module (_database/001_affiliate.sql): the affiliate, its payouts and commissions
-- (on master invoices; subscriber.affiliate_id above links the referred subscriber).
--
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `affiliate`;
CREATE TABLE `affiliate` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `code` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL,
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT '10.00',
  `commission_months` int DEFAULT NULL,
  `notes` text COLLATE utf8mb3_unicode_ci,
  `application_message` text COLLATE utf8mb3_unicode_ci,
  `rejection_reason` text COLLATE utf8mb3_unicode_ci,
  `reviewed_at` datetime DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_affiliate_user_id` (`user_id`),
  UNIQUE KEY `uq_affiliate_code` (`code`),
  KEY `deleted` (`deleted`),
  KEY `idx_affiliate_status` (`status`),
  CONSTRAINT `fk_affiliate_user1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `affiliate_payout`;
CREATE TABLE `affiliate_payout` (
  `id` binary(16) NOT NULL,
  `affiliate_id` binary(16) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb3_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `invoice_number` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `invoice_date` datetime DEFAULT NULL,
  `invoice_file` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `invoice_status` tinyint DEFAULT NULL,
  `invoice_rejection_note` text COLLATE utf8mb3_unicode_ci,
  `proof_files` text COLLATE utf8mb3_unicode_ci,
  `notes` text COLLATE utf8mb3_unicode_ci,
  `paid_at` datetime DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `fk_affiliate_payout_affiliate1_idx` (`affiliate_id`),
  KEY `idx_affiliate_payout_status` (`status`),
  KEY `idx_affiliate_payout_paid_at` (`paid_at`),
  CONSTRAINT `fk_affiliate_payout_affiliate1` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `affiliate_commission`;
CREATE TABLE `affiliate_commission` (
  `id` binary(16) NOT NULL,
  `affiliate_id` binary(16) NOT NULL,
  `invoice_id` binary(16) NOT NULL,
  `subscriber_id` binary(16) DEFAULT NULL,
  `affiliate_payout_id` binary(16) DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `base_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '1',
  `earned_at` datetime DEFAULT NULL,
  `payable_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_affiliate_commission_invoice_id` (`invoice_id`),
  KEY `fk_affiliate_commission_affiliate1_idx` (`affiliate_id`),
  KEY `fk_affiliate_commission_affiliate_payout1_idx` (`affiliate_payout_id`),
  KEY `idx_affiliate_commission_subscriber_id` (`subscriber_id`),
  KEY `idx_affiliate_commission_status_payable_at` (`status`, `payable_at`),
  KEY `idx_affiliate_commission_earned_at` (`earned_at`),
  CONSTRAINT `fk_affiliate_commission_affiliate1` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_affiliate_commission_affiliate_payout1` FOREIGN KEY (`affiliate_payout_id`) REFERENCES `affiliate_payout` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
SET FOREIGN_KEY_CHECKS=1;

-- Affiliate admin (backend/modules/affiliate): the `affiliate` settings group and the
-- affiliate e-mail templates (AffiliateSettingFormTest, AffiliateNotificationServiceTest).
-- Same shape as storedo_master, without the foreign keys to `user` / `language`.
DROP TABLE IF EXISTS `setting`;
CREATE TABLE `setting` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `setting` mediumtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`),
  KEY `deleted` (`deleted`),
  KEY `fk_setting_user1_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

DROP TABLE IF EXISTS `template_translation`;
DROP TABLE IF EXISTS `template`;
CREATE TABLE `template` (
  `id` binary(16) NOT NULL,
  `type` smallint NOT NULL,
  `variant` smallint DEFAULT NULL,
  `section` tinyint DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `template_translation` (
  `template_id` binary(16) NOT NULL,
  `language_id` varchar(5) COLLATE utf8mb3_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `header` mediumtext COLLATE utf8mb3_unicode_ci,
  `footer` mediumtext COLLATE utf8mb3_unicode_ci,
  `content` mediumtext COLLATE utf8mb3_unicode_ci,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`template_id`,`language_id`),
  KEY `fk_template_translation_language1_idx` (`language_id`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `fk_template_translation_template1` FOREIGN KEY (`template_id`) REFERENCES `template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Subscription payments (SubscriptionPaymentService, RecurringPaymentTest, PaymentExpiryTest):
-- payment metadata, document series, invoice templates, notifications.
--
DROP TABLE IF EXISTS `user_has_notification`;
DROP TABLE IF EXISTS `notification`;
DROP TABLE IF EXISTS `invoice_has_template`;
DROP TABLE IF EXISTS `document_series`;
DROP TABLE IF EXISTS `payment_metadata`;
CREATE TABLE `payment_metadata` (
  `subscription_id` binary(16) NOT NULL,
  `payer_id` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `card_id` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `payment_id` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `payment_method` tinyint DEFAULT NULL,
  `payment_processor` tinyint DEFAULT NULL,
  `recurring_payment` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`subscription_id`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `fk_payment_metadata_subscription1` FOREIGN KEY (`subscription_id`) REFERENCES `subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `document_series` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `type` tinyint DEFAULT NULL,
  `first_number` int DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `invoice_has_template` (
  `invoice_id` binary(16) NOT NULL,
  `template_id` binary(16) NOT NULL,
  PRIMARY KEY (`invoice_id`,`template_id`),
  KEY `fk_invoice_has_template_invoice1_idx` (`invoice_id`),
  KEY `fk_invoice_has_template_template1_idx` (`template_id`),
  CONSTRAINT `fk_invoice_has_template_invoice1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_invoice_has_template_template1` FOREIGN KEY (`template_id`) REFERENCES `template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `notification` (
  `id` binary(16) NOT NULL,
  `title` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb3_unicode_ci,
  `data` text COLLATE utf8mb3_unicode_ci,
  `type` tinyint DEFAULT NULL,
  `icon` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_by` binary(16) DEFAULT NULL,
  `updated_by` binary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE `user_has_notification` (
  `user_id` binary(16) NOT NULL,
  `notification_id` binary(16) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the notification was seen by the user.',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`notification_id`),
  KEY `fk_user_has_notification_notification1_idx` (`notification_id`),
  KEY `fk_user_has_notification_user1_idx` (`user_id`),
  CONSTRAINT `fk_user_has_notification_notification1` FOREIGN KEY (`notification_id`) REFERENCES `notification` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_has_notification_user1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
