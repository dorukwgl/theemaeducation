<?php

namespace EMA\Services;

use EMA\Models\Notice;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;

class NoticeService
{
    /**
     * Validate notice data
     * @param array $data Notice data
     * @return array Validation result with success, errors, and data
     */
    public function validateNoticeData(array $data): array
    {
        $errors = [];

        // Validate required fields
        if (!isset($data['title']) || empty(trim($data['title']))) {
            $errors[] = 'Notice title is required';
        } elseif (strlen(trim($data['title'])) < 3) {
            $errors[] = 'Notice title must be at least 3 characters';
        } elseif (strlen(trim($data['title'])) > 200) {
            $errors[] = 'Notice title must not exceed 200 characters';
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            $errors[] = 'Notice content is required';
        } elseif (strlen(trim($data['content'])) < 10) {
            $errors[] = 'Notice content must be at least 10 characters';
        } elseif (strlen(trim($data['content'])) > 10000) {
            $errors[] = 'Notice content must not exceed 10000 characters';
        }

        // Validate notice_type
        if (isset($data['notice_type'])) {
            $validTypes = ['info', 'warning', 'error', 'success', 'announcement'];
            if (!in_array($data['notice_type'], $validTypes)) {
                $errors[] = 'Invalid notice type. Must be: info, warning, error, success, or announcement';
            }
        }

        // Validate priority
        if (isset($data['priority'])) {
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (!in_array($data['priority'], $validPriorities)) {
                $errors[] = 'Invalid priority. Must be: low, medium, high, or critical';
            }
        }

        // Validate target_audience
        if (isset($data['target_audience'])) {
            $validAudiences = ['all', 'logged_in', 'admin', 'teachers', 'students'];
            if (!in_array($data['target_audience'], $validAudiences)) {
                $errors[] = 'Invalid target audience. Must be: all, logged_in, admin, teachers, or students';
            }
        }

        // Validate expires_at
        if (isset($data['expires_at']) && !empty($data['expires_at'])) {
            $expiresTimestamp = strtotime($data['expires_at']);
            if ($expiresTimestamp === false) {
                $errors[] = 'Invalid expiration date format';
            } elseif ($expiresTimestamp < time()) {
                $errors[] = 'Expiration date must be in the future';
            }
        }

        // Validate is_active
        if (isset($data['is_active'])) {
            if (!is_bool($data['is_active'])) {
                $errors[] = 'Active status must be boolean (true or false)';
            }
        }

        // Validate file attachment
        if (isset($data['attachment']) && is_uploaded_file($data['attachment'])) {
            $fileValidation = $this->validateNoticeAttachment($data['attachment']);
            if (!$fileValidation['valid']) {
                $errors = array_merge($errors, $fileValidation['errors']);
            }
        }

        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Notice data is valid',
                'data' => $this->sanitizeNoticeData($data)
            ];
        }

        return [
            'success' => false,
            'message' => 'Notice validation failed',
            'errors' => $errors
        ];
    }

    /**
     * Validate notice attachment upload
     * @param array $uploadedFile Uploaded file data
     * @return array Validation result
     */
    private function validateNoticeAttachment(array $uploadedFile): array
    {
        $errors = [];

        // Validate file size (max 10MB)
        $maxSize = 10485760; // 10MB
        if ($uploadedFile['size'] > $maxSize) {
            $errors[] = 'Notice attachment must not exceed 10MB';
        }

        // Validate MIME type
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/aac',
            'video/mp4',
            'video/webm'
        ];

        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            $errors[] = 'Invalid file type. Must be PDF, document, text, image, audio, or video';
        }

        // Validate file extension
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'aac', 'mp4', 'webm'];
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'Invalid file extension';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Sanitize notice data
     * @param array $data Notice data
     * @return array Sanitized data
     */
    private function sanitizeNoticeData(array $data): array
    {
        $sanitized = [
            'title' => trim($data['title']),
            'content' => trim($data['content'])
        ];

        if (isset($data['notice_type'])) {
            $sanitized['notice_type'] = $data['notice_type'];
        }

        if (isset($data['priority'])) {
            $sanitized['priority'] = $data['priority'];
        }

        if (isset($data['target_audience'])) {
            $sanitized['target_audience'] = $data['target_audience'];
        }

        if (isset($data['expires_at']) && !empty($data['expires_at'])) {
            $sanitized['expires_at'] = $data['expires_at'];
        }

        if (isset($data['is_active'])) {
            $sanitized['is_active'] = $data['is_active'];
        }

        return $sanitized;
    }

    /**
     * Determine file type from MIME type and extension
     * @param string $mimeType MIME type
     * @param string $extension File extension
     * @return string File type enum value
     */
    private function determineFileType(string $mimeType, string $extension): string
    {
        // Images
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            return 'jpeg';
        }

        // Documents
        if (in_array($mimeType, ['application/pdf'])) {
            return 'pdf';
        }

        if (in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            return 'docx';
        }

        if ($mimeType === 'text/plain' && $extension === 'txt') {
            return 'txt';
        }

        // Audio
        if (in_array($mimeType, ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac'])) {
            return 'mp3';
        }

        // Video
        if (in_array($mimeType, ['video/mp4', 'video/webm'])) {
            return 'mp4';
        }

        // Default fallback
        return strtolower($extension);
    }

    /**
     * Handle notice file upload
     * @param array $uploadedFile Uploaded file data
     * @return array Upload result
     */
    private function handleNoticeFileUpload(array $uploadedFile): array
    {
        $result = ['success' => false, 'file_path' => null, 'file_name' => null];

        try {
            // Generate secure filename
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $secureFilename = 'notice_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filePath = 'uploads/notices/' . $secureFilename;

            // Move file to uploads directory
            $fullPath = ROOT_PATH . '/' . $filePath;
            $uploadDir = dirname($fullPath);

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                chmod($fullPath, 0644);

                $result['success'] = true;
                $result['file_name'] = $uploadedFile['name'];
                $result['file_path'] = $filePath;
                $result['file_size'] = $uploadedFile['size'];
                $result['mime_type'] = $uploadedFile['type'];
                $result['file_type'] = $this->determineFileType($uploadedFile['type'], $extension);

                Logger::info('Notice file uploaded successfully', [
                    'file_path' => $filePath,
                    'file_type' => $result['file_type'],
                    'file_size' => $uploadedFile['size']
                ]);
            } else {
                Logger::error('Failed to move uploaded notice file', [
                    'tmp_name' => $uploadedFile['tmp_name'],
                    'destination' => $fullPath
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error handling notice file upload', [
                'error' => $e->getMessage()
            ]);
            return $result;
        }
    }

    /**
     * Get notice statistics with advanced metrics
     * @param int $noticeId Notice ID
     * @return array Comprehensive statistics
     */
    public function getNoticeAnalytics(int $noticeId): array
    {
        try {
            $stats = Notice::getNoticeStats($noticeId);

            if (!$stats['success']) {
                return $stats;
            }

            $analytics = $stats['data'];

            // Calculate engagement rate
            $totalViews = $analytics['view_statistics']['total_views'] ?? 0;
            $uniqueViewers = $analytics['view_statistics']['unique_viewers'] ?? 0;
            $totalDismissals = $analytics['dismissal_statistics']['total_dismissals'] ?? 0;
            $uniqueDismissers = $analytics['dismissal_statistics']['unique_dismissers'] ?? 0;

            // Engagement rate: (dismissals + unique viewers who haven't dismissed) / total views
            $engagementRate = $totalViews > 0
                ? round((($totalDismissals + ($uniqueViewers - $uniqueDismissers)) / $totalViews) * 100, 2)
                : 0;

            // Average time to first dismissal
            $firstViewedAt = $analytics['view_statistics']['first_viewed_at'] ?? null;
            $firstDismissedAt = $analytics['dismissal_statistics']['first_dismissed_at'] ?? null;

            if ($firstViewedAt && $firstDismissedAt) {
                $timeToFirstDismissal = strtotime($firstDismissedAt) - strtotime($firstViewedAt);
                $analytics['time_to_first_dismissal_hours'] = round($timeToFirstDismissal / 3600, 2);
            }

            // File analysis
            if (isset($analytics['attachment_statistics'])) {
                $attachmentStats = $analytics['attachment_statistics'];
                $totalAttachments = $attachmentStats['total_attachments'] ?? 0;
                $totalFileSizeBytes = $attachmentStats['total_file_size_bytes'] ?? 0;
                $totalFileSizeMB = $attachmentStats['total_file_size_mb'] ?? 0;

                $analytics['attachment_analysis'] = [
                    'has_attachments' => $totalAttachments > 0,
                    'total_attachments' => $totalAttachments,
                    'average_attachment_size_mb' => $totalAttachments > 0 ? round($totalFileSizeMB / $totalAttachments, 2) : 0,
                    'total_storage_mb' => $totalFileSizeMB
                ];
            }

            // Add engagement rate to analytics
            $analytics['engagement'] = [
                'total_views' => $totalViews,
                'unique_viewers' => $uniqueViewers,
                'total_dismissals' => $totalDismissals,
                'unique_dismissers' => $uniqueDismissers,
                'engagement_rate' => $engagementRate,
                'click_through_rate' => $totalViews > 0 ? round((($totalViews - $totalDismissals) / $totalViews) * 100, 2) : 0
            ];

            Logger::info('Notice analytics calculated', [
                'notice_id' => $noticeId
            ]);

            return [
                'success' => true,
                'message' => 'Analytics retrieved successfully',
                'data' => $analytics
            ];
        } catch (\Exception $e) {
            Logger::error('Error calculating notice analytics', [
                'notice_id' => $noticeId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to calculate analytics',
                'data' => []
            ];
        }
    }

    /**
     * Get user notice preferences
     * @param int $userId User ID
     * @return array User preferences
     */
    public function getUserNoticePreferences(int $userId): array
    {
        try {
            $dismissedNotices = Notice::getDismissedNotices($userId);

            // Calculate preference metrics
            $totalDismissals = count($dismissedNotices);
            $dismissalRates = [
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'success' => 0,
                'announcement' => 0
            ];

            foreach ($dismissedNotices as $notice) {
                if (isset($dismissalRates[$notice['notice_type']])) {
                    $dismissalRates[$notice['notice_type']]++;
                }
            }

            Logger::info('User notice preferences retrieved', [
                'user_id' => $userId,
                'total_dismissals' => $totalDismissals
            ]);

            return [
                'success' => true,
                'message' => 'Preferences retrieved successfully',
                'data' => [
                    'dismissed_notices' => $dismissedNotices,
                    'dismissal_summary' => [
                        'total_dismissals' => $totalDismissals,
                        'by_type' => $dismissalRates
                    ],
                    'dismissal_percentages' => $totalDismissals > 0 ? [
                        'info' => round(($dismissalRates['info'] / $totalDismissals) * 100, 2),
                        'warning' => round(($dismissalRates['warning'] / $totalDismissals) * 100, 2),
                        'error' => round(($dismissalRates['error'] / $totalDismissals) * 100, 2),
                        'success' => round(($dismissalRates['success'] / $totalDismissals) * 100, 2),
                        'announcement' => round(($dismissalRates['announcement'] / $totalDismissals) * 100, 2)
                    ] : []
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting user notice preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve preferences',
                'data' => []
            ];
        }
    }

    /**
     * Bulk update notice status
     * @param array $noticeIds Array of notice IDs
     * @param string $status Status to set ('active', 'inactive')
     * @return array Bulk operation result
     */
    public function bulkUpdateStatus(array $noticeIds, string $status): array
    {
        try {
            // Validate notice IDs
            if (empty($noticeIds) || count($noticeIds) > 50) {
                return [
                    'success' => false,
                    'message' => 'Invalid request. Maximum 50 notices allowed per bulk operation'
                ];
            }

            // Validate status
            $validStatuses = ['active', 'inactive'];
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status. Must be: active or inactive'
                ];
            }

            $isActive = ($status === 'active') ? 1 : 0;
            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            // Update each notice
            foreach ($noticeIds as $noticeId) {
                $notice = Notice::findById((int) $noticeId);
                if (!$notice) {
                    $failureCount++;
                    $errors[] = "Notice {$noticeId} not found";
                    continue;
                }

                $result = Notice::update((int) $noticeId, ['is_active' => $isActive]);
                if ($result) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $errors[] = "Failed to update notice {$noticeId}";
                }
            }

            Logger::info('Bulk notice status update completed', [
                'status' => $status,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);

            return [
                'success' => true,
                'message' => "Bulk status update completed: {$successCount} succeeded, {$failureCount} failed",
                'data' => [
                    'status' => $status,
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'errors' => $errors
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error in bulk notice status update', [
                'notice_ids' => $noticeIds,
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Bulk status update failed',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Cleanup expired notices
     * @return array Cleanup result
     */
    public function cleanupExpiredNotices(): array
    {
        try {
            $query = "
                UPDATE system_notices
                SET is_active = 0,
                updated_at = NOW()
                WHERE expires_at IS NOT NULL
                  AND expires_at < NOW()
                  AND is_active = 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $result = $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();

            Logger::info('Expired notices cleanup completed', [
                'affected_rows' => $affectedRows
            ]);

            return [
                'success' => true,
                'message' => "Expired notices cleanup completed: {$affectedRows} notices deactivated",
                'data' => [
                    'affected_rows' => $affectedRows
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error cleaning up expired notices', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to clean up expired notices',
                'errors' => [$e->getMessage()]
            ];
        }
    }
}