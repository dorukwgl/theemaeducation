-- Phase 3.1: Enhanced Folder System - Database Schema Migration
-- Date: 2025-04-21
-- Description: Add folder hierarchy, access control, and activity tracking

-- Add hierarchy and enhanced columns to folders table
ALTER TABLE `folders`
ADD COLUMN `parent_id` int DEFAULT NULL AFTER `id`,
ADD COLUMN `description` text DEFAULT NULL AFTER `icon_path`,
ADD COLUMN `sort_order` int DEFAULT 0 AFTER `description`,
ADD COLUMN `is_active` tinyint(1) DEFAULT 1 AFTER `sort_order`,
ADD COLUMN `created_by` int DEFAULT NULL AFTER `is_active`,
ADD COLUMN `access_type` enum('all','logged_in','private') DEFAULT 'private' AFTER `created_by`,
ADD COLUMN `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `access_type`,
ADD COLUMN `file_count_cache` int DEFAULT 0 AFTER `updated_at`;

-- Add indexes for hierarchy and search performance
CREATE INDEX `idx_folders_parent` ON `folders` (`parent_id`);
CREATE INDEX `idx_folders_active` ON `folders` (`is_active`);
CREATE INDEX `idx_folders_created_by` ON `folders` (`created_by`);
CREATE INDEX `idx_folders_access_type` ON `folders` (`access_type`);

-- Add foreign key for parent folder relationship
ALTER TABLE `folders`
ADD CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`)
REFERENCES `folders`(`id`) ON DELETE CASCADE;

-- Create folder_access_permissions table for user-specific folder access
CREATE TABLE `folder_access_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `user_id` int NOT NULL,
  `access_level` enum('read','write','admin') NOT NULL DEFAULT 'read',
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_user` (`folder_id`, `user_id`),
  KEY `idx_folder_access_user` (`user_id`),
  KEY `idx_folder_access_folder` (`folder_id`),
  CONSTRAINT `fk_folder_access_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_folder_access_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_folder_access_granted_by` FOREIGN KEY (`granted_by`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create folder_activity table for activity tracking and analytics
CREATE TABLE `folder_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` enum('view','create','update','delete','move','share','access_granted','access_revoked') NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_folder_activity_folder` (`folder_id`),
  KEY `idx_folder_activity_user` (`user_id`),
  KEY `idx_folder_activity_created` (`created_at`),
  KEY `idx_folder_activity_action` (`action`),
  CONSTRAINT `fk_folder_activity_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_folder_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Update existing folders table to add default access_type where it was 'logged_in'
-- This ensures backward compatibility with existing data
UPDATE `folders`
SET `access_type` = 'logged_in'
WHERE `access_type` IS NULL OR `access_type` NOT IN ('all', 'logged_in', 'private');

-- Add is_favorite column to folders table for user favorites
ALTER TABLE `folders`
ADD COLUMN `is_favorite` tinyint(1) DEFAULT 0 AFTER `access_type`;

-- Create index for favorites (will be populated dynamically)
CREATE INDEX `idx_folders_favorite` ON `folders` (`is_favorite`);

-- Create folder_favorites junction table for efficient favorite management
CREATE TABLE `folder_favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_user_favorite` (`folder_id`, `user_id`),
  KEY `idx_folder_favorites_user` (`user_id`),
  CONSTRAINT `fk_folder_fav_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_folder_fav_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;