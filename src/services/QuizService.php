<?php

namespace EMA\Services;

use EMA\Models\QuizSet;
use EMA\Models\Question;
use EMA\Utils\Validator;
use EMA\Utils\Logger;

class QuizService
{
    /**
     * Validate quiz set data
     * @param array $data Quiz set data
     * @return array Validation result with success, errors, and data
     */
    public function validateQuizSetData(array $data): array
    {
        $errors = [];

        // Validate required fields
        if (!isset($data['name']) || empty(trim($data['name']))) {
            $errors[] = 'Quiz set name is required';
        } elseif (strlen(trim($data['name'])) > 200) {
            $errors[] = 'Quiz set name must not exceed 200 characters';
        }

        if (!isset($data['folder_id']) || !is_numeric($data['folder_id'])) {
            $errors[] = 'Valid folder ID is required';
        }

        // Validate optional fields
        if (isset($data['description']) && strlen($data['description']) > 5000) {
            $errors[] = 'Description must not exceed 5000 characters';
        }

        // Validate duration_minutes
        if (isset($data['duration_minutes'])) {
            if (!is_numeric($data['duration_minutes']) || $data['duration_minutes'] < 0 || $data['duration_minutes'] > 1440) {
                $errors[] = 'Duration must be between 0 and 1440 minutes (24 hours)';
            }
        }

        // Validate passing_score
        if (isset($data['passing_score'])) {
            if (!is_numeric($data['passing_score']) || $data['passing_score'] < 0 || $data['passing_score'] > 100) {
                $errors[] = 'Passing score must be between 0 and 100';
            }
        }

        // Validate access_type
        if (isset($data['access_type'])) {
            $validAccessTypes = ['all', 'logged_in', 'private'];
            if (!in_array($data['access_type'], $validAccessTypes)) {
                $errors[] = 'Invalid access type. Must be: all, logged_in, or private';
            }
        }

        // Validate icon upload
        if (isset($data['icon']) && is_uploaded_file($data['icon'])) {
            $iconValidation = $this->validateQuizIcon($data['icon']);
            if (!$iconValidation['valid']) {
                $errors = array_merge($errors, $iconValidation['errors']);
            }
        }

        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Quiz set data is valid',
                'data' => $this->sanitizeQuizSetData($data)
            ];
        }

        return [
            'success' => false,
            'message' => 'Quiz set validation failed',
            'errors' => $errors
        ];
    }

    /**
     * Validate question data
     * @param array $data Question data
     * @return array Validation result with success, errors, and data
     */
    public function validateQuestionData(array $data): array
    {
        $errors = [];

        // Validate required fields
        if (!isset($data['quiz_set_id']) || !is_numeric($data['quiz_set_id'])) {
            $errors[] = 'Valid quiz set ID is required';
        }

        if (!isset($data['question']) || empty(trim($data['question']))) {
            $errors[] = 'Question text is required';
        } elseif (strlen(trim($data['question'])) > 5000) {
            $errors[] = 'Question text must not exceed 5000 characters';
        }

        if (!isset($data['correct_answer'])) {
            $errors[] = 'Correct answer is required';
        } elseif (!in_array(strtoupper($data['correct_answer']), ['A', 'B', 'C', 'D'])) {
            $errors[] = 'Correct answer must be A, B, C, or D';
        }

        // Validate optional text
        if (isset($data['optional_text']) && strlen($data['optional_text']) > 5000) {
            $errors[] = 'Optional text must not exceed 5000 characters';
        }

        // Validate question_type
        if (isset($data['question_type'])) {
            $validTypes = ['reading', 'listening'];
            if (!in_array($data['question_type'], $validTypes)) {
                $errors[] = 'Invalid question type. Must be: reading or listening';
            }
        }

        // Validate choice texts
        $choiceFields = ['choice_A_text', 'choice_B_text', 'choice_C_text', 'choice_D_text'];
        foreach ($choiceFields as $field) {
            if (isset($data[$field]) && strlen($data[$field]) > 2000) {
                $errors[] = str_replace('_', ' ', $field) . ' must not exceed 2000 characters';
            }
        }

        // Validate word formatting JSON
        $wordFormattingFields = ['question_word_formatting', 'optional_word_formatting'];
        foreach ($wordFormattingFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!is_string($data[$field]) || !$this->isValidWordFormattingJSON($data[$field])) {
                    $errors[] = str_replace('_', ' ', $field) . ' must be valid JSON with bold, underline, or italic arrays';
                }
            }
        }

        // Validate file uploads
        if (isset($data['question_file']) && is_uploaded_file($data['question_file'])) {
            $fileValidation = $this->validateQuestionFile($data['question_file']);
            if (!$fileValidation['valid']) {
                $errors = array_merge($errors, $fileValidation['errors']);
            }
        }

        $choiceFileFields = ['choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'];
        foreach ($choiceFileFields as $field) {
            if (isset($data[$field]) && is_uploaded_file($data[$field])) {
                $choice = strtoupper(explode('_', $field)[1]);
                $fileValidation = $this->validateChoiceFile($data[$field], $choice);
                if (!$fileValidation['valid']) {
                    $errors = array_merge($errors, $fileValidation['errors']);
                }
            }
        }

        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Question data is valid',
                'data' => $this->sanitizeQuestionData($data)
            ];
        }

        return [
            'success' => false,
            'message' => 'Question validation failed',
            'errors' => $errors
        ];
    }

    /**
     * Generate random quiz from quiz set
     * @param int $quizSetId Quiz set ID
     * @param int $questionCount Number of questions to include
     * @return array Randomly selected questions
     */
    public function generateRandomQuiz(int $quizSetId, int $questionCount): array
    {
        try {
            // Validate quiz set exists
            $quizSet = QuizSet::findById($quizSetId);
            if (!$quizSet) {
                return [
                    'success' => false,
                    'message' => 'Quiz set not found',
                    'questions' => []
                ];
            }

            // Validate question count
            if ($questionCount <= 0 || $questionCount > 100) {
                return [
                    'success' => false,
                    'message' => 'Question count must be between 1 and 100',
                    'questions' => []
                ];
            }

            // Get all questions for quiz set
            $questions = Question::findByQuizSetId($quizSetId);
            $totalQuestions = count($questions);

            if ($totalQuestions === 0) {
                return [
                    'success' => false,
                    'message' => 'No questions found in quiz set',
                    'questions' => []
                ];
            }

            if ($questionCount > $totalQuestions) {
                Logger::warning('Requested more questions than available', [
                    'requested' => $questionCount,
                    'available' => $totalQuestions
                ]);
                $questionCount = $totalQuestions;
            }

            // Randomly select questions
            $randomKeys = array_rand($questions, $questionCount);
            if (!is_array($randomKeys)) {
                $randomKeys = [$randomKeys];
            }

            $selectedQuestions = [];
            foreach ($randomKeys as $key) {
                $selectedQuestions[] = $questions[$key];
            }

            // Shuffle question order
            shuffle($selectedQuestions);

            Logger::info('Random quiz generated', [
                'quiz_set_id' => $quizSetId,
                'question_count' => count($selectedQuestions)
            ]);

            return [
                'success' => true,
                'message' => 'Random quiz generated successfully',
                'questions' => $selectedQuestions,
                'total_questions' => $totalQuestions,
                'selected_count' => count($selectedQuestions)
            ];
        } catch (\Exception $e) {
            Logger::error('Error generating random quiz', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to generate random quiz',
                'questions' => []
            ];
        }
    }

    /**
     * Calculate quiz score
     * @param int $attemptId Quiz attempt ID
     * @return array Score calculation results
     */
    public function calculateQuizScore(int $attemptId): array
    {
        try {
            // Get attempt details
            $db = \EMA\Config\Database::getInstance();
            $stmt = $db->prepare("
                SELECT id, quiz_set_id, user_id, started_at, completed_at
                FROM quiz_attempts
                WHERE id = ? LIMIT 1
            ");
            $stmt->bind_param('i', $attemptId);
            $stmt->execute();
            $attemptResult = $stmt->get_result();

            if (!$attemptResult->num_rows) {
                return [
                    'success' => false,
                    'message' => 'Quiz attempt not found',
                    'score' => 0,
                    'percentage' => 0
                ];
            }

            $attempt = $attemptResult->fetch_assoc();
            $stmt->close();

            // Get all results for this attempt
            $stmt = $db->prepare("
                SELECT qr.question_id, qr.user_answer, qr.is_correct, q.correct_answer
                FROM quiz_results qr
                JOIN questions q ON qr.question_id = q.id
                WHERE qr.quiz_attempt_id = ?
            ");
            $stmt->bind_param('i', $attemptId);
            $stmt->execute();
            $results = $stmt->get_result();

            $totalQuestions = 0;
            $correctAnswers = 0;
            $correctQuestions = [];
            $incorrectQuestions = [];

            while ($row = $results->fetch_assoc()) {
                $totalQuestions++;
                if ($row['is_correct']) {
                    $correctAnswers++;
                    $correctQuestions[] = $row['question_id'];
                } else {
                    $incorrectQuestions[] = [
                        'question_id' => $row['question_id'],
                        'user_answer' => $row['user_answer'],
                        'correct_answer' => $row['correct_answer']
                    ];
                }
            }
            $stmt->close();

            if ($totalQuestions === 0) {
                $score = 0;
                $percentage = 0;
            } else {
                $score = $correctAnswers;
                $percentage = round(($correctAnswers / $totalQuestions) * 100, 2);
            }

            // Calculate time spent
            $startedAt = strtotime($attempt['started_at']);
            $completedAt = $completedAt ? strtotime($attempt['completed_at']) : time();
            $timeSpentSeconds = $completedAt - $startedAt;

            // Update attempt record
            $stmt = $db->prepare("
                UPDATE quiz_attempts
                SET score = ?, correct_answers = ?, time_spent_seconds = ?, completed_at = COALESCE(completed_at, NOW())
                WHERE id = ?
            ");
            $stmt->bind_param('iii', $correctAnswers, $totalQuestions, $timeSpentSeconds, $attemptId);
            $stmt->execute();
            $stmt->close();

            // Get quiz set passing score
            $quizSet = QuizSet::findById($attempt['quiz_set_id']);
            $passingScore = $quizSet['passing_score'] ?? 70;
            $passed = $percentage >= $passingScore;

            // Log quiz completion activity
            $this->logQuizActivity($attempt['quiz_set_id'], $attempt['user_id'], 'complete', [
                'score' => $percentage,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'time_spent_seconds' => $timeSpentSeconds,
                'passed' => $passed
            ]);

            Logger::info('Quiz score calculated', [
                'attempt_id' => $attemptId,
                'score' => $percentage,
                'passed' => $passed
            ]);

            return [
                'success' => true,
                'message' => 'Score calculated successfully',
                'attempt_id' => $attemptId,
                'score' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'percentage' => $percentage,
                'passed' => $passed,
                'passing_score' => $passingScore,
                'time_spent_seconds' => $timeSpentSeconds,
                'correct_questions' => $correctQuestions,
                'incorrect_questions' => $incorrectQuestions
            ];
        } catch (\Exception $e) {
            Logger::error('Error calculating quiz score', [
                'attempt_id' => $attemptId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to calculate score',
                'score' => 0,
                'percentage' => 0
            ];
        }
    }

    /**
     * Get quiz analytics
     * @param int $quizSetId Quiz set ID
     * @param string|null $timeframe Timeframe: 'day', 'week', 'month', 'all'
     * @return array Quiz analytics data
     */
    public function getQuizAnalytics(int $quizSetId, ?string $timeframe = null): array
    {
        try {
            // Validate quiz set exists
            $quizSet = QuizSet::findById($quizSetId);
            if (!$quizSet) {
                return [
                    'success' => false,
                    'message' => 'Quiz set not found',
                    'analytics' => []
                ];
            }

            // Set date filter based on timeframe
            $dateCondition = '';
            switch ($timeframe) {
                case 'day':
                    $dateCondition = "AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $dateCondition = "AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $dateCondition = "AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
                case 'all':
                default:
                    $dateCondition = '';
                    break;
            }

            $db = \EMA\Config\Database::getInstance();

            // Get attempt frequency
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_attempts,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score
                FROM quiz_attempts qa
                WHERE qa.quiz_set_id = ? {$dateCondition}
            ");
            $stmt->bind_param('i', $quizSetId);
            $stmt->execute();
            $attemptStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Get completion rate
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_attempts,
                    COUNT(*) as total_attempts_started
                FROM quiz_attempts qa
                WHERE qa.quiz_set_id = ? {$dateCondition}
            ");
            $stmt->bind_param('i', $quizSetId);
            $stmt->execute();
            $completionStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Get time spent distribution
            $stmt = $db->prepare("
                SELECT
                    AVG(time_spent_seconds) as average_time,
                    MIN(time_spent_seconds) as fastest_time,
                    MAX(time_spent_seconds) as slowest_time
                FROM quiz_attempts qa
                WHERE qa.quiz_set_id = ? AND completed_at IS NOT NULL {$dateCondition}
            ");
            $stmt->bind_param('i', $quizSetId);
            $stmt->execute();
            $timeStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Get most missed questions
            $stmt = $db->prepare("
                SELECT
                    qr.question_id,
                    COUNT(*) as times_answered,
                    SUM(CASE WHEN qr.is_correct = 0 THEN 1 ELSE 0 END) as times_incorrect,
                    (SUM(CASE WHEN qr.is_correct = 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as error_rate
                FROM quiz_results qr
                JOIN quiz_attempts qa ON qr.quiz_attempt_id = qa.id
                WHERE qa.quiz_set_id = ? {$dateCondition}
                GROUP BY qr.question_id
                ORDER BY error_rate DESC, times_incorrect DESC
                LIMIT 10
            ");
            $stmt->bind_param('i', $quizSetId);
            $stmt->execute();
            $missedQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Calculate completion rate
            $completionRate = $completionStats['total_attempts_started'] > 0
                ? round(($completionStats['completed_attempts'] / $completionStats['total_attempts_started']) * 100, 2)
                : 0;

            Logger::info('Quiz analytics retrieved', [
                'quiz_set_id' => $quizSetId,
                'timeframe' => $timeframe ?? 'all'
            ]);

            return [
                'success' => true,
                'message' => 'Analytics retrieved successfully',
                'analytics' => [
                    'timeframe' => $timeframe ?? 'all',
                    'attempts' => [
                        'total_attempts' => (int) $attemptStats['total_attempts'],
                        'unique_users' => (int) $attemptStats['unique_users'],
                        'completion_rate' => $completionRate,
                        'average_score' => round((float) $attemptStats['average_score'], 2),
                        'highest_score' => (int) $attemptStats['highest_score'],
                        'lowest_score' => (int) $attemptStats['lowest_score']
                    ],
                    'time_distribution' => [
                        'average_time_seconds' => round((float) $timeStats['average_time'], 2),
                        'fastest_time_seconds' => (int) $timeStats['fastest_time'],
                        'slowest_time_seconds' => (int) $timeStats['slowest_time']
                    ],
                    'popular_questions' => [
                        'most_missed' => $missedQuestions
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting quiz analytics', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to retrieve analytics',
                'analytics' => []
            ];
        }
    }

    /**
     * Validate quiz icon upload
     * @param array $uploadedFile Uploaded file data
     * @return array Validation result
     */
    private function validateQuizIcon(array $uploadedFile): array
    {
        $errors = [];

        // Validate file size (max 2MB)
        $maxSize = 2097152; // 2MB
        if ($uploadedFile['size'] > $maxSize) {
            $errors[] = 'Quiz icon must not exceed 2MB';
        }

        // Validate MIME type (images only)
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            $errors[] = 'Quiz icon must be an image (JPG, PNG, GIF, or WebP)';
        }

        // Validate file extension
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'Quiz icon must have valid image extension';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate question file upload
     * @param array $uploadedFile Uploaded file data
     * @return array Validation result
     */
    private function validateQuestionFile(array $uploadedFile): array
    {
        $errors = [];

        // Validate file size (max 10MB)
        $maxSize = 10485760; // 10MB
        if ($uploadedFile['size'] > $maxSize) {
            $errors[] = 'Question file must not exceed 10MB';
        }

        // Validate MIME type
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac',
            'video/mp4', 'video/webm'
        ];
        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            $errors[] = 'Question file must be an image, audio, or video file';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate choice file upload
     * @param array $uploadedFile Uploaded file data
     * @param string $choice Choice letter
     * @return array Validation result
     */
    private function validateChoiceFile(array $uploadedFile, string $choice): array
    {
        $errors = [];

        // Validate file size (max 5MB)
        $maxSize = 5242880; // 5MB
        if ($uploadedFile['size'] > $maxSize) {
            $errors[] = "Choice {$choice} file must not exceed 5MB";
        }

        // Validate MIME type (images and audio only)
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac'
        ];
        if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
            $errors[] = "Choice {$choice} file must be an image or audio file";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate word formatting JSON structure
     * @param string $jsonString JSON string
     * @return bool True if valid, false otherwise
     */
    private function isValidWordFormattingJSON(string $jsonString): bool
    {
        $decoded = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (!is_array($decoded)) {
            return false;
        }

        $validKeys = ['bold', 'underline', 'italic'];
        foreach (array_keys($decoded) as $key) {
            if (!in_array($key, $validKeys)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize quiz set data
     * @param array $data Quiz set data
     * @return array Sanitized data
     */
    private function sanitizeQuizSetData(array $data): array
    {
        $sanitized = [
            'name' => trim($data['name']),
            'folder_id' => (int) $data['folder_id']
        ];

        if (isset($data['description'])) {
            $sanitized['description'] = trim($data['description']);
        }

        if (isset($data['duration_minutes'])) {
            $sanitized['duration_minutes'] = (int) $data['duration_minutes'];
        }

        if (isset($data['passing_score'])) {
            $sanitized['passing_score'] = (int) $data['passing_score'];
        }

        if (isset($data['access_type'])) {
            $sanitized['access_type'] = $data['access_type'];
        }

        if (isset($data['icon']) && is_uploaded_file($data['icon'])) {
            $sanitized['icon'] = $data['icon'];
        }

        return $sanitized;
    }

    /**
     * Sanitize question data
     * @param array $data Question data
     * @return array Sanitized data
     */
    private function sanitizeQuestionData(array $data): array
    {
        $sanitized = [
            'quiz_set_id' => (int) $data['quiz_set_id'],
            'question' => trim($data['question']),
            'correct_answer' => strtoupper($data['correct_answer'])
        ];

        if (isset($data['optional_text'])) {
            $sanitized['optional_text'] = trim($data['optional_text']);
        }

        if (isset($data['question_type'])) {
            $sanitized['question_type'] = $data['question_type'];
        }

        if (isset($data['question_word_formatting'])) {
            $sanitized['question_word_formatting'] = json_decode($data['question_word_formatting'], true);
        }

        if (isset($data['optional_word_formatting'])) {
            $sanitized['optional_word_formatting'] = json_decode($data['optional_word_formatting'], true);
        }

        $choiceTextFields = ['choice_A_text', 'choice_B_text', 'choice_C_text', 'choice_D_text'];
        foreach ($choiceTextFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = trim($data[$field]);
            }
        }

        $choiceFileFields = ['question_file', 'choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'];
        foreach ($choiceFileFields as $field) {
            if (isset($data[$field]) && is_uploaded_file($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        return $sanitized;
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