<?php

namespace EMA\Models;

use EMA\Utils\Logger;

class Notice
{
    /**
     * Find notice by ID
     * @param int $id Notice ID
     * @return array|null Notice details or null if not found
     */
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT n.*,
                       u.full_name as created_by_name,
                       u.email as created_by_email,
                       COUNT(DISTINCT nv.id) as view_count,
                       COUNT(DISTINCT nd.id) as dismissal_count,
                       GROUP_CONCAT(
                           CONCAT('<attachment>', na.file_name, '</attachment>')
                           SEPARATOR ''
                       ) as attachments_list
                FROM system_notices n
                LEFT JOIN users u ON n.created_by = u.id
                LEFT JOIN notice_views nv ON n.id = nv.notice_id
                LEFT JOIN notice_dismissals nd ON n.id = nd.notice_id
                LEFT JOIN notice_attachments na ON n.id = na.notice_id
                WHERE n.id = ? LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $notice = $result->fetch_assoc();
            $stmt->close();

            $noticeData = [
                'id' => (int) $notice['id'],
                'title' => $notice['title'],
                'content' => $notice['content'],
                'notice_type' => $notice['notice_type'],
                'priority' => $notice['priority'],
                'target_audience' => $notice['target_audience'],
                'expires_at' => $notice['expires_at'],
                'is_active' => (bool) $notice['is_active'],
                'created_by' => (int) $notice['created_by'],
                'created_by_name' => $notice['created_by_name'],
                'created_by_email' => $notice['created_by_email'],
                'created_at' => $notice['created_at'],
                'updated_at' => $notice['updated_at'],
                'view_count' => (int) $notice['view_count'],
                'dismissal_count' => (int) $notice['dismissal_count'],
                'attachments_list' => $notice['attachments_list']
            ];

            Logger::info('Notice found by ID', ['notice_id' => $id]);

