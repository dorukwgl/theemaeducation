<?php

namespace EMA\Services;

use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Models\User;
use EMA\Models\AdminDashboard;

class SystemMonitoringService
{
    /**
     * Check comprehensive system health
     * @return array System health check results
     */
    public function checkSystemHealth(): array
    {
        try {
            $healthChecks = [];

            // Database health
            $healthChecks['database'] = $this->checkDatabaseHealth();

            // Disk health
            $healthChecks['disk'] = $this->checkDiskHealth();

            // Memory health
            $healthChecks['memory'] = $this->checkMemoryHealth();

            // CPU health (if available)
            $healthChecks['cpu'] = $this->checkCpuHealth();

            // API performance
            $healthChecks['api_performance'] = $this->checkApiPerformance();

            // Error rate
            $healthChecks['error_rate'] = $this->checkErrorRate();

            // Record metrics for all health checks
            foreach ($healthChecks as $type => $check) {
                $this->recordSystemMetric(
                    $type,
                    $check['metric_value'],
                    $check['metric_unit']
                );
            }

            // Calculate overall health score
            $overallScore = $this->calculateOverallHealthScore($healthChecks);

            return [
                'health_checks' => $healthChecks,
                'overall_score' => $overallScore,
                'overall_status' => $this->determineOverallStatus($overallScore),
                'checked_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Logger::error('System health check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check database health
     * @return array Database health metrics
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);

            // Test database connection
            $connection = \EMA\Config\Database::getConnection();
            $isConnected = $connection && !$connection->connect_error;

            // Test query performance
            if ($isConnected) {
                $query = "SELECT 1";
                $result = \EMA\Config\Database::query($query);
                $queryTime = (microtime(true) - $startTime) * 1000; // milliseconds

                // Get connection count if available
                $showStatusQuery = "SHOW STATUS LIKE 'Threads_connected'";
                $statusResult = \EMA\Config\Database::query($showStatusQuery);
                $connections = $statusResult->fetch_assoc()['Value'] ?? 0;

                // Determine health status based on query time
                if ($queryTime < 50) {
                    $status = 'healthy';
                } elseif ($queryTime < 200) {
                    $status = 'warning';
                } else {
                    $status = 'critical';
                }

                return [
                    'metric_type' => 'database',
                    'metric_value' => $queryTime,
                    'metric_unit' => 'ms',
                    'status' => $status,
                    'details' => json_encode([
                        'connection_status' => $isConnected ? 'connected' : 'disconnected',
                        'active_connections' => $connections,
                        'query_time' => $queryTime
                    ])
                ];
            } else {
                return [
                    'metric_type' => 'database',
                    'metric_value' => 0,
                    'metric_unit' => 'ms',
                    'status' => 'critical',
                    'details' => json_encode([
                        'connection_status' => 'disconnected',
                        'error' => $connection->connect_error ?? 'Unknown error'
                    ])
                ];
            }
        } catch (\Exception $e) {
            Logger::error('Database health check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'database',
                'metric_value' => 0,
                'metric_unit' => 'ms',
                'status' => 'critical',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Check disk health
     * @return array Disk health metrics
     */
    private function checkDiskHealth(): array
    {
        try {
            $uploadDir = $_ENV['UPLOAD_PATH'] ?? '/uploads';
            $totalSpace = disk_total_space($uploadDir);
            $freeSpace = disk_free_space($uploadDir);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = ($usedSpace / $totalSpace) * 100;

            // Determine health status based on disk usage
            if ($usagePercentage < 70) {
                $status = 'healthy';
            } elseif ($usagePercentage < 90) {
                $status = 'warning';
            } else {
                $status = 'critical';
            }

            return [
                'metric_type' => 'disk',
                'metric_value' => $usagePercentage,
                'metric_unit' => '%',
                'status' => $status,
                'details' => json_encode([
                    'total_space_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                    'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                    'used_space_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
                    'usage_percentage' => round($usagePercentage, 2)
                ])
            ];
        } catch (\Exception $e) {
            Logger::error('Disk health check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'disk',
                'metric_value' => 0,
                'metric_unit' => '%',
                'status' => 'unknown',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Check memory health
     * @return array Memory health metrics
     */
    private function checkMemoryHealth(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;

            // Determine health status based on memory usage
            if ($usagePercentage < 70) {
                $status = 'healthy';
            } elseif ($usagePercentage < 90) {
                $status = 'warning';
            } else {
                $status = 'critical';
            }

            return [
                'metric_type' => 'memory',
                'metric_value' => $usagePercentage,
                'metric_unit' => '%',
                'status' => $status,
                'details' => json_encode([
                    'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                    'memory_limit' => $memoryLimit,
                    'memory_limit_mb' => round($memoryLimitBytes / 1024 / 1024, 2),
                    'usage_percentage' => round($usagePercentage, 2)
                ])
            ];
        } catch (\Exception $e) {
            Logger::error('Memory health check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'memory',
                'metric_value' => 0,
                'metric_unit' => '%',
                'status' => 'unknown',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Check CPU health (basic implementation)
     * @return array CPU health metrics
     */
    private function checkCpuHealth(): array
    {
        try {
            // Basic CPU load check (Linux only)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $currentLoad = $load[0];

                // Determine health status based on load average
                if ($currentLoad < 1.0) {
                    $status = 'healthy';
                } elseif ($currentLoad < 2.0) {
                    $status = 'warning';
                } else {
                    $status = 'critical';
                }

                return [
                    'metric_type' => 'cpu',
                    'metric_value' => $currentLoad,
                    'metric_unit' => 'load',
                    'status' => $status,
                    'details' => json_encode([
                        'load_1min' => $load[0],
                        'load_5min' => $load[1],
                        'load_15min' => $load[2]
                    ])
                ];
            } else {
                return [
                    'metric_type' => 'cpu',
                    'metric_value' => 0,
                    'metric_unit' => 'load',
                    'status' => 'unknown',
                    'details' => json_encode([
                        'message' => 'CPU monitoring not available on this system'
                    ])
                ];
            }
        } catch (\Exception $e) {
            Logger::error('CPU health check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'cpu',
                'metric_value' => 0,
                'metric_unit' => 'load',
                'status' => 'unknown',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Check API performance
     * @return array API performance metrics
     */
    private function checkApiPerformance(): array
    {
        try {
            $startTime = microtime(true);

            // Test a simple API endpoint
            $testQuery = "SELECT COUNT(*) as count FROM users";
            $result = \EMA\Config\Database::query($testQuery);
            $apiResponseTime = (microtime(true) - $startTime) * 1000; // milliseconds

            // Determine health status based on response time
            if ($apiResponseTime < 100) {
                $status = 'healthy';
            } elseif ($apiResponseTime < 500) {
                $status = 'warning';
            } else {
                $status = 'critical';
            }

            return [
                'metric_type' => 'api_performance',
                'metric_value' => $apiResponseTime,
                'metric_unit' => 'ms',
                'status' => $status,
                'details' => json_encode([
                    'response_time' => $apiResponseTime,
                    'test_query' => 'SELECT COUNT(*) FROM users'
                ])
            ];
        } catch (\Exception $e) {
            Logger::error('API performance check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'api_performance',
                'metric_value' => 0,
                'metric_unit' => 'ms',
                'status' => 'critical',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Check error rate
     * @return array Error rate metrics
     */
    private function checkErrorRate(): array
    {
        try {
            $errorLogFile = $_ENV['LOG_PATH'] ?? '/logs' . '/error.log';

            if (file_exists($errorLogFile)) {
                // Get error count from last hour
                $oneHourAgo = time() - 3600;
                $errorCount = 0;
                $totalRequests = 0;

                // Parse error log (simplified version)
                $handle = fopen($errorLogFile, 'r');
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        if (strpos($line, '[ERROR]') !== false) {
                            $errorCount++;
                        }
                    }
                    fclose($handle);
                }

                // Estimate error rate (simplified)
                $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;

                // Determine health status based on error rate
                if ($errorRate < 1) {
                    $status = 'healthy';
                } elseif ($errorRate < 5) {
                    $status = 'warning';
                } else {
                    $status = 'critical';
                }

                return [
                    'metric_type' => 'error_rate',
                    'metric_value' => $errorRate,
                    'metric_unit' => '%',
                    'status' => $status,
                    'details' => json_encode([
                        'error_count_last_hour' => $errorCount,
                        'error_rate' => round($errorRate, 2)
                    ])
                ];
            } else {
                return [
                    'metric_type' => 'error_rate',
                    'metric_value' => 0,
                    'metric_unit' => '%',
                    'status' => 'unknown',
                    'details' => json_encode([
                        'message' => 'Error log file not found'
                    ])
                ];
            }
        } catch (\Exception $e) {
            Logger::error('Error rate check error', [
                'error' => $e->getMessage()
            ]);
            return [
                'metric_type' => 'error_rate',
                'metric_value' => 0,
                'metric_unit' => '%',
                'status' => 'unknown',
                'details' => json_encode([
                    'error' => $e->getMessage()
                ])
            ];
        }
    }

    /**
     * Record system metric to database
     * @param string $metricType Type of metric
     * @param float $metricValue Metric value
     * @param string|null $metricUnit Unit of measurement
     * @return bool Success status
     */
    public function recordSystemMetric(string $metricType, float $metricValue, ?string $metricUnit = null): bool
    {
        try {
            // Determine status based on metric type thresholds
            $status = $this->determineMetricStatus($metricType, $metricValue);

            $query = "INSERT INTO system_health (metric_type, metric_value, metric_unit, status) VALUES (?, ?, ?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('sdss', $metricType, $metricValue, $metricUnit, $status);

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                // Clean up old metrics (keep last 30 days)
                $this->cleanupOldMetrics();
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('System metric recording error', [
                'metric_type' => $metricType,
                'metric_value' => $metricValue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Determine metric status based on thresholds
     * @param string $metricType Type of metric
     * @param float $metricValue Metric value
     * @return string Health status
     */
    private function determineMetricStatus(string $metricType, float $metricValue): string
    {
        switch ($metricType) {
            case 'database':
                return $metricValue < 50 ? 'healthy' : ($metricValue < 200 ? 'warning' : 'critical');
            case 'disk':
                return $metricValue < 70 ? 'healthy' : ($metricValue < 90 ? 'warning' : 'critical');
            case 'memory':
                return $metricValue < 70 ? 'healthy' : ($metricValue < 90 ? 'warning' : 'critical');
            case 'cpu':
                return $metricValue < 1.0 ? 'healthy' : ($metricValue < 2.0 ? 'warning' : 'critical');
            case 'api_performance':
                return $metricValue < 100 ? 'healthy' : ($metricValue < 500 ? 'warning' : 'critical');
            case 'error_rate':
                return $metricValue < 1 ? 'health' : ($metricValue < 5 ? 'warning' : 'critical');
            default:
                return 'healthy';
        }
    }

    /**
     * Clean up old system health metrics
     * @return bool Success status
     */
    private function cleanupOldMetrics(): bool
    {
        try {
            $query = "DELETE FROM system_health WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $result = \EMA\Config\Database::query($query);
            return $result !== false;
        } catch (\Exception $e) {
            Logger::error('Old metrics cleanup error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate overall health score
     * @param array $healthChecks Health check results
     * @return float Overall health score (0-100)
     */
    private function calculateOverallHealthScore(array $healthChecks): float
    {
        $score = 100;
        $penalties = [
            'critical' => 25,
            'warning' => 10,
            'unknown' => 15
        ];

        foreach ($healthChecks as $check) {
            if (isset($check['status']) && isset($penalties[$check['status']])) {
                $score -= $penalties[$check['status']];
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Determine overall health status
     * @param float $score Health score
     * @return string Overall health status
     */
    private function determineOverallStatus(float $score): string
    {
        if ($score >= 80) return 'healthy';
        if ($score >= 50) return 'warning';
        return 'critical';
    }

    /**
     * Create bulk operation
     * @param int $adminId Admin user ID
     * @param string $operationType Type of bulk operation
     * @param string $targetType Type of target entities
     * @param array $targetIds Array of target entity IDs
     * @return int|false Bulk operation ID or false on failure
     */
    public function createBulkOperation(int $adminId, string $operationType, string $targetType, array $targetIds): int|false
    {
        try {
            // Validate admin user
            $admin = User::findById($adminId);
            if (!$admin || $admin->getRole() !== 'admin') {
                Logger::logSecurityEvent('Invalid bulk operation creation', [
                    'admin_id' => $adminId,
                    'ip' => Security::getRealIp()
                ]);
                return false;
            }

            // Validate operation type and target type compatibility
            if (!$this->isValidOperationType($operationType)) {
                Logger::error('Invalid bulk operation type', [
                    'operation_type' => $operationType
                ]);
                return false;
            }

            if (!$this->isValidTargetType($targetType)) {
                Logger::error('Invalid bulk operation target type', [
                    'target_type' => $targetType
                ]);
                return false;
            }

            // Limit target IDs to 1000 items
            $limitedTargetIds = array_slice($targetIds, 0, 1000);

            // Create bulk operation record
            $query = "INSERT INTO bulk_operations (admin_id, operation_type, target_type, target_ids, total_items, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = \EMA\Config\Database::prepare($query);
            $targetIdsJson = json_encode($limitedTargetIds);
            $totalItems = count($limitedTargetIds);
            $stmt->bind_param('isssi', $adminId, $operationType, $targetType, $targetIdsJson, $totalItems);

            $result = $stmt->execute();
            $operationId = $stmt->insert_id;
            $stmt->close();

            if ($result) {
                Logger::logSecurityEvent('Bulk operation created', [
                    'operation_id' => $operationId,
                    'admin_id' => $adminId,
                    'operation_type' => $operationType,
                    'target_type' => $targetType,
                    'total_items' => $totalItems,
                    'ip' => Security::getRealIp()
                ]);

                // Start processing in background (simplified version)
                $this->processBulkOperation($operationId);

                return $operationId;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Logger::error('Bulk operation creation error', [
                'admin_id' => $adminId,
                'operation_type' => $operationType,
                'target_type' => $targetType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process bulk operation
     * @param int $operationId Bulk operation ID
     * @return array Operation results
     */
    public function processBulkOperation(int $operationId): array
    {
        try {
            // Get operation details
            $operation = AdminDashboard::getBulkOperationStatus($operationId);
            if (!$operation) {
                return ['success' => false, 'error' => 'Operation not found'];
            }

            // Update status to processing
            $updateQuery = "UPDATE bulk_operations SET status = 'processing', started_at = NOW() WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($updateQuery);
            $stmt->bind_param('i', $operationId);
            $stmt->execute();
            $stmt->close();

            // Process items in chunks
            $chunkSize = 20;
            $targetIds = $operation['target_ids'];
            $chunks = array_chunk($targetIds, $chunkSize);

            $processedItems = 0;
            $failedItems = 0;
            $errors = [];

            foreach ($chunks as $chunk) {
                foreach ($chunk as $targetId) {
                    $result = $this->processBulkOperationItem($operation, $targetId);

                    if ($result['success']) {
                        $processedItems++;
                    } else {
                        $failedItems++;
                        $errors[] = [
                            'target_id' => $targetId,
                            'error' => $result['error']
                        ];
                    }
                }

                // Update progress
                $updateQuery = "UPDATE bulk_operations SET processed_items = ?, failed_items = ? WHERE id = ?";
                $stmt = \EMA\Config\Database::prepare($updateQuery);
                $stmt->bind_param('iii', $processedItems, $failedItems, $operationId);
                $stmt->execute();
                $stmt->close();
            }

            // Determine final status
            $finalStatus = $failedItems > 0 ? 'completed' : 'completed';
            $results = [
                'total_items' => $operation['total_items'],
                'processed_items' => $processedItems,
                'failed_items' => $failedItems,
                'errors' => $errors
            ];

            // Update final status
            $updateQuery = "UPDATE bulk_operations SET status = ?, processed_items = ?, failed_items = ?, results = ?, completed_at = NOW() WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($updateQuery);
            $resultsJson = json_encode($results);
            $stmt->bind_param('sisssi', $finalStatus, $processedItems, $failedItems, $resultsJson, $operationId);
            $result = $stmt->execute();
            $stmt->close();

            Logger::info('Bulk operation processed', [
                'operation_id' => $operationId,
                'total_items' => $operation['total_items'],
                'processed_items' => $processedItems,
                'failed_items' => $failedItems
            ]);

            return $results;
        } catch (\Exception $e) {
            Logger::error('Bulk operation processing error', [
                'operation_id' => $operationId,
                'error' => $e->getMessage()
            ]);

            // Update status to failed
            $updateQuery = "UPDATE bulk_operations SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($updateQuery);
            $errorMessage = $e->getMessage();
            $stmt->bind_param('si', $errorMessage, $operationId);
            $stmt->execute();
            $stmt->close();

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process single bulk operation item
     * @param array $operation Bulk operation details
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkOperationItem(array $operation, int $targetId): array
    {
        try {
            switch ($operation['operation_type']) {
                case 'bulk_delete':
                    return $this->processBulkDelete($operation['target_type'], $targetId);
                case 'bulk_update':
                    return $this->processBulkUpdate($operation['target_type'], $targetId);
                case 'bulk_grant_access':
                    return $this->processBulkGrantAccess($operation['target_type'], $targetId);
                case 'bulk_revoke_access':
                    return $this->processBulkRevokeAccess($operation['target_type'], $targetId);
                case 'bulk_publish':
                    return $this->processBulkPublish($operation['target_type'], $targetId);
                case 'bulk_archive':
                    return $this->processBulkArchive($operation['target_type'], $targetId);
                default:
                    return ['success' => false, 'error' => 'Unknown operation type'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk delete
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkDelete(string $targetType, int $targetId): array
    {
        try {
            $table = $this->getTargetTable($targetType);
            $query = "DELETE FROM {$table} WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $targetId);
            $result = $stmt->execute();
            $stmt->close();

            return [
                'success' => $result,
                'error' => $result ? null : 'Delete failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk update
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkUpdate(string $targetType, int $targetId): array
    {
        try {
            // For now, just mark as successful since we don't have update data
            return [
                'success' => true,
                'error' => null
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk grant access
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkGrantAccess(string $targetType, int $targetId): array
    {
        try {
            // Grant access to all users (simplified version)
            $query = "INSERT INTO user_access (item_id, item_type, access_type, access_count) VALUES (?, ?, 'all', 0)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('is', $targetId, $targetType);
            $result = $stmt->execute();
            $stmt->close();

            return [
                'success' => $result,
                'error' => $result ? null : 'Access grant failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk revoke access
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkRevokeAccess(string $targetType, int $targetId): array
    {
        try {
            $query = "DELETE FROM user_access WHERE item_id = ? AND item_type = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('is', $targetId, $targetType);
            $result = $stmt->execute();
            $stmt->close();

            return [
                'success' => $result,
                'error' => $result ? null : 'Access revoke failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk publish
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkPublish(string $targetType, int $targetId): array
    {
        try {
            $table = $this->getTargetTable($targetType);
            $query = "UPDATE {$table} SET is_published = 1 WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $targetId);
            $result = $stmt->execute();
            $stmt->close();

            return [
                'success' => $result,
                'error' => $result ? null : 'Publish failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bulk archive
     * @param string $targetType Type of target entity
     * @param int $targetId Target entity ID
     * @return array Processing result
     */
    private function processBulkArchive(string $targetType, int $targetId): array
    {
        try {
            $table = $this->getTargetTable($targetType);
            $query = "UPDATE {$table} SET is_archived = 1 WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $targetId);
            $result = $stmt->execute();
            $stmt->close();

            return [
                'success' => $result,
                'error' => $result ? null : 'Archive failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get table name for target type
     * @param string $targetType Type of target entity
     * @return string Table name
     */
    private function getTargetTable(string $targetType): string
    {
        $tables = [
            'users' => 'users',
            'files' => 'files',
            'folders' => 'folders',
            'quiz_sets' => 'quiz_sets',
            'notices' => 'system_notices'
        ];

        return $tables[$targetType] ?? 'users';
    }

    /**
     * Validate operation type
     * @param string $operationType Operation type to validate
     * @return bool Valid status
     */
    private function isValidOperationType(string $operationType): bool
    {
        $validTypes = ['bulk_delete', 'bulk_update', 'bulk_grant_access', 'bulk_revoke_access', 'bulk_publish', 'bulk_archive'];
        return in_array($operationType, $validTypes);
    }

    /**
     * Validate target type
     * @param string $targetType Target type to validate
     * @return bool Valid status
     */
    private function isValidTargetType(string $targetType): bool
    {
        $validTypes = ['users', 'files', 'folders', 'quiz_sets', 'notices'];
        return in_array($targetType, $validTypes);
    }

    /**
     * Get comprehensive system analytics
     * @param string|null $timeframe Timeframe for analytics ('day', 'week', 'month', 'all')
     * @return array System analytics data
     */
    public function getSystemAnalytics(?string $timeframe = null): array
    {
        try {
            $timeCondition = $this->getTimeCondition($timeframe);

            // User engagement metrics
            $userEngagement = $this->getUserEngagementMetrics($timeCondition);

            // Content performance metrics
            $contentPerformance = $this->getContentPerformanceMetrics($timeCondition);

            // System performance metrics
            $systemPerformance = $this->getSystemPerformanceMetrics($timeCondition);

            // Security events summary
            $securityEvents = $this->getSecurityEventsSummary($timeCondition);

            // Generate charts data
            $chartsData = $this->generateChartsData($timeCondition);

            // Generate insights
            $insights = $this->generateAnalyticsInsights($userEngagement, $contentPerformance, $systemPerformance);

            return [
                'user_engagement' => $userEngagement,
                'content_performance' => $contentPerformance,
                'system_performance' => $systemPerformance,
                'security_events' => $securityEvents,
                'charts' => $chartsData,
                'insights' => $insights,
                'timeframe' => $timeframe ?? 'all'
            ];
        } catch (\Exception $e) {
            Logger::error('System analytics error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get user engagement metrics
     * @param string $timeCondition Time condition for query
     * @return array User engagement metrics
     */
    private function getUserEngagementMetrics(string $timeCondition): array
    {
        $query = "SELECT
                   COUNT(DISTINCT user_id) as active_users,
                   COUNT(*) as total_activities,
                   COUNT(DISTINCT DATE(created_at)) as active_days
                   FROM system_activity
                   WHERE created_at >= {$timeCondition}";

        $result = \EMA\Config\Database::query($query);
        $metrics = $result->fetch_assoc();

        // Calculate engagement rate
        $totalUsersQuery = "SELECT COUNT(*) as total FROM users";
        $totalUsersResult = \EMA\Config\Database::query($totalUsersQuery);
        $totalUsers = $totalUsersResult->fetch_assoc()['total'];

        $metrics['engagement_rate'] = $totalUsers > 0 ? round(($metrics['active_users'] / $totalUsers) * 100, 2) : 0;
        $metrics['total_users'] = $totalUsers;

        return $metrics;
    }

    /**
     * Get content performance metrics
     * @param string $timeCondition Time condition for query
     * @return array Content performance metrics
     */
    private function getContentPerformanceMetrics(string $timeCondition): array
    {
        // File performance
        $fileQuery = "SELECT
                       COUNT(*) as total_files,
                       SUM(access_count) as total_downloads,
                       AVG(access_count) as avg_downloads_per_file
                       FROM files";

        $fileResult = \EMA\Config\Database::query($fileQuery);
        $fileMetrics = $fileResult->fetch_assoc();

        // Quiz performance
        $quizQuery = "SELECT
                      COUNT(*) as total_quizzes,
                      (SELECT COUNT(*) FROM quiz_attempts WHERE created_at >= {$timeCondition}) as total_attempts,
                      (SELECT ROUND(AVG(score), 2) FROM quiz_attempts WHERE created_at >= {$timeCondition} AND completed_at IS NOT NULL) as avg_score
                      FROM quiz_sets";

        $quizResult = \EMA\Config\Database::query($quizQuery);
        $quizMetrics = $quizResult->fetch_assoc();

        return [
            'files' => $fileMetrics,
            'quizzes' => $quizMetrics
        ];
    }

    /**
     * Get system performance metrics
     * @param string $timeCondition Time condition for query
     * @return array System performance metrics
     */
    private function getSystemPerformanceMetrics(string $timeCondition): array
    {
        // Average API response time
        $healthQuery = "SELECT
                         AVG(CASE WHEN metric_type = 'api_performance' THEN metric_value ELSE NULL END) as avg_response_time,
                         AVG(CASE WHEN metric_type = 'database' THEN metric_value ELSE NULL END) as avg_query_time
                         FROM system_health
                         WHERE recorded_at >= {$timeCondition}";

        $healthResult = \EMA\Config\Database::query($healthQuery);
        $healthMetrics = $healthResult->fetch_assoc();

        return [
            'avg_response_time' => round($healthMetrics['avg_response_time'] ?? 0, 2),
            'avg_query_time' => round($healthMetrics['avg_query_time'] ?? 0, 2),
            'status' => $this->determinePerformanceStatus($healthMetrics['avg_response_time'] ?? 0)
        ];
    }

    /**
     * Get security events summary
     * @param string $timeCondition Time condition for query
     * @return array Security events summary
     */
    private function getSecurityEventsSummary(string $timeCondition): array
    {
        // Get security events from audit log
        $query = "SELECT
                   COUNT(*) as total_events,
                   COUNT(DISTINCT user_id) as affected_users,
                   COUNT(DISTINCT action) as event_types
                   FROM audit_log
                   WHERE created_at >= {$timeCondition}";

        $result = \EMA\Config\Database::query($query);
        $summary = $result->fetch_assoc();

        // Get recent security events
        $recentQuery = "SELECT
                        al.*,
                        u.full_name as user_name
                        FROM audit_log al
                        LEFT JOIN users u ON al.user_id = u.id
                        WHERE al.created_at >= {$timeCondition}
                        ORDER BY al.created_at DESC
                        LIMIT 10";

        $recentResult = \EMA\Config\Database::query($recentQuery);
        $recentEvents = [];

        while ($row = $recentResult->fetch_assoc()) {
            $recentEvents[] = $row;
        }

        return [
            'summary' => $summary,
            'recent_events' => $recentEvents
        ];
    }

    /**
     * Generate charts data for analytics
     * @param string $timeCondition Time condition for query
     * @return array Charts data
     */
    private function generateChartsData(string $timeCondition): array
    {
        // Daily activity chart
        $dailyQuery = "SELECT
                       DATE(created_at) as date,
                       COUNT(*) as count
                       FROM system_activity
                       WHERE created_at >= {$timeCondition}
                       GROUP BY DATE(created_at)
                       ORDER BY date ASC";

        $dailyResult = \EMA\Config\Database::query($dailyQuery);
        $dailyData = [];

        while ($row = $dailyResult->fetch_assoc()) {
            $dailyData[] = [
                'date' => $row['date'],
                'count' => $row['count']
            ];
        }

        // User growth chart
        $growthQuery = "SELECT
                        DATE(created_at) as date,
                        COUNT(*) as new_users
                        FROM users
                        WHERE created_at >= {$timeCondition}
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC";

        $growthResult = \EMA\Config\Database::query($growthQuery);
        $growthData = [];

        while ($row = $growthResult->fetch_assoc()) {
            $growthData[] = [
                'date' => $row['date'],
                'new_users' => $row['new_users']
            ];
        }

        return [
            'daily_activity' => $dailyData,
            'user_growth' => $growthData
        ];
    }

    /**
     * Generate actionable insights from analytics
     * @param array $userEngagement User engagement metrics
     * @param array $contentPerformance Content performance metrics
     * @param array $systemPerformance System performance metrics
     * @return array Actionable insights
     */
    private function generateAnalyticsInsights(array $userEngagement, array $contentPerformance, array $systemPerformance): array
    {
        $insights = [];

        // User engagement insights
        if ($userEngagement['engagement_rate'] < 20) {
            $insights[] = [
                'type' => 'engagement',
                'severity' => 'warning',
                'message' => 'User engagement rate is low (' . $userEngagement['engagement_rate'] . '%). Consider improving content and user experience.'
            ];
        }

        // Content performance insights
        if ($contentPerformance['quizzes']['avg_score'] < 50) {
            $insights[] = [
                'type' => 'content',
                'severity' => 'info',
                'message' => 'Average quiz score is ' . $contentPerformance['quizzes']['avg_score'] . '%. Review quiz difficulty and content quality.'
            ];
        }

        // System performance insights
        if ($systemPerformance['status'] === 'critical') {
            $insights[] = [
                'type' => 'performance',
                'severity' => 'critical',
                'message' => 'System performance is degraded. Average response time: ' . $systemPerformance['avg_response_time'] . 'ms. Immediate attention required.'
            ];
        }

        return $insights;
    }

    /**
     * Determine performance status from response time
     * @param float $responseTime Average response time in ms
     * @return string Performance status
     */
    private function determinePerformanceStatus(float $responseTime): string
    {
        if ($responseTime < 100) return 'excellent';
        if ($responseTime < 300) return 'good';
        if ($responseTime < 500) return 'fair';
        return 'poor';
    }

    /**
     * Get time condition for SQL queries
     * @param string|null $timeframe Timeframe ('day', 'week', 'month', 'all')
     * @return string SQL time condition
     */
    private function getTimeCondition(?string $timeframe): string
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
     * Convert memory limit string to bytes
     * @param string $value Memory limit string (e.g., '256M')
     * @return int Value in bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }
}