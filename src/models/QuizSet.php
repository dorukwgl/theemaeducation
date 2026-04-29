<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;

class QuizSet
{
    /**
     * Find quiz set by ID with folder details
     * @param int $id Quiz set ID
     * @return array|null Quiz set details or null if not found
     */
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.updated_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                WHERE qs.id = ? LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $quizSet = $result->fetch_assoc();
            $stmt->close();

            $quizSetData = [
                'id' => (int) $quizSet['id'],
                'folder_id' => (int) $quizSet['folder_id'],
                'name' => $quizSet['name'],
                'description' => $quizSet['description'],
                'icon_path' => $quizSet['icon_path'],
                'access_type' => $quizSet['access_type'],
                'question_count' => (int) $quizSet['question_count'],
                'total_questions' => (int) $quizSet['total_questions'],
                'duration_minutes' => (int) $quizSet['duration_minutes'],
                'passing_score' => (int) $quizSet['passing_score'],
                'is_published' => (bool) $quizSet['is_published'],
                'created_by' => $quizSet['created_by'],
                'updated_at' => $quizSet['updated_at'],
                'folder_name' => $quizSet['folder_name'],
                'folder_icon_path' => $quizSet['folder_icon_path']
            ];

            Logger::info('Quiz set found by ID', ['quiz_set_id' => $id]);

