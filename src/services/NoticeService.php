<?php

namespace EMA\Services;

use EMA\Utils\Logger;

class NoticeService
{
    public function validateNoticeData(array $data): array
    {
        $errors = [];

        if (!isset($data['title']) || empty(trim($data['title']))) {
            $errors[] = 'Title is required';
        } elseif (strlen(trim($data['title'])) < 3) {
            $errors[] = 'Title must be at least 3 characters';
        } elseif (strlen(trim($data['title'])) > 200) {
            $errors[] = 'Title must not exceed 200 characters';
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            $errors[] = 'Content is required';
        } elseif (strlen(trim($data['content'])) < 10) {
            $errors[] = 'Content must be at least 10 characters';
        } elseif (strlen(trim($data['content'])) > 10000) {
            $errors[] = 'Content must not exceed 10000 characters';
        }

        if (isset($data['attachment']) && is_array($data['attachment'])) {
            $fileValidation = $this->validateNoticeAttachment($data['attachment']);
            if (!$fileValidation['valid']) {
                $errors = array_merge($errors, $fileValidation['errors']);
            }
        }

        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Validation passed',
                'data' => [
                    'title' => trim($data['title']),
                    'content' => trim($data['content']),
                ],
            ];
        }

        return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
    }

    private function validateNoticeAttachment(array $uploadedFile): array
    {
        $errors = [];
        $maxSize = 10485760;

        if ($uploadedFile['size'] > $maxSize) {
            $errors[] = 'File must not exceed 10MB';
        }

        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac',
            'video/mp4', 'video/webm',
        ];

        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            $errors[] = 'Invalid file type. Allowed: PDF, document, image, audio, video';
        }

        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'aac', 'mp4', 'webm'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = 'Invalid file extension';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Handle notice file upload. Aligned with FileController pattern:
     * DB stores path without 'uploads/' prefix; filesystem path = ROOT_PATH . '/uploads/' . $dbPath.
     */
    public function handleNoticeFileUpload(array $uploadedFile): array
    {
        $result = ['success' => false, 'file_path' => null, 'file_name' => null];

        try {
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $filename = 'notice_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $dbPath = 'notices/' . $filename;
            $fullPath = ROOT_PATH . '/uploads/' . $dbPath;

            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                chmod($fullPath, 0644);
                $result['success'] = true;
                $result['file_name'] = $uploadedFile['name'];
                $result['file_path'] = $dbPath;
                $result['file_size'] = $uploadedFile['size'];
                $result['mime_type'] = $uploadedFile['type'];
                $result['file_type'] = $this->determineFileType($uploadedFile['type'], $ext);
            }
        } catch (\Exception $e) {
            Logger::error('Error handling notice file upload', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    private function determineFileType(string $mimeType, string $extension): string
    {
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) return 'jpeg';
        if (in_array($mimeType, ['application/pdf'])) return 'pdf';
        if (in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) return 'docx';
        if ($mimeType === 'text/plain' && $extension === 'txt') return 'txt';
        if (in_array($mimeType, ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac'])) return 'mp3';
        if (in_array($mimeType, ['video/mp4', 'video/webm'])) return 'mp4';
        return strtolower($extension);
    }
}
