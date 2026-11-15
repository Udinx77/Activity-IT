-- =====================================================================
-- IDA - Activity v2: Generate Tim, Plan (ceklis), Troubleshoot, alasan Pending
-- Tambahan di atas act_install.sql (v1). Tidak mengubah data lama.
-- Import SEKALI lewat phpMyAdmin (database IDA). Aman bila terulang (IF NOT EXISTS).
-- Butuh MariaDB 10.2+ (dipakai: ADD COLUMN IF NOT EXISTS).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `act_teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_date` date NOT NULL,
  `shift` tinyint unsigned NOT NULL,
  `division_id` bigint unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `plan_type` enum('schedule','unschedule') NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `act_teams_date_shift_index` (`team_date`,`shift`),
  KEY `act_teams_division_id_foreign` (`division_id`),
  CONSTRAINT `act_teams_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `act_divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `team_date` date NOT NULL,
  `shift` tinyint unsigned NOT NULL,
  `nik` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `role` enum('pengawas','teknisi') NOT NULL,
  `slot` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `act_team_members_shift_nik_unique` (`team_date`,`shift`,`nik`),
  KEY `act_team_members_team_id_foreign` (`team_id`),
  CONSTRAINT `act_team_members_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `act_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `seq` smallint unsigned NOT NULL DEFAULT 1,
  `title` text NOT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `tool_id` bigint unsigned DEFAULT NULL,
  `note` text,
  `carry_root_code` varchar(20) DEFAULT NULL,
  `status` enum('open','finish','pending','continue','closed') NOT NULL DEFAULT 'open',
  `activity_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `act_plans_team_id_foreign` (`team_id`),
  KEY `act_plans_carry_root_code_index` (`carry_root_code`),
  KEY `act_plans_status_index` (`status`),
  KEY `act_plans_activity_id_foreign` (`activity_id`),
  CONSTRAINT `act_plans_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `act_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `act_plans_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `act_activities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `act_troubleshoots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `kind` enum('done','remaining','next_plan') NOT NULL,
  `seq` smallint unsigned NOT NULL DEFAULT 1,
  `text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `act_troubleshoots_activity_kind_index` (`activity_id`,`kind`),
  CONSTRAINT `act_troubleshoots_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `act_activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `act_activities`
  ADD COLUMN IF NOT EXISTS `team_id` bigint unsigned DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pending_reason_type` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pending_reason` text,
  ADD INDEX IF NOT EXISTS `act_activities_team_id_index` (`team_id`);

SET FOREIGN_KEY_CHECKS = 1;