            return $quizSetData;
        } catch (\Exception $e) {
            Logger::error('Error finding quiz set by ID', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create quiz set
     * @param array $data Quiz set data
     * @return int|false New quiz set ID or false on failure
     */
    public static function create(array $data): int|false
    {
        try {
            // Validate required fields
            if (!isset($data['folder_id']) || !isset($data['name'])) {
                Logger::warning('Quiz set creation failed: Missing required fields', ['data' => $data]);
                return false;
            }

            $folderId = (int) $data['folder_id'];
            $name = trim($data['name']);
            $description = $data['description'] ?? null;
            $iconPath = $data['icon_path'] ?? null;
            $accessType = $data['access_type'] ?? 'logged_in';
            $durationMinutes = (int) ($data['duration_minutes'] ?? 0);
            $passingScore = (int) ($data['passing_score'] ?? 70);
            $createdBy = $data['created_by'] ?? null;

            // Validate folder exists
            $folder = Folder::findById($folderId);
            if (!$folder) {
                Logger::warning('Quiz set creation failed: Folder not found', ['folder_id' => $folderId]);
                return false;
            }

            // Validate access_type
            if (!in_array($accessType, ['all', 'logged_in'])) {
                Logger::warning('Quiz set creation failed: Invalid access type', ['access_type' => $accessType]);
                return false;
            }

            // Insert quiz set
            $query = "INSERT INTO quiz_sets (folder_id, name, description, icon_path, access_type, duration_minutes, passing_score, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('isssisii', $folderId, $name, $description, $iconPath, $accessType, $durationMinutes, $passingScore, $createdBy);

            if ($stmt->execute()) {
                $quizSetId = $stmt->insert_id;
                $stmt->close();

                Logger::info('Quiz set created successfully', [
                    'quiz_set_id' => $quizSetId,
                    'folder_id' => $folderId,
                    'name' => $name,
                    'access_type' => $accessType
                ]);

                return $quizSetId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating quiz set', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update quiz set
     * @param int $id Quiz set ID
     * @param array $data Update data
     * @return bool true if successful, false otherwise
     */
    public static function update(int $id, array $data): bool
    {
        try {
            // Check if quiz set exists
            $quizSet = self::findById($id);
            if (!$quizSet) {
                Logger::warning('Quiz set update failed: Quiz set not found', ['quiz_set_id' => $id]);
                return false;
            }

            $updates = [];
            $types = '';
            $params = [];

            // Handle name update
            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updates[] = 'name = ?';
                $types .= 's';
                $params[] = trim($data['name']);
            }

            // Handle description update
            if (isset($data['description'])) {
                $updates[] = 'description = ?';
                $types .= 's';
                $params[] = $data['description'];
            }

            // Handle icon_path update
            if (isset($data['icon_path'])) {
                // Delete old icon if exists
                if ($quizSet['icon_path'] && file_exists(ROOT_PATH . '/' . $quizSet['icon_path'])) {
                    unlink(ROOT_PATH . '/' . $quizSet['icon_path']);
                    Logger::info('Old quiz set icon deleted', [
                        'quiz_set_id' => $id,
                        'old_icon_path' => $quizSet['icon_path']
                    ]);
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $data['icon_path'];
            }

            // Handle access_type update
            if (isset($data['access_type'])) {
                $accessType = $data['access_type'];
                if (!in_array($accessType, ['all', 'logged_in'])) {
                    Logger::warning('Quiz set update failed: Invalid access type', ['access_type' => $accessType]);
                    return false;
                }

                $updates[] = 'access_type = ?';
                $types .= 's';
                $params[] = $accessType;
            }

            // Handle duration_minutes update
            if (isset($data['duration_minutes'])) {
                $updates[] = 'duration_minutes = ?';
                $types .= 'i';
                $params[] = (int) $data['duration_minutes'];
            }

            // Handle passing_score update
            if (isset($data['passing_score'])) {
                $updates[] = 'passing_score = ?';
                $types .= 'i';
                $params[] = (int) $data['passing_score'];
            }

            // Handle is_published update
            if (isset($data['is_published'])) {
                $updates[] = 'is_published = ?';
                $types .= 'i';
                $params[] = (bool) $data['is_published'] ? 1 : 0;
            }

            if (empty($updates)) {
                Logger::warning('Quiz set update failed: No valid fields to update');
                return false;
            }

            // Build and execute query
            $query = "UPDATE quiz_sets SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $stmt->close();

                Logger::info('Quiz set updated successfully', [
                    'quiz_set_id' => $id,
                    'updates' => array_keys($data)
                ]);

                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set', [
                'quiz_set_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete quiz set with cascade cleanup
     * @param int $id Quiz set ID
     * @return bool true if successful, false otherwise
     */
    public static function delete(int $id): bool
    {
        try {
            // Check if quiz set exists
            $quizSet = self::findById($id);
            if (!$quizSet) {
                Logger::warning('Quiz set deletion failed: Quiz set not found', ['quiz_set_id' => $id]);
                return false;
            }

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            try {
                // Delete access permissions
                $accessQuery = "DELETE FROM access_permissions WHERE item_id = ? AND item_type = 'quiz_set'";
                $accessStmt = \EMA\Config\Database::prepare($accessQuery);
                $accessStmt->bind_param('i', $id);
                $accessStmt->execute();
                $accessStmt->close();

                // Delete quiz activities
                $activityQuery = "DELETE FROM quiz_activity WHERE quiz_set_id = ?";
                $activityStmt = \EMA\Config\Database::prepare($activityQuery);
                $activityStmt->bind_param('i', $id);
                $activityStmt->execute();
                $activityStmt->close();

                // Delete quiz attempts
                $attemptsQuery = "DELETE FROM quiz_attempts WHERE quiz_set_id = ?";
                $attemptsStmt = \EMA\Config\Database::prepare($attemptsQuery);
                $attemptsStmt->bind_param('i', $id);
                $attemptsStmt->execute();
                $attemptsStmt->close();

                // Delete quiz results (will cascade via foreign key)

                // Delete icon file if exists
                if ($quizSet['icon_path'] && file_exists(ROOT_PATH . '/' . $quizSet['icon_path'])) {
                    unlink(ROOT_PATH . '/' . $quizSet['icon_path']);
                    Logger::info('Quiz set icon deleted', [
                        'quiz_set_id' => $id,
                        'icon_path' => $quizSet['icon_path']
                    ]);
                }

                // Delete quiz set record
                $deleteQuizQuery = "DELETE FROM quiz_sets WHERE id = ?";
                $deleteQuizStmt = \EMA\Config\Database::prepare($deleteQuizQuery);
                $deleteQuizStmt->bind_param('i', $id);
                $result = $deleteQuizStmt->execute();
                $deleteQuizStmt->close();

                if ($result) {
                    \EMA\Config\Database::commit();

                    Logger::info('Quiz set deleted successfully', [
                        'quiz_set_id' => $id,
                        'name' => $quizSet['name']
                    ]);

                    return true;
                }

                throw new \Exception('Failed to delete quiz set record');
            } catch (\Exception $e) {
                \EMA\Config\Database::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting quiz set', [
                'quiz_set_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get quiz set questions with access filtering
     * @param int $quizSetId Quiz set ID
     * @param int|null $userId Optional user ID for access filtering
     * @return array Array of questions with access info
     */
    public static function getQuestions(int $quizSetId, ?int $userId = null): array
    {
        try {
            // Check if quiz set exists and is published
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                return [];
            }

            if (!$quizSet['is_published']) {
                Logger::warning('Quiz set not published', ['quiz_set_id' => $quizSetId]);
                return [];
            }

            // Get questions with optimized single query
            $query = "
                SELECT q.id, q.quiz_set_id, q.question, q.optional_text,
                       q.correct_answer, q.question_type, q.question_word_formatting,
                       q.optional_word_formatting,
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
                $questionData = [
                    'id' => (int) $row['id'],
                    'quiz_set_id' => (int) $row['quiz_set_id'],
                    'question' => $row['question'],
                    'optional_text' => $row['optional_text'],
                    'correct_answer' => $row['correct_answer'],
                    'question_type' => $row['question_type'],
                    'question_word_formatting' => json_decode($row['question_word_formatting'], true),
                    'optional_word_formatting' => json_decode($row['optional_word_formatting'], true),
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

                // Check access if userId provided and user is not admin
                if ($userId && !User::isAdminById($userId)) {
                    $hasAccess = Access::checkAccess($userId, $quizSetId, 'quiz_set');
                    $questionData['has_access'] = $hasAccess;
                } else {
                    $questionData['has_access'] = true;
                }

                $questions[] = $questionData;
            }

            $stmt->close();

            Logger::info('Quiz set questions retrieved', [
                'quiz_set_id' => $quizSetId,
                'question_count' => count($questions)
            ]);

            return $questions;
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set questions', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check quiz set access
     * @param int $userId User ID
     * @param int $quizSetId Quiz set ID
     * @return bool true if user has access, false otherwise
     */
    public static function checkQuizSetAccess(int $userId, int $quizSetId): bool
    {
        try {
            // Check if user is admin
            if (User::isAdminById($userId)) {
                return true;
            }

            // Get quiz set details
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                return false;
            }

            // Check if quiz set is published
            if (!$quizSet['is_published']) {
                return false;
            }

            // Check quiz set access_type
            $accessType = $quizSet['access_type'];

            // Public access
            if ($accessType === 'all') {
                return true;
            }

            // Logged-in access
            if ($accessType === 'logged_in') {
                // User must be authenticated (checked by caller)
                return true;
            }

            // Check individual permissions
            $hasAccess = Access::checkAccess($userId, $quizSetId, 'quiz_set');

            return $hasAccess;
        } catch (\Exception $e) {
            Logger::error('Error checking quiz set access', [
                'user_id' => $userId,
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all quiz sets with filtering and pagination
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Filter by folder ID
     * @param int|null $userId User ID for access filtering
     * @param bool $includeQuestionCount Include question counts
     * @param bool $publishedOnly Only published quiz sets
     * @return array Quiz sets
     */
    public static function getAllQuizSets(
        int $page,
        int $perPage,
        ?int $folderId = null,
        ?int $userId = null,
        bool $includeQuestionCount = false,
        bool $publishedOnly = true
    ): array {
        try {
            $conditions = [];
            $params = [];
            $types = '';

            // Add folder filter
            if ($folderId !== null) {
                $conditions[] = 'qs.folder_id = ?';
                $params[] = $folderId;
                $types .= 'i';
            }

            // Add published filter for non-admin users
            if ($publishedOnly && $userId !== null) {
                $conditions[] = 'qs.is_published = 1';
            }

            // Add access control filtering for non-admin users
            if ($userId !== null && !User::isAdminById($userId)) {
                $conditions[] = "(
                    qs.access_type = 'all'
                    OR qs.access_type = 'logged_in'
                    OR qs.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'quiz_set'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $params[] = $userId;
                $types .= 'i';
            }

            // Build WHERE clause
            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // Get quiz sets with pagination
            $offset = ($page - 1) * $perPage;
            $query = "
                SELECT qs.*,
                       fl.name as folder_name,
                       fl.icon_path as folder_icon_path";

            if ($includeQuestionCount) {
                $query .= ",
                       (SELECT COUNT(*) FROM questions WHERE quiz_set_id = qs.id) as question_count";
            }

            $query .= "
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                {$whereClause}
                ORDER BY qs.created_at DESC
                LIMIT ? OFFSET ?";

            $types .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $quizSets = [];
            while ($row = $result->fetch_assoc()) {
                $quizSetData = [
                    'id' => (int) $row['id'],
                    'folder_id' => (int) $row['folder_id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'icon_path' => $row['icon_path'],
                    'access_type' => $row['access_type'],
                    'question_count' => $includeQuestionCount ? (int) $row['question_count'] : null,
                    'total_questions' => (int) $row['total_questions'],
                    'duration_minutes' => (int) $row['duration_minutes'],
                    'passing_score' => (int) $row['passing_score'],
                    'is_published' => (bool) $row['is_published'],
                    'created_by' => $row['created_by'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
                $quizSets[] = $quizSetData;
            }

            $stmt->close();

            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM quiz_sets qs {$whereClause}";
            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countTypes = substr($types, 0, strlen($types) - 2);
            $countParams = array_slice($params, 0, count($params) - 2);
            $countStmt->bind_param($countTypes, ...$countParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            Logger::info('Quiz sets retrieved successfully', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'count' => count($quizSets),
                'user_id' => $userId,
                'admin_access' => $userId === null || User::isAdminById($userId)
            ]);

            return $quizSets;
        } catch (\Exception $e) {
            Logger::error('Error retrieving quiz sets', [
                'error' => $e->getMessage(),
                'page' => $page,
                'per_page' => $perPage,
                'user_id' => $userId
            ]);
            return [];
        }
    }

    /**
     * Get quiz set statistics
     * @param int $quizSetId Quiz set ID
     * @return array Quiz set statistics
     */
    public static function getQuizSetStats(int $quizSetId): array
    {
        try {
            // Get quiz set details
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                return [];
            }

            // Get total questions
            $questionQuery = "SELECT COUNT(*) as total_questions FROM questions WHERE quiz_set_id = ?";
            $questionStmt = \EMA\Config\Database::prepare($questionQuery);
            $questionStmt->bind_param('i', $quizSetId);
            $questionStmt->execute();
            $questionResult = $questionStmt->get_result();
            $totalQuestions = $questionResult->fetch_assoc()['total_questions'];
            $questionStmt->close();

            // Get total attempts
            $attemptsQuery = "
                SELECT COUNT(*) as total_attempts,
                       AVG(score) as average_score,
                       AVG(correct_answers) as average_correct,
                       COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completions
                FROM quiz_attempts
                WHERE quiz_set_id = ?
            ";
            $attemptsStmt = \EMA\Config\Database::prepare($attemptsQuery);
            $attemptsStmt->bind_param('i', $quizSetId);
            $attemptsStmt->execute();
            $attemptsResult = $attemptsStmt->get_result();
            $attemptsStats = $attemptsResult->fetch_assoc();
            $attemptsStmt->close();

            // Get user access distribution
            $accessQuery = "
                SELECT COUNT(DISTINCT identifier) as users_with_access,
                       SUM(CASE WHEN identifier LIKE 'user_%' THEN 1 ELSE 0 END) as individual_access_count,
                       SUM(CASE WHEN access_type = 'all' THEN 1 ELSE 0 END) as public_access_count
                FROM access_permissions
                WHERE item_id = ? AND item_type = 'quiz_set'
            ";
            $accessStmt = \EMA\Config\Database::prepare($accessQuery);
            $accessStmt->bind_param('i', $quizSetId);
            $accessStmt->execute();
            $accessResult = $accessStmt->get_result();
            $accessStats = $accessResult->fetch_assoc();
            $accessStmt->close();

            $statistics = [
                'quiz_set_id' => $quizSetId,
                'quiz_set_name' => $quizSet['name'],
                'total_questions' => (int) $totalQuestions,
                'total_attempts' => (int) $attemptsStats['total_attempts'],
                'average_score' => (float) $attemptsStats['average_score'],
                'average_correct' => (float) $attemptsStats['average_correct'],
                'completion_rate' => $attemptsStats['total_attempts'] > 0
                    ? round(($attemptsStats['completions'] / $attemptsStats['total_attempts']) * 100, 2)
                    : 0,
                'users_with_access' => (int) $accessStats['users_with_access'],
                'individual_access_count' => (int) $accessStats['individual_access_count'],
                'public_access_count' => (int) $accessStats['public_access_count'],
                'access_type' => $quizSet['access_type'],
                'is_published' => $quizSet['is_published']
            ];

            Logger::info('Quiz set statistics retrieved', [
                'quiz_set_id' => $quizSetId
            ]);

            return $statistics;
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set statistics', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
