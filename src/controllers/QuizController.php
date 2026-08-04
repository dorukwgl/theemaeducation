<?php

namespace EMA\Controllers;

use EMA\Models\QuizSet;
use EMA\Models\Question;
use EMA\Models\User;
use EMA\Services\QuizService;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Utils\Validator;
use EMA\Core\Request;
use EMA\Core\Response;

class QuizController
{
    private Request $request;
    private Response $response;
    private $quizService;

    public function __construct()
    {
        // Request will be set by Router via setRequest()
        $this->response = new Response();
        $this->quizService = new QuizService();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Merge file uploads from $_FILES into the data array.
     * Follows the same pattern used for icon uploads in store()/update().
     *
     * @param array $data Merged request data
     * @return array Data array with file upload arrays merged in
     */
    private function mergeQuestionFileUploads(array $data): array
    {
        // Some clients send the question image as `image`; map it to the API field
        if (array_key_exists('image', $data) && !array_key_exists('question_file', $data)) {
            $data['question_file'] = $data['image'];
            unset($data['image']);
        }

        $fileFields = ['question_file', 'choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'];
        foreach ($fileFields as $field) {
            if ($this->request->hasFile($field)) {
                $data[$field] = $this->request->getFile($field);
            }
        }
        return $data;
    }

    /**
     * List quiz sets
     * Endpoint: GET /api/quiz-sets
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function index(): void
    {
        try {
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();
            $userId = $currentUser['id'];

            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $folderId = $this->request->getInput('folder_id') ? (int) $this->request->getInput('folder_id') : null;
            $includeQuestionCount = $this->request->getInput('include_question_count') === 'true';
            $accessType = $this->request->getInput('access_type');
            $status = $this->request->getInput('status');

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Validate access_type parameter
            if ($accessType && !in_array($accessType, ['all', 'logged_in', 'private'])) {
                $this->response->badRequest('Invalid access_type parameter. Must be "all", "logged_in", or "private"');
                return;
            }

            // Validate status parameter
            if ($status && !in_array($status, ['published', 'draft', 'archived'])) {
                $this->response->badRequest('Invalid status parameter. Must be "published", "draft", or "archived"');
                return;
            }

            // For admin users, get all quiz sets. For non-admin, get accessible quiz sets
            if ($currentUser['role'] === 'admin') {
                $quizSets = QuizSet::getAllQuizSetsPaginated($page, $perPage, $folderId, $accessType, $status, $includeQuestionCount);
            } else {
                $quizSets = QuizSet::getLoggedInQuizSetsPaginated($userId, $page, $perPage, $folderId, $accessType, $status, $includeQuestionCount);
            }

            $this->response->success([
                'quiz_sets' => $quizSets['quiz_sets'],
                'pagination' => $quizSets['pagination'],
                'total' => $quizSets['total']
            ], 'Quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error listing quiz sets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve quiz sets', 500, ['Internal server error']);
        }
    }

    /**
     * Get quiz set details
     * Endpoint: GET /api/quiz-sets/{id}
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function show(int $id): void
    {
        try {
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();
            $userId = $currentUser['id'];
            $includeQuestions = $this->request->getInput('include_questions') === 'true';
            $includeStats = $this->request->getInput('include_stats') === 'true';

            // Get quiz set details
            $quizSet = QuizSet::findById($id);

            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Check access using enhanced QuizSet model access control
            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                $this->response->forbidden('Access denied to quiz set');
                return;
            }

            // Prepare response data
            $responseData = [
                'quiz_set' => $quizSet,
                'access_info' => [
                    'has_access' => true,
                    'access_type' => $quizSet['access_type'],
                    'status' => $quizSet['status']
                ]
            ];

            // Include questions if requested
            if ($includeQuestions) {
                $questions = Question::findByQuizSetId($id);

                // For non-admin users, only include questions if quiz is published
                if ($quizSet['status'] !== 'published' && $currentUser['role'] !== 'admin') {
                    $responseData['message'] = 'Quiz set is not published yet';
                }

                $responseData['questions'] = $questions;
            }

            // Include statistics if requested and user has permission
            if ($includeStats && ($currentUser['role'] === 'admin' || $quizSet['created_by'] == $userId)) {
                $stats = QuizSet::getQuizSetStats($id);
                $responseData['stats'] = $stats;
            }

            $this->response->success($responseData, 'Quiz set retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set details', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve quiz set', 500, ['Internal server error']);
        }
    }

    /**
     * Create quiz set
     * Endpoint: POST /api/quiz-sets
     * Middleware: AuthMiddleware (admin only)
     */
    public function store(): void
    {
        try {
            $data = $this->request->allInput();

            // Include icon from files if present
            if ($this->request->hasFile('icon')) {
                $data['icon'] = $_FILES['icon'];
            }

            // Validate input data
            $validation = $this->quizService->validateQuizSetData($data);

            if (!$validation['success']) {
                $this->response->validationError($validation['errors']);
                return;
            }

            $sanitizedData = $validation['data'];
            $sanitizedData['created_by'] = \EMA\Middleware\AuthMiddleware::getCurrentUserId();

            // Create quiz set
            $quizSetId = QuizSet::create($sanitizedData);

            if ($quizSetId) {
                $this->response->created([
                    'quiz_set' => QuizSet::findById($quizSetId)
                ], 'Quiz set created successfully');
            } else {
                $this->response->error('Failed to create quiz set', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error creating quiz set', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to create quiz set', 500, ['Internal server error']);
        }
    }

    /**
     * Update quiz set
     * Endpoint: PUT /api/quiz-sets/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function update(int $id): void
    {
        try {
            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            $data = $this->request->allInput();

            // Include icon from files if present
            if ($this->request->hasFile('icon')) {
                $data['icon'] = $_FILES['icon'];
            }

            // Validate input data
            $validation = $this->quizService->validateQuizSetData($data);
            if (!$validation['success']) {
                $this->response->validationError($validation['errors']);
            }

            $sanitizedData = $validation['data'];

            // Update quiz set
            $result = QuizSet::update($id, $sanitizedData);

            if ($result) {
                $this->response->success([
                    'quiz_set' => QuizSet::findById($id)
                ], 'Quiz set updated successfully');
            } else {
                $this->response->error('Failed to update quiz set', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to update quiz set', 500, ['Internal server error']);
        }
    }

    /**
     * Delete quiz set
     * Endpoint: DELETE /api/quiz-sets/{id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function delete(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Delete quiz set
            $result = QuizSet::delete($id);

            if ($result) {
                $this->response->success([], 'Quiz set deleted successfully');
            } else {
                $this->response->error('Failed to delete quiz set', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to delete quiz set', 500, ['Internal server error']);
        }
    }

    /**
     * Get quiz set questions
     * Endpoint: GET /api/quiz-sets/{id}/questions
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function questions(int $id): void
    {
        try {
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();
            $userId = $currentUser['id'];
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $includeFiles = $this->request->getInput('include_files') !== 'false';

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }
            // Check access using QuizSet model access control (handles public/logged_in/private)
            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                $this->response->forbidden('Access denied to quiz set');
                return;
            }

            // Get questions
            $questions = Question::findByQuizSetId($id);
            $totalQuestions = count($questions);

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $questions = array_slice($questions, $offset, $perPage);

            // Filter file URLs based on parameter (include by default)
            if (!$includeFiles) {
                foreach ($questions as &$question) {
                    $question['question_file'] = null;
                    $question['question_file_type'] = null;
                    $question['question_file_mime'] = null;
                    foreach (['choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'] as $field) {
                        $question[$field] = null;
                    }
                    foreach (['A', 'B', 'C', 'D'] as $choice) {
                        if (isset($question['choice_' . $choice]['file'])) {
                            $question['choice_' . $choice]['file'] = null;
                            $question['choice_' . $choice]['file_type'] = null;
                            $question['choice_' . $choice]['file_mime'] = null;
                        }
                    }
                }
                unset($question);
            }

            $this->response->success([
                'questions' => $questions,
                'total' => $totalQuestions,
                'page' => $page,
                'per_page' => $perPage,
                'quiz_set_id' => $id
            ], 'Questions retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set questions', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve questions', 500, ['Internal server error']);
        }
    }

    /**
     * Create question in quiz set
     * Endpoint: POST /api/quiz-sets/{id}/questions
     * Middleware: AuthMiddleware (admin only)
     */
    public function createQuestion(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            $data = $this->request->allInput();
            $data['quiz_set_id'] = $id;
            $data = $this->mergeQuestionFileUploads($data);

            if ($this->request->wasInputDiscarded()) {
                $contentLength = $this->request->getHeader('Content-Length', 'unknown');
                $this->response->error(
                    "Upload failed: request body ({$contentLength} bytes) exceeds upload limits. " .
                        'Ask the server administrator to increase post_max_size and upload_max_filesize.',
                    413
                );
                return;
            }
            // Validate input data
            $validation = $this->quizService->validateQuestionData($data);

            if (!$validation['success']) {
                $this->response->validationError($validation['errors']);
                return;
            }

            $sanitizedData = $validation['data'];

            // Create question
            $questionId = Question::create($sanitizedData);

            if ($questionId) {
                $this->response->created([
                    'question' => Question::findById($questionId)
                ], 'Question created successfully');
            } else {
                $this->response->error('Failed to create question', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error creating question', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to create question', 500, ['Internal server error']);
        }
    }

    /**
     * Update question in quiz set
     * Endpoint: PUT /api/quiz-sets/{id}/questions/{question_id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function updateQuestion(int $id, int $questionId): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Check if question exists
            $question = Question::findById($questionId);
            if (!$question) {
                $this->response->notFound('Question not found');
                return;
            }

            // Check if question belongs to quiz set
            if ($question['quiz_set_id'] != $id) {
                $this->response->badRequest('Question does not belong to this quiz set');
                return;
            }

            $data = $this->request->allInput();
            $data['quiz_set_id'] = $id;
            $data = $this->mergeQuestionFileUploads($data);

            // Validate input data
            $validation = $this->quizService->validateQuestionData($data, true);

            if (!$validation['success']) {
                $this->response->validationError($validation['errors']);
                return;
            }

            $sanitizedData = $validation['data'];

            // Update question
            $result = Question::update($questionId, $sanitizedData);

            if ($result) {
                $this->response->success([
                    'question' => Question::findById($questionId)
                ], 'Question updated successfully');
            } else {
                $this->response->error('Failed to update question', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating question', [
                'question_id' => $questionId,
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to update question', 500, ['Internal server error']);
        }
    }

    /**
     * Delete question from quiz set
     * Endpoint: DELETE /api/quiz-sets/{id}/questions/{question_id}
     * Middleware: AuthMiddleware (admin only)
     */
    public function deleteQuestion(int $id, int $questionId): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Check if question exists
            $question = Question::findById($questionId);
            if (!$question) {
                $this->response->notFound('Question not found');
                return;
            }

            // Check if question belongs to quiz set
            if ($question['quiz_set_id'] != $id) {
                $this->response->badRequest('Question does not belong to this quiz set');
                return;
            }

            // Delete question
            $result = Question::delete($questionId);

            if ($result) {
                $this->response->success([
                    'backup_id' => $questionId
                ], 'Question deleted successfully');
            } else {
                $this->response->error('Failed to delete question', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting question', [
                'question_id' => $questionId,
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to delete question', 500, ['Internal server error']);
        }
    }

    /**
     * Start quiz attempt
     * Endpoint: POST /api/quiz-sets/{id}/start
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function startAttempt(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $data = $this->request->allInput();

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Check access
            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                $this->response->forbidden('Access denied to quiz set');
                return;
            }

            // Check if quiz is published (using new status field)
            if ($quizSet['status'] !== 'published' && !\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Quiz set is not published yet');
                return;
            }

            // Generate random questions
            $questionCount = isset($data['question_count']) ? (int) $data['question_count'] : null;
            $randomQuiz = $this->quizService->generateRandomQuiz($id, $questionCount ?? 20);

            if (!$randomQuiz['success']) {
                $this->response->badRequest($randomQuiz['message']);
                return;
            }

            // Get user's attempt number
            $stmt = \EMA\Config\Database::prepare("
                SELECT COALESCE(MAX(attempt_number), 0) + 1 as attempt_number
                FROM quiz_attempts
                WHERE user_id = ? AND quiz_set_id = ?
            ");
            $stmt->bind_param('ii', $userId, $id);
            $stmt->execute();
            $attemptNumberResult = $stmt->get_result()->fetch_assoc();
            $attemptNumber = $attemptNumberResult['attempt_number'];
            $stmt->close();

            // Create quiz attempt record
            $stmt = \EMA\Config\Database::prepare("
                INSERT INTO quiz_attempts (quiz_set_id, user_id, attempt_number, started_at, ip_address)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            $stmt->bind_param('iiss', $id, $userId, $attemptNumber, $ipAddress);
            $stmt->execute();
            $attemptId = $stmt->insert_id;
            $stmt->close();

            // Log quiz start activity
            $this->logQuizActivity($id, $userId, 'start', [
                'attempt_id' => $attemptId,
                'attempt_number' => $attemptNumber,
                'question_count' => count($randomQuiz['questions'])
            ]);

            $this->response->success([
                'attempt' => [
                    'id' => $attemptId,
                    'attempt_number' => $attemptNumber,
                    'started_at' => date('Y-m-d H:i:s'),
                    'quiz_set_id' => $id
                ],
                'questions' => $randomQuiz['questions']
            ], 'Quiz attempt started successfully');
        } catch (\Exception $e) {
            Logger::error('Error starting quiz attempt', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to start quiz attempt', 500, ['Internal server error']);
        }
    }

    /**
     * Submit quiz answers
     * Endpoint: POST /api/quiz-sets/{id}/submit
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function submitAttempt(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $data = $this->request->allInput();

            // Validate required fields
            if (!isset($data['attempt_id']) || !isset($data['answers']) || !is_array($data['answers'])) {
                $this->response->badRequest('Attempt ID and answers are required');
                return;
            }

            $attemptId = (int) $data['attempt_id'];
            $answers = $data['answers'];

            // Get attempt details
            $stmt = \EMA\Config\Database::prepare("
                SELECT id, user_id, quiz_set_id, started_at, completed_at
                FROM quiz_attempts
                WHERE id = ? LIMIT 1
            ");
            $stmt->bind_param('i', $attemptId);
            $stmt->execute();
            $attemptResult = $stmt->get_result();

            if (!$attemptResult->num_rows) {
                $this->response->notFound('Quiz attempt not found');
                return;
            }

            $attempt = $attemptResult->fetch_assoc();
            $stmt->close();

            // Check if attempt belongs to user
            if ($attempt['user_id'] != $userId) {
                $this->response->forbidden('Access denied to this attempt');
                return;
            }

            // Check if attempt is already completed
            if ($attempt['completed_at']) {
                $this->response->badRequest('Quiz attempt already completed');
                return;
            }

            // Validate answers
            foreach ($answers as $answer) {
                if (!isset($answer['question_id']) || !isset($answer['answer'])) {
                    $this->response->badRequest('Each answer must have question_id and answer fields');
                    return;
                }

                $answer['answer'] = strtoupper($answer['answer']);
                if (!in_array($answer['answer'], ['A', 'B', 'C', 'D'])) {
                    $this->response->badRequest('Invalid answer format for question ' . $answer['question_id']);
                    return;
                }
            }

            // Store individual results
            foreach ($answers as $answer) {
                $question = Question::findById((int) $answer['question_id']);
                if (!$question) {
                    continue;
                }

                $isCorrect = $question['correct_answer'] === $answer['answer'];
                $timeSpent = $answer['time_spent_seconds'] ?? null;

                $stmt = \EMA\Config\Database::prepare("
                    INSERT INTO quiz_results (quiz_attempt_id, question_id, user_answer, is_correct, time_spent_seconds)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $questionId = (int) $answer['question_id'];
                $userAnswer = $answer['answer'];
                $correctFlag = $isCorrect ? 1 : 0;
                $stmt->bind_param('iisii', $attemptId, $questionId, $userAnswer, $correctFlag, $timeSpent);
                $stmt->execute();
                $stmt->close();
            }

            // Calculate score
            $scoreResult = $this->quizService->calculateQuizScore($attemptId);

            $this->response->success($scoreResult, 'Quiz submitted successfully');
        } catch (\Exception $e) {
            Logger::error('Error submitting quiz attempt', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to submit quiz', 500, ['Internal server error']);
        }
    }

    /**
     * Get quiz statistics
     * Endpoint: GET /api/quiz-sets/{id}/statistics
     * Middleware: AuthMiddleware (admin or quiz owner)
     */
    public function statistics(int $id): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $timeframe = $this->request->getInput('timeframe');

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Check permissions
            if (!\EMA\Middleware\AuthMiddleware::isAdmin() && $quizSet['created_by'] != $userId) {
                $this->response->forbidden('Access denied to quiz statistics');
                return;
            }

            // Get analytics
            $analytics = $this->quizService->getQuizAnalytics($id, $timeframe);

            if ($analytics['success']) {
                $this->response->success($analytics, 'Quiz statistics retrieved successfully');
            } else {
                $this->response->error($analytics['message'], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error getting quiz statistics', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve statistics', 500, ['Internal server error']);
        }
    }

    /**
     * Batch quiz set access check
     * Endpoint: POST /api/quiz-sets/batch-check
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function batchCheck(): void
    {
        try {
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $data = $this->request->allInput();

            // Validate required fields
            if (!isset($data['quiz_set_ids']) || !is_array($data['quiz_set_ids'])) {
                $this->response->badRequest('quiz_set_ids array is required');
                return;
            }

            $quizSetIds = $data['quiz_set_ids'];

            // Validate array size (max 50)
            if (count($quizSetIds) > 50) {
                $this->response->badRequest('Maximum 50 quiz sets allowed per batch check');
                return;
            }

            // Batch check access
            $results = [];
            foreach ($quizSetIds as $quizSetId) {
                $hasAccess = QuizSet::checkQuizSetAccess($userId, (int) $quizSetId);
                $results[] = [
                    'id' => (int) $quizSetId,
                    'has_access' => $hasAccess
                ];
            }

            $accessibleCount = count(array_filter($results, fn($r) => $r['has_access']));

            $this->response->success([
                'results' => $results,
                'summary' => [
                    'total_checked' => count($quizSetIds),
                    'accessible_count' => $accessibleCount,
                    'inaccessible_count' => count($quizSetIds) - $accessibleCount
                ]
            ], 'Batch access check completed');
        } catch (\Exception $e) {
            Logger::error('Error in batch quiz set access check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Batch access check failed', 500, ['Internal server error']);
        }
    }

    /**
     * Log quiz activity
     * @param int $quizSetId Quiz set ID
     * @param int|null $userId User ID
     * @param string $action Action type
     * @param array|null $details Additional details
     */
    private function logQuizActivity(int $quizSetId, ?int $userId, string $action, ?array $details = null): void
    {
        try {
            $stmt = \EMA\Config\Database::prepare("
                INSERT INTO quiz_activity (quiz_set_id, user_id, action, details, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $detailsJson = $details ? json_encode($details) : null;
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

            $stmt->bind_param('iisss', $quizSetId, $userId, $action, $detailsJson, $ipAddress);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            Logger::error('Error logging quiz activity', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Public folder quiz sets - Get public published quiz sets in a specific folder
     * GET /api/public/folder/{id}/quiz-sets
     * No authentication required
     */
    public function publicFolderQuizSets(int $folderId): void
    {
        try {
            $folder = \EMA\Models\Folder::findById($folderId);
            if (!$folder) {
                $this->response->notFound('Folder not found');
                return;
            }

            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $search = $this->request->getInput('search');
            $includeQuestionCount = $this->request->getInput('include_question_count') === 'true';

            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $quizSets = QuizSet::getPublicQuizSetsPaginated($page, $perPage, $search, $folderId, $includeQuestionCount);

            $responseData = [
                'folder' => $folder,
                'quiz_sets' => $quizSets['quiz_sets'],
                'pagination' => $quizSets['pagination']
            ];

            $this->response->success($responseData, 'Folder quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting public folder quiz sets', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve folder quiz sets', 500, ['Internal server error']);
        }
    }

    /**
     * Public index - Get all public published quiz sets
     * GET /api/public/quiz-sets
     * No authentication required
     */
    public function publicIndex(): void
    {
        try {
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $folderId = $this->request->getInput('folder_id') ? (int) $this->request->getInput('folder_id') : null;
            $search = $this->request->getInput('search');
            $includeQuestionCount = $this->request->getInput('include_question_count') === 'true';

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Get public published quiz sets
            $quizSets = QuizSet::getPublicQuizSetsPaginated($page, $perPage, $search, $folderId, $includeQuestionCount);

            $this->response->success($quizSets, 'Public quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting public quiz sets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve public quiz sets', 500, ['Internal server error']);
        }
    }

    /**
     * Public show - Display a public published quiz set
     * GET /api/public/quiz-sets/{id}
     * No authentication required
     */
    public function publicShow(int $id): void
    {
        try {
            $includeQuestions = $this->request->getInput('include_questions') === 'true';

            // Get quiz set details
            $quizSet = QuizSet::findById($id);

            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Check if quiz set is public and published
            if (!QuizSet::isQuizSetPublic($id) || !QuizSet::isQuizSetPublished($id)) {
                $this->response->forbidden('Quiz set is not publicly available');
                return;
            }

            // Prepare response data
            $responseData = [
                'quiz_set' => $quizSet,
                'access_info' => [
                    'is_public' => true,
                    'is_published' => true,
                    'access_type' => $quizSet['access_type'],
                    'status' => $quizSet['status']
                ]
            ];

            // Include questions if requested
            if ($includeQuestions) {
                $questions = Question::findByQuizSetId($id);
                $responseData['questions'] = $questions;
            }

            $this->response->success($responseData, 'Quiz set retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting public quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve quiz set', 500, ['Internal server error']);
        }
    }

    /**
     * Public questions - Get questions from a public published quiz set
     * GET /api/public/quiz-sets/{id}/questions
     * No authentication required
     */
    public function publicQuestions(int $id): void
    {
        try {
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $includeFiles = $this->request->getInput('include_files') !== 'false';

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Check if quiz set exists and is public
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            if (!QuizSet::isQuizSetPublic($id) || !QuizSet::isQuizSetPublished($id)) {
                $this->response->forbidden('Quiz set is not publicly available');
                return;
            }

            // Get questions
            $questions = Question::findByQuizSetId($id);
            $totalQuestions = count($questions);

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $questions = array_slice($questions, $offset, $perPage);

            // Filter file URLs based on parameter (include by default)
            if (!$includeFiles) {
                foreach ($questions as &$question) {
                    $question['question_file'] = null;
                    $question['question_file_type'] = null;
                    $question['question_file_mime'] = null;
                    foreach (['choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'] as $field) {
                        $question[$field] = null;
                    }
                    foreach (['A', 'B', 'C', 'D'] as $choice) {
                        if (isset($question['choice_' . $choice]['file'])) {
                            $question['choice_' . $choice]['file'] = null;
                            $question['choice_' . $choice]['file_type'] = null;
                            $question['choice_' . $choice]['file_mime'] = null;
                        }
                    }
                }
                unset($question);
            }

            $this->response->success([
                'questions' => $questions,
                'total' => $totalQuestions,
                'page' => $page,
                'per_page' => $perPage,
                'quiz_set_id' => $id
            ], 'Questions retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting public quiz set questions', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve questions', 500, ['Internal server error']);
        }
    }

    /**
     * Authenticated index - Get quiz sets accessible to logged-in users
     * GET /api/quiz-sets
     * Requires authentication
     */
    public function authenticatedIndex(): void
    {
        try {
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();
            $userId = $currentUser['id'];

            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $folderId = $this->request->getInput('folder_id') ? (int) $this->request->getInput('folder_id') : null;
            $includeQuestionCount = $this->request->getInput('include_question_count') === 'true';
            $accessType = $this->request->getInput('access_type');
            $status = $this->request->getInput('status');

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Validate access_type parameter
            if ($accessType && !in_array($accessType, ['all', 'logged_in', 'private'])) {
                $this->response->badRequest('Invalid access_type parameter. Must be "all", "logged_in", or "private"');
                return;
            }

            // Validate status parameter
            if ($status && !in_array($status, ['published', 'draft', 'archived'])) {
                $this->response->badRequest('Invalid status parameter. Must be "published", "draft", or "archived"');
                return;
            }

            // For admin users, get all quiz sets. For non-admin, get accessible quiz sets
            if ($currentUser['role'] === 'admin') {
                $quizSets = QuizSet::getAllQuizSetsPaginated($page, $perPage, $folderId, $accessType, $status, $includeQuestionCount);
            } else {
                $quizSets = QuizSet::getLoggedInQuizSetsPaginated($userId, $page, $perPage, $folderId, $accessType, $status, $includeQuestionCount);
            }

            $this->response->success([
                'quiz_sets' => $quizSets['quiz_sets'],
                'pagination' => $quizSets['pagination'],
                'total' => $quizSets['total']
            ], 'Quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error getting authenticated quiz sets', [
                'user_id' => $currentUser['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to retrieve quiz sets', 500, ['Internal server error']);
        }
    }

    /**
     * Update quiz set status (Admin only)
     * PUT /api/admin/quiz-sets/{id}/status
     */
    public function updateStatus(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Get current user
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();

            // Get quiz set details
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Get new status from request
            $data = $this->request->allInput();
            $newStatus = $data['status'] ?? null;

            if (!$newStatus) {
                $this->response->badRequest('Status is required');
                return;
            }

            // Validate status
            if (!in_array($newStatus, ['published', 'draft', 'archived'])) {
                $this->response->badRequest('Invalid status. Must be "published", "draft", or "archived"');
                return;
            }

            // Update status
            $result = QuizSet::updateStatus($id, $newStatus);

            if ($result) {
                $updatedQuizSet = QuizSet::findById($id);
                $this->response->success($updatedQuizSet, 'Quiz set status updated successfully');
            } else {
                $this->response->error('Failed to update quiz set status', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set status', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to update quiz set status', 500, ['Internal server error']);
        }
    }

    /**
     * Update quiz set access type (Admin only)
     * PUT /api/admin/quiz-sets/{id}/access-type
     */
    public function updateAccessType(int $id): void
    {
        try {
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            // Get quiz set details
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

            // Get new access type from request
            $data = $this->request->allInput();
            $newAccessType = $data['access_type'] ?? null;

            if (!$newAccessType) {
                $this->response->badRequest('Access type is required');
                return;
            }

            // Validate access type
            if (!in_array($newAccessType, ['all', 'logged_in', 'private'])) {
                $this->response->badRequest('Invalid access type. Must be "all", "logged_in", or "private"');
                return;
            }

            // Update access type
            $result = QuizSet::updateAccessType($id, $newAccessType);

            if ($result) {
                $updatedQuizSet = QuizSet::findById($id);
                $this->response->success($updatedQuizSet, 'Quiz set access type updated successfully');
            } else {
                $this->response->error('Failed to update quiz set access type', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set access type', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->response->error('Failed to update quiz set access type', 500, ['Internal server error']);
        }
    }

    /**
     * Get quiz sets granted to a specific user (admin use)
     * GET /api/admin/users/{userId}/quiz-sets/granted
     */
    public function userGrantedQuizSets(int $userId): void
    {
        try {
            $user = User::findById($userId);
            if (!$user) {
                $this->response->notFound('User not found');
                return;
            }

            $page = (int) ($this->request->getQueryParameter('page', 1));
            $perPage = (int) ($this->request->getQueryParameter('per_page', 20));

            $validation = Validator::make([
                'page' => $page,
                'per_page' => $perPage
            ], [
                'page' => 'integer|min:1',
                'per_page' => 'integer|between:1,100'
            ]);

            if (!$validation->validate()) {
                $this->response->badRequest('Invalid pagination parameters');
                return;
            }

            $folderId = $this->request->getQueryParameter('folder_id');
            $search = $this->request->getQueryParameter('search');
            $status = $this->request->getQueryParameter('status');

            if ($folderId !== null) {
                $folderId = (int) $folderId;
            }

            $validation = Validator::make([
                'status' => $status,
            ], [
                'status' => 'in:published,draft,archived',
            ]);

            if (!$validation->validate()) {
                $this->response->badRequest('Invalid status parameter');
                return;
            }

            $result = QuizSet::getGrantedQuizSetsForUser($userId, $page, $perPage, $folderId, $search, $status);
            $this->response->success($result, 'Granted quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error retrieving granted quiz sets for user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            $this->response->error('Failed to retrieve granted quiz sets', 500);
        }
    }

    /**
     * Get quiz sets NOT granted to a specific user (admin use)
     * GET /api/admin/users/{userId}/quiz-sets/not-granted
     */
    public function userNotGrantedQuizSets(int $userId): void
    {
        try {
            $user = User::findById($userId);
            if (!$user) {
                $this->response->notFound('User not found');
                return;
            }

            $page = (int) ($this->request->getQueryParameter('page', 1));
            $perPage = (int) ($this->request->getQueryParameter('per_page', 20));

            $validation = Validator::make([
                'page' => $page,
                'per_page' => $perPage
            ], [
                'page' => 'integer|min:1',
                'per_page' => 'integer|between:1,100'
            ]);

            if (!$validation->validate()) {
                $this->response->badRequest('Invalid pagination parameters');
                return;
            }

            $folderId = $this->request->getQueryParameter('folder_id');
            $search = $this->request->getQueryParameter('search');
            $status = $this->request->getQueryParameter('status');

            if ($folderId !== null) {
                $folderId = (int) $folderId;
            }

            $validation = Validator::make([
                'status' => $status,
            ], [
                'status' => 'in:published,draft,archived',
            ]);

            if (!$validation->validate()) {
                $this->response->badRequest('Invalid status parameter');
                return;
            }

            $result = QuizSet::getNotGrantedQuizSetsForUser($userId, $page, $perPage, $folderId, $search, $status);
            $this->response->success($result, 'Not-granted quiz sets retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Error retrieving not-granted quiz sets for user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            $this->response->error('Failed to retrieve not-granted quiz sets', 500);
        }
    }
}
