-- EMA Education Platform - Migration to Optimized Database Schema
-- This script migrates data from old database structure to new optimized schema
-- IMPORTANT: Run this script in a single transaction to ensure data integrity
-- WARNING: This script will DROP old tables - BACKUP YOUR DATABASE BEFORE RUNNING!

-- ==============================================================================
-- Migration Configuration
-- ==============================================================================
-- Set to true to enable script execution (set to false for testing)
SET @MIGRATION_ENABLED = true;

-- ==============================================================================
-- PRE-MIGRATION CHECKS
-- ==============================================================================
-- Check if this is a fresh database (not migrated yet)
SELECT COUNT(*) as check
FROM `quiz_sets`
WHERE 1 = 1;

-- If more than 0 rows exist, database is not fresh
-- For this migration, we assume database is in old state
-- ==============================================================================

-- START TRANSACTION
START TRANSACTION;

-- ==============================================================================
-- CREATE NEW TABLES WITH OPTIMIZED SCHEMA
-- ==============================================================================

-- Users Table
DROP TABLE IF EXISTS `users_new`;

CREATE TABLE `users_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `is_logged_in` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email_new` (`email`),
  KEY `idx_users_created_at_new` (`created_at` DESC),
  KEY `idx_users_last_login_new` (`last_login_at` DESC),
  KEY `idx_users_role_new` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Folders Table
DROP TABLE IF EXISTS `folders_new`;

CREATE TABLE `folders_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `access_type` enum('all','logged_in','private') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'private',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `file_count_cache` int DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_folders_parent_new` (`parent_id`),
  KEY `idx_folders_active_new` (`is_active`),
  KEY `idx_folders_created_by_new` (`created_by`),
  KEY `idx_folders_access_type_new` (`access_type`),
  CONSTRAINT `fk_folders_parent_new` FOREIGN KEY (`parent_id`)
    REFERENCES `folders_new`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Files Table
DROP TABLE IF EXISTS `files_new`;

CREATE TABLE `files_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in',
  `access_count` int DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_files_folder_new` (`folder_id`),
  KEY `idx_files_created_at_new` (`created_at` DESC),
  KEY `idx_files_folder_created_new` (`folder_id`, `created_at` DESC),
  KEY `idx_files_access_type_new` (`access_type`),
  CONSTRAINT `fk_files_folder_new` FOREIGN KEY (`folder_id`)
    REFERENCES `folders_new`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Quiz Sets Table
DROP TABLE IF EXISTS `quiz_sets_new`;

CREATE TABLE `quiz_sets_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_count` int DEFAULT 0,
  `total_questions` int DEFAULT 0,
  `duration_minutes` int DEFAULT 0,
  `passing_score` int DEFAULT 70,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_sets_folder_new` (`folder_id`),
  KEY `idx_quiz_sets_published_new` (`is_published`, `created_at` DESC),
  KEY `idx_quiz_sets_created_at_new` (`created_at` DESC),
  KEY `idx_quiz_sets_access_type_new` (`access_type`),
  KEY `idx_quiz_sets_created_by_new` (`created_by`),
  CONSTRAINT `fk_quiz_sets_folder_new` FOREIGN KEY (`folder_id`)
    REFERENCES `folders_new`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Questions Table
DROP TABLE IF EXISTS `questions_new`;

CREATE TABLE `questions_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `question_number` int DEFAULT 0,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `optional_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_file` mediumblob,
  `question_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `choice_A_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_A_file` mediumblob,
  `choice_A_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_A_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_file` mediumblob,
  `choice_B_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_file` mediumblob,
  `choice_C_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_quiz_set_new` (`quiz_set_id`),
  KEY `idx_questions_created_at_new` (`created_at` DESC),
  KEY `idx_questions_quiz_order_new` (`quiz_set_id`, `question_number`),
  CONSTRAINT `fk_questions_quiz_set_new` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets_new`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==============================================================================
-- MIGRATE DATA FROM OLD TABLES TO NEW TABLES
-- ==============================================================================

-- Migrate Users Data
INSERT INTO `users_new` (`id`, `full_name`, `image`, `email`, `phone`, `password`, `created_at`, `updated_at`, `last_login_at`, `role`, `is_logged_in`)
SELECT `id`, `full_name`, `image`, `email`, `phone`, `password`, `created_at`, `updated_at`, `last_login_at`, `role`, `is_logged_in`
FROM `users`
ORDER BY `id`;

