<?php

namespace EMA\Controllers;

use EMA\Models\User;
use EMA\Models\AdminDashboard;
use EMA\Services\SystemMonitoringService;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class AdminController
{
    private Request $request;
    private Response $response;
    private SystemMonitoringService $systemMonitoringService;

    public function __construct()
    {
        // Request will be set by Router via setRequest()
        $this->response = new Response();
        $this->systemMonitoringService = new SystemMonitoringService();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get comprehensive admin dashboard data
     * Endpoint: GET /api/admin/dashboard
     * Middleware: AuthMiddleware (admin only)
     */
    public function dashboard(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access dashboard', 403);
                return;
            }

            // Get comprehensive dashboard data
            $dashboardData = AdminDashboard::getSystemOverview();

            // Generate system alerts
            $alerts = $this->generateSystemAlerts($dashboardData);

            $this->response->success([
                'overview' => $dashboardData,
                'alerts' => $alerts,
                'generated_at' => date('Y-m-d H:i:s')
            ], 'Admin dashboard data retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Admin dashboard error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get user activity statistics
     * Endpoint: GET /api/admin/user-activity
     * Middleware: AuthMiddleware (admin only)
     */
    public function userActivity(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access user activity', 403);
                return;
            }

            // Get timeframe parameter
            $timeframe = $this->request->getQueryParameter('timeframe', 'all');

            // Validate timeframe
            $validTimeframes = ['day', 'week', 'month', 'all'];
            if (!in_array($timeframe, $validTimeframes)) {
                $this->response->error('Invalid timeframe parameter', 400);
                return;
            }

            // Get user activity statistics
            $activityData = AdminDashboard::getUserActivityStats($timeframe);
            $this->response->success($activityData, 'User activity statistics retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('User activity statistics error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve user activity statistics', 500);
        }
    }

    /**
     * Get system health status
     * Endpoint: GET /api/admin/system-health
     * Middleware: AuthMiddleware (admin only)
     */
    public function systemHealth(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access system health', 403);
                return;
            }

            // Get system health data
            $healthData = AdminDashboard::getSystemHealth();

            $this->response->success($healthData, 'System health data retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('System health retrieval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve system health data', 500);
        }
    }

    /**
     * Get audit log entries
     * Endpoint: GET /api/admin/audit-log
     * Middleware: AuthMiddleware (admin only)
     */
    public function auditLog(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access audit log', 403);
                return;
            }

            // Get query parameters
            $userId = $this->request->getQueryParameter('user_id');
            $action = $this->request->getQueryParameter('action');
            $entityType = $this->request->getQueryParameter('entity_type');
            $page = (int) $this->request->getQueryParameter('page', 1);
            $perPage = min((int) $this->request->getQueryParameter('per_page', 50), 100);

            // Get audit log data
            $auditLogData = AdminDashboard::getAuditLog(
                $userId ? (int) $userId : null,
                $action,
                $entityType,
                $page,
                $perPage
            );

            $this->response->success($auditLogData, 'Audit log entries retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Audit log retrieval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve audit log entries', 500);
        }
    }

    /**
     * Create bulk operation
     * Endpoint: POST /api/admin/bulk-operations
     * Middleware: AuthMiddleware (admin only)
     */
    public function createBulkOperation(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can create bulk operations', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'operation_type' => 'required|in:bulk_delete,bulk_update,bulk_grant_access,bulk_revoke_access,bulk_publish,bulk_archive',
                'target_type' => 'required|in:users,files,folders,quiz_sets,notices',
                'target_ids' => 'required|array',
                'target_ids.*' => 'integer'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $operationType = $data['operation_type'];
            $targetType = $data['target_type'];
            $targetIds = $data['target_ids'];
            $adminId = $currentUser['id'];

            // Create bulk operation
            $operationId = $this->systemMonitoringService->createBulkOperation(
                $adminId,
                $operationType,
                $targetType,
                $targetIds
            );

            if ($operationId) {
                $this->response->success([
                    'operation_id' => $operationId,
                    'operation_type' => $operationType,
                    'target_type' => $targetType,
                    'total_items' => count($targetIds)
                ], 'Bulk operation created successfully');
            } else {
                $this->response->error('Failed to create bulk operation', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Bulk operation creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to create bulk operation', 500);
        }
    }

    /**
     * Get bulk operation status
     * Endpoint: GET /api/admin/bulk-operations/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function bulkOperationStatus(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access bulk operation status', 403);
                return;
            }

            // Get bulk operation status
            $operation = AdminDashboard::getBulkOperationStatus($id);

            if (!$operation) {
                $this->response->error('Bulk operation not found', 404);
                return;
            }
            $this->response->success($operation, 'Bulk operation status retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Bulk operation status retrieval error', [
                'error' => $e->getMessage(),
                'operation_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve bulk operation status', 500);
        }
    }

    /**
     * Cancel bulk operation
     * Endpoint: DELETE /api/admin/bulk-operations/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function cancelBulkOperation(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can cancel bulk operations', 403);
                return;
            }

            // Check if operation exists
            $operation = AdminDashboard::getBulkOperationStatus($id);
            if (!$operation) {
                $this->response->error('Bulk operation not found', 404);
                return;
            }

            // Check if operation can be cancelled
            if (!in_array($operation['status'], ['pending', 'processing'])) {
                $this->response->error('Cannot cancel operation with status: ' . $operation['status'], 400);
                return;
            }

            // Update operation status to cancelled
            $query = "UPDATE bulk_operations SET status = 'cancelled', completed_at = NOW() WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                $this->response->success('Bulk operation cancelled successfully');
            } else {
                $this->response->error('Failed to cancel bulk operation', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Bulk operation cancellation error', [
                'error' => $e->getMessage(),
                'operation_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to cancel bulk operation', 500);
        }
    }

    /**
     * Get system analytics
     * Endpoint: GET /api/admin/analytics
     * Middleware: AuthMiddleware (admin only)
     */
    public function systemAnalytics(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can access analytics', 403);
                return;
            }

            // Get timeframe parameter
            $timeframe = $this->request->getQueryParameter('timeframe', 'all');

            // Validate timeframe
            $validTimeframes = ['day', 'week', 'month', 'all'];
            if (!in_array($timeframe, $validTimeframes)) {
                $this->response->error('Invalid timeframe parameter', 400);
                return;
            }

            // Get system analytics
            $analyticsData = $this->systemMonitoringService->getSystemAnalytics($timeframe);

            $this->response->success($analyticsData, 'System analytics retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('System analytics retrieval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve system analytics', 500);
        }
    }

    /**
     * Run comprehensive system health check
     * Endpoint: POST /api/admin/health-check
     * Middleware: AuthMiddleware (admin only)
     */
    public function runHealthCheck(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can run health checks', 403);
                return;
            }

            // Run comprehensive health check
            $healthCheckResults = $this->systemMonitoringService->checkSystemHealth();

            // Generate issues and recommendations
            $issues = $this->identifyHealthIssues($healthCheckResults);
            $recommendations = $this->generateHealthRecommendations($healthCheckResults);

            $this->response->success([
                'health_check' => $healthCheckResults,
                'issues' => $issues,
                'recommendations' => $recommendations
            ], 'Health check completed successfully');
        } catch (\Exception $e) {
            Logger::error('Health check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to run health check', 500);
        }
    }

    /**
     * Clear audit log entries
     * Endpoint: DELETE /api/admin/audit-log
     * Middleware: AuthMiddleware (admin only)
     */
    public function clearAuditLog(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can clear audit log', 403);
                return;
            }

            // Get older_than parameter
            $olderThan = max((int) $this->request->getQueryParameter('older_than', 90), 30);

            // Validate older_than parameter
            if ($olderThan < 30) {
                $this->response->error('Minimum retention period is 30 days', 400);
                return;
            }

            // Delete old audit log entries
            $query = "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $olderThan);
            $result = $stmt->execute();
            $deletedCount = $stmt->affected_rows;
            $stmt->close();

            if ($result !== false) {
                $this->response->success([
                    'deleted_count' => $deletedCount,
                    'older_than_days' => $olderThan
                ], 'Audit log cleaned successfully');
            } else {
                $this->response->error('Failed to clear audit log', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Audit log cleanup error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to clear audit log', 500);
        }
    }

    /**
     * Generate system alerts from dashboard data
     * @param array $dashboardData Dashboard overview data
     * @return array System alerts
     */
    private function generateSystemAlerts(array $dashboardData): array
    {
        $alerts = [];

        // Health alerts
        if (isset($dashboardData['health']['overall_status']) && $dashboardData['health']['overall_status'] !== 'healthy') {
            $alerts[] = [
                'type' => 'health',
                'severity' => $dashboardData['health']['overall_status'],
                'message' => 'System health is ' . $dashboardData['health']['overall_status'] . '. Review health metrics for details.'
            ];
        }

        // User activity alerts
        if (isset($dashboardData['users']['active_24h']) && $dashboardData['users']['active_24h'] === 0) {
            $alerts[] = [
                'type' => 'user_activity',
                'severity' => 'warning',
                'message' => 'No user activity in the last 24 hours. Check if there are any system issues.'
            ];
        }

        // Disk usage alerts
        if (isset($dashboardData['health']['disk']['status']) && $dashboardData['health']['disk']['status'] !== 'healthy') {
            $alerts[] = [
                'type' => 'disk',
                'severity' => $dashboardData['health']['disk']['status'],
                'message' => 'Disk usage is ' . $dashboardData['health']['disk']['status'] . '. Consider cleaning up old files or upgrading storage.'
            ];
        }

        // Quiz completion alerts
        if (isset($dashboardData['quizzes']['completion_rate']) && $dashboardData['quizzes']['completion_rate'] < 50) {
            $alerts[] = [
                'type' => 'quiz',
                'severity' => 'info',
                'message' => 'Quiz completion rate is low (' . $dashboardData['quizzes']['completion_rate'] . '%). Review quiz content and difficulty.'
            ];
        }

        return $alerts;
    }

    /**
     * Identify health issues from health check results
     * @param array $healthCheckResults Health check results
     * @return array Health issues
     */
    private function identifyHealthIssues(array $healthCheckResults): array
    {
        $issues = [];

        foreach ($healthCheckResults['health_checks'] as $checkType => $check) {
            if ($check['status'] !== 'healthy') {
                $issues[] = [
                    'type' => $checkType,
                    'severity' => $check['status'],
                    'metric_value' => $check['metric_value'],
                    'metric_unit' => $check['metric_unit'],
                    'details' => json_decode($check['details'] ?? '{}', true)
                ];
            }
        }

        return $issues;
    }

    /**
     * Generate health recommendations from health check results
     * @param array $healthCheckResults Health check results
     * @return array Health recommendations
     */
    private function generateHealthRecommendations(array $healthCheckResults): array
    {
        $recommendations = [];

        foreach ($healthCheckResults['health_checks'] as $checkType => $check) {
            if ($check['status'] !== 'healthy') {
                switch ($checkType) {
                    case 'database':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Optimize database queries and consider upgrading database resources',
                            'target' => 'database_performance'
                        ];
                        break;
                    case 'disk':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Clean up old files and consider upgrading storage capacity',
                            'target' => 'disk_space'
                        ];
                        break;
                    case 'memory':
                        $recommendations[] = [
                            'priority' => 'medium',
                            'action' => 'Optimize memory usage and consider increasing PHP memory limit',
                            'target' => 'memory_usage'
                        ];
                        break;
                    case 'cpu':
                        $recommendations[] = [
                            'priority' => 'medium',
                            'action' => 'Monitor CPU usage and consider optimizing resource-intensive operations',
                            'target' => 'cpu_usage'
                        ];
                        break;
                    case 'api_performance':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Implement caching and optimize API endpoints for better performance',
                            'target' => 'api_response_time'
                        ];
                        break;
                    case 'error_rate':
                        $recommendations[] = [
                            'priority' => 'critical',
                            'action' => 'Review recent errors and fix critical issues immediately',
                            'target' => 'error_rate'
                        ];
                        break;
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get all admin users
     * Legacy method kept for compatibility
     */
    public function index(): void
    {
        try {
            // Get all admin users
            $admins = User::getAllAdmins();

            $this->response->success([
                'admins' => $admins,
                'total' => count($admins)
            ], 'Admin users retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Admin listing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve admin users', 500);
        }
    }

    public function grant(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can grant admin privileges', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'user_id' => 'required|integer'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $userId = (int) $data['user_id'];

            // Check if user exists
            $user = User::findById($userId);
            if (!$user) {
                $this->response->error('User not found', 404);
                return;
            }

            // Check if email matches (if provided) for security
            if (isset($data['email'])) {
                if ($user->getEmail() !== $data['email']) {
                    $this->response->error('Email does not match user', 400);
                    return;
                }
            }

            // Check if user is already admin
            if (User::isAdminById($userId)) {
                $this->response->error('User is already an admin', 400);
                return;
            }

            // Grant admin privileges
            $result = User::grantAdmin($userId, $currentUser['email']);

            if ($result) {
                // Get updated admin data
                $adminData = User::findById($userId);
                $userData = $adminData->toArray();
                unset($userData['password']);
                $this->response->success($userData, 'Admin privileges granted successfully');
            } else {
                $this->response->error('Failed to grant admin privileges', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Admin grant error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to grant admin privileges', 500);
        }
    }

    public function revoke(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can revoke admin privileges', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'user_id' => 'required|integer'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $userId = (int) $data['user_id'];

            // Prevent self-demotion
            if ($currentUser['id'] === $userId) {
                $this->response->error('You cannot revoke your own admin privileges', 400);
                return;
            }

            // Check if user exists
            $user = User::findById($userId);
            if (!$user) {
                $this->response->error('User not found', 404);
                return;
            }

            // Check if email matches (if provided) for security
            if (isset($data['email'])) {
                if ($user->getEmail() !== $data['email']) {
                    $this->response->error('Email does not match user', 400);
                    return;
                }
            }

            // Check if user is actually an admin
            if (!User::isAdminById($userId)) {
                $this->response->error('User is not an admin', 400);
                return;
            }

            // Revoke admin privileges
            $result = User::revokeAdmin($userId);

            if ($result) {
                // Get updated user data
                $userData = User::findById($userId);
                $userArray = $userData->toArray();
                unset($userArray['password']);

                $this->response->success($userArray, 'Admin privileges revoked successfully');
            } else {
                $this->response->error('Failed to revoke admin privileges', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Admin revoke error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to revoke admin privileges', 500);
        }
    }

    public function list(): void
    {
        try {
            // Get all admin users with different response format
            $admins = User::getAllAdmins();

            $this->response->success([
                'total' => count($admins),
                'admins' => $admins
            ], 'All admin users listed');
        } catch (\Exception $e) {
            Logger::error('Admin list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to list admin users', 500);
        }
    }

    public function approveReset(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can approve password resets', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'reset_id' => 'required|integer',
                'action' => 'required|in:approve,reject'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $resetId = (int) $data['reset_id'];
            $action = $data['action'];

            // Check if reset request exists
            $stmt = \EMA\Config\Database::prepare(
                "SELECT * FROM password_reset_requests WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $resetId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $this->response->error('Password reset request not found', 404);
                return;
            }

            $resetRequest = $result->fetch_assoc();

            // Check if request is still pending
            if ($resetRequest['request_status'] !== 'pending') {
                $this->response->error('Password reset request is not pending', 400);
                return;
            }

            // Update request status
            $updateStmt = \EMA\Config\Database::prepare(
                "UPDATE password_reset_requests SET request_status = ? WHERE id = ?"
            );
            $updateStmt->bind_param('si', $action, $resetId);
            $result = $updateStmt->execute();

            if ($result) {
                $message = $action === 'approve'
                    ? 'Password reset approved successfully'
                    : 'Password reset rejected successfully';

                $this->response->success($message);
            } else {
                $this->response->error('Failed to update password reset status', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Password reset approval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to process password reset approval', 500);
        }
    }
}