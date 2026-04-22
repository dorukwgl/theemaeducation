-- EMA Education Platform - Complete Database Schema with Optimized Indexes
-- This migration creates all tables with proper indexing for performance optimization
-- Generated: 2026-04-22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `is_logged_in` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`),
  KEY `idx_users_created_at` (`created_at` DESC),
  KEY `idx_users_last_login` (`last_login_at` DESC),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Folders Table
CREATE TABLE IF NOT EXISTS `folders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT 0,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `access_type` enum('all','logged_in','private') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'private',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `file_count_cache` int DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_folders_parent` (`parent_id`),
  KEY `idx_folders_active` (`is_active`),
  KEY `idx_folders_created_by` (`created_by`),
  KEY `idx_folders_access_type` (`access_type`),
  CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Files Table
CREATE TABLE IF NOT EXISTS `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in',
  `access_count` int DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_files_folder` (`folder_id`),
  KEY `idx_files_created_at` (`created_at` DESC),
  KEY `idx_files_folder_created` (`folder_id`, `created_at` DESC),
  KEY `idx_files_access_type` (`access_type`),
  CONSTRAINT `fk_files_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Quiz Sets Table
CREATE TABLE IF NOT EXISTS `quiz_sets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_count` int DEFAULT 0,
  `total_questions` int DEFAULT 0,
  `duration_minutes` int DEFAULT 0,
  `passing_score` int DEFAULT 70,
  `is_published` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_sets_folder` (`folder_id`),
  KEY `idx_quiz_sets_published` (`is_published`, `created_at` DESC),
  KEY `idx_quiz_sets_created_at` (`created_at` DESC),
  KEY `idx_quiz_sets_access_type` (`access_type`),
  KEY `idx_quiz_sets_created_by` (`created_by`),
  CONSTRAINT `fk_quiz_sets_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Questions Table