            return $noticeData;
        } catch (\Exception $e) {
            Logger::error('Error finding notice by ID', [
                'notice_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all notices with filtering
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

            // Add target audience filtering
            if (isset($filters['target_audience']) && !empty($filters['target_audience'])) {
                $conditions[] = 'n.target_audience IN (\'all\', ?)';
                $params[] = $filters['target_audience'];
                $types .= 's';
            }

            // Add expiration filtering
            $conditions[] = '(n.expires_at IS NULL OR n.expires_at > NOW())';

            // Combine conditions
            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // Validate pagination
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $offset = ($page -1) * $perPage;

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

            $params[] = $perPage;
            $params[] = $offset;

            $types .= 'iiiii';

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
            $countQuery = "
                SELECT COUNT(DISTINCT n.id)
                FROM system_notices n
                LEFT JOIN notice_dismissals nd ON n.id = nd.notice_id AND nd.user_id = ?
                {$whereClause}
            ";

            $countTypes = str_replace('LIMIT ? OFFSET ?', '', $types);
            $countTypes = str_replace('iiiii', 'iiii', $countTypes);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countParams = array_slice($params, 0, -2); // Remove perPage and offset
            $countStmt->bind_param($countTypes, ...$countParams);
            $countStmt->execute();
            $totalResult = $countStmt->get_result()->fetch_row();
            $totalCount = $totalResult[0];
            $countStmt->close();

            Logger::info('Notices retrieved successfully', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'total_count' => $totalCount
            ]);

            return [
                'notices' => $notices,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $totalCount,
                    'total_pages' => ceil($totalCount / $perPage)
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error retrieving notices', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [
                'notices' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_count' => 0,
                    'total_pages' => 0
                ]
            ];
        }
    }

    /**
     * Create notice
     * @param array $data Notice data
     * @return int|false New notice ID or false on failure
     */
    public static function create(array $data): int|false
    {
        try {
            $db = \EMA\Config\Database::getInstance();

            // Validate required fields
            if (!isset($data['title']) || !isset($data['content']) || !isset($data['created_by'])) {
                Logger::warning('Notice creation failed: Missing required fields', ['data' => $data]);
                return false;
            }

            $title = trim($data['title']);
            $content = trim($data['content']);
            $createdBy = (int) $data['created_by'];
            $noticeType = $data['notice_type'] ?? 'info';
            $priority = $data['priority'] ?? 'medium';
            $targetAudience = $data['target_audience'] ?? 'all';
            $expiresAt = $data['expires_at'] ?? null;

            // Validate notice_type
            $validTypes = ['info', 'warning', 'error', 'success', 'announcement'];
            if (!in_array($noticeType, $validTypes)) {
                Logger::warning('Notice creation failed: Invalid notice type', ['notice_type' => $noticeType]);
                return false;
            }

            // Validate priority
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (!in_array($priority, $validPriorities)) {
                Logger::warning('Notice creation failed: Invalid priority', ['priority' => $priority]);
                return false;
            }

            // Validate target_audience
            $validAudiences = ['all', 'logged_in', 'admin', 'teachers', 'students'];
            if (!in_array($targetAudience, $validAudiences)) {
                Logger::warning('Notice creation failed: Invalid target audience', ['target_audience' => $targetAudience]);
                return false;
            }

            // Validate expiration date
            if ($expiresAt) {
                $expiresTimestamp = strtotime($expiresAt);
                if ($expiresTimestamp === false || $expiresTimestamp < time()) {
                    Logger::warning('Notice creation failed: Invalid expiration date', ['expires_at' => $expiresAt]);
                    return false;
                }
            }

            // Insert notice
            $query = "
                INSERT INTO system_notices (
                    title, content, notice_type, priority,
                    target_audience, expires_at, is_active, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ";

            $stmt = $db->prepare($query);
            $stmt->bind_param('sssssis',
                $title, $content, $noticeType, $priority,
                $targetAudience, $expiresAt, $createdBy
            );

            if ($stmt->execute()) {
                $noticeId = $stmt->insert_id;
                $stmt->close();

                Logger::info('Notice created successfully', [
                    'notice_id' => $noticeId,
                    'created_by' => $createdBy
                ]);

                return $noticeId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating notice', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update notice
     * @param int $id Notice ID
     * @param array $data Update data
     * @return bool true if successful, false otherwise
     */
    public static function update(int $id, array $data): bool
    {
        try {
            // Check if notice exists
            $notice = self::findById($id);
            if (!$notice) {
                Logger::warning('Notice update failed: Notice not found', ['notice_id' => $id]);
                return false;
            }

            $updates = [];
            $types = '';
            $params = [];

            // Handle title update
            if (isset($data['title']) && !empty(trim($data['title']))) {
                $updates[] = 'title = ?';
                $types .= 's';
                $params[] = trim($data['title']);
            }

            // Handle content update
            if (isset($data['content']) && !empty(trim($data['content']))) {
                $updates[] = 'content = ?';
                $types .= 's';
                $params[] = trim($data['content']);
            }

            // Handle notice_type update
            if (isset($data['notice_type'])) {
                $validTypes = ['info', 'warning', 'error', 'success', 'announcement'];
                if (in_array($data['notice_type'], $validTypes)) {
                    $updates[] = 'notice_type = ?';
                    $types .= 's';
                    $params[] = $data['notice_type'];
                }
            }

            // Handle priority update
            if (isset($data['priority'])) {
                $validPriorities = ['low', 'medium', 'high', 'critical'];
                if (in_array($data['priority'], $validPriorities)) {
                    $updates[] = 'priority = ?';
                    $types .= 's';
                    $params[] = $data['priority'];
                }
            }

            // Handle target_audience update
            if (isset($data['target_audience'])) {
                $validAudiences = ['all', 'logged_in', 'admin', 'teachers', 'students'];
                if (in_array($data['target_audience'], $validAudiences)) {
                    $updates[] = 'target_audience = ?';
                    $types .= 's';
                    $params[] = $data['target_audience'];
                }
            }

            // Handle expires_at update
            if (isset($data['expires_at'])) {
                $expiresTimestamp = strtotime($data['expires_at']);
                if ($expiresTimestamp !== false && $expiresTimestamp >= time()) {
                    $updates[] = 'expires_at = ?';
                    $types .= 's';
                    $params[] = $data['expires_at'];
                }
            }

            // Handle is_active toggle
            if (isset($data['is_active'])) {
                $updates[] = 'is_active = ?';
                $types .= 'i';
                $params[] = $data['is_active'] ? 1 : 0;
            }

            if (empty($updates)) {
                Logger::warning('Notice update failed: No valid fields to update');
                return false;
            }

            // Build and execute query
            $query = "UPDATE system_notices SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $affectedRows = $stmt->affected_rows;
                $stmt->close();

                Logger::info('Notice updated successfully', [
                    'notice_id' => $id,
                    'affected_rows' => $affectedRows
                ]);

                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating notice', [
                'notice_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete notice with cascade
     * @param int $id Notice ID
     * @return bool true if successful, false otherwise
     */
    public static function delete(int $id): bool
    {
        try {
            // Check if notice exists
            $notice = self::findById($id);
            if (!$notice) {
                Logger::warning('Notice deletion failed: Notice not found', ['notice_id' => $id]);
                return false;
            }

            // Get attachments for cleanup
            $attachments = self::getNoticeAttachments($id);

            // Delete notice (cascade will handle attachments, views, dismissals)
            $query = "DELETE FROM system_notices WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                // Delete physical attachment files
                foreach ($attachments as $attachment) {
                    $filePath = ROOT_PATH . '/' . $attachment['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                        Logger::info('Notice attachment deleted', [
                            'attachment_id' => $attachment['id'],
                            'file_path' => $attachment['file_path']
                        ]);
                    }
                }

                Logger::info('Notice deleted successfully', [
                    'notice_id' => $id,
                    'attachments_count' => count($attachments)
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error deleting notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get notice attachments
     * @param int $noticeId Notice ID
     * @return array Attachments
     */
    public static function getNoticeAttachments(int $noticeId): array
    {
        try {
            $query = "
                SELECT id, file_name, file_path, file_size, mime_type, file_type, uploaded_at
                FROM notice_attachments
                WHERE notice_id = ?
                ORDER BY uploaded_at ASC
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $noticeId);
            $stmt->execute();
            $result = $stmt->get_result();

            $attachments = [];
            while ($row = $result->fetch_assoc()) {
                $attachments[] = [
                    'id' => (int) $row['id'],
                    'notice_id' => $noticeId,
                    'file_name' => $row['file_name'],
                    'file_path' => $row['file_path'],
                    'file_size' => (int) $row['file_size'],
                    'mime_type' => $row['mime_type'],
                    'file_type' => $row['file_type'],
                    'uploaded_at' => $row['uploaded_at']
                ];
            }

            $stmt->close();
            return $attachments;
        } catch (\Exception $e) {
            Logger::error('Error getting notice attachments', [
                'notice_id' => $noticeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create notice attachment
     * @param int $noticeId Notice ID
     * @param array $attachmentData Attachment data
     * @return int|false Attachment ID or false on failure
     */
    public static function createAttachment(int $noticeId, array $attachmentData): int|false
    {
        try {
            $db = \EMA\Config\Database::getInstance();

            // Validate required fields
            if (!isset($attachmentData['file_name']) || !isset($attachmentData['file_path']) || !isset($attachmentData['mime_type'])) {
                Logger::warning('Notice attachment creation failed: Missing required fields', ['attachment_data' => $attachmentData]);
                return false;
            }

            $fileName = trim($attachmentData['file_name']);
            $filePath = trim($attachmentData['file_path']);
            $mimeType = trim($attachmentData['mime_type']);
            $fileSize = (int) $attachmentData['file_size'];
            $fileType = $attachmentData['file_type'] ?? 'pdf';

            // Validate file_type
            $validTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4', 'webm'];
            if (!in_array($fileType, $validTypes)) {
                Logger::warning('Notice attachment creation failed: Invalid file type', ['file_type' => $fileType]);
                return false;
            }

            // Insert attachment
            $query = "
                INSERT INTO notice_attachments (notice_id, file_name, file_path, file_size, mime_type, file_type, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $db->prepare($query);
            $stmt->bind_param('isssis',
                $noticeId, $fileName, $filePath, $fileSize, $mimeType, $fileType
            );

            if ($stmt->execute()) {
                $attachmentId = $stmt->insert_id;
                $stmt->close();

                Logger::info('Notice attachment created successfully', [
                    'attachment_id' => $attachmentId,
                    'notice_id' => $noticeId
                ]);

                return $attachmentId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating notice attachment', [
                'notice_id' => $noticeId,
                'attachment_data' => $attachmentData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete notice attachment
     * @param int $attachmentId Attachment ID
     * @return bool true if successful, false otherwise
     */
    public static function deleteAttachment(int $attachmentId): bool
    {
        try {
            // Get attachment details
            $query = "
                SELECT id, file_path FROM notice_attachments WHERE id = ? LIMIT 1
            ";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $attachmentId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $stmt->close();
                return false;
            }

            $attachment = $result->fetch_assoc();
            $stmt->close();

            // Delete physical file
            if (file_exists(ROOT_PATH . '/' . $attachment['file_path'])) {
                unlink(ROOT_PATH . '/' . $attachment['file_path']);
                Logger::info('Notice attachment file deleted', [
                    'attachment_id' => $attachmentId,
                    'file_path' => $attachment['file_path']
                ]);
            }

            // Delete attachment record
            $deleteQuery = "DELETE FROM notice_attachments WHERE id = ?";
            $deleteStmt = \EMA\Config\Database::prepare($deleteQuery);
            $deleteStmt->bind_param('i', $attachmentId);
            $result = $deleteStmt->execute();
            $deleteStmt->close();

            if ($result) {
                Logger::info('Notice attachment deleted successfully', [
                    'attachment_id' => $attachmentId
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error deleting notice attachment', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Track notice view
     * @param int $noticeId Notice ID
     * @param int|null $userId User ID (null for anonymous views)
     * @return bool true if successful, false otherwise
     */
    public static function trackView(int $noticeId, ?int $userId = null): bool
    {
        try {
            // Check if notice exists
            $query = "SELECT id FROM system_notices WHERE id = ? LIMIT 1";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $noticeId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $stmt->close();
                return false;
            }

            $stmt->close();

            // Check if view already tracked
            $checkQuery = "
                SELECT id FROM notice_views WHERE notice_id = ? AND user_id = ? LIMIT 1
            ";
            $checkStmt = \EMA\Config\Database::prepare($checkQuery);
            $checkStmt->bind_param('ii', $noticeId, $userId ?? 0);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $checkStmt->close();
                return false; // View already tracked
            }

            $checkStmt->close();

            // Insert view record
            $insertQuery = "
                INSERT INTO notice_views (notice_id, user_id, viewed_at) VALUES (?, ?, NOW())
            ";
            $insertStmt = \EMA\Config\Database::prepare($insertQuery);
            $insertStmt->bind_param('ii', $noticeId, $userId ?? 0);
            $result = $insertStmt->execute();
            $insertStmt->close();

            if ($result) {
                Logger::info('Notice view tracked', [
                    'notice_id' => $noticeId,
                    'user_id' => $userId
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error tracking notice view', [
                'notice_id' => $noticeId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Dismiss notice for user
     * @param int $noticeId Notice ID
     * @param int $userId User ID
     * @return bool true if successful, false otherwise
     */
    public static function dismissNotice(int $noticeId, int $userId): bool
    {
        try {
            // Check if notice exists
            $query = "SELECT id FROM system_notices WHERE id = ? LIMIT 1";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $noticeId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $stmt->close();
                return false;
            }

            $stmt->close();

            // Check if already dismissed
            $checkQuery = "
                SELECT id FROM notice_dismissals WHERE notice_id = ? AND user_id = ? LIMIT 1
            ";
            $checkStmt = \EMA\Config\Database::prepare($checkQuery);
            $checkStmt->bind_param('ii', $noticeId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $checkStmt->close();
                return false; // Already dismissed
            }

            $checkStmt->close();

            // Insert dismissal record
            $insertQuery = "
                INSERT INTO notice_dismissals (notice_id, user_id, dismissed_at) VALUES (?, ?, NOW())
            ";
            $insertStmt = \EMA\Config\Database::prepare($insertQuery);
            $insertStmt->bind_param('ii', $noticeId, $userId);
            $result = $insertStmt->execute();
            $insertStmt->close();

            if ($result) {
                Logger::info('Notice dismissed', [
                    'notice_id' => $noticeId,
                    'user_id' => $userId
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error dismissing notice', [
                'notice_id' => $noticeId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get notice statistics
     * @param int $noticeId Notice ID
     * @return array Statistics
     */
    public static function getNoticeStats(int $noticeId): array
    {
        try {
            // Check if notice exists
            $notice = self::findById($noticeId);
            if (!$notice) {
                return [
                    'success' => false,
                    'message' => 'Notice not found'
                ];
            }

            $db = \EMA\Config\Database::getInstance();

            // Get view statistics
            $viewStatsQuery = "
                SELECT
                    COUNT(*) as total_views,
                    COUNT(DISTINCT user_id) as unique_viewers,
                    MIN(viewed_at) as first_viewed_at,
                    MAX(viewed_at) as last_viewed_at
                FROM notice_views
                WHERE notice_id = ?
            ";
            $viewStmt = $db->prepare($viewStatsQuery);
            $viewStmt->bind_param('i', $noticeId);
            $viewStmt->execute();
            $viewStats = $viewStmt->get_result()->fetch_assoc();
            $viewStmt->close();

            // Get dismissal statistics
            $dismissalStatsQuery = "
                SELECT
                    COUNT(*) as total_dismissals,
                    COUNT(DISTINCT user_id) as unique_dismissers,
                    MIN(dismissed_at) as first_dismissed_at,
                    MAX(dismissed_at) as last_dismissed_at
                FROM notice_dismissals
                WHERE notice_id = ?
            ";
            $dismissalStmt = $db->prepare($dismissalStatsQuery);
            $dismissalStmt->bind_param('i', $noticeId);
            $dismissalStmt->execute();
            $dismissalStats = $dismissalStmt->get_result()->fetch_assoc();
            $dismissalStmt->close();

            // Get attachment statistics
            $attachmentStatsQuery = "
                SELECT
                    COUNT(*) as total_attachments,
                    SUM(file_size) as total_file_size
                FROM notice_attachments
                WHERE notice_id = ?
            ";
            $attachmentStmt = $db->prepare($attachmentStatsQuery);
            $attachmentStmt->bind_param('i', $noticeId);
            $attachmentStmt->execute();
            $attachmentStats = $attachmentStmt->get_result()->fetch_assoc();
            $attachmentStmt->close();

            Logger::info('Notice statistics retrieved', [
                'notice_id' => $noticeId
            ]);

            return [
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'notice_id' => $noticeId,
                'view_statistics' => [
                    'total_views' => (int) $viewStats['total_views'],
                    'unique_viewers' => (int) $viewStats['unique_viewers'],
                    'first_viewed_at' => $viewStats['first_viewed_at'],
                    'last_viewed_at' => $viewStats['last_viewed_at']
                ],
                'dismissal_statistics' => [
                    'total_dismissals' => (int) $dismissalStats['total_dismissals'],
                    'unique_dismissers' => (int) $dismissalStats['unique_dismissers'],
                    'first_dismissed_at' => $dismissalStats['first_dismissed_at'],
                    'last_dismissed_at' => $dismissalStats['last_dismissed_at']
                ],
                'attachment_statistics' => [
                    'total_attachments' => (int) $attachmentStats['total_attachments'],
                    'total_file_size_bytes' => (int) $attachmentStats['total_file_size'],
                    'total_file_size_mb' => round((int) $attachmentStats['total_file_size'] / 1048576, 2)
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting notice statistics', [
                'notice_id' => $noticeId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ];
        }
    }

    /**
     * Get dismissed notices for user
     * @param int $userId User ID
     * @return array Dismissed notices
     */
    public static function getDismissedNotices(int $userId): array
    {
        try {
            $query = "
                SELECT n.*, nd.dismissed_at as dismissed_at
                FROM system_notices n
                JOIN notice_dismissals nd ON n.id = nd.notice_id
                WHERE nd.user_id = ?
                ORDER BY nd.dismissed_at DESC
                LIMIT 50
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $dismissedNotices = [];
            while ($row = $result->fetch_assoc()) {
                $dismissedNotices[] = [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'notice_type' => $row['notice_type'],
                    'priority' => $row['priority'],
                    'dismissed_at' => $row['dismissed_at'],
                    'created_at' => $row['created_at']
                ];
            }

            $stmt->close();

            Logger::info('Dismissed notices retrieved', [
                'user_id' => $userId,
                'count' => count($dismissedNotices)
            ]);

            return $dismissedNotices;
        } catch (\Exception $e) {
            Logger::error('Error getting dismissed notices', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}