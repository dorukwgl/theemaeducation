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

            $fileUpload = null;
            if ($this->request->hasFile('attachment')) {
                $fileUpload = $this->noticeService->handleNoticeFileUpload($this->request->getFile('attachment'));
                if (!$fileUpload['success']) {
                    $this->response->error('File upload failed', 400, ['Failed to upload file']);
                    return;
                }
            }

            $noticeId = Notice::create($sanitized);

            if (!$noticeId) {
                if ($fileUpload) {
                    @unlink(ROOT_PATH . '/uploads/' . $fileUpload['file_path']);
                }
                $this->response->error('Failed to create notice', 500);
                return;
            }

            if ($fileUpload) {
                $attachmentId = Notice::createAttachment($noticeId, $fileUpload);
                if (!$attachmentId) {
                    Logger::error('Notice created but attachment record failed', ['notice_id' => $noticeId]);
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

            if ($this->request->hasFile('attachment')) {
                $fileUpload = $this->noticeService->handleNoticeFileUpload($this->request->getFile('attachment'));
                if (!$fileUpload['success']) {
                    $this->response->error('File upload failed', 400, ['Failed to upload file']);
                    return;
                }

                $oldAttachments = Notice::getNoticeAttachments($id);
                foreach ($oldAttachments as $old) {
                    Notice::deleteAttachment($old['id']);
                }

                $attachmentId = Notice::createAttachment($id, $fileUpload);
                if (!$attachmentId) {
                    Logger::error('Notice updated but new attachment record failed', ['notice_id' => $id]);
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
