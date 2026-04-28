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

            Response::json([
                'success' => true,
                'message' => 'Quiz sets retrieved successfully',
                'data' => [
                    'quiz_sets' => $quizSets,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ]);

            Logger::info('Quiz sets listed', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage
            ]);
        } catch (\Exception $e) {
            Logger::error('Error listing quiz sets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve quiz sets',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

            // Check access
            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to quiz set'
                ], 403);
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

            Response::json([
                'success' => true,
                'message' => 'Quiz set retrieved successfully',
                'data' => $responseData
            ]);

            Logger::info('Quiz set viewed', [
                'user_id' => $userId,
                'quiz_set_id' => $id,
                'include_questions' => $includeQuestions,
                'include_stats' => $includeStats
            ]);
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set details', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve quiz set',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuizSetData($data);

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

            // Create quiz set
            $quizSetId = QuizSet::create($sanitizedData);

            if ($quizSetId) {
                Response::json([
                    'success' => true,
                    'message' => 'Quiz set created successfully',
                    'data' => [
                        'quiz_set' => QuizSet::findById($quizSetId)
                    ]
                ], 201);

                Logger::logSecurityEvent('Quiz set created', [
                    'quiz_set_id' => $quizSetId,
                    'created_by' => $sanitizedData['created_by']
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to create quiz set'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error creating quiz set', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to create quiz set',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuizSetData($data);

            if (!$validation['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
                return;
            }

            $sanitizedData = $validation['data'];

            // Update quiz set
            $result = QuizSet::update($id, $sanitizedData);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Quiz set updated successfully',
                    'data' => [
                        'quiz_set' => QuizSet::findById($id)
                    ]
                ]);

                Logger::logSecurityEvent('Quiz set updated', [
                    'quiz_set_id' => $id,
                    'updated_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to update quiz set'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to update quiz set',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Delete quiz set
            $result = QuizSet::delete($id);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Quiz set deleted successfully'
                ]);

                Logger::logSecurityEvent('Quiz set deleted', [
                    'quiz_set_id' => $id,
                    'deleted_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to delete quiz set'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to delete quiz set',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to quiz set'
                ], 403);
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

            Response::json([
                'success' => true,
                'message' => 'Questions retrieved successfully',
                'data' => [
                    'questions' => $questions,
                    'total' => $totalQuestions,
                    'page' => $page,
                    'per_page' => $perPage,
                    'quiz_set_id' => $id
                ]
            ]);

            Logger::info('Quiz set questions viewed', [
                'user_id' => $userId,
                'quiz_set_id' => $id,
                'page' => $page
            ]);
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set questions', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve questions',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

                        $data = $request->all();
            $data['quiz_set_id'] = $id;

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuestionData($data);

            if (!$validation['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
                return;
            }

            $sanitizedData = $validation['data'];

            // Create question
            $questionId = Question::create($sanitizedData);

            if ($questionId) {
                Response::json([
                    'success' => true,
                    'message' => 'Question created successfully',
                    'data' => [
                        'question' => Question::findById($questionId)
                    ]
                ], 201);

                Logger::logSecurityEvent('Question created', [
                    'question_id' => $questionId,
                    'quiz_set_id' => $id,
                    'created_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to create question'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error creating question', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to create question',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if question exists
            $question = Question::findById($questionId);
            if (!$question) {
                Response::json([
                    'success' => false,
                    'message' => 'Question not found'
                ], 404);
                return;
            }

            // Check if question belongs to quiz set
            if ($question['quiz_set_id'] != $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Question does not belong to this quiz set'
                ], 400);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Validate input data
            $validation = $this->quizService->validateQuestionData($data);

            if (!$validation['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 422);
                return;
            }

            $sanitizedData = $validation['data'];

            // Update question
            $result = Question::update($questionId, $sanitizedData);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Question updated successfully',
                    'data' => [
                        'question' => Question::findById($questionId)
                    ]
                ]);

                Logger::logSecurityEvent('Question updated', [
                    'question_id' => $questionId,
                    'quiz_set_id' => $id,
                    'updated_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to update question'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error updating question', [
                'question_id' => $questionId,
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to update question',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Admin access required'
                ], 403);
                return;
            }

            // Check if question exists
            $question = Question::findById($questionId);
            if (!$question) {
                Response::json([
                    'success' => false,
                    'message' => 'Question not found'
                ], 404);
                return;
            }

            // Check if question belongs to quiz set
            if ($question['quiz_set_id'] != $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Question does not belong to this quiz set'
                ], 400);
                return;
            }

                        $data = $request->all();

            // Validate CSRF token
            if (!Security::verifyCsrfToken($data['_csrf_token'] ?? '')) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 422);
                return;
            }

            // Delete question
            $result = Question::delete($questionId);

            if ($result) {
                Response::json([
                    'success' => true,
                    'message' => 'Question deleted successfully',
                    'data' => [
                        'backup_id' => $questionId
                    ]
                ]);

                Logger::logSecurityEvent('Question deleted', [
                    'question_id' => $questionId,
                    'quiz_set_id' => $id,
                    'deleted_by' => \EMA\Middleware\AuthMiddleware::getCurrentUserId()
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Failed to delete question'
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting question', [
                'question_id' => $questionId,
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to delete question',
                'errors' => ['Internal server error']
            ], 500);
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
                        $data = $request->all();

            // Check if quiz set exists
            $quizSet = QuizSet::findById($id);
            if (!$quizSet) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

            // Check access
            if (!QuizSet::checkQuizSetAccess($userId, $id)) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to quiz set'
                ], 403);
                return;
            }

            // Check if quiz is published
            if (!$quizSet['is_published'] && !\EMA\Middleware\AuthMiddleware::isAdmin()) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set is not published yet'
                ], 403);
                return;
            }

            // Generate random questions
            $questionCount = isset($data['question_count']) ? (int) $data['question_count'] : null;
            $randomQuiz = $this->quizService->generateRandomQuiz($id, $questionCount ?? 20);

            if (!$randomQuiz['success']) {
                Response::json([
                    'success' => false,
                    'message' => $randomQuiz['message']
                ], 400);
                return;
            }

            // Get user's attempt number
            $db = \EMA\Config\Database::getInstance();
            $stmt = $db->prepare("
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
            $stmt = $db->prepare("
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

            Response::json([
                'success' => true,
                'message' => 'Quiz attempt started successfully',
                'data' => [
                    'attempt' => [
                        'id' => $attemptId,
                        'attempt_number' => $attemptNumber,
                        'started_at' => date('Y-m-d H:i:s'),
                        'quiz_set_id' => $id
                    ],
                    'questions' => $randomQuiz['questions']
                ]
            ]);

            Logger::info('Quiz attempt started', [
                'user_id' => $userId,
                'quiz_set_id' => $id,
                'attempt_id' => $attemptId,
                'attempt_number' => $attemptNumber
            ]);
        } catch (\Exception $e) {
            Logger::error('Error starting quiz attempt', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to start quiz attempt',
                'errors' => ['Internal server error']
            ], 500);
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
                        $data = $request->all();

            // Validate required fields
            if (!isset($data['attempt_id']) || !isset($data['answers']) || !is_array($data['answers'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Attempt ID and answers are required'
                ], 400);
                return;
            }

            $attemptId = (int) $data['attempt_id'];
            $answers = $data['answers'];

            // Get attempt details
            $db = \EMA\Config\Database::getInstance();
            $stmt = $db->prepare("
                SELECT id, user_id, quiz_set_id, started_at
                FROM quiz_attempts
                WHERE id = ? LIMIT 1
            ");
            $stmt->bind_param('i', $attemptId);
            $stmt->execute();
            $attemptResult = $stmt->get_result();

            if (!$attemptResult->num_rows) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz attempt not found'
                ], 404);
                return;
            }

            $attempt = $attemptResult->fetch_assoc();
            $stmt->close();

            // Check if attempt belongs to user
            if ($attempt['user_id'] != $userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to this attempt'
                ], 403);
                return;
            }

            // Check if attempt is already completed
            if ($attempt['completed_at']) {
                Response::json([
                    'success' => false,
                    'message' => 'Quiz attempt already completed'
                ], 400);
                return;
            }

            // Validate answers
            foreach ($answers as $answer) {
                if (!isset($answer['question_id']) || !isset($answer['answer'])) {
                    Response::json([
                        'success' => false,
                        'message' => 'Each answer must have question_id and answer fields'
                    ], 400);
                    return;
                }

                $answer['answer'] = strtoupper($answer['answer']);
                if (!in_array($answer['answer'], ['A', 'B', 'C', 'D'])) {
                    Response::json([
                        'success' => false,
                        'message' => 'Invalid answer format for question ' . $answer['question_id']
                    ], 400);
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

                $stmt = $db->prepare("
                    INSERT INTO quiz_results (quiz_attempt_id, question_id, user_answer, is_correct, time_spent_seconds)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iisii', $attemptId, (int) $answer['question_id'], $answer['answer'], $isCorrect ? 1 : 0, $timeSpent);
                $stmt->execute();
                $stmt->close();
            }

            // Calculate score
            $scoreResult = $this->quizService->calculateQuizScore($attemptId);

            Response::json([
                'success' => true,
                'message' => 'Quiz submitted successfully',
                'data' => $scoreResult
            ]);

            Logger::info('Quiz attempt submitted', [
                'user_id' => $userId,
                'quiz_set_id' => $id,
                'attempt_id' => $attemptId,
                'score' => $scoreResult['percentage']
            ]);
        } catch (\Exception $e) {
            Logger::error('Error submitting quiz attempt', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to submit quiz',
                'errors' => ['Internal server error']
            ], 500);
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
                Response::json([
                    'success' => false,
                    'message' => 'Quiz set not found'
                ], 404);
                return;
            }

            // Check permissions
            if (!\EMA\Middleware\AuthMiddleware::isAdmin() && $quizSet['created_by'] != $userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Access denied to quiz statistics'
                ], 403);
                return;
            }

            // Get analytics
            $analytics = $this->quizService->getQuizAnalytics($id, $timeframe);

            if ($analytics['success']) {
                Response::json([
                    'success' => true,
                    'message' => 'Quiz statistics retrieved successfully',
                    'data' => $analytics
                ]);

                Logger::info('Quiz statistics viewed', [
                    'user_id' => $userId,
                    'quiz_set_id' => $id,
                    'timeframe' => $timeframe
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => $analytics['message']
                ], 500);
            }
        } catch (\Exception $e) {
            Logger::error('Error getting quiz statistics', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'errors' => ['Internal server error']
            ], 500);
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
                        $data = $request->all();

            // Validate required fields
            if (!isset($data['quiz_set_ids']) || !is_array($data['quiz_set_ids'])) {
                Response::json([
                    'success' => false,
                    'message' => 'quiz_set_ids array is required'
                ], 400);
                return;
            }

            $quizSetIds = $data['quiz_set_ids'];

            // Validate array size (max 50)
            if (count($quizSetIds) > 50) {
                Response::json([
                    'success' => false,
                    'message' => 'Maximum 50 quiz sets allowed per batch check'
                ], 400);
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

            Response::json([
                'success' => true,
                'message' => 'Batch access check completed',
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total_checked' => count($quizSetIds),
                        'accessible_count' => $accessibleCount,
                        'inaccessible_count' => count($quizSetIds) - $accessibleCount
                    ]
                ]
            ]);

            Logger::info('Batch quiz set access check', [
                'user_id' => $userId,
                'total_checked' => count($quizSetIds),
                'accessible_count' => $accessibleCount
            ]);
        } catch (\Exception $e) {
            Logger::error('Error in batch quiz set access check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Response::json([
                'success' => false,
                'message' => 'Batch access check failed',
                'errors' => ['Internal server error']
            ], 500);
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
            $db = \EMA\Config\Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO quiz_activity (quiz_set_id, user_id, action, details, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $detailsJson = $details ? json_encode($details) : null;
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

            $stmt->bind_param('iisss', $quizSetId, $userId, $action, $detailsJson, $ipAddress);
            $stmt->execute();
            $stmt->close();

            Logger::info('Quiz activity logged', [
                'quiz_set_id' => $quizSetId,
                'user_id' => $userId,
                'action' => $action
            ]);
        } catch (\Exception $e) {
            Logger::error('Error logging quiz activity', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
        }
    }
}