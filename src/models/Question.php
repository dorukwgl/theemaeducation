<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Utils\ImageProcessor;
use EMA\Config\Constants;

class Question
{
    /**
     * Find question by ID
     * @param int $id Question ID
     * @return array|null Question details or null if not found
     */
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT q.id, q.quiz_set_id, q.question, q.optional_text,
                       q.correct_answer, q.question_type, q.question_word_formatting,
                       q.optional_word_formatting,
                       choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime,
                       choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime,
                       choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime,
                       choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime,
                       qs.name as quiz_set_name, qs.access_type as quiz_set_access_type
                FROM questions q
                LEFT JOIN quiz_sets qs ON q.quiz_set_id = qs.id
                WHERE q.id = ? LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $question = $result->fetch_assoc();
            $stmt->close();

            $questionData = [
                'id' => (int) $question['id'],
                'quiz_set_id' => (int) $question['quiz_set_id'],
                'question' => $question['question'],
                'optional_text' => $question['optional_text'],
                'correct_answer' => $question['correct_answer'],
                'question_type' => $question['question_type'],
                'question_word_formatting' => json_decode($question['question_word_formatting'], true),
                'optional_word_formatting' => json_decode($question['optional_word_formatting'], true),
                'question_file' => $question['question_file'] ?? null,
                'question_file_type' => $question['question_file_type'] ?? null,
                'question_file_mime' => $question['question_file_mime'] ?? null,
                'choice_A' => [
                    'text' => $question['choice_A_text'],
                    'file' => $question['choice_A_file'],
                    'file_type' => $question['choice_A_file_type'],
                    'file_mime' => $question['choice_A_file_mime']
                ],
                'choice_A_file' => $question['choice_A_file'],
                'choice_A_file_type' => $question['choice_A_file_type'],
                'choice_A_file_mime' => $question['choice_A_file_mime'],
                'choice_B' => [
                    'text' => $question['choice_B_text'],
                    'file' => $question['choice_B_file'],
                    'file_type' => $question['choice_B_file_type'],
                    'file_mime' => $question['choice_B_file_mime']
                ],
                'choice_B_file' => $question['choice_B_file'],
                'choice_B_file_type' => $question['choice_B_file_type'],
                'choice_B_file_mime' => $question['choice_B_file_mime'],
                'choice_C' => [
                    'text' => $question['choice_C_text'],
                    'file' => $question['choice_C_file'],
                    'file_type' => $question['choice_C_file_type'],
                    'file_mime' => $question['choice_C_file_mime']
                ],
                'choice_C_file' => $question['choice_C_file'],
                'choice_C_file_type' => $question['choice_C_file_type'],
                'choice_C_file_mime' => $question['choice_C_file_mime'],
                'choice_D' => [
                    'text' => $question['choice_D_text'],
                    'file' => $question['choice_D_file'],
                    'file_type' => $question['choice_D_file_type'],
                    'file_mime' => $question['choice_D_file_mime']
                ],
                'choice_D_file' => $question['choice_D_file'],
                'choice_D_file_type' => $question['choice_D_file_type'],
                'choice_D_file_mime' => $question['choice_D_file_mime'],
                'quiz_set_name' => $question['quiz_set_name'],
                'quiz_set_access_type' => $question['quiz_set_access_type']
            ];

