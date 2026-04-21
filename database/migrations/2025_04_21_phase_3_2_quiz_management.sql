-- Phase 3.2: Quiz Management System Database Enhancements
-- Date: April 21, 2026
-- Description: Enhanced quiz management schema with activity tracking, attempt management, and detailed results

-- ============================================
-- 1. Enhanced Quiz Sets Table
-- ============================================

-- Add quiz set management columns
ALTER TABLE `quiz_sets`
ADD COLUMN `description` text DEFAULT NULL AFTER `name`,
ADD COLUMN `question_count` int DEFAULT 0 AFTER `icon_path`,
ADD COLUMN `total_questions` int DEFAULT 0 AFTER `question_count`,
ADD COLUMN `duration_minutes` int DEFAULT 0 AFTER `question_count`,
ADD COLUMN `passing_score` int DEFAULT 70 AFTER `question_count`,
ADD COLUMN `is_published` tinyint(1) DEFAULT 0 AFTER `access_type`,
ADD COLUMN `created_by` int DEFAULT NULL AFTER `is_published`,
ADD COLUMN `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `is_published`;

-- Add indexes for quiz set performance
CREATE INDEX `idx_quiz_sets_folder` ON `quiz_sets` (`folder_id`);
CREATE INDEX `idx_quiz_sets_published` ON `quiz_sets` (`is_published`);
CREATE INDEX `idx_quiz_sets_created_by` ON `quiz_sets` (`created_by`);

-- Add foreign key for folder relationship
ALTER TABLE `quiz_sets`
ADD CONSTRAINT `fk_quiz_sets_folder` FOREIGN KEY (`folder_id`)
REFERENCES `folders`(`id`) ON DELETE CASCADE;

-- ============================================
-- 2. Quiz Activity Tracking Table
-- ============================================

CREATE TABLE `quiz_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` enum('view','start','submit','complete','retake','access_granted','access_revoked') NOT NULL,
  `score` int DEFAULT NULL,
  `total_questions` int DEFAULT NULL,
  `correct_answers` int DEFAULT NULL,
  `time_spent_seconds` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_activity_quiz` (`quiz_set_id`),
  KEY `idx_quiz_activity_user` (`user_id`),
  KEY `idx_quiz_activity_created` (`created_at`),
  CONSTRAINT `fk_quiz_activity_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 3. Quiz Attempts Table
-- ============================================

CREATE TABLE `quiz_attempts` (
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
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_quiz_attempt` (`user_id`, `quiz_set_id`, `attempt_number`),
  KEY `idx_quiz_attempts_quiz` (`quiz_set_id`),
  KEY `idx_quiz_attempts_user` (`user_id`),
  CONSTRAINT `fk_quiz_attempts_quiz` FOREIGN KEY (`quiz_set_id`)
    REFERENCES `quiz_sets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_attempts_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 4. Quiz Results Table
-- ============================================

CREATE TABLE `quiz_results` (
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
  CONSTRAINT `fk_quiz_results_attempt` FOREIGN KEY (`quiz_attempt_id`)
    REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_results_question` FOREIGN KEY (`question_id`)
    REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 5. Update existing data for new columns
-- ============================================

-- Set initial question counts for existing quiz sets
UPDATE `quiz_sets` qs
LEFT JOIN (
    SELECT quiz_set_id, COUNT(*) as total_questions
    FROM `questions`
    GROUP BY quiz_set_id
) q ON qs.id = q.quiz_set_id
SET qs.total_questions = COALESCE(q.total_questions, 0);

-- Set initial is_published to 1 for existing quiz sets (backwards compatible)
UPDATE `quiz_sets` SET is_published = 1 WHERE is_published IS NULL;
