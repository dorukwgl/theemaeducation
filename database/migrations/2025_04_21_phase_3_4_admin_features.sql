-- Phase 3.4: Admin Features - Database Schema Enhancements
-- This migration adds comprehensive admin functionality tables for system monitoring,
-- activity tracking, audit logging, and bulk operations.

-- 1. System Activity Tracking Table
-- Tracks all user and system activity for analytics and monitoring
CREATE TABLE IF NOT EXISTS `system_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` enum('login','logout','file_download','quiz_attempt','notice_view','admin_action','system_event') NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_activity_user` (`user_id`),
  KEY `idx_system_activity_admin` (`admin_id`),
  KEY `idx_system_activity_action` (`action`),
  KEY `idx_system_activity_created` (`created_at`),
  KEY `idx_system_activity_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_system_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_system_activity_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. System Health Monitoring Table
-- Records system health metrics for monitoring and alerting
CREATE TABLE IF NOT EXISTS `system_health` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric_type` enum('database','disk','memory','cpu','api_performance','error_rate') NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(20) DEFAULT NULL,
  `status` enum('healthy','warning','critical') NOT NULL DEFAULT 'healthy',
  `details` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_health_type` (`metric_type`),
  KEY `idx_system_health_status` (`status`),
  KEY `idx_system_health_recorded` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Audit Log Table
-- Comprehensive audit trail for all admin actions and important system changes
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_user` (`user_id`),
  KEY `idx_audit_log_action` (`action`),
  KEY `idx_audit_log_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_log_created` (`created_at`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Bulk Operations Table
-- Tracks bulk administrative operations for progress monitoring and accountability
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
  `results` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bulk_operations_admin` (`admin_id`),
  KEY `idx_bulk_operations_status` (`status`),
  KEY `idx_bulk_operations_type` (`operation_type`),
  KEY `idx_bulk_operations_created` (`created_at`),
  CONSTRAINT `fk_bulk_operations_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration completed successfully
-- These tables provide the foundation for comprehensive admin features:
-- - system_activity: User activity tracking for analytics
-- - system_health: System monitoring and health checks
-- - audit_log: Comprehensive audit trail for accountability
-- - bulk_operations: Efficient bulk operation management