            return $questionData;
        } catch (\Exception $e) {
            Logger::error('Error finding question by ID', [
                'question_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find questions by quiz set ID
     * @param int $quizSetId Quiz set ID
     * @return array Array of questions
     */
    public static function findByQuizSetId(int $quizSetId): array
    {
        try {
            $query = "
                SELECT q.id, q.quiz_set_id, q.question, q.optional_text,
                       q.correct_answer, q.question_type, q.question_word_formatting,
                       q.optional_word_formatting,
                       q.question_file, q.question_file_type, q.question_file_mime,
                       choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime,
                       choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime,
                       choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime,
                       choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime
                FROM questions q
                WHERE q.quiz_set_id = ?
                ORDER BY q.id ASC
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $quizSetId);
            $stmt->execute();
            $result = $stmt->get_result();

            $questions = [];
            while ($row = $result->fetch_assoc()) {
                $questions[] = [
                    'id' => (int) $row['id'],
                    'quiz_set_id' => (int) $row['quiz_set_id'],
                    'question' => $row['question'],
                    'optional_text' => $row['optional_text'],
                    'correct_answer' => $row['correct_answer'],
                    'question_type' => $row['question_type'],
                    'question_word_formatting' => json_decode($row['question_word_formatting'], true),
                    'optional_word_formatting' => json_decode($row['optional_word_formatting'], true),
                    'question_file' => $row['question_file'],
                    'question_file_type' => $row['question_file_type'],
                    'question_file_mime' => $row['question_file_mime'],
                    'choice_A' => [
                        'text' => $row['choice_A_text'],
                        'file' => $row['choice_A_file'],
                        'file_type' => $row['choice_A_file_type'],
                        'file_mime' => $row['choice_A_file_mime']
                    ],
                    'choice_B' => [
                        'text' => $row['choice_B_text'],
                        'file' => $row['choice_B_file'],
                        'file_type' => $row['choice_B_file_type'],
                        'file_mime' => $row['choice_B_file_mime']
                    ],
                    'choice_C' => [
                        'text' => $row['choice_C_text'],
                        'file' => $row['choice_C_file'],
                        'file_type' => $row['choice_C_file_type'],
                        'file_mime' => $row['choice_C_file_mime']
                    ],
                    'choice_D' => [
                        'text' => $row['choice_D_text'],
                        'file' => $row['choice_D_file'],
                        'file_type' => $row['choice_D_file_type'],
                        'file_mime' => $row['choice_D_file_mime']
                    ]
                ];
            }

            $stmt->close();

            return $questions;
        } catch (\Exception $e) {
            Logger::error('Error finding questions by quiz set ID', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create question
     * @param array $data Question data with multimedia support
     * @return int|false New question ID or false on failure
     */
    public static function create(array $data): int|false
    {
        try {
            // Validate required fields
            if (!isset($data['quiz_set_id']) || !isset($data['question'])) {
                return false;
            }

            $quizSetId = (int) $data['quiz_set_id'];
            $question = trim($data['question']);
            $optionalText = $data['optional_text'] ?? null;
            $correctAnswer = strtoupper($data['correct_answer']);
            $questionType = $data['question_type'] ?? 'reading';
            $questionWordFormatting = isset($data['question_word_formatting']) ? json_encode($data['question_word_formatting']) : json_encode([]);
            $optionalWordFormatting = isset($data['optional_word_formatting']) ? json_encode($data['optional_word_formatting']) : json_encode([]);
            $choiceAText = $data['choice_A_text'] ?? null;
            $choiceBText = $data['choice_B_text'] ?? null;
            $choiceCText = $data['choice_C_text'] ?? null;
            $choiceDText = $data['choice_D_text'] ?? null;
            $choiceAWordFormatting = isset($data['choice_A_word_formatting']) ? json_encode($data['choice_A_word_formatting']) : json_encode([]);
            $choiceBWordFormatting = isset($data['choice_B_word_formatting']) ? json_encode($data['choice_B_word_formatting']) : json_encode([]);
            $choiceCWordFormatting = isset($data['choice_C_word_formatting']) ? json_encode($data['choice_C_word_formatting']) : json_encode([]);
            $choiceDWordFormatting = isset($data['choice_D_word_formatting']) ? json_encode($data['choice_D_word_formatting']) : json_encode([]);

            // Validate question exists
            $quizSet = QuizSet::findById($quizSetId);
            if (!$quizSet) {
                return false;
            }

            // Validate correct_answer
            if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                return false;
            }

            // Validate question_type
            if (!in_array($questionType, ['reading', 'listening'])) {
                return false;
            }

            // Handle question file upload
            $questionFile = null;
            $questionFileType = null;
            $questionFileMime = null;
            if (isset($data['question_file']) && is_array($data['question_file']) && isset($data['question_file']['tmp_name'])) {
                $questionUpload = self::handleQuestionFileUpload($data['question_file']);
                if (!$questionUpload['success']) {
                    return false;
                }
                $questionFile = $questionUpload['file_path'];
                $questionFileType = $questionUpload['file_type'];
                $questionFileMime = $questionUpload['file_mime'];
            }

            // Handle choice A file upload
            $choiceAFile = null;
            $choiceAFileType = null;
            $choiceAFileMime = null;
            if (isset($data['choice_A_file']) && is_array($data['choice_A_file']) && isset($data['choice_A_file']['tmp_name'])) {
                $choiceAUpload = self::handleChoiceFileUpload($data['choice_A_file'], 'A');
                if (!$choiceAUpload['success']) {
                    return false;
                }
                $choiceAFile = $choiceAUpload['file_path'];
                $choiceAFileType = $choiceAUpload['file_type'];
                $choiceAFileMime = $choiceAUpload['file_mime'];
            }

            // Handle choice B file upload
            $choiceBFile = null;
            $choiceBFileType = null;
            $choiceBFileMime = null;
            if (isset($data['choice_B_file']) && is_array($data['choice_B_file']) && isset($data['choice_B_file']['tmp_name'])) {
                $choiceBUpload = self::handleChoiceFileUpload($data['choice_B_file'], 'B');
                if (!$choiceBUpload['success']) {
                    return false;
                }
                $choiceBFile = $choiceBUpload['file_path'];
                $choiceBFileType = $choiceBUpload['file_type'];
                $choiceBFileMime = $choiceBUpload['file_mime'];
            }

            // Handle choice C file upload
            $choiceCFile = null;
            $choiceCFileType = null;
            $choiceCFileMime = null;
            if (isset($data['choice_C_file']) && is_array($data['choice_C_file']) && isset($data['choice_C_file']['tmp_name'])) {
                $choiceCUpload = self::handleChoiceFileUpload($data['choice_C_file'], 'C');
                if (!$choiceCUpload['success']) {
                    return false;
                }
                $choiceCFile = $choiceCUpload['file_path'];
                $choiceCFileType = $choiceCUpload['file_type'];
                $choiceCFileMime = $choiceCUpload['file_mime'];
            }

            // Handle choice D file upload
            $choiceDFile = null;
            $choiceDFileType = null;
            $choiceDFileMime = null;
            if (isset($data['choice_D_file']) && is_array($data['choice_D_file']) && isset($data['choice_D_file']['tmp_name'])) {
                $choiceDUpload = self::handleChoiceFileUpload($data['choice_D_file'], 'D');
                if (!$choiceDUpload['success']) {
                    return false;
                }
                $choiceDFile = $choiceDUpload['file_path'];
                $choiceDFileType = $choiceDUpload['file_type'];
                $choiceDFileMime = $choiceDUpload['file_mime'];
            }

            // Insert question
            $query = "
                INSERT INTO questions (
                    quiz_set_id, question, optional_text,
                    correct_answer, question_type, question_word_formatting, optional_word_formatting,
                    question_file, question_file_type, question_file_mime,
                    choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime,
                    choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime,
                    choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime,
                    choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime,
                    choice_A_word_formatting, choice_B_word_formatting, choice_C_word_formatting, choice_D_word_formatting
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param(
                'isssssssssssssssssssssssssssss',
                $quizSetId, $question, $optionalText,
                $correctAnswer, $questionType, $questionWordFormatting, $optionalWordFormatting,
                $questionFile, $questionFileType, $questionFileMime,
                $choiceAText, $choiceAFile, $choiceAFileType, $choiceAFileMime,
                $choiceBText, $choiceBFile, $choiceBFileType, $choiceBFileMime,
                $choiceCText, $choiceCFile, $choiceCFileType, $choiceCFileMime,
                $choiceDText, $choiceDFile, $choiceDFileType, $choiceDFileMime,
                $choiceAWordFormatting, $choiceBWordFormatting, $choiceCWordFormatting, $choiceDWordFormatting
            );

            if ($stmt->execute()) {
                $questionId = $stmt->insert_id;
                $stmt->close();

                // Update quiz set question count
                self::updateQuizSetQuestionCount($quizSetId);

                return $questionId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating question', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update question
     * @param int $id Question ID
     * @param array $data Update data
     * @return bool true if successful, false otherwise
     */
    public static function update(int $id, array $data): bool
    {
        try {
            // Check if question exists
            $question = self::findById($id);
            if (!$question) {
                return false;
            }

            $updates = [];
            $types = '';
            $params = [];

            // Handle question update
            if (isset($data['question']) && !empty(trim($data['question']))) {
                $updates[] = 'question = ?';
                $types .= 's';
                $params[] = trim($data['question']);
            }

            // Handle optional_text update
            if (isset($data['optional_text'])) {
                $updates[] = 'optional_text = ?';
                $types .= 's';
                $params[] = $data['optional_text'];
            }

            // Handle correct_answer update
            if (isset($data['correct_answer'])) {
                $correctAnswer = strtoupper($data['correct_answer']);
                if (!in_array($correctAnswer, ['A', 'B', 'C', 'D'])) {
                    return false;
                }

                $updates[] = 'correct_answer = ?';
                $types .= 's';
                $params[] = $correctAnswer;
            }

            // Handle question_type update
            if (isset($data['question_type'])) {
                $questionType = $data['question_type'];
                if (!in_array($questionType, ['reading', 'listening'])) {
                    return false;
                }

                $updates[] = 'question_type = ?';
                $types .= 's';
                $params[] = $questionType;
            }

            // Handle question_word_formatting update
            if (isset($data['question_word_formatting'])) {
                $questionWordFormatting = json_encode($data['question_word_formatting']);
                $updates[] = 'question_word_formatting = ?';
                $types .= 's';
                $params[] = $questionWordFormatting;
            }

            // Handle optional_word_formatting update
            if (isset($data['optional_word_formatting'])) {
                $optionalWordFormatting = json_encode($data['optional_word_formatting']);
                $updates[] = 'optional_word_formatting = ?';
                $types .= 's';
                $params[] = $optionalWordFormatting;
            }

            // Handle file replacements with cleanup
            if (isset($data['question_file']) && is_array($data['question_file']) && isset($data['question_file']['tmp_name'])) {
                $questionUpload = self::handleQuestionFileUpload($data['question_file']);
                if (!$questionUpload['success']) {
                    return false;
                }

                // Delete old file if exists (handle both legacy and new path formats)
                $oldPath = $question['question_file'] ?? null;
                if ($oldPath) {
                    $prefix = strpos($oldPath, 'uploads/') === 0 ? '' : 'uploads/';
                    $fullPath = ROOT_PATH . '/' . $prefix . $oldPath;
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }

                $updates[] = 'question_file = ?';
                $updates[] = 'question_file_type = ?';
                $updates[] = 'question_file_mime = ?';
                $types .= 'sss';
                $params[] = $questionUpload['file_path'];
                $params[] = $questionUpload['file_type'];
                $params[] = $questionUpload['file_mime'];
            }

            // Handle choice file replacements (A, B, C, D)
            $choiceFields = ['A', 'B', 'C', 'D'];
            foreach ($choiceFields as $choice) {
                $fileKey = 'choice_' . $choice . '_file';

                if (isset($data[$fileKey]) && is_array($data[$fileKey]) && isset($data[$fileKey]['tmp_name'])) {
                    $choiceUpload = self::handleChoiceFileUpload($data[$fileKey], $choice);
                    if (!$choiceUpload['success']) {
                        return false;
                    }

                    // Delete old file if exists (handle both legacy and new path formats)
                    $oldPath = $question[$fileKey] ?? null;
                    if ($oldPath) {
                        $prefix = strpos($oldPath, 'uploads/') === 0 ? '' : 'uploads/';
                        $fullPath = ROOT_PATH . '/' . $prefix . $oldPath;
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }

                    $typeKey = 'choice_' . $choice . '_file_type';
                    $mimeKey = 'choice_' . $choice . '_file_mime';
                    $updates[] = $fileKey . ' = ?';
                    $updates[] = $typeKey . ' = ?';
                    $updates[] = $mimeKey . ' = ?';
                    $types .= 'sss';
                    $params[] = $choiceUpload['file_path'];
                    $params[] = $choiceUpload['file_type'];
                    $params[] = $choiceUpload['file_mime'];
                }
            }

            if (empty($updates)) {
                return false;
            }

            // Build and execute query
            $query = "UPDATE questions SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating question', [
                'question_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete question with backup and cleanup
     * @param int $id Question ID
     * @return bool true if successful, false otherwise
     */
    public static function delete(int $id): bool
    {
        try {
            // Check if question exists
            $question = self::findById($id);
            if (!$question) {
                return false;
            }

            // Backup question to questions_backup table
            $backupQuery = "
                INSERT INTO questions_backup (
                    quiz_set_id, question, optional_text,
                    correct_answer, question_type, question_word_formatting, optional_word_formatting,
                    question_file, question_file_type, question_file_mime,
                    choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime,
                    choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime,
                    choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime,
                    choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime
                )
                SELECT quiz_set_id, question, optional_text,
                       correct_answer, question_type, question_word_formatting, optional_word_formatting,
                       question_file, question_file_type, question_file_mime,
                       choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime,
                       choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime,
                       choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime,
                       choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime
                FROM questions WHERE id = ?
            ";
            $backupStmt = \EMA\Config\Database::prepare($backupQuery);
            $backupStmt->bind_param('i', $id);
            $backupStmt->execute();
            $backupStmt->close();

            // Delete physical files (handle both legacy and new path formats)
            $fileFields = ['question_file', 'choice_A_file', 'choice_B_file', 'choice_C_file', 'choice_D_file'];
            foreach ($fileFields as $field) {
                $oldPath = $question[$field] ?? null;
                if ($oldPath) {
                    $prefix = strpos($oldPath, 'uploads/') === 0 ? '' : 'uploads/';
                    $fullPath = ROOT_PATH . '/' . $prefix . $oldPath;
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }

            // Delete question record (cascade will handle quiz_results)
            $deleteQuery = "DELETE FROM questions WHERE id = ?";
            $deleteStmt = \EMA\Config\Database::prepare($deleteQuery);
            $deleteStmt->bind_param('i', $id);
            $result = $deleteStmt->execute();
            $deleteStmt->close();

            if ($result) {
                // Update quiz set question count
                $quizSetId = $question['quiz_set_id'];
                self::updateQuizSetQuestionCount($quizSetId);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error deleting question', [
                'question_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Batch create questions
     * @param array $questionsData Array of question data for bulk creation
     * @return array Result with success count, failure count, errors
     */
    public static function batchCreate(array $questionsData): array
    {
        try {
            // Validate quiz set IDs exist
            $quizSetIds = array_unique(array_column($questionsData, 'quiz_set_id'));
            foreach ($quizSetIds as $quizSetId) {
                $quizSet = QuizSet::findById((int) $quizSetId);
                if (!$quizSet) {
                    return [
                        'success' => false,
                        'message' => 'Quiz set not found',
                        'success_count' => 0,
                        'failure_count' => count($questionsData),
                        'errors' => ["Quiz set {$quizSetId} not found"]
                    ];
                }
            }

            // Validate data limit (max 20 per batch)
            if (count($questionsData) > 20) {
                return [
                    'success' => false,
                    'message' => 'Maximum 20 questions allowed per batch',
                    'success_count' => 0,
                    'failure_count' => count($questionsData),
                    'errors' => ['Batch size limit exceeded']
                ];
            }

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            try {
                foreach ($questionsData as $index => $data) {
                    $questionId = self::create($data);

                    if ($questionId) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $errors[] = "Question {$index}: Creation failed";
                    }
                }

                // Update quiz set question count after each successful creation
                if ($questionId) {
                    self::updateQuizSetQuestionCount((int) $data['quiz_set_id']);
                }

                \EMA\Config\Database::commit();

                return [
                    'success' => true,
                    'message' => "Batch creation completed: {$successCount} succeeded, {$failureCount} failed",
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'errors' => $errors
                ];
            } catch (\Exception $e) {
                \EMA\Config\Database::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Logger::error('Error in batch question creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Batch creation failed',
                'success_count' => 0,
                'failure_count' => count($questionsData),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Handle question file upload
     * @param array $uploadedFile Uploaded file data
     * @return array Upload result with success flag
     */
    private static function handleQuestionFileUpload(array $uploadedFile): array
    {
        try {
            $result = ['success' => false, 'file_path' => null, 'file_type' => null, 'file_mime' => null];

            // Validate file size (max 10MB)
            $maxSize = 10485760; // 10MB
            if ($uploadedFile['size'] > $maxSize) {
                return $result;
            }

            // Validate MIME type
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac',
                'video/mp4', 'video/webm',
                'application/pdf'
            ];

            if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
                return $result;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'aac', 'mp4', 'webm', 'pdf'];

            if (!in_array($extension, $allowedExtensions)) {
                return $result;
            }

            // Generate secure filename using UUID
            $secureFilename = 'question_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $fullFilePath = ROOT_PATH . '/' . Constants::PATH_QUESTIONS . '/' . $secureFilename; // absolute path for filesystem
            $dbFilePath = 'questions/' . $secureFilename; // for DB storage (without uploads/ prefix)

            // Determine if this is an image file
            $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $isImage = in_array($uploadedFile['type'], $imageMimeTypes);

            // Process image files with compression, move others as-is
            if ($isImage) {
                $result['success'] = ImageProcessor::compressImage($uploadedFile['tmp_name'], $fullFilePath, 100);

                if ($result['success']) {
                    $result['file_path'] = $dbFilePath;
                    $result['file_type'] = $extension;
                    $result['file_mime'] = $uploadedFile['type'];
                } else {
                    Logger::error('Failed to compress question image', [
                        'tmp_name' => $uploadedFile['tmp_name'],
                        'destination' => $fullFilePath
                    ]);
                }
            } else {
                // Move non-image files (audio, video) directly
                if (move_uploaded_file($uploadedFile['tmp_name'], $fullFilePath)) {
                    chmod($fullFilePath, 0644);

                    $result['success'] = true;
                    $result['file_path'] = $dbFilePath;
                    $result['file_type'] = $extension;
                    $result['file_mime'] = $uploadedFile['type'];

                } else {
                    Logger::error('Failed to move uploaded question file', [
                        'tmp_name' => $uploadedFile['tmp_name'],
                        'destination' => $fullFilePath
                    ]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error handling question file upload', [
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'file_path' => null, 'file_type' => null, 'file_mime' => null];
        }
    }

    /**
     * Handle choice file upload
     * @param array $uploadedFile Uploaded file data
     * @param string $choice Choice letter (A, B, C, D)
     * @return array Upload result with success flag
     */
    private static function handleChoiceFileUpload(array $uploadedFile, string $choice): array
    {
        try {
            $result = ['success' => false, 'file_path' => null, 'file_type' => null, 'file_mime' => null];

            // Validate file size (max 5MB)
            $maxSize = 5242880; // 5MB
            if ($uploadedFile['size'] > $maxSize) {
                return $result;
            }

            // Validate MIME type (images and audio only for choices)
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac'
            ];

            if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
                return $result;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'aac'];

            if (!in_array($extension, $allowedExtensions)) {
                return $result;
            }

            // Generate secure filename using UUID with choice
            $secureFilename = 'choice_' . $choice . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $fullFilePath = ROOT_PATH . '/' . Constants::PATH_CHOICES . '/' . $secureFilename; // absolute path for filesystem
            $dbFilePath = 'choices/' . $secureFilename; // for DB storage (without uploads/ prefix)

            // Determine if this is an image file
            $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $isImage = in_array($uploadedFile['type'], $imageMimeTypes);

            // Process image files with compression, move others as-is
            if ($isImage) {
                $result['success'] = ImageProcessor::compressImage($uploadedFile['tmp_name'], $fullFilePath, 100);

                if ($result['success']) {
                    $result['file_path'] = $dbFilePath;
                    $result['file_type'] = $extension;
                    $result['file_mime'] = $uploadedFile['type'];
                } else {
                    Logger::error('Failed to compress choice image', [
                        'choice' => $choice,
                        'tmp_name' => $uploadedFile['tmp_name'],
                        'destination' => $fullFilePath
                    ]);
                }
            } else {
                // Move non-image files (audio) directly
                if (move_uploaded_file($uploadedFile['tmp_name'], $fullFilePath)) {
                    chmod($fullFilePath, 0644);

                    $result['success'] = true;
                    $result['file_path'] = $dbFilePath;
                    $result['file_type'] = $extension;
                    $result['file_mime'] = $uploadedFile['type'];
                } else {
                    Logger::error('Failed to move uploaded choice file', [
                        'choice' => $choice,
                        'tmp_name' => $uploadedFile['tmp_name'],
                        'destination' => $fullFilePath
                    ]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error handling choice file upload', [
                'choice' => $choice,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'file_path' => null, 'file_type' => null, 'file_mime' => null];
        }
    }

    /**
     * Update quiz set question count
     * @param int $quizSetId Quiz set ID
     */
    private static function updateQuizSetQuestionCount(int $quizSetId): void
    {
        try {
            $query = "
                UPDATE quiz_sets qs
                SET total_questions = (
                    SELECT COUNT(*)
                    FROM questions q
                    WHERE q.quiz_set_id = ?
                ),
                question_count = (
                    SELECT COUNT(*)
                    FROM questions q
                    WHERE q.quiz_set_id = ? AND q.id <= (
                        SELECT COALESCE(MAX(id), 0)
                        FROM questions
                        WHERE quiz_set_id = ?
                    )
                )
                WHERE qs.id = ?
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('iiii', $quizSetId, $quizSetId, $quizSetId, $quizSetId);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set question count', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
