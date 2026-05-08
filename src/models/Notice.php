<?php

namespace EMA\Models;

use EMA\Utils\Logger;

class Notice
{
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT n.*, u.full_name as created_by_name, u.email as created_by_email
                FROM system_notices n
                LEFT JOIN users u ON n.created_by = u.id
                WHERE n.id = ? LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $n = $result->fetch_assoc();
            $stmt->close();

            return [
                'id' => (int) $n['id'],
                'title' => $n['title'],
                'content' => $n['content'],
                'notice_type' => $n['notice_type'],
                'priority' => $n['priority'],
                'is_active' => (bool) $n['is_active'],
                'created_by' => (int) $n['created_by'],
                'created_by_name' => $n['created_by_name'],
                'created_by_email' => $n['created_by_email'],
                'created_at' => $n['created_at'],
                'updated_at' => $n['updated_at'],
            ];
        } catch (\Exception $e) {
            Logger::error('Error finding notice by ID', ['notice_id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public static function getAllNotices(int $page = 1, int $perPage = 20): array
    {
        try {
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $offset = ($page - 1) * $perPage;

            $query = "
                SELECT n.*, u.full_name as created_by_name
                FROM system_notices n
                LEFT JOIN users u ON n.created_by = u.id
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ii', $perPage, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            $notices = [];
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $id = (int) $row['id'];
                $ids[] = $id;
                $notices[$id] = [
                    'id' => $id,
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'notice_type' => $row['notice_type'],
                    'priority' => $row['priority'],
                    'is_active' => (bool) $row['is_active'],
                    'created_by' => (int) $row['created_by'],
                    'created_by_name' => $row['created_by_name'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }
            $stmt->close();

            // Batch fetch attachments for all notices on this page
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                $attStmt = \EMA\Config\Database::prepare("
                    SELECT id, notice_id, file_name, file_path, file_size, mime_type, file_type, uploaded_at
                    FROM notice_attachments WHERE notice_id IN ($placeholders) ORDER BY uploaded_at ASC
                ");
                $attStmt->bind_param($types, ...$ids);
                $attStmt->execute();
                $attResult = $attStmt->get_result();
                while ($a = $attResult->fetch_assoc()) {
                    $notices[(int) $a['notice_id']]['attachments'][] = [
                        'id' => (int) $a['id'],
                        'file_name' => $a['file_name'],
                        'file_path' => $a['file_path'],
                        'file_size' => (int) $a['file_size'],
                        'mime_type' => $a['mime_type'],
                        'file_type' => $a['file_type'],
                        'uploaded_at' => $a['uploaded_at'],
                    ];
                }
                $attStmt->close();
            }

            // Ensure every notice has an attachments key
            foreach ($notices as &$n) {
                if (!isset($n['attachments'])) {
                    $n['attachments'] = [];
                }
            }
            unset($n);

            $countStmt = \EMA\Config\Database::prepare("SELECT COUNT(*) FROM system_notices");
            $countStmt->execute();
            $totalCount = (int) $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            return [
                'notices' => array_values($notices),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_count' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
            ];
        } catch (\Exception $e) {
            Logger::error('Error retrieving notices', ['error' => $e->getMessage()]);
            return ['notices' => [], 'pagination' => ['current_page' => 1, 'per_page' => $perPage, 'total_count' => 0, 'total_pages' => 0]];
        }
    }

    public static function create(array $data): int|false
    {
        try {
            if (!isset($data['title']) || !isset($data['content']) || !isset($data['created_by'])) {
                return false;
            }

            $query = "
                INSERT INTO system_notices (title, content, notice_type, priority, is_active, created_by, created_at, updated_at)
                VALUES (?, ?, 'info', 'medium', 1, ?, NOW(), NOW())
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ssi', $data['title'], $data['content'], $data['created_by']);

            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $id;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating notice', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public static function update(int $id, array $data): bool
    {
        try {
            $fields = [];
            $params = [];
            $types = '';

            if (isset($data['title']) && !empty(trim($data['title']))) {
                $fields[] = 'title = ?';
                $types .= 's';
                $params[] = trim($data['title']);
            }

            if (isset($data['content']) && !empty(trim($data['content']))) {
                $fields[] = 'content = ?';
                $types .= 's';
                $params[] = trim($data['content']);
            }

            if (empty($fields)) {
                return false;
            }

            $fields[] = 'updated_at = NOW()';
            $types .= 'i';
            $params[] = $id;

            $query = "UPDATE system_notices SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating notice', ['notice_id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        try {
            $attachments = self::getNoticeAttachments($id);

            $stmt = \EMA\Config\Database::prepare("DELETE FROM system_notices WHERE id = ?");
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                foreach ($attachments as $a) {
                    $path = ROOT_PATH . '/uploads/' . $a['file_path'];
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting notice', ['notice_id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function getNoticeAttachments(int $noticeId): array
    {
        try {
            $stmt = \EMA\Config\Database::prepare("
                SELECT id, file_name, file_path, file_size, mime_type, file_type, uploaded_at
                FROM notice_attachments WHERE notice_id = ? ORDER BY uploaded_at ASC
            ");
            $stmt->bind_param('i', $noticeId);
            $stmt->execute();
            $result = $stmt->get_result();

            $attachments = [];
            while ($row = $result->fetch_assoc()) {
                $attachments[] = [
                    'id' => (int) $row['id'],
                    'file_name' => $row['file_name'],
                    'file_path' => $row['file_path'],
                    'file_size' => (int) $row['file_size'],
                    'mime_type' => $row['mime_type'],
                    'file_type' => $row['file_type'],
                    'uploaded_at' => $row['uploaded_at'],
                ];
            }
            $stmt->close();
            return $attachments;
        } catch (\Exception $e) {
            Logger::error('Error getting notice attachments', ['notice_id' => $noticeId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public static function createAttachment(int $noticeId, array $data): int|false
    {
        try {
            $stmt = \EMA\Config\Database::prepare("
                INSERT INTO notice_attachments (notice_id, file_name, file_path, file_size, mime_type, file_type, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('ississ',
                $noticeId, $data['file_name'], $data['file_path'],
                $data['file_size'], $data['mime_type'], $data['file_type']
            );

            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $id;
            }
            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating notice attachment', ['notice_id' => $noticeId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function deleteAttachment(int $attachmentId): bool
    {
        try {
            $stmt = \EMA\Config\Database::prepare("SELECT id, file_path FROM notice_attachments WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $attachmentId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $stmt->close();
                return false;
            }

            $attachment = $result->fetch_assoc();
            $stmt->close();

            $path = ROOT_PATH . '/uploads/' . $attachment['file_path'];
            if (file_exists($path)) {
                unlink($path);
            }

            $del = \EMA\Config\Database::prepare("DELETE FROM notice_attachments WHERE id = ?");
            $del->bind_param('i', $attachmentId);
            $result = $del->execute();
            $del->close();
            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting notice attachment', ['attachment_id' => $attachmentId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
