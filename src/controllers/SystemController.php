<?php

namespace EMA\Controllers;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class SystemController
{
    private Request $request;
    private Response $response;

    public function __construct()
    {
        // Request will be set by Router via setRequest()
        $this->response = new Response();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get all system notices
     * Endpoint: GET /api/notices
     * Middleware: None (public access)
     */
    public function notices(): void
    {
        try {
            $query = "SELECT * FROM system_notices WHERE is_active = 1 ORDER BY created_at DESC";
            $stmt = \EMA\Config\Database::prepare($query);
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
                    'expires_at' => $row['expires_at'],
                    'created_at' => $row['created_at'],
                    'is_active' => (bool) $row['is_active']
                ];
            }

            $stmt->close();

            $this->response->success('System notices retrieved successfully', [
                'notices' => $notices,
                'total' => count($notices)
            ]);

            Logger::info('System notices accessed', [
                'ip' => Security::getRealIp()
            ]);
        } catch (\Exception $e) {
            Logger::error('System notices retrieval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve system notices', 500);
        }
    }

    /**
     * Create a new system notice
     * Endpoint: POST /api/notices
     * Middleware: AuthMiddleware (admin only)
     */
    public function storeNotice(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::logSecurityEvent('Unauthorized notice creation attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can create system notices', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'title' => 'required|min:3|max:200',
                'content' => 'required|min:10',
                'notice_type' => 'required|in:info,warning,error,success',
                'priority' => 'required|in:low,medium,high',
                'expires_at' => 'nullable|date'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $title = $data['title'];
            $content = $data['content'];
            $noticeType = $data['notice_type'];
            $priority = $data['priority'];
            $expiresAt = $data['expires_at'] ?? null;
            $createdBy = $currentUser['id'];

            // Insert notice
            $query = "INSERT INTO system_notices (title, content, notice_type, priority, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('sssssi', $title, $content, $noticeType, $priority, $expiresAt, $createdBy);

            if ($stmt->execute()) {
                $noticeId = $stmt->insert_id;
                $stmt->close();

                Logger::logSecurityEvent('System notice created', [
                    'notice_id' => $noticeId,
                    'title' => $title,
                    'created_by' => $createdBy,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('System notice created successfully', [
                    'notice_id' => $noticeId,
                    'title' => $title,
                    'notice_type' => $noticeType,
                    'priority' => $priority
                ]);
            } else {
                $stmt->close();
                $this->response->error('Failed to create system notice', 500);
            }
        } catch (\Exception $e) {
            Logger::error('System notice creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to create system notice', 500);
        }
    }

    /**
     * Delete a system notice
     * Endpoint: DELETE /api/notices/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function deleteNotice(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::logSecurityEvent('Unauthorized notice deletion attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'notice_id' => $id,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can delete system notices', 403);
                return;
            }

            // Check if notice exists
            $checkStmt = \EMA\Config\Database::prepare("SELECT id FROM system_notices WHERE id = ? LIMIT 1");
            $checkStmt->bind_param('i', $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if (!$checkResult->num_rows) {
                $this->response->error('System notice not found', 404);
                return;
            }

            $checkStmt->close();

            // Delete notice
            $deleteStmt = \EMA\Config\Database::prepare("DELETE FROM system_notices WHERE id = ?");
            $deleteStmt->bind_param('i', $id);
            $result = $deleteStmt->execute();
            $deleteStmt->close();

            if ($result) {
                Logger::logSecurityEvent('System notice deleted', [
                    'notice_id' => $id,
                    'deleted_by' => $currentUser['id'],
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('System notice deleted successfully');
            } else {
                $this->response->error('Failed to delete system notice', 500);
            }
        } catch (\Exception $e) {
            Logger::error('System notice deletion error', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to delete system notice', 500);
        }
    }

    /**
     * Track file download for analytics
     * Endpoint: POST /api/analytics/track-download
     * Middleware: None (public access for anonymous tracking)
     */
    public function trackDownload(): void
    {
        try {
            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'file_id' => 'required|integer',
                'user_id' => 'nullable|integer'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $fileId = (int) $data['file_id'];
            $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
            $ipAddress = Security::getRealIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Check if file exists
            $file = \EMA\Models\File::findById($fileId);
            if (!$file) {
                $this->response->error('File not found', 404);
                return;
            }

            // Insert analytics record
            $query = "INSERT INTO download_analytics (file_id, user_id, ip_address, user_agent, downloaded_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('iiss', $fileId, $userId, $ipAddress, $userAgent);

            if ($stmt->execute()) {
                $stmt->close();

                Logger::info('File download tracked', [
                    'file_id' => $fileId,
                    'user_id' => $userId,
                    'ip' => $ipAddress
                ]);

                $this->response->success('Download tracked successfully');
            } else {
                $stmt->close();
                $this->response->error('Failed to track download', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Download tracking error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't expose tracking errors to client
            $this->response->success('Tracking recorded');
        }
    }

    /**
     * Manage free content access
     * Endpoint: POST /api/content/free-access
     * Middleware: AuthMiddleware (admin only)
     */
    public function freeAccess(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::logSecurityEvent('Unauthorized free access management attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can manage free access', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set',
                'is_free' => 'required|boolean'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $isFree = (bool) $data['is_free'];
            $managedBy = $currentUser['id'];

            // Check if item exists
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $checkStmt = \EMA\Config\Database::prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
            $checkStmt->bind_param('i', $itemId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if (!$checkResult->num_rows) {
                $this->response->error('Item not found', 404);
                return;
            }

            $checkStmt->close();

            // Update or insert free access setting
            $query = "INSERT INTO free_content_access (item_id, item_type, is_free, managed_by, managed_at)
                      VALUES (?, ?, ?, ?, NOW())
                      ON DUPLICATE KEY UPDATE is_free = ?, managed_by = ?, managed_at = NOW()";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('isibii', $itemId, $itemType, $isFree, $managedBy, $isFree, $managedBy);

            if ($stmt->execute()) {
                $stmt->close();

                Logger::logSecurityEvent('Free content access managed', [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'is_free' => $isFree,
                    'managed_by' => $managedBy,
                    'ip' => Security::getRealIp()
                ]);

                $action = $isFree ? 'granted' : 'revoked';
                $this->response->success("Free access {$action} successfully", [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'is_free' => $isFree
                ]);
            } else {
                $stmt->close();
                $this->response->error('Failed to manage free access', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Free access management error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to manage free access', 500);
        }
    }
}
