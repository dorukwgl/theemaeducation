<?php

namespace EMA\Controllers;

use EMA\Models\Notice;
use EMA\Services\NoticeService;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class NoticeController
{
    private Request $request;
    private Response $response;
    private $noticeService;

    public function __construct()
    {
        $this->response = new Response();
        $this->noticeService = new NoticeService();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function index(): void
    {
        try {
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));

            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $result = Notice::getAllNotices($page, $perPage);

            $this->response->success([
                'notices' => $result['notices'],
                'pagination' => $result['pagination'],
            ], 'Notices retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error retrieving notices', ['error' => $e->getMessage()]);
            $this->response->error('Failed to retrieve notices', 500);
        }
    }

    public function show(int $id): void
    {
        try {
            $notice = Notice::findById($id);

            if (!$notice) {
                $this->response->error('Notice not found', 404);
                return;
            }

            $attachments = Notice::getNoticeAttachments($id);

            $this->response->success([
                'notice' => $notice,
                'attachments' => $attachments,
            ], 'Notice retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting notice', ['notice_id' => $id, 'error' => $e->getMessage()]);
            $this->response->error('Failed to retrieve notice', 500);
        }
    }

    /**
     * Normalize uploaded files from $_FILES['attachment'].
     * Handles both single file and attachment[] array notation.
     */
    private function getNoticeUploadedFiles(): array
    {
        $raw = $this->request->allFiles();
        $files = $raw['attachment'] ?? [];
        if (empty($files)) {
            return [];
        }

        // attachment[] array notation
        if (is_array($files['error'] ?? null)) {
            $result = [];
            foreach ($files['error'] as $i => $err) {
                if ($err === UPLOAD_ERR_OK) {
                    $result[] = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                }
            }
            return $result;
        }

        // Single attachment field
        if ($files['error'] === UPLOAD_ERR_OK) {
            return [$files];
        }

        return [];
    }

    public function store(): void
    {
        try {
            if (!AuthMiddleware::isAdmin()) {
                $this->response->error('Admin access required', 403);
                return;
            }

            $data = $this->request->allInput();

            $validation = $this->noticeService->validateNoticeData($data);
            if (!$validation['success']) {
                $this->response->error('Validation failed', 422, $validation['errors']);
                return;
            }

            $sanitized = $validation['data'];
            $sanitized['created_by'] = AuthMiddleware::getCurrentUserId();

            $uploadedFiles = $this->getNoticeUploadedFiles();
            $fileUploads = [];
            foreach ($uploadedFiles as $uf) {
                $result = $this->noticeService->handleNoticeFileUpload($uf);
                if (!$result['success']) {
                    foreach ($fileUploads as $uploaded) {
                        @unlink(ROOT_PATH . '/uploads/' . $uploaded['file_path']);
                    }
                    $this->response->error('File upload failed', 400, ['Failed to upload file']);
                    return;
                }
                $fileUploads[] = $result;
            }

            $noticeId = Notice::create($sanitized);

            if (!$noticeId) {
                foreach ($fileUploads as $uploaded) {
                    @unlink(ROOT_PATH . '/uploads/' . $uploaded['file_path']);
                }
                $this->response->error('Failed to create notice', 500);
                return;
            }

            foreach ($fileUploads as $uploaded) {
                $attachmentId = Notice::createAttachment($noticeId, $uploaded);
                if (!$attachmentId) {
                    Logger::error('Notice created but attachment record failed', ['notice_id' => $noticeId, 'file' => $uploaded['file_name']]);
                }
            }

            $this->response->success(['notice' => Notice::findById($noticeId)], 'Notice created successfully', 201);
        } catch (\Exception $e) {
            Logger::error('Error creating notice', ['error' => $e->getMessage()]);
            $this->response->error('Failed to create notice', 500);
        }
    }

    public function update(int $id): void
    {
        try {
            if (!AuthMiddleware::isAdmin()) {
                $this->response->error('Admin access required', 403);
                return;
            }

            $notice = Notice::findById($id);
            if (!$notice) {
                $this->response->error('Notice not found', 404);
                return;
            }

            $data = $this->request->allInput();

            $validation = $this->noticeService->validateNoticeData($data);
            if (!$validation['success']) {
                $this->response->error('Validation failed', 422, $validation['errors']);
                return;
            }

            $sanitized = $validation['data'];

            // Normalize uploaded files
            $uploadedFiles = $this->getNoticeUploadedFiles();
            if (!empty($uploadedFiles)) {
                $fileUploads = [];
                foreach ($uploadedFiles as $uf) {
                    $result = $this->noticeService->handleNoticeFileUpload($uf);
                    if (!$result['success']) {
                        foreach ($fileUploads as $uploaded) {
                            @unlink(ROOT_PATH . '/uploads/' . $uploaded['file_path']);
                        }
                        $this->response->error('File upload failed', 400, ['Failed to upload file']);
                        return;
                    }
                    $fileUploads[] = $result;
                }

                // Replace all old attachments with the new ones
                $oldAttachments = Notice::getNoticeAttachments($id);
                foreach ($oldAttachments as $old) {
                    Notice::deleteAttachment($old['id']);
                }

                foreach ($fileUploads as $uploaded) {
                    $attachmentId = Notice::createAttachment($id, $uploaded);
                    if (!$attachmentId) {
                        Logger::error('Notice updated but attachment record failed', ['notice_id' => $id, 'file' => $uploaded['file_name']]);
                    }
                }
            }

            if (Notice::update($id, $sanitized)) {
                $this->response->success(['notice' => Notice::findById($id)], 'Notice updated successfully');
            } else {
                $this->response->error('Failed to update notice', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating notice', ['notice_id' => $id, 'error' => $e->getMessage()]);
            $this->response->error('Failed to update notice', 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            if (!AuthMiddleware::isAdmin()) {
                $this->response->error('Admin access required', 403);
                return;
            }

            $notice = Notice::findById($id);
            if (!$notice) {
                $this->response->error('Notice not found', 404);
                return;
            }

            $data = $this->request->allInput();

            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                $this->response->error('Invalid CSRF token', 422);
                return;
            }

            if (Notice::delete($id)) {
                $this->response->success([], 'Notice deleted successfully');
            } else {
                $this->response->error('Failed to delete notice', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting notice', ['notice_id' => $id, 'error' => $e->getMessage()]);
            $this->response->error('Failed to delete notice', 500);
        }
    }
}
