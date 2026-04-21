<?php

namespace EMA\Models;

use EMA\Utils\Logger;

class AdminDashboard
{
    /**
     * Get comprehensive system overview statistics
     * @return array System overview data including users, files, quizzes, health status
     */
    public static function getSystemOverview(): array
    {
        try {
            $overview = [];

            // User statistics
            $overview['users'] = self::getUserStatistics();

            // File statistics
            $overview['files'] = self::getFileStatistics();

            // Quiz statistics
            $overview['quizzes'] = self::getQuizStatistics();

            // System health
            $overview['health'] = self::getLatestSystemHealth();

            // Recent activity summary
            $overview['recent_activity'] = self::getRecentActivitySummary();

            return $overview;
        } catch (\Exception $e) {
            Logger::error('System overview error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get user statistics
     * @return array User statistics data
     */
    private static function getUserStatistics(): array
    {
        $stats = [];

        // Total users
        $query = "SELECT COUNT(*) as total FROM users";
        $result = \EMA\Config\Database::query($query);
        $stats['total'] = $result->fetch_assoc()['total'];

        // Active users (last 24 hours)
        $query = "SELECT COUNT(*) as active FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = \EMA\Config\Database::query($query);
        $stats['active_24h'] = $result->fetch_assoc()['active'];

        // New users today
        $query = "SELECT COUNT(*) as new_today FROM users WHERE DATE(created_at) = CURDATE()";
        $result = \EMA\Config\Database::query($query);
        $stats['new_today'] = $result->fetch_assoc()['new_today'];

        // New users this week
        $query = "SELECT COUNT(*) as new_week FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result = \EMA\Config\Database::query($query);
        $stats['new_week'] = $result->fetch_assoc()['new_week'];

        // New users this month
        $query = "SELECT COUNT(*) as new_month FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = \EMA\Config\Database::query($query);
        $stats['new_month'] = $result->fetch_assoc()['new_month'];

        // Admin count
        $query = "SELECT COUNT(*) as admins FROM users WHERE role = 'admin'";
        $result = \EMA\Config\Database::query($query);
        $stats['admins'] = $result->fetch_assoc()['admins'];

        return $stats;
    }

    /**
     * Get file statistics
     * @return array File statistics data
     */
    private static function getFileStatistics(): array
    {
        $stats = [];

        // Total files
        $query = "SELECT COUNT(*) as total FROM files";
        $result = \EMA\Config\Database::query($query);
        $stats['total'] = $result->fetch_assoc()['total'];

        // Total downloads
        $query = "SELECT SUM(access_count) as total_downloads FROM files";
        $result = \EMA\Config\Database::query($query);
        $stats['total_downloads'] = $result->fetch_assoc()['total_downloads'] ?? 0;

        // Total storage (MB)
        $query = "SELECT SUM(file_size) as total_storage FROM files";
        $result = \EMA\Config\Database::query($query);
        $stats['total_storage_mb'] = round(($result->fetch_assoc()['total_storage'] ?? 0) / 1024 / 1024, 2);

        return $stats;
    }

    /**
     * Get quiz statistics
     * @return array Quiz statistics data
     */
    private static function getQuizStatistics(): array
    {
        $stats = [];

        // Total quiz sets
        $query = "SELECT COUNT(*) as total FROM quiz_sets";
        $result = \EMA\Config\Database::query($query);
        $stats['total'] = $result->fetch_assoc()['total'];

        // Total quiz attempts
        $query = "SELECT COUNT(*) as total_attempts FROM quiz_attempts";
        $result = \EMA\Config\Database::query($query);
        $stats['total_attempts'] = $result->fetch_assoc()['total_attempts'];

        // Completion rate
        $query = "SELECT
                   COUNT(*) as total,
                   SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed
                   FROM quiz_attempts";
        $result = \EMA\Config\Database::query($query);
        $data = $result->fetch_assoc();
        $stats['completion_rate'] = $data['total'] > 0 ? round(($data['completed'] / $data['total']) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Get latest system health status
     * @return array System health data
     */
    private static function getLatestSystemHealth(): array
    {
        $health = [];

        try {
            // Get latest health metrics for each type
            $metricTypes = ['database', 'disk', 'memory', 'cpu', 'api_performance', 'error_rate'];

            foreach ($metricTypes as $type) {
                $query = "SELECT * FROM system_health WHERE metric_type = ? ORDER BY recorded_at DESC LIMIT 1";
                $stmt = \EMA\Config\Database::prepare($query);
                $stmt->bind_param('s', $type);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $health[$type] = $result->fetch_assoc();
                } else {
                    $health[$type] = [
                        'metric_type' => $type,
                        'status' => 'unknown',
                        'metric_value' => 0,
                        'metric_unit' => null
                    ];
                }

                $stmt->close();
            }

            // Calculate overall health score
            $health['overall_score'] = self::calculateHealthScore($health);
            $health['overall_status'] = self::determineHealthStatus($health['overall_score']);

        } catch (\Exception $e) {
            Logger::error('System health retrieval error', [
                'error' => $e->getMessage()
            ]);
            $health['error'] = 'Unable to retrieve health metrics';
        }

        return $health;
    }

    /**
     * Get recent activity summary
     * @return array Recent activity data
     */
    private static function getRecentActivitySummary(): array
    {
        try {
            $query = "SELECT
                       action,
                       COUNT(*) as count
                       FROM system_activity
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       GROUP BY action
                       ORDER BY count DESC
                       LIMIT 10";

            $result = \EMA\Config\Database::query($query);
            $activities = [];

            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }

            return $activities;
        } catch (\Exception $e) {
            Logger::error('Recent activity summary error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calculate health score from metrics
     * @param array $health Health metrics
     * @return float Health score (0-100)
     */
    private static function calculateHealthScore(array $health): float
    {
        $score = 100;
        $penalties = [
            'critical' => 25,
            'warning' => 10,
            'unknown' => 15
        ];

        foreach ($health as $metric) {
            if (is_array($metric) && isset($metric['status'])) {
                $status = $metric['status'];
                if (isset($penalties[$status])) {
                    $score -= $penalties[$status];
                }
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Determine health status from score
     * @param float $score Health score
     * @return string Health status
     */
    private static function determineHealthStatus(float $score): string
    {
        if ($score >= 80) return 'healthy';
        if ($score >= 50) return 'warning';
        return 'critical';
    }

    /**
     * Get user activity statistics
     * @param string|null $timeframe Timeframe for statistics ('day', 'week', 'month', 'all')
     * @return array User activity statistics
     */
    public static function getUserActivityStats(?string $timeframe = null): array
    {
        try {
            $timeCondition = self::getTimeCondition($timeframe);

            $query = "SELECT
                       action,
                       COUNT(*) as count,
                       COUNT(DISTINCT user_id) as unique_users,
                       COUNT(DISTINCT DATE(created_at)) as active_days
                       FROM system_activity
                       WHERE created_at >= {$timeCondition}
                       GROUP BY action
                       ORDER BY count DESC";

            $result = \EMA\Config\Database::query($query);
            $activities = [];

            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }

            // Get peak activity hours
            $peakHours = self::getPeakActivityHours($timeCondition);
            // Get user growth trends
            $growthTrends = self::getUserGrowthTrends($timeCondition);

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
     * Get system health metrics
     * @return array System health data with history
     */
    public static function getSystemHealth(): array
    {
        try {
            $health = self::getLatestSystemHealth();

            // Get historical data (last 24 hours)
            $query = "SELECT
                       metric_type,
                       status,
                       metric_value,
                       metric_unit,
                       recorded_at
                       FROM system_health
                       WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       ORDER BY recorded_at DESC";

            $result = \EMA\Config\Database::query($query);
            $history = [];

            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }

            $health['history'] = $history;

            // Generate health recommendations
            $health['recommendations'] = self::generateHealthRecommendations($health);

            return $health;
        } catch (\Exception $e) {
            Logger::error('System health error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get audit log entries
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

            if ($userId !== null) {
                $conditions[] = "al.user_id = ?";
                $params[] = $userId;
            }

            if ($action !== null) {
                $conditions[] = "al.action LIKE ?";
                $params[] = "%{$action}%";
            }

            if ($entityType !== null) {
                $conditions[] = "al.entity_type = ?";
                $params[] = $entityType;
            }

            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM audit_log al {$whereClause}";
            $stmt = \EMA\Config\Database::prepare($countQuery);
            foreach ($params as $index => $param) {
                $stmt->bind_param(is_int($param) ? 'i' : 's', $param);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Get paginated results
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

            $stmt = \EMA\Config\Database::prepare($query);
            $paramTypes = str_repeat(is_int($params[0] ?? '') ? 'i' : 's', count($params)) . 'ii';
            $allParams = array_merge($params, [$perPage, $offset]);
            $stmt->bind_param($paramTypes, ...$allParams);
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

    /**
     * Get bulk operation status
     * @param int $operationId Bulk operation ID
     * @return array|null Bulk operation details or null if not found
     */
    public static function getBulkOperationStatus(int $operationId): ?array
    {
        try {
            $query = "SELECT
                      bo.*,
                      u.full_name as admin_name,
                      u.email as admin_email
                      FROM bulk_operations bo
                      LEFT JOIN users u ON bo.admin_id = u.id
                      WHERE bo.id = ? LIMIT 1";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return null;
            }

            $operation = $result->fetch_assoc();

            // Parse target_ids JSON
            if (isset($operation['target_ids'])) {
                $operation['target_ids'] = json_decode($operation['target_ids'], true);
            }

            // Parse results JSON
            if (isset($operation['results'])) {
                $operation['results'] = json_decode($operation['results'], true);
            }

            // Calculate progress percentage
            if ($operation['total_items'] > 0) {
                $operation['progress'] = round(($operation['processed_items'] / $operation['total_items']) * 100, 2);
            } else {
                $operation['progress'] = 0;
            }

            $stmt->close();

            return $operation;
        } catch (\Exception $e) {
            Logger::error('Bulk operation status error', [
                'error' => $e->getMessage(),
                'operation_id' => $operationId
            ]);
            throw $e;
        }
    }

    /**
     * Get time condition for SQL queries
     * @param string|null $timeframe Timeframe ('day', 'week', 'month', 'all')
     * @return string SQL time condition
     */
    private static function getTimeCondition(?string $timeframe): string
    {
        switch ($timeframe) {
            case 'day':
                return "DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'week':
                return "DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'all':
            default:
                return "'1970-01-01'";
        }
    }

    /**
     * Get peak activity hours
     * @param string $timeCondition Time condition for query
     * @return array Peak activity hours data
     */
    private static function getPeakActivityHours(string $timeCondition): array
    {
        $query = "SELECT
                   HOUR(created_at) as hour,
                   COUNT(*) as count
                   FROM system_activity
                   WHERE created_at >= {$timeCondition}
                   GROUP BY HOUR(created_at)
                   ORDER BY count DESC
                   LIMIT 5";

        $result = \EMA\Config\Database::query($query);
        $peakHours = [];

        while ($row = $result->fetch_assoc()) {
            $peakHours[] = $row;
        }

        return $peakHours;
    }

    /**
     * Get user growth trends
     * @param string $timeCondition Time condition for query
     * @return array User growth trends
     */
    private static function getUserGrowthTrends(string $timeCondition): array
    {
        $query = "SELECT
                   DATE(created_at) as date,
                   COUNT(*) as new_users
                   FROM users
                   WHERE created_at >= {$timeCondition}
                   GROUP BY DATE(created_at)
                   ORDER BY date ASC";

        $result = \EMA\Config\Database::query($query);
        $trends = [];

        while ($row = $result->fetch_assoc()) {
            $trends[] = $row;
        }

        return $trends;
    }

    /**
     * Generate health recommendations based on metrics
     * @param array $health Health metrics
     * @return array Health recommendations
     */
    private static function generateHealthRecommendations(array $health): array
    {
        $recommendations = [];

        foreach ($health as $metric => $data) {
            if (is_array($data) && isset($data['status']) && $data['status'] !== 'healthy') {
                switch ($metric) {
                    case 'database':
                        $recommendations[] = [
                            'type' => 'database',
                            'severity' => $data['status'],
                            'message' => 'Database performance is degraded. Consider optimizing queries or upgrading resources.'
                        ];
                        break;
                    case 'disk':
                        $recommendations[] = [
                            'type' => 'disk',
                            'severity' => $data['status'],
                            'message' => 'Disk space is running low. Consider cleaning up old files or upgrading storage.'
                        ];
                        break;
                    case 'memory':
                        $recommendations[] = [
                            'type' => 'memory',
                            'severity' => $data['status'],
                            'message' => 'Memory usage is high. Consider optimizing memory usage or upgrading resources.'
                        ];
                        break;
                    case 'api_performance':
                        $recommendations[] = [
                            'type' => 'api_performance',
                            'severity' => $data['status'],
                            'message' => 'API response times are slower than expected. Consider caching or optimizing endpoints.'
                        ];
                        break;
                    case 'error_rate':
                        $recommendations[] = [
                            'type' => 'error_rate',
                            'severity' => $data['status'],
                            'message' => 'Error rate is elevated. Review recent logs and error patterns.'
                        ];
                        break;
                }
            }
        }

        return $recommendations;
    }
}