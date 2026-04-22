<?php

namespace EMA\Models;

use EMA\Utils\Logger;

/**
 * Fixed Notice - SQL Injection fix
 * This file contains security fixes for the original Notice model
 */
class Notice_Fixed
{
    /**
     * Get all notices with filtering (FIXED SQL Injection)
     * @param int|null $userId User ID for personalized filtering
     * @param array|null $filters Filter options
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Notices array with pagination info
     */
    public static function getAllNotices(?int $userId = null, ?array $filters = null, int $page = 1, int $perPage = 20): array
    {
        try {
            $conditions = [];
            $params = [];
            $types = '';

            // Build where conditions
            if (isset($filters['notice_type']) && !empty($filters['notice_type'])) {
                $conditions[] = 'n.notice_type = ?';
                $params[] = $filters['notice_type'];
                $types .= 's';
            }

            if (isset($filters['priority']) && !empty($filters['priority'])) {
                $conditions[] = 'n.priority = ?';
                $params[] = $filters['priority'];
                $types .= 's';
            }

            if (isset($filters['active_only']) && $filters['active_only']) {
                $conditions[] = 'n.is_active = 1';
            }

            if (isset($filters['exclude_dismissed']) && $filters['exclude_dismissed'] && $userId) {
                $conditions[] = '(nd.id IS NULL OR n.id NOT IN (
                    SELECT notice_id FROM notice_dismissals WHERE user_id = ?
                ))';
                $params[] = $userId;
                $types .= 'i';
            }

            // Fixed: Use parameter binding instead of direct insertion
            if (isset($filters['target_audience']) && !empty($filters['target_audience'])) {
                $conditions[] = '(n.target_audience = ? OR n.target_audience = ?)';
                $params[] = 'all';
                $params[] = $filters['target_audience'];
                $types .= 'ss';
            }

            // Add expiration filtering
            $conditions[] = '(n.expires_at IS NULL OR n.expires_at > NOW())';

            // Combine conditions
            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // Validate pagination
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $offset = ($page - 1) * $perPage;

            $query = "
                SELECT n.*,
                       u.full_name as created_by_name,
                       COUNT(DISTINCT CASE WHEN nv.user_id = ? THEN nv.id END) as is_viewed,
                       COUNT(DISTINCT CASE WHEN nd.user_id = ? THEN nd.id END) as is_dismissed,
                       GROUP_CONCAT(
                           CONCAT('<attachment>', na.file_name, '</attachment>')
                           SEPARATOR ''
                       ) as attachments_list
                FROM system_notices n
                LEFT JOIN users u ON n.created_by = u.id
                LEFT JOIN notice_views nv ON n.id = nv.notice_id AND nv.user_id = ?
                LEFT JOIN notice_dismissals nd ON n.id = nd.notice_id AND nd.user_id = ?
                LEFT JOIN notice_attachments na ON n.id = na.notice_id
                {$whereClause}
                GROUP BY n.id
                ORDER BY
                    FIELD(n.priority, 'critical', 'high', 'medium', 'low') DESC,
                    n.created_at DESC
                LIMIT ? OFFSET ?
            ";

            // Add user ID parameters for personalized filtering
            if ($userId) {
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $params[] = 0;
                $params[] = 0;
            }
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $perPage;
            $params[] = $offset;

            $types .= 'iiiiiii';

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $notices = [];
            while ($row = $result->fetch_assoc()) {
                $notices[] = [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'notice_type' => $row['notice_type'],
                    'priority' => $row['priority'],
                    'target_audience' => $row['target_audience'],
                    'expires_at' => $row['expires_at'],
                    'is_active' => (bool) $row['is_active'],
                    'created_by' => (int) $row['created_by'],
                    'created_by_name' => $row['created_by_name'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'is_viewed' => (bool) $row['is_viewed'],
                    'is_dismissed' => (bool) $row['is_dismissed'],
                    'attachments_list' => $row['attachments_list']
                ];
            }
            $stmt->close();

            // Get total count for pagination
            $countQuery = "SELECT COUNT(DISTINCT n.id) as total
                           FROM system_notices n
                           {$whereClause}";
            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countParams = array_slice($params, 0, count($params) - 4); // Remove pagination params
            $countTypes = substr($types, 0, strlen($types) - 4);
            $countStmt->bind_param($countTypes, ...$countParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            return [
                'notices' => $notices,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
        } catch (\Exception $e) {
            Logger::error('Error retrieving all notices', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'filters' => $filters
            ]);
            throw $e;
        }
    }
}