-- Migrate Folders Data
INSERT INTO `folders_new` (`id`, `name`, `icon_path`, `description`, `parent_id`, `sort_order`, `is_active`, `created_by`, `access_type`, `file_count_cache`)
SELECT `id`, `name`, `icon_path`, `description`, `parent_id`, `sort_order`, `is_active`, `created_by`, `access_type`, `file_count_cache`
FROM `folders`
ORDER BY `id`;

-- Migrate Files Data
INSERT INTO `files_new` (`id`, `folder_id`, `name`, `file_path`, `icon_path`, `file_size`, `mime_type`, `access_type`, `access_count`, `created_at`, `updated_at`)
SELECT `id`, `folder_id`, `name`, `file_path`, `icon_path`, `file_size`, `mime_type`, `access_type`, `access_count`, `created_at`, `updated_at`
FROM `files`
ORDER BY `id`;

-- Migrate Quiz Sets Data
INSERT INTO `quiz_sets_new` (`id`, `folder_id`, `name`, `icon_path`, `description`, `question_count`, `total_questions`, `duration_minutes`, `passing_score`, `is_published`, `created_by`, `access_type`, `created_at`, `updated_at`)
SELECT `id`, `folder_id`, `name`, `icon_path`, `description`, `question_count`, `total_questions`, `duration_minutes`, `passing_score`, `is_published`, `created_by`, `access_type`, `created_at`, `updated_at`
FROM `quiz_sets`
ORDER BY `id`;

-- Migrate Questions Data
INSERT INTO `questions_new` (`id`, `quiz_set_id`, `question_number`, `question`, `optional_text`, `question_file`, `question_file_type`, `question_file_mime`, `correct_answer`, `choice_A_text`, `choice_A_file`, `choice_A_file_type`, `choice_A_file_mime`, `choice_B_text`, `choice_B_file`, `choice_B_file_type`, `choice_B_file_mime`, `choice_C_text`, `choice_C_file`, `choice_C_file_mime`, `choice_D_text`, `choice_D_file`, `choice_D_file_mime`, `question_type`, `question_word_formatting`, `optional_word_formatting`, `choice_A_word_formatting`, `choice_B_word_formatting`, `choice_C_word_formatting`, `choice_D_word_formatting`, `created_at`)
SELECT `id`, `quiz_set_id`, `question_number`, `question`, `optional_text`, `question_file`, `question_file_type`, `question_file_mime`, `correct_answer`, `choice_A_text`, `choice_A_file`, `choice_A_file_type`, `choice_A_file_mime`, `choice_B_text`, `choice_B_file`, `choice_B_file_mime`, `choice_C_text`, `choice_C_file`, `choice_C_file_mime`, `choice_D_text`, `choice_D_file_mime`, `question_type`, `question_word_formatting`, `optional_word_formatting`, `choice_A_word_formatting`, `choice_B_word_formatting`, `choice_C_word_formatting`, `created_at`
FROM `questions`
ORDER BY `id`;

-- ==============================================================================
-- UPDATE FOREIGN KEY REFERENCES
-- ==============================================================================

-- Update foreign key references to point to new tables
SET FOREIGN_KEY_CHECKS = 0;

-- ==============================================================================
-- BACKUP OLD TABLES (BEFORE DROPPING)
-- ==============================================================================

-- Create backup tables with timestamp
CREATE TABLE `users_backup` LIKE `users`;
CREATE TABLE `folders_backup` LIKE `folders`;
CREATE TABLE `files_backup` LIKE `files`;
CREATE TABLE `quiz_sets_backup` LIKE `quiz_sets`;
CREATE TABLE `questions_backup` LIKE `questions`;

-- Backup existing data
INSERT INTO `users_backup` SELECT * FROM `users`;
INSERT INTO `folders_backup` SELECT * FROM `folders`;
INSERT INTO `files_backup` SELECT * FROM `files`;
INSERT INTO `quiz_sets_backup` SELECT * FROM `quiz_sets`;
INSERT INTO `questions_backup` SELECT * FROM `questions`;

-- ==============================================================================
-- RENAME TABLES
-- ==============================================================================

RENAME TABLE `users` TO `users_old`;
RENAME TABLE `folders` TO `folders_old`;
RENAME TABLE `files` TO `files_old`;
RENAME TABLE `quiz_sets` TO `quiz_sets_old`;
RENAME TABLE `questions` TO `questions_old`;

-- Activate new tables
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- VERIFICATION
-- ==============================================================================

-- Check data migration integrity
SELECT 'Users Migration: ' as type,
       COUNT(*) as old_count
