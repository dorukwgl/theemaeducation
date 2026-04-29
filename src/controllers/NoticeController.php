<?php

namespace EMA\Controllers;

use EMA\Models\Notice;
use EMA\Services\NoticeService;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;

class NoticeController
{
    private Request $request;
    private Response $response;
    private $noticeService;

    public function __construct()
    {
        // Request will be set by Router via setRequest()
        $this->response = new Response();
        $this->noticeService = new NoticeService();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get all notices with filtering
     * Endpoint: GET /api/notices
     * Middleware: None (public access)
     */
    public function index(): void
    {
        try {
                        $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId(); // Can be null for unauthenticated users
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));

            // Build filters
            $filters = [];

            if ($this->request->getInput('notice_type')) {
                $filters['notice_type'] = $this->request->getInput('notice_type');
            }

            if ($this->request->getInput('priority')) {
                $filters['priority'] = $this->request->getInput('priority');
            }

            if ($this->request->getInput('active_only') === 'true') {
                $filters['active_only'] = true;
            }

            if ($this->request->getInput('target_audience')) {
                $filters['target_audience'] = $this->request->getInput('target_audience');
            }

            if ($userId) {
                $filters['exclude_dismissed'] = true;
            }

            // Validate pagination
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Get notices
            $result = Notice::getAllNotices($userId, $filters, $page, $perPage);

            Response::json([
                'success' => true,
                'message' => 'Notices retrieved successfully',
                'data' => [
                    'notices' => $result['notices'],
                    'pagination' => $result['pagination'],
                    'filters_applied' => $filters
                ]
            ]);

            Logger::info('Notices retrieved', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'total_count' => $result['pagination']['total_count']
            ]);
        } catch (\Exception $e) {
            Logger::error('Error retrieving notices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve notices',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Get notice details
     * Endpoint: GET /api/notices/{id}
     * Middleware: None (public access)
     */
    public function show(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId(); // Can be null for unauthenticated users

            $notice = Notice::findById($id);

            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

            // Get attachments
            $attachments = Notice::getNoticeAttachments($id);

            // Get statistics
            $stats = Notice::getNoticeStats($id);

            // Track view if user is authenticated
            if ($userId) {
                Notice::trackView($id, $userId);
            }

            $responseData = [
                'notice' => $notice,
                'attachments' => $attachments,
                'statistics' => $stats
            ];

            Response::json([
                'success' => true,
                'message' => 'Notice retrieved successfully',
                'data' => $responseData
            ]);

            Logger::info('Notice viewed', [
                'notice_id' => $id,
                'user_id' => $userId,
                'has_attachments' => count($attachments) > 0
            ]);
        } catch (\Exception $e) {
            Logger::error('Error getting notice details', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve notice',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Create notice
     * Endpoint: POST /api/notices
     * Middleware: AuthMiddleware (admin only)
     */
    public function store(): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate notice data
            $validation = $this->noticeService->validateNoticeData($data);

            if (!$validation['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
                return;
            }

            $sanitizedData = $validation['data'];
            $sanitizedData['created_by'] = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            // Handle file attachment upload
            if (isset($data['attachment']) && is_uploaded_file($data['attachment'])) {
                $fileUpload = $this->noticeService->handleNoticeFileUpload($data['attachment']);
                if (!$fileUpload['success']) {
                    Response::json([
                        'success' => false,
                        'message' => 'File upload failed',
                        'errors' => $fileUpload['errors'] ?? ['Failed to upload attachment']
                    ], 400);
                    return;
                }

                $sanitizedData['attachment_data'] = $fileUpload;
            }

            // Create notice
            $noticeId = Notice::create($sanitizedData);

            if ($noticeId) {
                // Create file attachment if uploaded
                if (isset($sanitizedData['attachment_data'])) {
                    $attachmentId = Notice::createAttachment($noticeId, $sanitizedData['attachment_data']);

                    if (!$attachmentId) {
                        Logger::error('Notice created but attachment creation failed', [
                            'notice_id' => $noticeId
                        ]);
                    }
                }

                Response::json([
                    'success' => true,
                    'message' => 'Notice created successfully',
                    'data' => [
                        'notice' => Notice::findById($noticeId)
                    ]
                ], 201);

                Logger::logSecurityEvent('Notice created', [
                    'notice_id' => $noticeId,
                    'created_by' => $sanitizedData['created_by']
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to create notice'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error creating notice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to create notice',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Update notice
     * Endpoint: PUT /api/notices/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function update(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if notice exists
            $notice = Notice::findById($id);
            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate notice data
            $validation = $this->noticeService->validateNoticeData($data);

            if (!$validation['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
                return;
            }

            $sanitizedData = $validation['data'];

            // Update notice
            $result = Notice::update($id, $sanitizedData);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Notice updated successfully',
                    'data' => [
                        'notice' => Notice::findById($id)
                    ]
                ]);

                Logger::logSecurityEvent('Notice updated', [
                    'notice_id' => $id,
                    'updated_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to update notice'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to update notice',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Delete notice
     * Endpoint: DELETE /api/notices/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function delete(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if notice exists
            $notice = Notice::findById($id);
            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Delete notice
            $result = Notice::delete($id);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Notice deleted successfully'
                ]);

                Logger::logSecurityEvent('Notice deleted', [
                    'notice_id' => $id,
                    'deleted_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to delete notice'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to delete notice',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Upload notice attachment
     * Endpoint: POST /api/notices/{id}/upload-attachment
     * Middleware: AuthMiddleware (admin only)
     */
    public function uploadAttachment(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if notice exists
            $notice = Notice::findById($id);
            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Check if file was uploaded
            if (!isset($data['attachment']) || !is_uploaded_file($data['attachment'])) {
                Response::json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
                return;
            }

            // Handle file upload
            $fileUpload = $this->noticeService->handleNoticeFileUpload($data['attachment']);

            if (!$fileUpload['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'File upload failed',
                    'errors' => $fileUpload['errors'] ?? ['Failed to upload attachment']
                ], 400);
                return;
            }

            // Create attachment record
            $attachmentId = Notice::createAttachment($id, $fileUpload);

            if ($attachmentId) {
                Response::json([
                    'success' => true,
                    'message' => 'Attachment uploaded successfully',
                    'data' => [
                        'attachment' => [
                            'id' => $attachmentId,
                            'notice_id' => $id,
                            'file_name' => $fileUpload['file_name'],
                            'file_path' => $fileUpload['file_path'],
                            'file_size' => $fileUpload['file_size'],
                            'mime_type' => $fileUpload['mime_type'],
                            'file_type' => $fileUpload['file_type']
                        ]
                    ]
                ]);

                Logger::logSecurityEvent('Notice attachment uploaded', [
                    'notice_id' => $id,
                    'attachment_id' => $attachmentId,
                    'file_name' => $fileUpload['file_name']
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to create attachment record'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error uploading notice attachment', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to upload attachment',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Delete notice attachment
     * Endpoint: DELETE /api/notices/{id}/attachment
     * Middleware: AuthMiddleware (admin only)
     */
    public function deleteAttachment(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate attachment_id parameter
            $attachmentId = (int) $data['attachment_id'] ?? 0;

            if (!$attachmentId) {
                Response::json([
                    'success' => false,
                    'message' => 'Attachment ID is required'
                ], 400);
                return;
            }

            // Delete attachment
            $result = Notice::deleteAttachment($attachmentId);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Attachment deleted successfully'
                ]);

                Logger::logSecurityEvent('Notice attachment deleted', [
                    'attachment_id' => $attachmentId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to delete attachment'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting notice attachment', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Track notice view
     * Endpoint: POST /api/notices/{id}/view
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function trackView(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Authentication required to track views'
                ], 401);
                return;
            }

            // Track view
            $result = Notice::trackView($id, $userId);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'View tracked successfully'
                ]);

                Logger::info('Notice view tracked', [
                    'notice_id' => $id,
                    'user_id' => $userId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to track view'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error tracking notice view', [
                'notice_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to track view',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Dismiss notice for user
     * Endpoint: POST /api/notices/{id}/dismiss
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function dismiss(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
                return;
            }

            // Check if notice exists
            $notice = Notice::findById($id);
            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Dismiss notice
            $result = Notice::dismissNotice($id, $userId);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Notice dismissed successfully'
                ]);

                Logger::info('Notice dismissed', [
                    'notice_id' => $id,
                    'user_id' => $userId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to dismiss notice'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error dismissing notice', [
                'notice_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to dismiss notice',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Get dismissed notices
     * Endpoint: GET /api/notices/dismissed
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function dismissed(): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
                return;
            }

            $dismissedNotices = Notice::getDismissedNotices($userId);

            Response::json([
                'success' => true,
                'message' => 'Dismissed notices retrieved successfully',
                'data' => [
                    'dismissed_notices' => $dismissedNotices,
                    'count' => count($dismissedNotices)
                ]
            ]);

            Logger::info('Dismissed notices retrieved', [
                'user_id' => $userId,
                'count' => count($dismissedNotices)
            ]);
        } catch (\Exception $e) {
            Logger::error('Error retrieving dismissed notices', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve dismissed notices',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Get notice statistics
     * Endpoint: GET /api/notices/{id}/statistics
     * Middleware: AuthMiddleware (admin or notice owner)
     */
    public function statistics(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            // Check if notice exists
            $notice = Notice::findById($id);
            if (!$notice) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice not found'
                ], 404);
                return;
            }

            // Check permissions (admin or notice owner)
            if (!\EMA\Middleware\AuthMiddleware::isAdmin() && $notice['created_by'] != $userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to notice statistics'
                ], 403);
                return;
            }

            // Get analytics
            $analytics = $this->noticeService->getNoticeAnalytics($id);

            if ($analytics['success']) {
                Response::json([
                    'success' => true,
                    'message' => 'Notice statistics retrieved successfully',
                    'data' => $analytics['data']
                ]);

                Logger::info('Notice statistics viewed', [
                    'notice_id' => $id,
                    'user_id' => $userId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => $analytics['message']
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error getting notice statistics', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve notice statistics',
                'errors' => ['Internal server error']
            ], 500);
        }
    }

    /**
     * Bulk update notice status
     * Endpoint: POST /api/notices/bulk-update-status
     * Middleware: AuthMiddleware (admin only)
     */
    public function bulkUpdateStatus(): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate required fields
            if (!isset($data['notice_ids']) || !is_array($data['notice_ids'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Notice IDs array is required'
                ], 400);
                return;
            }

            if (!isset($data['status'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Status is required'
                ], 400);
                return;
            }

            $noticeIds = $data['notice_ids'];
            $status = $data['status'];

            // Perform bulk update
            $result = $this->noticeService->bulkUpdateStatus($noticeIds, $status);

            if ($result['success']) {
                Response::json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ]);

                Logger::logSecurityEvent('Bulk notice status update', [
                    'status' => $status,
                    'notice_count' => count($noticeIds)
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? ['Bulk status update failed']
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error in bulk notice status update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Bulk status update failed',
                'errors' => ['Internal server error']
            ], 500);
        }
    }
}