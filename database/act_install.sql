-- =====================================================================
-- IDA  -  Section "Activity Plan & Action"  (tabel awalan act_)
-- Isi: struktur 11 tabel + data awal (4 divisi, 11 pengawas, 4 shift,
--      33 teknisi, device, lokasi, sarana, 99 activity dari Excel OnBoard).
-- CARA PAKAI : import SEKALI ke database IDA (phpMyAdmin > Import).
--   - Tabel bawaan IDA tidak disentuh (semua tabel baru berawalan act_).
--   - Aman diulang untuk STRUKTUR (CREATE TABLE IF NOT EXISTS),
--     tetapi DATA hanya boleh diimport sekali (jangan diimport dua kali).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------- STRUKTUR ----------------------------
CREATE TABLE IF NOT EXISTS `act_divisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `prefix` varchar(4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `act_divisions_name_unique` (`name`),
  UNIQUE KEY `act_divisions_prefix_unique` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_supervisors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL DEFAULT 'SPV',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_technicians` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_tools` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `division_id` bigint unsigned NOT NULL,
  `parent_code` varchar(20) DEFAULT NULL,
  `activity_date` date NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `plan` enum('schedule','unschedule') NOT NULL,
  `start_time` time DEFAULT NULL,
  `finish_time` time DEFAULT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `problem` text,
  `troubleshoot` text,
  `tool_id` bigint unsigned DEFAULT NULL,
  `status` enum('finish','pending','continue','closed') NOT NULL,
  `remark` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `act_activities_code_unique` (`code`),
  KEY `act_activities_parent_code_index` (`parent_code`),
  KEY `act_activities_activity_date_index` (`activity_date`),
  KEY `act_activities_status_index` (`status`),
  KEY `act_activities_division_id_foreign` (`division_id`),
  KEY `act_activities_shift_id_foreign` (`shift_id`),
  KEY `act_activities_device_id_foreign` (`device_id`),
  KEY `act_activities_location_id_foreign` (`location_id`),
  KEY `act_activities_supervisor_id_foreign` (`supervisor_id`),
  KEY `act_activities_tool_id_foreign` (`tool_id`),
  CONSTRAINT `act_activities_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `act_divisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `act_activities_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `act_shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `act_activities_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `act_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `act_activities_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `act_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `act_activities_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `act_supervisors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `act_activities_tool_id_foreign` FOREIGN KEY (`tool_id`) REFERENCES `act_tools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_activity_technicians` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `technician_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `act_activity_technicians_unique` (`activity_id`,`technician_id`),
  KEY `act_activity_technicians_technician_id_foreign` (`technician_id`),
  CONSTRAINT `act_activity_technicians_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `act_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `act_activity_technicians_technician_id_foreign` FOREIGN KEY (`technician_id`) REFERENCES `act_technicians` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `changes` longtext,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `act_activity_logs_activity_id_foreign` (`activity_id`),
  CONSTRAINT `act_activity_logs_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `act_activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_settings` (
  `key` varchar(191) NOT NULL,
  `value` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------ DATA ------------------------------
SET FOREIGN_KEY_CHECKS = 1;
