-- Phase 1: Access Control Schema Updates
-- Migration: Add status fields and update access_type enums for Files and Quiz Sets
-- Date: 2025-04-30
-- Description: This migration adds status management and private access type support

-- Start transaction for safety
START TRANSACTION;

-- =============================================
-- FILES TABLE UPDATES
-- =============================================

-- Step 1: Add status field to files table
ALTER TABLE files
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'
AFTER access_type;

-- Step 2: Update access_type enum to include 'private'
-- Note: MariaDB/MySQL requires recreating the column to modify ENUM
ALTER TABLE files
MODIFY COLUMN access_type ENUM('all', 'logged_in', 'private')
DEFAULT 'logged_in';

-- Step 3: Create performance indexes for files
CREATE INDEX idx_files_status ON files(status);
CREATE INDEX idx_files_access_status ON files(access_type, status);

-- =============================================
-- QUIZ SETS TABLE UPDATES
-- =============================================

-- Step 4: Add status field to quiz_sets table
ALTER TABLE quiz_sets
ADD COLUMN status ENUM('published', 'draft', 'archived') DEFAULT 'draft'
AFTER is_published;

-- Step 5: Update access_type enum to include 'private'
ALTER TABLE quiz_sets
MODIFY COLUMN access_type ENUM('all', 'logged_in', 'private')
DEFAULT 'logged_in';

-- Step 6: Create performance indexes for quiz_sets
CREATE INDEX idx_quiz_sets_status ON quiz_sets(status);
CREATE INDEX idx_quiz_sets_access_status ON quiz_sets(access_type, status);

-- =============================================
-- DATA MIGRATION
-- =============================================

-- Step 7: Migrate existing quiz_sets is_published values to new status field
UPDATE quiz_sets SET status = 'published' WHERE is_published = 1;
UPDATE quiz_sets SET status = 'draft' WHERE is_published = 0;

-- Step 8: Set all existing files to active status
UPDATE files SET status = 'active' WHERE status IS NULL;

-- =============================================
-- VERIFICATION QUERIES (for manual verification only)
-- =============================================

-- Verify files table structure (run manually if needed)
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME = 'files'
-- AND COLUMN_NAME IN ('access_type', 'status');

-- Verify quiz_sets table structure (run manually if needed)
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME = 'quiz_sets'
-- AND COLUMN_NAME IN ('access_type', 'status', 'is_published');

-- Verify indexes were created (run manually if needed)
-- SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
-- FROM INFORMATION_SCHEMA.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME IN ('files', 'quiz_sets')
-- AND INDEX_NAME LIKE 'idx_%_status%'
-- ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Sample data verification (run manually if needed)
-- SELECT id, name, access_type, status, created_at FROM files LIMIT 3;
-- SELECT id, name, access_type, status, is_published, created_at FROM quiz_sets LIMIT 3;

-- =============================================
-- END OF MIGRATION
-- =============================================