<?php

namespace EMA\Models;

use EMA\Utils\Logger;

/**
 * Fixed AdminDashboard - Security and N+1 problem fixes
 * This file contains security fixes for the original AdminDashboard model
 */
class AdminDashboard_Fixed
{
    /**
     * Get user activity statistics (FIXED SQL Injection)
     * @param string|null $timeframe Timeframe for statistics
     * @return array User activity statistics
     */
    public static function getUserActivityStats(?string $timeframe = null): array
    {
        try {
            // Fixed: Use parameter binding instead of direct variable insertion
            $timeValue = self::getTimeConditionValue($timeframe);

            $query = "SELECT
                       action,
                       COUNT(*) as count,
                       COUNT(DISTINCT user_id) as unique_users,
                       COUNT(DISTINCT DATE(created_at)) as active_days
                       FROM system_activity
                       WHERE created_at >= ?
                       GROUP BY action
                       ORDER BY count DESC";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('s', $timeValue);
            $stmt->execute();
            $result = $stmt->get_result();
            $activities = [];
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
            $stmt->close();

            // Get peak activity hours
            $peakHours = self::getPeakActivityHours($timeValue);
            // Get user growth trends
            $growthTrends = self::getUserGrowthTrends($timeValue);

            return [
                'activities' => $activities,
                'peak_hours' => $peakHours,
                'growth_trends' => $growthTrends,
                'timeframe' => $timeframe ?? 'all'
            ];
        } catch (\Exception $e) {
            Logger::error('User activity statistics error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get peak activity hours (FIXED SQL Injection)
     * @param string $timeValue Time value for WHERE clause
     * @return array Peak activity hours data
     */
    private static function getPeakActivityHours(string $timeValue): array
    {
        $query = "SELECT
                   HOUR(created_at) as hour,
                   COUNT(*) as count
                   FROM system_activity
                   WHERE created_at >= ?
                   GROUP BY HOUR(created_at)
                   ORDER BY count DESC
                   LIMIT 5";

        $stmt = \EMA\Config\Database::prepare($query);
        $stmt->bind_param('s', $timeValue);
        $stmt->execute();
        $result = $stmt->get_result();
        $peakHours = [];
        while ($row = $result->fetch_assoc()) {
            $peakHours[] = $row;
        }
        $stmt->close();

        return $peakHours;
    }

    /**
     * Get user growth trends (FIXED SQL Injection)
     * @param string $timeValue Time value for WHERE clause
     * @return array User growth trends
     */
    private static function getUserGrowthTrends(string $timeValue): array
    {
        $query = "SELECT
                   DATE(created_at) as date,
                   COUNT(*) as new_users
                   FROM users
                   WHERE created_at >= ?
                   GROUP BY DATE(created_at)
                   ORDER BY date ASC";

        $stmt = \EMA\Config\Database::prepare($query);
        $stmt->bind_param('s', $timeValue);
        $stmt->execute();
        $result = $stmt->get_result();
        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = $row;
        }
        $stmt->close();

        return $trends;
    }

    /**
     * Get time condition value (FIXED)
     * @param string|null $timeframe Timeframe
     * @return string Time condition value
     */
    private static function getTimeConditionValue(?string $timeframe): string
    {
        switch ($timeframe) {
            case 'day':
                return DATE_SUB(NOW(), INTERVAL 1 DAY);
            case 'week':
                return DATE_SUB(NOW(), INTERVAL 7 DAY);
            case 'month':
                return DATE_SUB(NOW(), INTERVAL 30 DAY);
            case 'all':
            default:
                return '1970-01-01';
        }
    }

    /**
     * Get audit log entries (FIXED bind_param issue)
     * @param int|null $userId Filter by user ID
     * @param string|null $action Filter by action type
     * @param string|null $entityType Filter by entity type
     * @param int $page Pagination page
     * @param int $perPage Items per page
     * @return array Paginated audit log entries
     */
    public static function getAuditLog(
        ?int $userId = null,
        ?string $action = null,
        ?string $entityType = null,
        int $page = 1,
        int $perPage = 50
    ): array {
        try {
            $conditions = [];
            $params = [];
            $types = '';

            if ($userId !== null) {
                $conditions[] = "al.user_id = ?";
                $params[] = $userId;
                $types .= 'i';
            }

            if ($action !== null) {
                $conditions[] = "al.action LIKE ?";
                $params[] = "%{$action}%";
                $types .= 's';
            }

            if ($entityType !== null) {
                $conditions[] = "al.entity_type = ?";
                $params[] = $entityType;
                $types .= 's';
            }

            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

            // Fixed: Proper parameter binding for count query
            $countQuery = "SELECT COUNT(*) as total FROM audit_log al {$whereClause}";
            $stmt = \EMA\Config\Database::prepare($countQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Fixed: Proper parameter binding for main query
            $offset = ($page - 1) * $perPage;
            $query = "SELECT
                      al.*,
                      u.full_name as user_name,
                      u.email as user_email
                      FROM audit_log al
                      LEFT JOIN users u ON al.user_id = u.id
                      {$whereClause}
                      ORDER BY al.created_at DESC
                      LIMIT ? OFFSET ?";

            $types .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $logs = [];
            while ($row = $result->fetch_assoc()) {
                // Parse JSON fields if they exist
                if (isset($row['old_values'])) {
                    $row['old_values'] = json_decode($row['old_values'], true);
                }
                if (isset($row['new_values'])) {
                    $row['new_values'] = json_decode($row['new_values'], true);
                }
                $logs[] = $row;
            }

            $stmt->close();

            return [
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
        } catch (\Exception $e) {
            Logger::error('Audit log retrieval error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType
            ]);
            throw $e;
        }
    }
}