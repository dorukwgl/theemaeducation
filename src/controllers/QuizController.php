<?php

namespace EMA\Controllers;

use EMA\Models\QuizSet;
use EMA\Models\Question;
use EMA\Services\QuizService;
use EMA\Utils\Logger;
use EMA\Utils\Security;
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
     * List quiz sets
     * Endpoint: GET /api/quiz-sets
     * Middleware: AuthMiddleware (authenticated users)
     */
    public function index(): void
    {
        try {
            $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $folderId = $this->request->getInput('folder_id') ? (int) $this->request->getInput('folder_id') : null;
            $includeQuestionCount = $this->request->getInput('include_question_count') === 'true';
            $publishedOnly = $this->request->getInput('published_only') !== 'false';

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $quizSets = QuizSet::getAllQuizSets($page, $perPage, $folderId, $userId, $includeQuestionCount, $publishedOnly);

            $this->response->success([
                'quiz_sets' => $quizSets,
                'page' => $page,
                'per_page' => $perPage
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
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
            $includeQuestions = $this->request->getInput('include_questions') === 'true';
            $includeStats = $this->request->getInput('include_stats') === 'true';

            // Get quiz set details
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

            // Prepare response data
            $responseData = [
                'quiz_set' => $quizSet,
                'access_info' => [
                    'has_access' => true,
                    'access_type' => $quizSet['access_type']
                ]
            ];

            // Include questions if requested
            if ($includeQuestions) {
                $questions = Question::findByQuizSetId($id);

                // For non-admin users, only include published questions if quiz is not published
                if (!$quizSet['is_published'] && !\EMA\Middleware\AuthMiddleware::isAdmin()) {
                    $responseData['message'] = 'Quiz set is not published yet';
                }

                $responseData['questions'] = $questions;
            }

            // Include statistics if requested and user has permission
            if ($includeStats && (\EMA\Middleware\AuthMiddleware::isAdmin() || $quizSet['created_by'] == $userId)) {
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
            // Require admin role
            if (!\EMA\Middleware\AuthMiddleware::isAdmin()) {
                $this->response->forbidden('Admin access required');
                return;
            }

            $data = $this->request->allInput();

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

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                $this->response->validationError(['csrf_token' => 'Invalid CSRF token']);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuizSetData($data);

            if (!$validation['success']) {
                $this->response->validationError($validation['errors']);
                return;
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

            $data = $this->request->allInput();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                $this->response->validationError(['csrf_token' => 'Invalid CSRF token']);
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
            $userId = \EMA\Middleware\AuthMiddleware::getCurrentUserId();
                        $page = (int) ($this->request->getInput('page', 1));
            $perPage = (int) ($this->request->getInput('per_page', 20));
            $includeFiles = $this->request->getInput('include_files') === 'true';

            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 20;

            // Check if quiz set exists and user has access
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                $this->response->notFound('Quiz set not found');
                return;
            }

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

            // Filter file URLs based on parameter
            if (!$includeFiles) {
                foreach ($questions as &$question) {
                    foreach (['question_file', 'choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'] as $field) {
                        if (isset($question[$field])) {
                            $question[$field] = null;
                        }
                    }
                    foreach (['A', 'B', 'C', 'D'] as $choice) {
                        if (isset($question['choice_' . $choice]['file'])) {
                            $question['choice_' . $choice]['file'] = null;
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

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                $this->response->validationError(['csrf_token' => 'Invalid CSRF token']);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuestionData($data);

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

            $data = $this->request->allInput();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['csrf_token'] ?? '')) {
                $this->response->validationError(['csrf_token' => 'Invalid CSRF token']);
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

            // Check if quiz is published
            if (!$quizSet['is_published'] && !\EMA\Middleware\AuthMiddleware::isAdmin()) {
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
            $stmt->bind_param('iis', $id, $userId, $attemptNumber, $ipAddress);
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
                SELECT id, user_id, quiz_set_id, started_at
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
}