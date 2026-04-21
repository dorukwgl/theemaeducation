-- Phase 3.3: Notice Management System Database Enhancements
-- Date: April 21, 2026
-- Description: Enhanced notice management schema with file attachments, analytics, user preferences

-- ============================================
-- 1. Enhanced System Notices Table
-- ============================================

-- Create enhanced system notices table with full support for modern notice management
CREATE TABLE `system_notices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `notice_type` enum('info','warning','error','success','announcement') NOT NULL DEFAULT 'info',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `target_audience` enum('all','logged_in','admin','teachers','students') NOT NULL DEFAULT 'all',
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notices_active` (`is_active`),
  KEY `idx_notices_type` (`notice_type`),
  KEY `idx_notices_priority` (`priority`),
  KEY `idx_notices_expires` (`expires_at`),
  KEY `idx_notices_audience` (`target_audience`),
  KEY `idx_notices_created_by` (`created_by`),
  CONSTRAINT `fk_notices_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 2. Notice Attachments Table
-- ============================================

-- Create table for file attachments with comprehensive metadata
CREATE TABLE `notice_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notice_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_type` enum('pdf','doc','docx','txt','jpg','jpeg','png','gif','mp3','mp4','webm') NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attachments_notice` (`notice_id`),
  KEY `idx_attachments_type` (`file_type`),
  CONSTRAINT `fk_attachments_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `system_notices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 3. Notice Views Table
-- ============================================

-- Create table for tracking notice views for analytics
CREATE TABLE `notice_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notice_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_views_notice` (`notice_id`),
  KEY `idx_views_user` (`user_id`),
  CONSTRAINT `fk_views_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `system_notices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_views_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 4. Notice Dismissals Table
-- ============================================

-- Create table for user notice dismissal preferences
CREATE TABLE `notice_dismissals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notice_id` int NOT NULL,
  `user_id` int NOT NULL,
  `dismissed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dismissal` (`notice_id`, `user_id`),
  KEY `idx_dismissals_notice` (`notice_id`),
  KEY `idx_dismissals_user` (`user_id`),
  CONSTRAINT `fk_dismissals_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `system_notices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dismissals_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 5. Legacy Data Migration
-- ============================================

-- Migrate existing data from legacy notices table if it exists
INSERT INTO `system_notices` (title, content, notice_type, priority, created_by, created_at, updated_at)
SELECT
    title,
    text_content as content,
    'info' as notice_type,
    'medium' as priority,
    1 as created_by,
    created_at,
    CURRENT_TIMESTAMP as updated_at
FROM `notices`
WHERE created_at IS NOT NULL
ON DUPLICATE KEY UPDATE content = content;

-- Mark migrated notices as inactive initially for review
UPDATE `system_notices` SET is_active = 0 WHERE id IN (SELECT id FROM (SELECT MIN(id) as id FROM `notices` GROUP BY title));