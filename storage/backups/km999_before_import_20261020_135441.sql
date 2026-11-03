-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: km999
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.22.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_years` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '民國學年度，如 115',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '顯示名稱，如 115學年度',
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_years_year_code_unique` (`year_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (1,'115','115學年度',1,115,'2026-07-19 05:25:30','2026-07-26 06:28:27');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `makeup_for_classroom_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned NOT NULL,
  `class_date` date NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `duration_hours` decimal(3,1) DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_classroom_id_student_id_class_date_unique` (`classroom_id`,`student_id`,`class_date`),
  KEY `attendances_student_id_foreign` (`student_id`),
  KEY `attendances_class_date_status_index` (`class_date`,`status`),
  KEY `attendances_makeup_for_classroom_id_foreign` (`makeup_for_classroom_id`),
  CONSTRAINT `attendances_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_makeup_for_classroom_id_foreign` FOREIGN KEY (`makeup_for_classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('-cache-f9d1f58ba2f32ce04ed0967e9c1bc773','i:1;',1792221896),('-cache-f9d1f58ba2f32ce04ed0967e9c1bc773:timer','i:1792221896;',1792221896),('laravel-cache-e50185db38818dd0e473c2690a1afc77','i:1;',1784703679),('laravel-cache-e50185db38818dd0e473c2690a1afc77:timer','i:1784703679;',1784703679),('laravel-cache-f9d1f58ba2f32ce04ed0967e9c1bc773','i:2;',1788873013),('laravel-cache-f9d1f58ba2f32ce04ed0967e9c1bc773:timer','i:1788873013;',1788873013),('laravel-cache-qaz898970@gmail.com|172.24.0.1','i:1;',1784703679),('laravel-cache-qaz898970@gmail.com|172.24.0.1:timer','i:1784703679;',1784703679);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classroom_extra_session_student`
--

DROP TABLE IF EXISTS `classroom_extra_session_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_extra_session_student` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_extra_session_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extra_session_student_unique` (`classroom_extra_session_id`,`student_id`),
  KEY `classroom_extra_session_student_student_id_foreign` (`student_id`),
  CONSTRAINT `cess_pivot_session_fk` FOREIGN KEY (`classroom_extra_session_id`) REFERENCES `classroom_extra_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_extra_session_student_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classroom_extra_session_student`
--

LOCK TABLES `classroom_extra_session_student` WRITE;
/*!40000 ALTER TABLE `classroom_extra_session_student` DISABLE KEYS */;
/*!40000 ALTER TABLE `classroom_extra_session_student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classroom_extra_sessions`
--

DROP TABLE IF EXISTS `classroom_extra_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_extra_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_extra_sessions_classroom_id_session_date_unique` (`classroom_id`,`session_date`),
  CONSTRAINT `classroom_extra_sessions_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classroom_extra_sessions`
--

LOCK TABLES `classroom_extra_sessions` WRITE;
/*!40000 ALTER TABLE `classroom_extra_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `classroom_extra_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classroom_schedules`
--

DROP TABLE IF EXISTS `classroom_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned DEFAULT NULL,
  `weekday` tinyint unsigned NOT NULL COMMENT '1=Mon ... 7=Sun',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classroom_schedules_classroom_id_weekday_index` (`classroom_id`,`weekday`),
  KEY `classroom_schedules_weekday_start_time_index` (`weekday`,`start_time`),
  KEY `classroom_schedules_course_id_foreign` (`course_id`),
  KEY `classroom_schedules_classroom_id_course_id_index` (`classroom_id`,`course_id`),
  CONSTRAINT `classroom_schedules_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_schedules_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classroom_schedules`
--

LOCK TABLES `classroom_schedules` WRITE;
/*!40000 ALTER TABLE `classroom_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `classroom_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classrooms`
--

DROP TABLE IF EXISTS `classrooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classrooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned DEFAULT NULL,
  `grade_level_id` bigint unsigned DEFAULT NULL,
  `teacher_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '行事曆／篩選用 #RRGGBB',
  `weekday` tinyint unsigned DEFAULT NULL COMMENT '1=Mon ... 7=Sun',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `extra_sessions` json DEFAULT NULL,
  `active_periods` json DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classrooms_teacher_id_foreign` (`teacher_id`),
  KEY `classrooms_status_index` (`status`),
  KEY `classrooms_weekday_index` (`weekday`),
  KEY `classrooms_grade_level_id_foreign` (`grade_level_id`),
  KEY `classrooms_course_id_foreign` (`course_id`),
  CONSTRAINT `classrooms_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classrooms_grade_level_id_foreign` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classrooms_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classrooms`
--

LOCK TABLES `classrooms` WRITE;
/*!40000 ALTER TABLE `classrooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `classrooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_bundle_course`
--

DROP TABLE IF EXISTS `course_bundle_course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_bundle_course` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_bundle_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `price` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_bundle_course_course_bundle_id_course_id_unique` (`course_bundle_id`,`course_id`),
  KEY `course_bundle_course_course_id_foreign` (`course_id`),
  CONSTRAINT `course_bundle_course_course_bundle_id_foreign` FOREIGN KEY (`course_bundle_id`) REFERENCES `course_bundles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_bundle_course_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_bundle_course`
--

LOCK TABLES `course_bundle_course` WRITE;
/*!40000 ALTER TABLE `course_bundle_course` DISABLE KEYS */;
INSERT INTO `course_bundle_course` VALUES (1,1,8,3000),(2,1,9,3000),(3,2,8,3000),(4,2,9,3000),(5,2,10,3000),(6,3,8,3100),(7,3,9,3100),(8,3,10,3100);
/*!40000 ALTER TABLE `course_bundle_course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_bundles`
--

DROP TABLE IF EXISTS `course_bundles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_bundles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_year_id` bigint unsigned DEFAULT NULL,
  `grade_level_id` bigint unsigned DEFAULT NULL,
  `min_courses` tinyint unsigned NOT NULL DEFAULT '2',
  `price` int unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_bundles_academic_year_id_foreign` (`academic_year_id`),
  KEY `course_bundles_grade_level_id_foreign` (`grade_level_id`),
  CONSTRAINT `course_bundles_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_bundles_grade_level_id_foreign` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_bundles`
--

LOCK TABLES `course_bundles` WRITE;
/*!40000 ALTER TABLE `course_bundles` DISABLE KEYS */;
INSERT INTO `course_bundles` VALUES (1,'英／數 雙科',1,1,2,3000,1,10,'2026-10-11 09:20:25','2026-10-11 09:20:25'),(2,'英／數／理 雙科',1,2,2,3000,1,30,'2026-10-11 09:20:25','2026-10-11 09:20:25'),(3,'英／數／理 雙科',1,3,2,3100,1,60,'2026-10-11 09:20:25','2026-10-11 09:20:25');
/*!40000 ALTER TABLE `course_bundles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_categories`
--

DROP TABLE IF EXISTS `course_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_categories_name_unique` (`name`),
  KEY `course_categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_categories`
--

LOCK TABLES `course_categories` WRITE;
/*!40000 ALTER TABLE `course_categories` DISABLE KEYS */;
INSERT INTO `course_categories` VALUES (6,'英文',1,'2026-07-21 08:52:17','2026-07-26 06:28:27'),(7,'數學',2,'2026-07-21 08:52:22','2026-07-26 06:28:27'),(8,'理化',3,'2026-07-21 08:52:27','2026-07-26 06:28:27'),(9,'國文',4,'2026-07-21 08:52:41','2026-07-26 06:28:27'),(10,'社會',6,'2026-07-21 08:52:46','2026-07-26 06:28:27'),(11,'生物',5,'2026-07-21 08:52:57','2026-07-26 06:28:27'),(12,'短期',10,'2026-08-06 08:24:18','2026-08-06 08:24:18');
/*!40000 ALTER TABLE `course_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_fee_plan`
--

DROP TABLE IF EXISTS `course_fee_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_fee_plan` (
  `fee_plan_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  UNIQUE KEY `course_fee_plan_fee_plan_id_course_id_unique` (`fee_plan_id`,`course_id`),
  KEY `course_fee_plan_course_id_index` (`course_id`),
  CONSTRAINT `course_fee_plan_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_fee_plan_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_fee_plan`
--

LOCK TABLES `course_fee_plan` WRITE;
/*!40000 ALTER TABLE `course_fee_plan` DISABLE KEYS */;
INSERT INTO `course_fee_plan` VALUES (2,7),(5,7),(8,7),(1,8),(3,8),(6,8),(1,9),(3,9),(6,9),(3,10),(6,10),(4,11),(7,11),(2,12),(8,13);
/*!40000 ALTER TABLE `course_fee_plan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_prices`
--

DROP TABLE IF EXISTS `course_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `level` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_hours` decimal(3,1) NOT NULL DEFAULT '1.0',
  `tuition` int unsigned NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_prices_course_id_sort_order_index` (`course_id`,`sort_order`),
  CONSTRAINT `course_prices_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_prices`
--

LOCK TABLES `course_prices` WRITE;
/*!40000 ALTER TABLE `course_prices` DISABLE KEYS */;
INSERT INTO `course_prices` VALUES (41,8,'國一',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(42,8,'國二',1.5,0,1,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(43,8,'國三',1.5,0,2,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(44,9,'國一',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(45,9,'國二',1.5,0,1,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(46,9,'國三',1.5,0,2,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(47,10,'國二',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(48,10,'國三',1.5,0,1,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(49,11,'國二',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(50,11,'國三',1.5,0,1,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(51,7,'國一',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(52,7,'國二',1.5,0,1,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(53,7,'國三',1.5,0,2,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(54,12,'國一',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(55,13,'國三',1.5,0,0,'2026-07-26 06:28:27','2026-07-26 06:28:27'),(57,14,'國一',1.0,0,0,'2026-09-10 06:59:48','2026-09-10 06:59:48'),(58,14,'國二',1.0,0,1,'2026-09-10 06:59:48','2026-09-10 06:59:48'),(59,14,'國三',1.0,0,2,'2026-09-10 06:59:48','2026-09-10 06:59:48');
/*!40000 ALTER TABLE `course_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_category_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收款／行事曆用 #RRGGBB',
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `pricing_group` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekdays` json DEFAULT NULL COMMENT 'ISO 週一=1…週日=7，用於耗材按上課日計費',
  `schedules` json DEFAULT NULL COMMENT '[{weekday,start_time,end_time}] 課程預設上課時段',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courses_course_category_id_name_unique` (`course_category_id`,`name`),
  KEY `courses_status_index` (`status`),
  CONSTRAINT `courses_course_category_id_foreign` FOREIGN KEY (`course_category_id`) REFERENCES `course_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (7,9,'國文課','#1e3a5f','active','humanities','[2, 6]','[{\"level\": \"國一\", \"weekday\": 2, \"end_time\": \"18:30\", \"start_time\": \"17:00\"}, {\"level\": \"國一\", \"weekday\": 2, \"end_time\": \"20:00\", \"start_time\": \"18:30\"}, {\"level\": \"國二\", \"weekday\": 6, \"end_time\": \"15:00\", \"start_time\": \"13:00\"}, {\"level\": \"國三\", \"weekday\": 6, \"end_time\": \"16:40\", \"start_time\": \"15:10\"}]',NULL,NULL,'2026-07-21 08:53:10','2026-07-26 06:28:27'),(8,6,'英文課','#0d9488','active','core','[1, 4]','[{\"level\": \"國一\", \"weekday\": 1, \"end_time\": \"19:00\", \"start_time\": \"17:30\"}, {\"level\": \"國一\", \"weekday\": 4, \"end_time\": \"19:00\", \"start_time\": \"17:30\"}, {\"level\": \"國二\", \"weekday\": 1, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國二\", \"weekday\": 4, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國三\", \"weekday\": 1, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}, {\"level\": \"國三\", \"weekday\": 4, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}]',NULL,NULL,'2026-07-21 08:54:02','2026-07-26 06:28:27'),(9,7,'數學課','#1e3a5f','active','core','[3, 6]','[{\"level\": \"國一\", \"weekday\": 3, \"end_time\": \"19:00\", \"start_time\": \"17:30\"}, {\"level\": \"國一\", \"weekday\": 6, \"end_time\": \"10:30\", \"start_time\": \"09:00\"}, {\"level\": \"國二\", \"weekday\": 3, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國二\", \"weekday\": 6, \"end_time\": \"10:30\", \"start_time\": \"09:00\"}, {\"level\": \"國三\", \"weekday\": 3, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}, {\"level\": \"國三\", \"weekday\": 6, \"end_time\": \"12:00\", \"start_time\": \"10:30\"}]',NULL,NULL,'2026-07-21 08:54:14','2026-07-26 06:28:27'),(10,8,'理化課','#0d9488','active','core','[2, 5]','[{\"level\": \"國二\", \"weekday\": 2, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國二\", \"weekday\": 5, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國三\", \"weekday\": 2, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}, {\"level\": \"國三\", \"weekday\": 5, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}]',NULL,NULL,'2026-07-21 08:54:28','2026-07-26 06:28:27'),(11,8,'理化科研班','#a9fed2','active','research','[2, 5]','[{\"level\": \"國二\", \"weekday\": 2, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國二\", \"weekday\": 5, \"end_time\": \"15:30\", \"start_time\": \"14:00\"}, {\"level\": \"國三\", \"weekday\": 2, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}, {\"level\": \"國三\", \"weekday\": 5, \"end_time\": \"21:00\", \"start_time\": \"19:30\"}]',NULL,NULL,'2026-07-21 08:54:40','2026-07-26 06:28:27'),(12,11,'生物課','#0d9488','active','humanities','[5]','[{\"level\": \"國一\", \"weekday\": 5, \"end_time\": \"19:00\", \"start_time\": \"17:30\"}]',NULL,NULL,'2026-07-21 08:55:00','2026-07-26 06:28:27'),(13,10,'社會課','#0d9488','active','humanities','[6]','[{\"level\": \"國三\", \"weekday\": 6, \"end_time\": \"15:00\", \"start_time\": \"13:30\"}]',NULL,NULL,'2026-07-21 08:55:16','2026-07-26 06:28:27'),(14,12,'AI夏令營體驗','#ff5900','active','other','[1, 2, 3]','[{\"level\": \"國一\", \"weekday\": 1, \"end_time\": \"17:00\", \"start_time\": \"15:00\"}, {\"level\": \"國三\", \"weekday\": 3, \"end_time\": \"17:00\", \"start_time\": \"15:00\"}, {\"level\": \"國二\", \"weekday\": 2, \"end_time\": \"17:00\", \"start_time\": \"15:00\"}]','2026-09-01','2026-09-30','2026-08-06 08:26:22','2026-09-10 06:59:48');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollment_tuitions`
--

DROP TABLE IF EXISTS `enrollment_tuitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollment_tuitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `duration_hours` decimal(4,1) NOT NULL,
  `tuition_amount` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollment_tuitions_enrollment_id_duration_hours_unique` (`enrollment_id`,`duration_hours`),
  CONSTRAINT `enrollment_tuitions_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollment_tuitions`
--

LOCK TABLES `enrollment_tuitions` WRITE;
/*!40000 ALTER TABLE `enrollment_tuitions` DISABLE KEYS */;
/*!40000 ALTER TABLE `enrollment_tuitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `tuition_amount` int unsigned NOT NULL,
  `joined_at` date DEFAULT NULL,
  `left_at` date DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_classroom_id_student_id_unique` (`classroom_id`,`student_id`),
  KEY `enrollments_student_id_foreign` (`student_id`),
  KEY `enrollments_status_index` (`status`),
  CONSTRAINT `enrollments_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_discounts`
--

DROP TABLE IF EXISTS `fee_discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fee_discounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'amount=固定金額；percent=折扣％',
  `value` int unsigned NOT NULL COMMENT '金額元，或百分比 1–100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fee_discounts_is_active_sort_order_index` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_discounts`
--

LOCK TABLES `fee_discounts` WRITE;
/*!40000 ALTER TABLE `fee_discounts` DISABLE KEYS */;
INSERT INTO `fee_discounts` VALUES (1,'早鳥優惠','amount',1000,1,0,NULL,NULL,NULL,'2026-09-08 13:16:03','2026-09-08 13:16:03');
/*!40000 ALTER TABLE `fee_discounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_plans`
--

DROP TABLE IF EXISTS `fee_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fee_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_year_id` bigint unsigned DEFAULT NULL,
  `grade_level_id` bigint unsigned DEFAULT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_group` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month' COMMENT 'month|session_block',
  `session_block_size` tinyint unsigned DEFAULT NULL,
  `list_price` int unsigned NOT NULL,
  `quarter_price` int unsigned DEFAULT NULL,
  `quarter_single_price` int unsigned DEFAULT NULL,
  `quarter_double_price` int unsigned DEFAULT NULL,
  `material_fee` int unsigned NOT NULL DEFAULT '0',
  `material_unit` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'term' COMMENT 'term|subject',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fee_plans_academic_year_id_foreign` (`academic_year_id`),
  KEY `fee_plans_grade_level_id_pricing_group_index` (`grade_level_id`,`pricing_group`),
  CONSTRAINT `fee_plans_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_plans_grade_level_id_foreign` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_plans`
--

LOCK TABLES `fee_plans` WRITE;
/*!40000 ALTER TABLE `fee_plans` DISABLE KEYS */;
INSERT INTO `fee_plans` VALUES (1,1,1,'英／數','core','month',NULL,3600,3200,NULL,NULL,1200,'term',10,1,'2026-07-22 02:07:25','2026-07-22 02:07:25'),(2,1,1,'國／生','humanities','month',NULL,2000,1800,NULL,NULL,900,'term',20,1,'2026-07-22 02:07:25','2026-07-22 02:07:25'),(3,1,2,'英／數／理','core','month',NULL,3600,3200,NULL,NULL,1200,'term',30,1,'2026-07-22 02:07:25','2026-07-22 02:07:25'),(4,1,2,'理化科研','research','session_block',4,1100,950,NULL,NULL,1500,'subject',40,1,'2026-07-22 02:07:25','2026-07-22 02:07:25'),(5,1,2,'國文','humanities','month',NULL,2000,1800,NULL,NULL,900,'term',50,1,'2026-07-22 02:07:26','2026-07-22 02:07:26'),(6,1,3,'英／數／理','core','month',NULL,3600,3300,NULL,NULL,1500,'term',60,1,'2026-07-22 02:07:26','2026-07-22 02:07:26'),(7,1,3,'理化科研','research','session_block',4,1100,950,NULL,NULL,1500,'subject',70,1,'2026-07-22 02:07:26','2026-07-22 02:07:26'),(8,1,3,'國／社','humanities','month',NULL,2000,1800,NULL,NULL,1000,'term',80,1,'2026-07-22 02:07:26','2026-07-22 02:07:26');
/*!40000 ALTER TABLE `fee_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_levels`
--

DROP TABLE IF EXISTS `grade_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '如 國一',
  `code` tinyint unsigned NOT NULL COMMENT '編號，如 7',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_levels_name_unique` (`name`),
  UNIQUE KEY `grade_levels_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_levels`
--

LOCK TABLES `grade_levels` WRITE;
/*!40000 ALTER TABLE `grade_levels` DISABLE KEYS */;
INSERT INTO `grade_levels` VALUES (1,'國一',7,1,1,'2026-07-19 05:25:30','2026-07-19 05:25:30'),(2,'國二',8,2,1,'2026-07-19 05:25:30','2026-07-19 05:25:30'),(3,'國三',9,3,1,'2026-07-19 05:25:30','2026-07-19 05:25:30');
/*!40000 ALTER TABLE `grade_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL COMMENT '放假日期',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '假日名稱',
  `is_custom` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'true=手動自訂；false=官方CSV匯入',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `holidays_date_unique` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
INSERT INTO `holidays` VALUES (1,'2027-01-01','中華民國開國紀念日',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(2,'2027-02-04','小年夜',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(3,'2027-02-05','農曆除夕',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(4,'2027-02-06','春節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(5,'2027-02-07','春節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(6,'2027-02-08','春節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(7,'2027-02-09','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(8,'2027-02-10','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(9,'2027-02-28','和平紀念日',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(10,'2027-03-01','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(11,'2027-04-04','兒童節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(12,'2027-04-05','清明節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(13,'2027-04-06','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(14,'2027-04-30','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(15,'2027-05-01','勞動節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(16,'2027-06-09','端午節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(17,'2027-09-15','中秋節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(18,'2027-09-28','教師節',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(19,'2027-10-10','國慶日',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(20,'2027-10-11','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(21,'2027-10-25','臺灣光復暨金門古寧頭大捷紀念日',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(22,'2027-12-24','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(23,'2027-12-25','行憲紀念日',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(24,'2027-12-31','補假',0,'2026-07-26 04:32:06','2026-07-26 04:32:06'),(25,'2026-01-01','中華民國開國紀念日',0,'2026-07-26 04:34:09','2026-07-26 04:34:09'),(26,'2026-02-15','小年夜',0,'2026-07-26 04:34:09','2026-07-26 04:34:09'),(27,'2026-02-16','農曆除夕',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(28,'2026-02-17','春節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(29,'2026-02-18','春節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(30,'2026-02-19','春節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(31,'2026-02-20','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(32,'2026-02-27','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(33,'2026-02-28','和平紀念日',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(34,'2026-04-03','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(35,'2026-04-04','兒童節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(36,'2026-04-05','清明節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(37,'2026-04-06','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(38,'2026-05-01','勞動節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(39,'2026-06-19','端午節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(40,'2026-09-25','中秋節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(41,'2026-09-28','教師節',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(42,'2026-10-09','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(43,'2026-10-10','國慶日',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(44,'2026-10-25','臺灣光復暨金門古寧頭大捷紀念日',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(45,'2026-10-26','補假',0,'2026-07-26 04:34:10','2026-07-26 04:34:10'),(46,'2026-12-25','行憲紀念日',0,'2026-07-26 04:34:10','2026-07-26 04:34:10');
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1),(5,'2026_05_08_071132_add_role_to_users_table',1),(6,'2026_05_08_071133_create_students_table',1),(7,'2026_05_08_071133_create_teachers_table',1),(8,'2026_05_08_071134_create_01_courses_table',1),(9,'2026_05_08_071135_create_02_classrooms_table',1),(10,'2026_05_08_071136_create_03_enrollments_table',1),(11,'2026_05_08_071137_create_04_attendances_table',1),(12,'2026_05_08_071138_create_05_reconciliations_table',1),(13,'2026_05_11_062223_create_course_prices_table',1),(14,'2026_05_11_062943_create_course_categories_table',1),(15,'2026_05_11_062946_add_course_category_id_to_courses_table',1),(16,'2026_05_11_080000_create_classroom_schedules_table',1),(17,'2026_05_11_081500_add_settled_by_user_id_to_reconciliations_table',1),(18,'2026_05_12_120000_add_color_to_classrooms_table',1),(19,'2026_05_12_120000_add_price_tiers_to_students_table',1),(20,'2026_05_13_100000_replace_student_price_tiers_with_school_segment',1),(21,'2026_05_13_120000_add_extra_sessions_to_classrooms_table',1),(22,'2026_05_14_100000_create_classroom_extra_sessions_tables',1),(23,'2026_05_15_100000_add_active_periods_to_classrooms_table',1),(24,'2026_05_16_100000_add_makeup_for_classroom_id_to_attendances_table',1),(25,'2026_06_04_100000_add_duration_hours_to_course_prices_and_attendances',1),(26,'2026_06_09_000001_create_reconciliation_logs_table',1),(27,'2026_06_10_000001_create_user_todos_table',1),(28,'2026_06_19_100000_create_enrollment_tuitions_table',1),(29,'2026_07_19_000001_create_academic_years_and_grade_levels_tables',2),(30,'2026_07_21_000001_add_optional_profile_fields_to_students_table',3),(31,'2026_07_21_000002_add_parent_phones_and_structured_address_to_students_table',4),(32,'2026_07_22_000001_create_fee_plans_and_add_pricing_group_to_courses',5),(33,'2026_07_22_000002_make_reconciliations_support_course_billing',6),(34,'2026_07_24_000001_add_grade_level_id_to_classrooms_table',7),(35,'2026_07_24_000002_add_course_id_to_classroom_schedules',8),(36,'2026_07_24_000003_add_weekdays_to_courses',9),(37,'2026_07_25_000001_add_schedules_to_courses',10),(38,'2026_07_25_000002_add_level_to_course_schedules',11),(39,'2026_07_26_000001_add_color_to_courses_table',12),(40,'2026_07_26_000002_create_holidays_table',13),(41,'2026_07_26_000003_create_course_fee_plan_table',14),(42,'2026_08_06_100000_create_student_course_drops_table',15),(43,'2026_09_07_010000_create_schedule_exceptions_tables',15),(44,'2026_09_08_110000_add_intended_course_ids_to_students_table',15),(45,'2026_09_08_120000_create_student_promotion_logs_table',15),(46,'2026_09_08_130000_create_student_material_payments_table',16),(47,'2026_09_08_140000_add_receipt_no_to_reconciliations_table',16),(48,'2026_09_08_150000_create_fee_discounts_table',16),(49,'2026_09_08_160000_add_start_end_dates_to_courses_table',17),(50,'2026_09_10_150000_make_fee_plans_grade_level_id_nullable',18),(51,'2026_10_01_110000_add_siblings_to_students_table',19),(53,'2026_10_11_170000_create_course_bundles_table',20),(54,'2026_10_16_210000_convert_session_block_fee_prices_to_monthly',21),(55,'2026_10_16_214000_add_price_to_course_bundle_course',22);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reconciliation_logs`
--

DROP TABLE IF EXISTS `reconciliation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `classroom_id` bigint unsigned NOT NULL,
  `billing_year` smallint unsigned NOT NULL,
  `billing_month` tinyint unsigned NOT NULL,
  `expected_amount` int unsigned NOT NULL DEFAULT '0',
  `paid_amount` int unsigned NOT NULL DEFAULT '0',
  `paid_date` date DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by_user_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reconciliation_logs_student_id_foreign` (`student_id`),
  KEY `reconciliation_logs_classroom_id_foreign` (`classroom_id`),
  KEY `reconciliation_logs_performed_by_user_id_foreign` (`performed_by_user_id`),
  KEY `reconciliation_logs_billing_year_billing_month_index` (`billing_year`,`billing_month`),
  KEY `reconciliation_logs_action_index` (`action`),
  KEY `reconciliation_logs_created_at_index` (`created_at`),
  CONSTRAINT `reconciliation_logs_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reconciliation_logs_performed_by_user_id_foreign` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliation_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reconciliation_logs`
--

LOCK TABLES `reconciliation_logs` WRITE;
/*!40000 ALTER TABLE `reconciliation_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `reconciliation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reconciliations`
--

DROP TABLE IF EXISTS `reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `classroom_id` bigint unsigned DEFAULT NULL,
  `course_id` bigint unsigned DEFAULT NULL,
  `pay_cycle` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_year` smallint unsigned NOT NULL,
  `billing_month` tinyint unsigned NOT NULL,
  `expected_amount` int unsigned NOT NULL,
  `paid_amount` int unsigned NOT NULL DEFAULT '0',
  `paid_date` date DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `settled_by_user_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `receipt_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_discount_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reco_unique_student_course_month` (`student_id`,`course_id`,`billing_year`,`billing_month`),
  KEY `reconciliations_billing_year_billing_month_index` (`billing_year`,`billing_month`),
  KEY `reconciliations_status_index` (`status`),
  KEY `reconciliations_settled_by_user_id_foreign` (`settled_by_user_id`),
  KEY `reconciliations_student_id_index` (`student_id`),
  KEY `reconciliations_course_id_foreign` (`course_id`),
  KEY `reconciliations_classroom_id_foreign` (`classroom_id`),
  KEY `reconciliations_receipt_no_index` (`receipt_no`),
  KEY `reconciliations_fee_discount_id_foreign` (`fee_discount_id`),
  CONSTRAINT `reconciliations_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliations_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliations_fee_discount_id_foreign` FOREIGN KEY (`fee_discount_id`) REFERENCES `fee_discounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliations_settled_by_user_id_foreign` FOREIGN KEY (`settled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reconciliations`
--

LOCK TABLES `reconciliations` WRITE;
/*!40000 ALTER TABLE `reconciliations` DISABLE KEYS */;
INSERT INTO `reconciliations` VALUES (1,6,NULL,9,'quarterly',2026,9,2850,2850,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,000｜比例 6/8｜教材／耗材 600｜教材 2026下半年（7–12） 600（年 1,200 ÷ 2，打勾收取）','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(2,6,NULL,9,'quarterly',2026,10,3000,3000,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(3,6,NULL,9,'quarterly',2026,11,3000,3000,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(4,6,NULL,9,'quarterly',2026,12,3000,3000,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(5,6,NULL,8,'quarterly',2026,9,2475,2475,'2026-09-10','paid',1,'報名計價｜英文課｜單價 3,000｜比例 5/8｜教材／耗材 600｜教材 2026下半年（7–12） 600（年 1,200 ÷ 2，打勾收取）','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(6,6,NULL,8,'quarterly',2026,10,3000,3000,'2026-09-10','paid',1,'報名計價｜英文課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(7,6,NULL,8,'quarterly',2026,11,3000,3000,'2026-09-10','paid',1,'報名計價｜英文課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(8,6,NULL,8,'quarterly',2026,12,3000,3000,'2026-09-10','paid',1,'報名計價｜英文課｜單價 3,000','20260910001',NULL,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(9,2,NULL,9,'quarterly',2026,9,3075,3075,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,100｜比例 6/8｜教材／耗材 750｜教材 2026下半年（7–12） 750（年 1,500 ÷ 2，打勾收取）','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(10,2,NULL,9,'quarterly',2026,10,3100,3100,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(11,2,NULL,9,'quarterly',2026,11,3100,3100,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(12,2,NULL,9,'quarterly',2026,12,3100,3100,'2026-09-10','paid',1,'報名計價｜數學課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(13,2,NULL,10,'quarterly',2026,9,2688,2688,'2026-09-10','paid',1,'報名計價｜理化課｜單價 3,100｜比例 5/8｜教材／耗材 750｜教材 2026下半年（7–12） 750（年 1,500 ÷ 2，打勾收取）','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(14,2,NULL,10,'quarterly',2026,10,3100,3100,'2026-09-10','paid',1,'報名計價｜理化課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(15,2,NULL,10,'quarterly',2026,11,3100,3100,'2026-09-10','paid',1,'報名計價｜理化課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(16,2,NULL,10,'quarterly',2026,12,3100,3100,'2026-09-10','paid',1,'報名計價｜理化課｜單價 3,100','20260910002',NULL,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(27,1,NULL,9,'quarterly',2026,7,3600,3600,'2026-10-05','paid',1,'報名計價｜數學課｜單價 3,000｜教材／耗材 600｜教材 2026下半年（7–12） 600（年費 1,200，手動／半年勾選）',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(28,1,NULL,9,'quarterly',2026,8,3000,3000,'2026-10-05','paid',1,'報名計價｜數學課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(29,1,NULL,9,'quarterly',2026,9,3000,3000,'2026-10-05','paid',1,'報名計價｜數學課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(30,1,NULL,9,'quarterly',2026,10,3000,3000,'2026-10-05','paid',1,'報名計價｜數學課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(31,1,NULL,9,'quarterly',2026,11,3000,3000,'2026-10-05','paid',1,'報名計價｜數學課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(32,1,NULL,8,'quarterly',2026,7,3600,3600,'2026-10-05','paid',1,'報名計價｜英文課｜單價 3,000｜教材／耗材 600｜教材 2026下半年（7–12） 600（年費 1,200，手動／半年勾選）',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(33,1,NULL,8,'quarterly',2026,8,3000,3000,'2026-10-05','paid',1,'報名計價｜英文課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(34,1,NULL,8,'quarterly',2026,9,3000,3000,'2026-10-05','paid',1,'報名計價｜英文課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(35,1,NULL,8,'quarterly',2026,10,3000,3000,'2026-10-05','paid',1,'報名計價｜英文課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(36,1,NULL,8,'quarterly',2026,11,3000,3000,'2026-10-05','paid',1,'報名計價｜英文課｜單價 3,000',NULL,NULL,'2026-10-05 12:54:26','2026-10-05 12:54:26');
/*!40000 ALTER TABLE `reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_exception_course`
--

DROP TABLE IF EXISTS `schedule_exception_course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_exception_course` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schedule_exception_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sched_exc_course_unique` (`schedule_exception_id`,`course_id`),
  KEY `schedule_exception_course_course_id_foreign` (`course_id`),
  CONSTRAINT `schedule_exception_course_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedule_exception_course_schedule_exception_id_foreign` FOREIGN KEY (`schedule_exception_id`) REFERENCES `schedule_exceptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_exception_course`
--

LOCK TABLES `schedule_exception_course` WRITE;
/*!40000 ALTER TABLE `schedule_exception_course` DISABLE KEYS */;
INSERT INTO `schedule_exception_course` VALUES (1,1,8),(2,2,8);
/*!40000 ALTER TABLE `schedule_exception_course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_exceptions`
--

DROP TABLE IF EXISTS `schedule_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'closure=停課；makeup=補課',
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade_level_id` bigint unsigned DEFAULT NULL,
  `start_time` time DEFAULT NULL COMMENT '補課選填時段',
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedule_exceptions_type_date_from_date_to_index` (`type`,`date_from`,`date_to`),
  KEY `schedule_exceptions_grade_level_id_index` (`grade_level_id`),
  CONSTRAINT `schedule_exceptions_grade_level_id_foreign` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_exceptions`
--

LOCK TABLES `schedule_exceptions` WRITE;
/*!40000 ALTER TABLE `schedule_exceptions` DISABLE KEYS */;
INSERT INTO `schedule_exceptions` VALUES (1,'closure','2026-09-21','2026-09-21','@@',1,NULL,NULL,'2026-09-08 13:25:53','2026-09-08 13:25:53'),(2,'makeup','2026-09-29','2026-09-29','QQ',1,NULL,NULL,'2026-09-08 13:26:45','2026-09-08 13:26:45');
/*!40000 ALTER TABLE `schedule_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('2qz1xHYEsDRkbCiVjMH9u7NYmhfEYuibLkY8n7Pf',NULL,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJCSWU0TUh2akR1bWRIb3pobE9VcHF2TGpFVWZ1c091TjZhRW1xbnBZIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wva205OTkubG9jYWxcL2ZlZS1wbGFucyJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wva205OTkubG9jYWxcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1792315757),('bMvAr3mXsLvdvKWRy3e5CBWCJ1TPT3vZVvR6W3iK',1,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJvZHFBWTR0c0lLU3psdHRyU3FxbjlkdWl2UFFYY3BwNXNnakNpa3lZIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==',1792130557),('eWQkSGlMWXG5pxGVRHxJf0ZPhqSIrNbXqcv2oiBS',NULL,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.25.6 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','eyJfdG9rZW4iOiJrWDlmQlNUNmdBOGpCR3lHWW81OE5hM09yTVRHMXF4bzBCNTEyaEJKIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1792152791),('g2Ufbhmmj9aE5t3INuTLqSh82ObnoriWjMka1ato',1,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJkVVhsTms0MkNKUjRpNjNzRjBFYnpqalk3ZVMxczRMa1l6clhNaHM4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9wYXltZW50LWxpc3RzIiwicm91dGUiOiJwYXltZW50LWxpc3RzLmluZGV4In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9',1792228327),('Q24TMhJumS911ZJStSBOBsXPToabXsm7NLKSYafP',1,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ0WFVLa3MxNkVpb1g2U01yUWhCMGM1em5wQmhNd2hhZFRyTW9XelNlIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9mZWUtcGxhbnMiLCJyb3V0ZSI6ImZlZS1wbGFucy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==',1792165663),('Q6w3VskRT6wQd3TD7RgeIck1DlrgjUewhRpUns5a',NULL,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.25.6 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','eyJfdG9rZW4iOiJZcDRqVmlXTER5aWJQaEZwQXNGSGRRVnpRVDd2bDU4OVJ6N2FjQ1hMIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1792221411),('RlszezxJHAecrlq8de59MrdyK6m1OsfS5uWipsFm',NULL,'172.24.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJZRGxwaDRaZ0xQbWF5ak9QYWdXMnB4VmFqM0hCOFdGbGFuZ21NZE1WIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2ttOTk5LmxvY2FsXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1792464672);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_course_drops`
--

DROP TABLE IF EXISTS `student_course_drops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_course_drops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `dropped_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_course_drops_student_id_course_id_unique` (`student_id`,`course_id`),
  KEY `student_course_drops_course_id_foreign` (`course_id`),
  CONSTRAINT `student_course_drops_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_course_drops_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_course_drops`
--

LOCK TABLES `student_course_drops` WRITE;
/*!40000 ALTER TABLE `student_course_drops` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_course_drops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_material_payments`
--

DROP TABLE IF EXISTS `student_material_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_material_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `period_year` smallint unsigned NOT NULL COMMENT '半年所屬年份',
  `period_half` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'H1=1–6；H2=7–12',
  `amount` int unsigned NOT NULL DEFAULT '0',
  `paid_at` date NOT NULL,
  `paid_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_material_period_unique` (`student_id`,`course_id`,`period_year`,`period_half`),
  KEY `student_material_payments_course_id_foreign` (`course_id`),
  KEY `student_material_payments_paid_by_user_id_foreign` (`paid_by_user_id`),
  KEY `smp_student_period_idx` (`student_id`,`period_year`,`period_half`),
  CONSTRAINT `student_material_payments_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_material_payments_paid_by_user_id_foreign` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_material_payments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_material_payments`
--

LOCK TABLES `student_material_payments` WRITE;
/*!40000 ALTER TABLE `student_material_payments` DISABLE KEYS */;
INSERT INTO `student_material_payments` VALUES (1,6,9,2026,'H2',600,'2026-09-10',1,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(2,6,8,2026,'H2',600,'2026-09-10',1,'2026-09-10 01:10:01','2026-09-10 01:10:01'),(3,2,9,2026,'H2',750,'2026-09-10',1,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(4,2,10,2026,'H2',750,'2026-09-10',1,'2026-09-10 02:35:58','2026-09-10 02:35:58'),(7,1,9,2026,'H2',600,'2026-07-01',1,'2026-10-05 12:54:26','2026-10-05 12:54:26'),(8,1,8,2026,'H2',600,'2026-07-01',1,'2026-10-05 12:54:26','2026-10-05 12:54:26');
/*!40000 ALTER TABLE `student_material_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_promotion_logs`
--

DROP TABLE IF EXISTS `student_promotion_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_promotion_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `action` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'promote|graduate',
  `from_grade_level_id` bigint unsigned DEFAULT NULL,
  `to_grade_level_id` bigint unsigned DEFAULT NULL,
  `from_grade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_grade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_student_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_student_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_status` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_mode` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'auto|manual',
  `previous_course_ids` json DEFAULT NULL,
  `new_course_ids` json DEFAULT NULL,
  `performed_by_user_id` bigint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_promotion_logs_from_grade_level_id_foreign` (`from_grade_level_id`),
  KEY `student_promotion_logs_to_grade_level_id_foreign` (`to_grade_level_id`),
  KEY `student_promotion_logs_performed_by_user_id_foreign` (`performed_by_user_id`),
  KEY `student_promotion_logs_created_at_index` (`created_at`),
  KEY `student_promotion_logs_student_id_created_at_index` (`student_id`,`created_at`),
  KEY `student_promotion_logs_action_index` (`action`),
  CONSTRAINT `student_promotion_logs_from_grade_level_id_foreign` FOREIGN KEY (`from_grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_promotion_logs_performed_by_user_id_foreign` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_promotion_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_promotion_logs_to_grade_level_id_foreign` FOREIGN KEY (`to_grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_promotion_logs`
--

LOCK TABLES `student_promotion_logs` WRITE;
/*!40000 ALTER TABLE `student_promotion_logs` DISABLE KEYS */;
INSERT INTO `student_promotion_logs` VALUES (1,2,'graduate',3,NULL,'國三','已畢業','11509001','11509001','active','graduated',NULL,NULL,NULL,1,'國三轉已畢業','2026-09-08 14:43:18','2026-09-08 14:43:18'),(2,5,'promote',1,2,'國一','國二','11507003','11508003','active','active','auto','[8, 9]','[8, 9]',1,'科目不變，自動帶課','2026-09-08 14:43:49','2026-09-08 14:43:49');
/*!40000 ALTER TABLE `student_promotion_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academic_year_id` bigint unsigned DEFAULT NULL,
  `grade_level_id` bigint unsigned DEFAULT NULL,
  `intended_course_ids` json DEFAULT NULL COMMENT '轉檔後預選科目；收款優先使用',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_phones` json DEFAULT NULL,
  `siblings` json DEFAULT NULL,
  `graduate_school` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_school` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '年級班別，如 忠班',
  `id_number` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_district` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_zip` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` text COLLATE utf8mb4_unicode_ci,
  `school_segment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_student_code_unique` (`student_code`),
  KEY `students_status_index` (`status`),
  KEY `students_academic_year_id_foreign` (`academic_year_id`),
  KEY `students_grade_level_id_foreign` (`grade_level_id`),
  CONSTRAINT `students_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_grade_level_id_foreign` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'11507001',1,1,NULL,'陳曉明',NULL,NULL,NULL,NULL,NULL,NULL,'陽明國中','２班',NULL,'823 高雄市田寮區','高雄市','田寮區','823',NULL,'男','active',NULL,NULL,'2026-07-21 09:27:47','2026-07-21 09:27:47'),(2,'11509001',1,3,NULL,'陳國二',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'graduated',NULL,NULL,'2026-07-26 03:02:16','2026-09-08 14:43:18'),(3,'11509001g3',1,3,NULL,'陳國三',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'graduated',NULL,NULL,'2026-07-26 03:02:21','2026-08-10 14:32:42'),(4,'11507002',1,1,NULL,'王曉明',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-08-10 14:10:00','2026-08-10 14:10:00'),(5,'11508003',1,2,'[8, 9]','魏國安',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-08-13 06:28:36','2026-09-08 14:43:49'),(6,'11507004',1,1,NULL,'AAA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-08-29 14:09:41','2026-08-29 14:09:41'),(7,'11507005',1,1,NULL,'Bllen',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-09-06 18:52:37','2026-09-06 18:52:37'),(8,'11507006',1,1,NULL,'CCC',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-09-08 13:09:59','2026-09-08 13:09:59'),(11,'11507007',1,1,NULL,'王小明','0912345678','母親','0987654321','[{\"phone\": \"0987654321\", \"title\": \"母親\"}, {\"phone\": \"0911222333\", \"title\": \"父親\"}]','[{\"name\": \"王小美\", \"grade\": \"國三\", \"school\": \"某某國中\", \"relation\": \"姊姊\"}]','某某國小','某某國中','101',NULL,'830 高雄市鳳山區 中正路1號','高雄市','鳳山區','830','中正路1號','男','active',NULL,NULL,'2026-10-16 05:43:12','2026-10-16 05:43:12');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teachers_user_id_foreign` (`user_id`),
  KEY `teachers_status_index` (`status`),
  CONSTRAINT `teachers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_todos`
--

DROP TABLE IF EXISTS `user_todos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_todos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_todos_user_id_completed_index` (`user_id`,`completed`),
  CONSTRAINT `user_todos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_todos`
--

LOCK TABLES `user_todos` WRITE;
/*!40000 ALTER TABLE `user_todos` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_todos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'超級管理員','admin@gmail.com','2026-07-19 05:00:55','$2y$12$0CZ4NAPof5wAtIhfDzcrDetgixcDMPh5uUzC/Ta9Lc8BXLa1nuvK2','super_admin',1,NULL,NULL,NULL,NULL,'2026-07-19 05:00:55','2026-07-19 05:00:55');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'km999'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-20 13:54:43