FROM `users_old`
UNION
SELECT COUNT(*) as new_count
FROM `users_new`;

SELECT 'Folders Migration: ' as type,
       COUNT(*) as old_count
FROM `folders_old`
UNION
SELECT COUNT(*) as new_count
FROM `folders_new`;

SELECT 'Files Migration: ' as type,
       COUNT(*) as old_count
FROM `files_old`
UNION
SELECT COUNT(*) as new_count
FROM `files_new`;

SELECT 'Quiz Sets Migration: ' as type,
       COUNT(*) as old_count
FROM `quiz_sets_old`
UNION
SELECT COUNT(*) as new_count
FROM `quiz_sets_new`;

SELECT 'Questions Migration: ' as type,
       COUNT(*) as old_count
FROM `questions_old`
UNION
SELECT COUNT(*) as new_count
FROM `questions_new`;

-- ==============================================================================
-- COMMIT OR ROLLBACK
-- ==============================================================================

-- If all verifications pass, commit the migration
SET @MIGRATION_COMPLETE = (
  SELECT
    CASE
        WHEN NOT EXISTS (SELECT 1 FROM `quiz_sets_old`)
        AND (SELECT 1 FROM `quiz_sets_new`) = 0
        THEN 'PARTIAL'
        WHEN (SELECT COUNT(*) FROM `users_old`) = (SELECT COUNT(*) FROM `users_new`)
        AND (SELECT COUNT(*) FROM `folders_old`) = (SELECT COUNT(*) FROM `folders_new`)
        AND (SELECT COUNT(*) FROM `files_old`) = (SELECT COUNT(*) FROM `files_new`)
        AND (SELECT COUNT(*) FROM `quiz_sets_old`) = (SELECT COUNT(*) FROM `quiz_sets_new`)
        AND (SELECT COUNT(*) FROM `questions_old`) = (SELECT COUNT(*) FROM `questions_new`)
        THEN 'COMPLETE'
        ELSE 'ERROR'
    END
);

-- If migration is complete, commit and clean up
IF @MIGRATION_COMPLETE = 'COMPLETE' THEN
    COMMIT;

    -- Drop old tables
    SET FOREIGN_KEY_CHECKS = 0;

    DROP TABLE IF EXISTS `users_old`;
    DROP TABLE IF EXISTS `folders_old`;
    DROP TABLE IF EXISTS `files_old`;
    DROP TABLE IF EXISTS `quiz_sets_old`;
    DROP TABLE IF EXISTS `questions_old`;

    -- Update auto_increment starting values
    -- This ensures new records continue from where old records left off
    SELECT @NEW_USERS_MAX_ID := MAX(id) FROM `users_new`;
    SELECT @NEW_FOLDERS_MAX_ID := MAX(id) FROM `folders_new`;
    SELECT @NEW_FILES_MAX_ID := MAX(id) FROM `files_new`;
    SELECT @NEW_QUIZ_SETS_MAX_ID := MAX(id) FROM `quiz_sets_new`;
    SELECT @NEW_QUESTIONS_MAX_ID := MAX(id) FROM `questions_new`;

    ALTER TABLE `users_new` AUTO_INCREMENT = @NEW_USERS_MAX_ID + 1;
    ALTER TABLE `folders_new` AUTO_INCREMENT = @NEW_FOLDERS_MAX_ID + 1;
    ALTER TABLE `files_new` AUTO_INCREMENT = @NEW_FILES_MAX_ID + 1;
    ALTER TABLE `quiz_sets_new` AUTO_INCREMENT = @NEW_QUIZ_SETS_MAX_ID + 1;
    ALTER TABLE `questions_new` AUTO_INCREMENT = @NEW_QUESTIONS_MAX_ID + 1;

    -- Rename new tables to original names
    RENAME TABLE `users_new` TO `users`;
    RENAME TABLE `folders_new` TO `folders`;
    RENAME TABLE `files_new` TO `files`;
    RENAME TABLE `quiz_sets_new` TO `quiz_sets`;
    RENAME TABLE `questions_new` TO `questions`;

    SELECT 'Migration Status: ' as status,
           @MIGRATION_COMPLETE as status
    FROM DUAL;

ELSE
    ROLLBACK;

    DROP TABLE IF EXISTS `users_new`;
    DROP TABLE IF EXISTS `folders_new`;
    DROP TABLE IF EXISTS `files_new`;
    DROP TABLE IF EXISTS `quiz_sets_new`;
    DROP TABLE IF EXISTS `questions_new`;

END IF;