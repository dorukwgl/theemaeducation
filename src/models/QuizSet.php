<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Config\Constants;

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
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
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
                'status' => $quizSet['status'],
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
                return false;
            }

            $folderId = (int) $data['folder_id'];
            $name = trim($data['name']);
            $description = $data['description'] ?? '';
            $iconPath = $data['icon_path'] ?? '';
            $accessType = $data['access_type'] ?? Constants::ACCESS_LOGGED_IN;
            $status = $data['status'] ?? Constants::STATUS_DRAFT;
            $isPublished = isset($data['is_published']) ? (bool) $data['is_published'] : false;
            $durationMinutes = (int) ($data['duration_minutes'] ?? 0);
            $passingScore = (int) ($data['passing_score'] ?? 70);
            $createdBy = $data['created_by'] ?? 1;

            // Validate folder exists
            $folder = Folder::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate access_type
            $validAccessTypes = [
                Constants::ACCESS_ALL,
                Constants::ACCESS_LOGGED_IN,
                Constants::ACCESS_PRIVATE
            ];
            if (!in_array($accessType, $validAccessTypes)) {
                return false;
            }

            // Validate status
            $validStatuses = [
                Constants::STATUS_PUBLISHED,
                Constants::STATUS_DRAFT,
                Constants::STATUS_ARCHIVED
            ];
            if (!in_array($status, $validStatuses)) {
                return false;
            }

            // Insert quiz set
            $query = "INSERT INTO quiz_sets (folder_id, name, access_type, status, is_published, duration_minutes, passing_score, created_by";
            $types = 'isssiiii';
            $params = [$folderId, $name, $accessType, $status, $isPublished ? 1 : 0, $durationMinutes, $passingScore, $createdBy];

            // Add description if provided
            if ($description !== '') {
                $query .= ", description";
                $types .= 's';
                $params[] = $description;
            }

            // Add icon_path if provided
            if ($iconPath !== '') {
                $query .= ", icon_path";
                $types .= 's';
                $params[] = $iconPath;
            }

            $query .= ") VALUES (";
            $placeholders = array_fill(0, count($params), '?');
            $query .= implode(', ', $placeholders);
            $query .= ")";

            $stmt = \EMA\Config\Database::prepare($query);

            if (!$stmt) {
                Logger::error('QuizSet::create - Prepare failed', [
                    'error' => \EMA\Config\Database::getConnection()->error,
                    'query' => $query
                ]);
                return false;
            }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $quizSetId = $stmt->insert_id;
                $stmt->close();
                return $quizSetId;
            } else {
                Logger::error('QuizSet::create - Execute failed', [
                    'error' => $stmt->error,
                    'errno' => $stmt->errno
                ]);
                $stmt->close();
                return false;
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
                if ($quizSet['icon_path'] && file_exists(ROOT_PATH . '/uploads/' . $quizSet['icon_path'])) {
                    unlink(ROOT_PATH . '/uploads/' . $quizSet['icon_path']);
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $data['icon_path'];
            }

            // Handle access_type update
            if (isset($data['access_type'])) {
                $accessType = $data['access_type'];
                $validAccessTypes = [
                    Constants::ACCESS_ALL,
                    Constants::ACCESS_LOGGED_IN,
                    Constants::ACCESS_PRIVATE
                ];
                if (!in_array($accessType, $validAccessTypes)) {
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

            // Handle status update
            if (isset($data['status'])) {
                $status = $data['status'];
                $validStatuses = [
                    Constants::STATUS_PUBLISHED,
                    Constants::STATUS_DRAFT,
                    Constants::STATUS_ARCHIVED
                ];
                if (!in_array($status, $validStatuses)) {
                    return false;
                }

                $updates[] = 'status = ?';
                $types .= 's';
                $params[] = $status;
            }

            if (empty($updates)) {
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
                if ($quizSet['icon_path'] && file_exists(ROOT_PATH . '/uploads/' . $quizSet['icon_path'])) {
                    unlink(ROOT_PATH . '/uploads/' . $quizSet['icon_path']);
                }

                // Delete quiz set record
                $deleteQuizQuery = "DELETE FROM quiz_sets WHERE id = ?";
                $deleteQuizStmt = \EMA\Config\Database::prepare($deleteQuizQuery);
                $deleteQuizStmt->bind_param('i', $id);
                $result = $deleteQuizStmt->execute();
                $deleteQuizStmt->close();

                if ($result) {
                    \EMA\Config\Database::commit();

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

            if (!$quizSet['is_published'] && (!$userId || !User::isAdminById($userId))) {
                return [];
            }

            // Get questions with optimized single query
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
                $questionData = [
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

            // Check quiz set status - only published quiz sets are accessible
            if ($quizSet['status'] !== Constants::STATUS_PUBLISHED) {
                return false;
            }

            // Check quiz set access_type
            $accessType = $quizSet['access_type'];

            // Public access
            if ($accessType === Constants::ACCESS_ALL) {
                return true;
            }

            // Logged-in access
            if ($accessType === Constants::ACCESS_LOGGED_IN) {
                // User must be authenticated (checked by caller)
                return true;
            }

            // Private access - check individual permissions via Access model
            if ($accessType === Constants::ACCESS_PRIVATE) {
                return Access::checkAccess($userId, $quizSetId, 'quiz_set');
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
     * @param bool $includeNonPublished Include non-published quiz sets (admin only)
     * @param string|null $statusFilter Filter by status (published, draft, archived)
     * @return array Quiz sets
     */
    public static function getAllQuizSets(
        int $page,
        int $perPage,
        ?int $folderId = null,
        ?int $userId = null,
        bool $includeQuestionCount = false,
        bool $publishedOnly = true,
        bool $includeNonPublished = false,
        ?string $statusFilter = null
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
            if ($publishedOnly && $userId !== null && !$includeNonPublished) {
                $conditions[] = 'qs.is_published = 1';
            }

            // Add status filter
            if ($statusFilter !== null) {
                $validStatuses = [
                    Constants::STATUS_PUBLISHED,
                    Constants::STATUS_DRAFT,
                    Constants::STATUS_ARCHIVED
                ];
                if (in_array($statusFilter, $validStatuses)) {
                    $conditions[] = 'qs.status = ?';
                    $params[] = $statusFilter;
                    $types .= 's';
                }
            }

            // Add access control filtering for non-admin users
            if ($userId !== null && !User::isAdminById($userId)) {
                $conditions[] = "(
                    qs.access_type = ?
                    OR qs.access_type = ?
                    OR qs.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'quiz_set'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $publicAccess = Constants::ACCESS_ALL;
                $loggedInAccess = Constants::ACCESS_LOGGED_IN;
                $params[] = $publicAccess;
                $params[] = $loggedInAccess;
                $params[] = $userId;
                $types .= 'sii';
            }

            // Build WHERE clause
            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // Get quiz sets with pagination
            $offset = ($page - 1) * $perPage;
            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
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
                    'status' => $row['status'],
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
                       0 as public_access_count
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
                'public_access_count' => $quizSet['access_type'] === Constants::ACCESS_ALL ? 1 : 0,
                'access_type' => $quizSet['access_type'],
                'status' => $quizSet['status'],
                'is_published' => $quizSet['is_published'],
                'is_active' => $quizSet['status'] === Constants::STATUS_PUBLISHED
            ];

            return $statistics;
        } catch (\Exception $e) {
            Logger::error('Error getting quiz set statistics', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if quiz set is published (active)
     * @param int $quizSetId Quiz set ID
     * @return bool true if published, false otherwise
     */
    public static function isQuizSetPublished(int $quizSetId): bool
    {
        try {
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                return false;
            }

            return $quizSet['status'] === Constants::STATUS_PUBLISHED;
        } catch (\Exception $e) {
            Logger::error('Error checking quiz set published status', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if quiz set is publicly accessible
     * @param int $quizSetId Quiz set ID
     * @return bool true if public and published, false otherwise
     */
    public static function isQuizSetPublic(int $quizSetId): bool
    {
        try {
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                return false;
            }

            return $quizSet['access_type'] === Constants::ACCESS_ALL &&
                   $quizSet['status'] === Constants::STATUS_PUBLISHED &&
                   $quizSet['is_published'];
        } catch (\Exception $e) {
            Logger::error('Error checking quiz set public status', [
                'quiz_set_id' => $quizSetId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update quiz set status
     * @param int $quizSetId Quiz set ID
     * @param string $status New status
     * @return bool true if successful, false otherwise
     */
    public static function updateStatus(int $quizSetId, string $status): bool
    {
        try {
            // Validate status
            $validStatuses = [
                Constants::STATUS_PUBLISHED,
                Constants::STATUS_DRAFT,
                Constants::STATUS_ARCHIVED
            ];
            if (!in_array($status, $validStatuses)) {
                Logger::error('Invalid status for quiz set', [
                    'quiz_set_id' => $quizSetId,
                    'status' => $status
                ]);
                return false;
            }

            // Check if quiz set exists
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                Logger::error('Quiz set not found for status update', [
                    'quiz_set_id' => $quizSetId
                ]);
                return false;
            }

            // Update status
            $query = "UPDATE quiz_sets SET status = ? WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);

            // Assign status to variable for bind_param
            $statusVar = $status;
            $stmt->bind_param('si', $statusVar, $quizSetId);

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set status', [
                'quiz_set_id' => $quizSetId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update quiz set access type
     * @param int $quizSetId Quiz set ID
     * @param string $accessType New access type
     * @return bool true if successful, false otherwise
     */
    public static function updateAccessType(int $quizSetId, string $accessType): bool
    {
        try {
            // Validate access type
            $validAccessTypes = [
                Constants::ACCESS_ALL,
                Constants::ACCESS_LOGGED_IN,
                Constants::ACCESS_PRIVATE
            ];
            if (!in_array($accessType, $validAccessTypes)) {
                Logger::error('Invalid access type for quiz set', [
                    'quiz_set_id' => $quizSetId,
                    'access_type' => $accessType
                ]);
                return false;
            }

            // Check if quiz set exists
            $quizSet = self::findById($quizSetId);
            if (!$quizSet) {
                Logger::error('Quiz set not found for access type update', [
                    'quiz_set_id' => $quizSetId
                ]);
                return false;
            }

            // Update access type
            $query = "UPDATE quiz_sets SET access_type = ? WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);

            // Assign accessType to variable for bind_param
            $accessTypeVar = $accessType;
            $stmt->bind_param('si', $accessTypeVar, $quizSetId);

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating quiz set access type', [
                'quiz_set_id' => $quizSetId,
                'access_type' => $accessType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get public quiz sets with pagination
     * @param int $folderId Folder ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Public quiz sets with pagination metadata
     */
    public static function getPublicQuizSetsPaginated(int $page, int $perPage, ?string $search = null, ?int $folderId = null, bool $includeQuestionCount = false): array
    {
        try {
            // Validate folder if provided
            if ($folderId !== null && !\EMA\Models\Folder::findById($folderId)) {
                return [
                    'quiz_sets' => [],
                    'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                    'total' => 0
                ];
            }

            // Build base query
            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
                       fl.name as folder_name,
                       fl.icon_path as folder_icon_path
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                WHERE qs.access_type = ?
                  AND qs.status = ?
                  AND qs.is_published = 1
            ";

            // Build count query
            $countQuery = "
                SELECT COUNT(*) as total
                FROM quiz_sets qs
                WHERE qs.access_type = ?
                  AND qs.status = ?
                  AND qs.is_published = 1
            ";

            // Build parameters arrays
            $params = [Constants::ACCESS_ALL, Constants::STATUS_PUBLISHED];
            $types = 'ss';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND qs.folder_id = ?";
                $countQuery .= " AND qs.folder_id = ?";
                $params[] = $folderId;
                $types .= 'i';
            }

            // Add search filter if provided
            if ($search !== null) {
                $query .= " AND (qs.name LIKE ? OR qs.id LIKE ?)";
                $countQuery .= " AND (qs.name LIKE ? OR qs.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }

            $query .= " ORDER BY qs.created_at DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'question_count' => (int) $row['question_count'],
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

            // Execute count query (reuse params except limit/offset)
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);

            // Only bind parameters if we have them
            if (!empty($countParams) && !empty($countTypes)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }

            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'quiz_sets' => $quizSets,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting public quiz sets paginated', [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [
                'quiz_sets' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get all quiz sets with pagination (admin use)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $accessType Optional access type filter
     * @param string|null $status Optional status filter
     * @param bool $includeQuestionCount Include question count in results
     * @return array Paginated quiz sets with metadata
     */
    public static function getAllQuizSetsPaginated(int $page, int $perPage, ?int $folderId = null, ?string $accessType = null, ?string $status = null, bool $includeQuestionCount = false): array
    {
        try {
            // Build base query
            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
                       fl.name as folder_name,
                       fl.icon_path as folder_icon_path
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                WHERE 1=1
            ";

            // Build count query
            $countQuery = "SELECT COUNT(*) as total FROM quiz_sets qs WHERE 1=1";

            // Build parameters arrays
            $params = [];
            $types = '';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND qs.folder_id = ?";
                $countQuery .= " AND qs.folder_id = ?";
                $params[] = $folderId;
                $types .= 'i';
            }

            // Add access type filter if provided
            if ($accessType !== null) {
                $query .= " AND qs.access_type = ?";
                $countQuery .= " AND qs.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            // Add status filter if provided
            if ($status !== null) {
                $query .= " AND qs.status = ?";
                $countQuery .= " AND qs.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            $query .= " ORDER BY qs.created_at DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'question_count' => (int) $row['question_count'],
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

            // Execute count query (reuse params except limit/offset)
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);

            // Only bind parameters if we have them
            if (!empty($countParams) && !empty($countTypes)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }

            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'quiz_sets' => $quizSets,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting all quiz sets paginated', [
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'access_type' => $accessType,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [
                'quiz_sets' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get quiz sets accessible to logged-in user with pagination
     * @param int $userId User ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $accessType Optional access type filter
     * @param string|null $status Optional status filter
     * @param bool $includeQuestionCount Include question count in results
     * @return array Paginated quiz sets with metadata
     */
    public static function getLoggedInQuizSetsPaginated(int $userId, int $page, int $perPage, ?int $folderId = null, ?string $accessType = null, ?string $status = null, bool $includeQuestionCount = false): array
    {
        try {
            // Build base query - quiz sets that are public, logged_in, or private with permission
            $query = "
                SELECT DISTINCT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
                       fl.name as folder_name,
                       fl.icon_path as folder_icon_path
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                LEFT JOIN access_permissions ap ON (ap.item_id = qs.id AND ap.item_type = 'quiz_set' AND ap.identifier = ? AND ap.is_active = 1)
                WHERE qs.status = ?
                AND (
                    qs.access_type = ?
                    OR qs.access_type = ?
                    OR (qs.access_type = ? AND ap.id IS NOT NULL)
                )
            ";

            // Build count query
            $countQuery = "
                SELECT COUNT(DISTINCT qs.id) as total
                FROM quiz_sets qs
                LEFT JOIN access_permissions ap ON (ap.item_id = qs.id AND ap.item_type = 'quiz_set' AND ap.identifier = ? AND ap.is_active = 1)
                WHERE qs.status = ?
                AND (
                    qs.access_type = ?
                    OR qs.access_type = ?
                    OR (qs.access_type = ? AND ap.id IS NOT NULL)
                )
            ";

            // Build base parameters
            $identifier = 'user_' . $userId;
            $params = [$identifier, Constants::STATUS_PUBLISHED, Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE];
            $types = 'sssss';
            $countParams = [$identifier, Constants::STATUS_PUBLISHED, Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE];
            $countTypes = 'sssss';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND qs.folder_id = ?";
                $countQuery .= " AND qs.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            // Add access type filter if provided
            if ($accessType !== null) {
                $query .= " AND qs.access_type = ?";
                $countQuery .= " AND qs.access_type = ?";
                $params[] = $accessType;
                $countParams[] = $accessType;
                $types .= 's';
                $countTypes .= 's';
            }

            // Add status filter if provided (overrides the published status requirement)
            if ($status !== null) {
                // Remove the default status filter and add custom one
                $query = str_replace("WHERE qs.status = ?", "WHERE 1=1", $query);
                $countQuery = str_replace("WHERE qs.status = ?", "WHERE 1=1", $countQuery);

                // Remove the STATUS_PUBLISHED from params
                array_splice($params, 1, 1);
                array_splice($countParams, 1, 1);
                $types = 'ssss'; // Remove one 's'
                $countTypes = 'ssss';

                $query .= " AND qs.status = ?";
                $countQuery .= " AND qs.status = ?";
                $params[] = $status;
                $countParams[] = $status;
                $types .= 's';
                $countTypes .= 's';
            }

            $query .= " ORDER BY qs.created_at DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'question_count' => (int) $row['question_count'],
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

            // Execute count query (reuse params except limit/offset)
            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'quiz_sets' => $quizSets,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting logged-in quiz sets paginated', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'access_type' => $accessType,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [
                'quiz_sets' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get quiz sets granted to a specific user via access_permissions (admin use)
     * @param int $userId User ID to check grants for
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $search Optional search term
     * @return array Paginated quiz sets with permission metadata
     */
    public static function getGrantedQuizSetsForUser(int $userId, int $page, int $perPage, ?int $folderId = null, ?string $search = null): array
    {
        try {
            $identifier = 'user_' . $userId;

            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path,
                       ap.access_times, ap.times_accessed, ap.is_active as grant_active, ap.granted_at
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                INNER JOIN access_permissions ap ON ap.item_id = qs.id
                    AND ap.item_type = 'quiz_set'
                    AND ap.identifier = ?
                WHERE 1=1
            ";

            $countQuery = "
                SELECT COUNT(qs.id) as total
                FROM quiz_sets qs
                INNER JOIN access_permissions ap ON ap.item_id = qs.id
                    AND ap.item_type = 'quiz_set'
                    AND ap.identifier = ?
                WHERE 1=1
            ";

            $params = [$identifier];
            $types = 's';
            $countParams = [$identifier];
            $countTypes = 's';

            if ($folderId !== null) {
                $query .= " AND qs.folder_id = ?";
                $countQuery .= " AND qs.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            if ($search !== null) {
                $query .= " AND qs.name LIKE ?";
                $countQuery .= " AND qs.name LIKE ?";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $countParams[] = $searchParam;
                $types .= 's';
                $countTypes .= 's';
            }

            $query .= " ORDER BY qs.created_at DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

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
                    'status' => $row['status'],
                    'question_count' => (int) $row['question_count'],
                    'total_questions' => (int) $row['total_questions'],
                    'duration_minutes' => (int) $row['duration_minutes'],
                    'passing_score' => (int) $row['passing_score'],
                    'is_published' => (bool) $row['is_published'],
                    'created_by' => $row['created_by'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path'],
                    'grant_info' => [
                        'access_times' => (int) $row['access_times'],
                        'times_accessed' => (int) $row['times_accessed'],
                        'is_active' => (bool) $row['grant_active'],
                        'granted_at' => $row['granted_at']
                    ]
                ];
                $quizSets[] = $quizSetData;
            }

            $stmt->close();

            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'quiz_sets' => $quizSets,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting granted quiz sets for user', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'search' => $search,
                'error' => $e->getMessage()
            ]);
            return [
                'quiz_sets' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get quiz sets NOT granted to a specific user via access_permissions (admin use)
     * @param int $userId User ID to check grants for
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $search Optional search term
     * @return array Paginated quiz sets without permission records
     */
    public static function getNotGrantedQuizSetsForUser(int $userId, int $page, int $perPage, ?int $folderId = null, ?string $search = null): array
    {
        try {
            $identifier = 'user_' . $userId;

            $query = "
                SELECT qs.id, qs.folder_id, qs.name, qs.description, qs.icon_path,
                       qs.access_type, qs.status, qs.question_count, qs.total_questions,
                       qs.duration_minutes, qs.passing_score, qs.is_published,
                       qs.created_by, qs.created_at, qs.updated_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM quiz_sets qs
                LEFT JOIN folders fl ON qs.folder_id = fl.id
                LEFT JOIN access_permissions ap ON ap.item_id = qs.id
                    AND ap.item_type = 'quiz_set'
                    AND ap.identifier = ?
                WHERE ap.id IS NULL
            ";

            $countQuery = "
                SELECT COUNT(qs.id) as total
                FROM quiz_sets qs
                LEFT JOIN access_permissions ap ON ap.item_id = qs.id
                    AND ap.item_type = 'quiz_set'
                    AND ap.identifier = ?
                WHERE ap.id IS NULL
            ";

            $params = [$identifier];
            $types = 's';
            $countParams = [$identifier];
            $countTypes = 's';

            if ($folderId !== null) {
                $query .= " AND qs.folder_id = ?";
                $countQuery .= " AND qs.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            if ($search !== null) {
                $query .= " AND qs.name LIKE ?";
                $countQuery .= " AND qs.name LIKE ?";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $countParams[] = $searchParam;
                $types .= 's';
                $countTypes .= 's';
            }

            $query .= " ORDER BY qs.created_at DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

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
                    'status' => $row['status'],
                    'question_count' => (int) $row['question_count'],
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

            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'quiz_sets' => $quizSets,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting not-granted quiz sets for user', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'search' => $search,
                'error' => $e->getMessage()
            ]);
            return [
                'quiz_sets' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }
}