CREATE TABLE IF NOT EXISTS `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `question_number` int DEFAULT 0,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `optional_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `question_file` mediumblob,
  `question_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `choice_A_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_A_file` mediumblob,
  `choice_A_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_A_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_B_file` mediumblob,
  `choice_B_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_C_file` mediumblob,
  `choice_C_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_D_file` mediumblob,
  `choice_D_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `question_word_formatting` json NOT NULL,
  `optional_word_formatting` json NOT NULL,
  `choice_A_word_formatting` json NOT NULL,
  `choice_B_word_formatting` json NOT NULL,
  `choice_C_word_formatting` json NOT NULL,
  `choice_D_word_formatting` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_quiz_set` (`quiz_set_id`),
  KEY `idx_questions_created_at` (`created_at` DESC),
  KEY `idx_questions_quiz_order` (`quiz_set_id`, `question_number`),
  CONSTRAINT `fk_questions_quiz_set` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Questions Backup Table
CREATE TABLE IF NOT EXISTS `questions_backup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_question_id` int NOT NULL,
  `quiz_set_id` int NOT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `optional_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `question_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `choice_A_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_A_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_B_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_C_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_D_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `backup_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'deleted',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_backup_quiz_set` (`quiz_set_id`),
  KEY `idx_questions_backup_original` (`original_question_id`),
  KEY `idx_questions_backup_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Access Permissions Table
CREATE TABLE IF NOT EXISTS `access_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access_level` enum('read','write','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'read',
  `access_times` int NOT NULL DEFAULT '0',
  `times_accessed` int NOT NULL DEFAULT '0',
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_access_identifier_item` (`identifier`,`item_id`,`item_type`),
  KEY `idx_access_user` (`user_id`),
  KEY `idx_access_item` (`item_type`, `item_id`),
  KEY `idx_access_active` (`is_active`),
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Access Table
CREATE TABLE IF NOT EXISTS `user_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `item_id` int NOT NULL,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access_times` int DEFAULT '-1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_access_identifier_item` (`identifier`,`item_id`,`item_type`),
  KEY `idx_user_access_item` (`item_type`, `item_id`),
  KEY `idx_user_access_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Access to All Users Table
CREATE TABLE IF NOT EXISTS `access_to_all_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `quiz_set_id` int DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT '1',
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_all_folder` (`folder_id`),
  KEY `idx_access_all_file` (`file_id`),
  KEY `idx_access_all_quiz` (`quiz_set_id`),
  CONSTRAINT `fk_access_all_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_all_file` FOREIGN KEY (`file_id`)
    REFERENCES `files`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_all_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Give Access to All Users Table
CREATE TABLE IF NOT EXISTS `give_access_to_all_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `quiz_set_id` int DEFAULT NULL,
  `access_granted` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_give_access_all_unique` (`folder_id`,`file_id`,`quiz_set_id`),
  KEY `idx_give_access_all_folder` (`folder_id`),
  KEY `idx_give_access_all_file` (`file_id`),
  KEY `idx_give_access_all_quiz` (`quiz_set_id`),
  KEY `idx_give_access_all_created_at` (`created_at` DESC),
  CONSTRAINT `fk_give_access_all_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_give_access_all_file` FOREIGN KEY (`file_id`)
    REFERENCES `files`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_give_access_all_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Give Access to Login Users Table
CREATE TABLE IF NOT EXISTS `give_access_to_login_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `access_granted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_give_access_login_unique` (`item_type`,`item_id`),
  KEY `idx_give_access_login_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Item Activation Status Table
CREATE TABLE IF NOT EXISTS `item_activation_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_type` enum('file','quiz_set') NOT NULL,
  `item_id` int NOT NULL,
  `is_activated` tinyint(1) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_activation_unique` (`item_type`,`item_id`),
  KEY `idx_item_activation_item` (`item_id`),
  KEY `idx_item_activation_updated_at` (`updated_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notices Table
CREATE TABLE IF NOT EXISTS `notices` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `text_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_dismissible` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notices_created_at` (`created_at` DESC),
  KEY `idx_notices_active` (`is_active`, `created_at` DESC),
  KEY `idx_notices_dismissible` (`is_dismissible`),
  KEY `idx_notices_created_by` (`created_by`),
  KEY `idx_notices_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notice Views Table
CREATE TABLE IF NOT EXISTS `notice_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notice_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_notice_views_unique` (`notice_id`,`user_id`),
  KEY `idx_notice_views_user` (`user_id`),
  KEY `idx_notice_views_viewed_at` (`viewed_at` DESC),
  CONSTRAINT `fk_notice_views_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `notices`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_views_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notice Dismissals Table
CREATE TABLE IF NOT EXISTS `notice_dismissals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notice_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `dismissed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_notice_dismissals_unique` (`notice_id`,`user_id`),
  KEY `idx_notice_dismissals_user` (`user_id`),
  KEY `idx_notice_dismissals_dismissed_at` (`dismissed_at` DESC),
  CONSTRAINT `fk_notice_dismissals_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `notices`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_dismissals_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Password Reset Requests Table
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `request_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_status` (`request_status`),
  KEY `idx_password_reset_requested_at` (`requested_at` DESC),
  KEY `idx_password_reset_token` (`token`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `assigned_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admin_users_user` (`user_id`),
  KEY `idx_admin_users_assigned_at` (`assigned_at`),
  CONSTRAINT `fk_admin_users_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Quiz Activity Table
CREATE TABLE IF NOT EXISTS `quiz_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` enum('view','start','submit','complete','retake','access_granted','access_revoked') NOT NULL,
  `score` int DEFAULT NULL,
  `total_questions` int DEFAULT NULL,
  `correct_answers` int DEFAULT NULL,
  `time_spent_seconds` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_activity_quiz` (`quiz_set_id`),
  KEY `idx_quiz_activity_user` (`user_id`),
  KEY `idx_quiz_activity_action` (`action`),
  KEY `idx_quiz_activity_created_at` (`created_at` DESC),
  KEY `idx_quiz_activity_user_quiz` (`user_id`, `quiz_set_id`),
  CONSTRAINT `fk_quiz_activity_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Quiz Attempts Table
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `user_id` int NOT NULL,
  `attempt_number` int NOT NULL,
  `score` int DEFAULT NULL,
  `total_questions` int DEFAULT NULL,
  `correct_answers` int DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `time_spent_seconds` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_quiz_attempts_unique` (`user_id`, `quiz_set_id`, `attempt_number`),
  KEY `idx_quiz_attempts_quiz` (`quiz_set_id`),
  KEY `idx_quiz_attempts_user` (`user_id`),
  KEY `idx_quiz_attempts_started_at` (`started_at` DESC),
  KEY `idx_quiz_attempts_completed_at` (`completed_at` DESC),
  KEY `idx_quiz_attempts_user_quiz` (`user_id`, `quiz_set_id`),
  KEY `idx_quiz_attempts_user_complete` (`user_id`, `quiz_set_id`, `attempt_number`),
  CONSTRAINT `fk_quiz_attempts_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_attempts_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Quiz Results Table
CREATE TABLE IF NOT EXISTS `quiz_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `user_answer` enum('A','B','C','D') NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `time_spent_seconds` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_results_attempt` (`quiz_attempt_id`),
  KEY `idx_quiz_results_question` (`question_id`),
  KEY `idx_quiz_results_correct` (`is_correct`),
  KEY `idx_quiz_results_created_at` (`created_at` DESC),
  CONSTRAINT `fk_quiz_results_attempt` FOREIGN KEY (`quiz_attempt_id`)
    REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_results_question` FOREIGN KEY (`question_id`)
    REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- System Activity Table
CREATE TABLE IF NOT EXISTS `system_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` enum('login','logout','file_download','quiz_attempt','notice_view','admin_action','system_event') NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_activity_user` (`user_id`),
  KEY `idx_system_activity_admin` (`admin_id`),
  KEY `idx_system_activity_action` (`action`),
  KEY `idx_system_activity_created_at` (`created_at` DESC),
  KEY `idx_system_activity_entity` (`entity_type`, `entity_id`),
  KEY `idx_system_activity_user_action` (`user_id`, `created_at` DESC),
  CONSTRAINT `fk_system_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_system_activity_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- System Health Table
CREATE TABLE IF NOT EXISTS `system_health` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric_type` enum('database','disk','memory','cpu','api_performance','error_rate') NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('healthy','warning','critical') NOT NULL DEFAULT 'healthy',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_health_type` (`metric_type`),
  KEY `idx_system_health_status` (`status`),
  KEY `idx_system_health_recorded_at` (`recorded_at` DESC),
  KEY `idx_system_health_type_status` (`metric_type`, `status`, `recorded_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Audit Log Table
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_user` (`user_id`),
  KEY `idx_audit_log_action` (`action`),
  KEY `idx_audit_log_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_log_created_at` (`created_at` DESC),
  KEY `idx_audit_log_user_action` (`user_id`, `created_at` DESC),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bulk Operations Table
CREATE TABLE IF NOT EXISTS `bulk_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `operation_type` enum('bulk_delete','bulk_update','bulk_grant_access','bulk_revoke_access','bulk_publish','bulk_archive') NOT NULL,
  `target_type` enum('users','files','folders','quiz_sets','notices') NOT NULL,
  `target_ids` text NOT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `total_items` int DEFAULT 0,
  `processed_items` int DEFAULT 0,
  `failed_items` int DEFAULT 0,
  `results` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bulk_operations_admin` (`admin_id`),
  KEY `idx_bulk_operations_status` (`status`),
  KEY `idx_bulk_operations_type` (`operation_type`),
  KEY `idx_bulk_operations_target` (`target_type`),
  KEY `idx_bulk_operations_created_at` (`created_at` DESC),
  KEY `idx_bulk_operations_admin_status` (`admin_id`, `status`),
  CONSTRAINT `fk_bulk_operations_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Download Analytics Table
CREATE TABLE IF NOT EXISTS `download_analytics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `file_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_download_analytics_file` (`file_id`),
  KEY `idx_download_analytics_user` (`user_id`),
  KEY `idx_download_analytics_downloaded_at` (`downloaded_at` DESC),
  KEY `idx_download_analytics_file_user` (`file_id`, `user_id`),
  CONSTRAINT `fk_download_analytics_file` FOREIGN KEY (`file_id`)
    REFERENCES `files`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_download_analytics_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;