<?php
/**
 * EMA Education Platform - Database Migration Script
 * Migrates data from theemaeducation (old) to theemaeducation_new (new) database
 *
 * Usage: php database/migrate_data.php
 */

// Database Configuration
$oldDbConfig = [
    'host' => 'localhost',
    'username' => 'doruk',
    'password' => 'dorukdb',
    'database' => 'theemaeducation',
    'port' => 3306
];

$newDbConfig = [
    'host' => 'localhost',
    'username' => 'doruk',
    'password' => 'dorukdb',
    'database' => 'theemaeducation_new',
    'port' => 3306
];

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

class DatabaseMigration {
    private $oldConnection;
    private $newConnection;
    private $migratedCounts = [];
    private $errors = [];

    public function __construct($oldConfig, $newConfig) {
        $this->oldConnection = $this->createConnection($oldConfig, 'Old Database');
        $this->newConnection = $this->createConnection($newConfig, 'New Database');
    }

    private function createConnection($config, $name) {
        try {
            $conn = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );

            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }

            $conn->set_charset("utf8mb4");
            $this->log("✓ Connected to {$name}");
            return $conn;
        } catch (Exception $e) {
            $this->log("✗ Failed to connect to {$name}: " . $e->getMessage());
            die(1);
        }
    }

    private function log($message, $indent = 0) {
        $prefix = str_repeat('  ', $indent);
        echo "{$prefix}{$message}\n";
    }

    private function recordError($table, $error) {
        $this->errors[] = [
            'table' => $table,
            'error' => $error
        ];
        $this->log("  ✗ Error: {$error}", 1);
    }

    public function migrate() {
        $this->log("========================================");
        $this->log("EMA Education Platform - Data Migration");
        $this->log("========================================\n");

        $startTime = microtime(true);

        // Clear existing data from new database
        $this->clearNewDatabase();

        // Migrate tables in order of dependencies
        $this->migrateUsers();
        $this->migrateFolders();
        $this->migrateFiles();
        $this->migrateQuizSets();
        $this->migrateQuestions();
        $this->migrateQuestionsBackup();
        $this->migrateAccessPermissions();
        $this->migrateUserAccess();
        $this->migrateAccessToAllUsers();
        $this->migrateGiveAccessToAllUsers();
        $this->migrateGiveAccessToLoginUsers();
        $this->migrateItemActivationStatus();
        $this->migrateNotices();
        $this->migratePasswordResetRequests();
        $this->migrateAdminUsers();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->log("\n========================================");
        $this->log("Migration Summary");
        $this->log("========================================");
        $this->log("Total time: {$duration} seconds");
        $this->log("Tables migrated: " . count($this->migratedCounts));

        foreach ($this->migratedCounts as $table => $count) {
            $this->log("  {$table}: {$count} rows");
        }

        if (!empty($this->errors)) {
            $this->log("\n========================================");
            $this->log("Errors encountered: " . count($this->errors));
            $this->log("========================================");
            foreach ($this->errors as $error) {
                $this->log("  {$error['table']}: {$error['error']}");
            }
        }

        $this->log("\n✓ Migration completed!");
    }

    private function clearNewDatabase() {
        $this->log("Clearing existing data from new database...");
        $this->newConnection->query("SET FOREIGN_KEY_CHECKS = 0");

        $tables = [
            'download_analytics', 'bulk_operations', 'audit_log', 'system_health', 'system_activity',
            'quiz_results', 'quiz_attempts', 'quiz_activity', 'admin_users', 'password_reset_requests',
            'notice_dismissals', 'notice_views', 'notice_attachments', 'system_notices',
            'item_activation_status', 'give_access_to_login_users', 'give_access_to_all_users',
            'access_to_all_users', 'user_access', 'access_permissions', 'questions_backup',
            'questions', 'quiz_sets', 'files', 'folder_favorites', 'folder_activity',
            'folder_access_permissions', 'folders', 'users'
        ];

        foreach ($tables as $table) {
            $this->newConnection->query("TRUNCATE TABLE {$table}");
        }

        $this->newConnection->query("SET FOREIGN_KEY_CHECKS = 1");
        $this->log("✓ New database cleared\n");
    }

    private function migrateUsers() {
        $this->log("Migrating users...");

        $query = "SELECT * FROM users";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('users', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO users (id, full_name, image, email, phone, password, created_at, updated_at, last_login_at, role, is_logged_in)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $updated_at = null; // New field, default to null
            $last_login_at = null; // New field, default to null

            $stmt->bind_param(
                'isssssssssi',
                $row['id'],
                $row['full_name'],
                $row['image'],
                $row['email'],
                $row['phone'],
                $row['password'],
                $row['created_at'],
                $updated_at,
                $last_login_at,
                $row['role'],
                $row['is_logged_in']
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('users', "Failed to insert user ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['users'] = $count;
        $this->log("✓ Migrated {$count} users\n");
    }

    private function migrateFolders() {
        $this->log("Migrating folders...");

        $query = "SELECT * FROM folders";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('folders', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO folders (id, name, icon_path, description, parent_id, sort_order, is_active, created_by, access_type, updated_at, file_count_cache, is_favorite)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $description = null; // New field
            $parent_id = null; // New field
            $sort_order = 0; // New field
            $is_active = 1; // New field
            $created_by = null; // New field
            $access_type = 'private'; // New field
            $updated_at = null; // New field
            $file_count_cache = 0; // New field
            $is_favorite = 0; // New field

            $stmt->bind_param(
                'ssssiisissii',
                $row['id'],
                $row['name'],
                $row['icon_path'],
                $description,
                $parent_id,
                $sort_order,
                $is_active,
                $created_by,
                $access_type,
                $updated_at,
                $file_count_cache,
                $is_favorite
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('folders', "Failed to insert folder ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['folders'] = $count;
        $this->log("✓ Migrated {$count} folders\n");
    }

    private function migrateFiles() {
        $this->log("Migrating files...");

        $query = "SELECT * FROM files";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('files', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO files (id, folder_id, name, file_path, icon_path, file_size, mime_type, access_type, access_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $file_size = null; // New field
            $mime_type = null; // New field
            $access_count = 0; // New field
            $created_at = null; // Try to derive from somewhere or set to current
            $updated_at = null; // New field

            // Set created_at to current timestamp if not available
            $created_at = date('Y-m-d H:i:s');

            $stmt->bind_param(
                'isssssissss',
                $row['id'],
                $row['folder_id'],
                $row['name'],
                $row['file_path'],
                $row['icon_path'],
                $file_size,
                $mime_type,
                $row['access_type'],
                $access_count,
                $created_at,
                $updated_at
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('files', "Failed to insert file ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['files'] = $count;
        $this->log("✓ Migrated {$count} files\n");
    }

    private function migrateQuizSets() {
        $this->log("Migrating quiz_sets...");

        $query = "SELECT * FROM quiz_sets";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('quiz_sets', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO quiz_sets (id, folder_id, name, icon_path, description, question_count, total_questions, duration_minutes, passing_score, is_published, created_by, access_type, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $description = null; // New field
            $question_count = 0; // New field
            $total_questions = 0; // New field
            $duration_minutes = 0; // New field
            $passing_score = 70; // New field
            $is_published = 0; // New field
            $created_by = null; // New field
            $created_at = date('Y-m-d H:i:s'); // New field
            $updated_at = null; // New field

            $stmt->bind_param(
                'iisssiiiiiisss',
                $row['id'],
                $row['folder_id'],
                $row['name'],
                $row['icon_path'],
                $description,
                $question_count,
                $total_questions,
                $duration_minutes,
                $passing_score,
                $is_published,
                $created_by,
                $row['access_type'],
                $created_at,
                $updated_at
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('quiz_sets', "Failed to insert quiz_set ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['quiz_sets'] = $count;
        $this->log("✓ Migrated {$count} quiz_sets\n");
    }

    private function migrateQuestions() {
        $this->log("Migrating questions...");

        $query = "SELECT * FROM questions";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('questions', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO questions (id, quiz_set_id, question_number, question, optional_text, question_file, question_file_type, question_file_mime, correct_answer, choice_A_text, choice_A_file, choice_A_file_type, choice_A_file_mime, choice_B_text, choice_B_file, choice_B_file_type, choice_B_file_mime, choice_C_text, choice_C_file, choice_C_file_type, choice_C_file_mime, choice_D_text, choice_D_file, choice_D_file_type, choice_D_file_mime, question_type, question_word_formatting, optional_word_formatting, choice_A_word_formatting, choice_B_word_formatting, choice_C_word_formatting, choice_D_word_formatting, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $question_number = 0; // New field
            $created_at = date('Y-m-d H:i:s'); // New field

            // Convert longtext formatting fields to JSON
            $question_word_formatting = $this->convertToJSON($row['question_word_formatting'] ?? '');
            $optional_word_formatting = $this->convertToJSON($row['optional_word_formatting'] ?? '');
            $choice_A_word_formatting = $this->convertToJSON($row['choice_A_word_formatting'] ?? '');
            $choice_B_word_formatting = $this->convertToJSON($row['choice_B_word_formatting'] ?? '');
            $choice_C_word_formatting = $this->convertToJSON($row['choice_C_word_formatting'] ?? '');
            $choice_D_word_formatting = $this->convertToJSON($row['choice_D_word_formatting'] ?? '');

            $stmt->bind_param(
                'iiissbssssbsssbsssbsssbssssssssss',
                $row['id'],
                $row['quiz_set_id'],
                $question_number,
                $row['question'],
                $row['optional_text'],
                $row['question_file'],
                $row['question_file_type'],
                $row['question_file_mime'],
                $row['correct_answer'],
                $row['choice_A_text'],
                $row['choice_A_file'],
                $row['choice_A_file_type'],
                $row['choice_A_file_mime'],
                $row['choice_B_text'],
                $row['choice_B_file'],
                $row['choice_B_file_type'],
                $row['choice_B_file_mime'],
                $row['choice_C_text'],
                $row['choice_C_file'],
                $row['choice_C_file_type'],
                $row['choice_C_file_mime'],
                $row['choice_D_text'],
                $row['choice_D_file'],
                $row['choice_D_file_type'],
                $row['choice_D_file_mime'],
                $row['question_type'],
                $question_word_formatting,
                $optional_word_formatting,
                $choice_A_word_formatting,
                $choice_B_word_formatting,
                $choice_C_word_formatting,
                $choice_D_word_formatting,
                $created_at
            );

            // Handle blob fields separately
            $null = null;

            // Bind blob parameters
            $stmt->send_long_data(5, $row['question_file'] ?? '');
            $stmt->send_long_data(10, $row['choice_A_file'] ?? '');
            $stmt->send_long_data(14, $row['choice_B_file'] ?? '');
            $stmt->send_long_data(18, $row['choice_C_file'] ?? '');
            $stmt->send_long_data(22, $row['choice_D_file'] ?? '');

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('questions', "Failed to insert question ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['questions'] = $count;
        $this->log("✓ Migrated {$count} questions\n");
    }

    private function convertToJSON($longtext) {
        if (empty($longtext)) {
            return '{}';
        }

        // Try to parse as JSON, if fails, create a simple structure
        $decoded = json_decode($longtext, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $longtext;
        }

        // If it's just plain text, wrap it in a simple JSON structure
        return json_encode(['text' => $longtext]);
    }

    private function migrateQuestionsBackup() {
        $this->log("Migrating questions_backup...");

        $query = "SELECT * FROM questions_backup";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('questions_backup', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO questions_backup (original_question_id, quiz_set_id, question, optional_text, question_file, correct_answer, choice_A_text, choice_A_file, choice_B_text, choice_B_file, choice_C_text, choice_C_file, choice_D_text, choice_D_file, question_type, backup_reason, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $original_question_id = $row['id']; // Use old ID as original_question_id
            $backup_reason = 'deleted'; // New field
            $created_at = date('Y-m-d H:i:s'); // created_at

            $stmt->bind_param(
                'iisssssssssssssss',
                $original_question_id,
                $row['quiz_set_id'],
                $row['question'],
                $row['optional_text'],
                $row['question_file'],
                $row['correct_answer'],
                $row['choice_A_text'],
                $row['choice_A_file'],
                $row['choice_B_text'],
                $row['choice_B_file'],
                $row['choice_C_text'],
                $row['choice_C_file'],
                $row['choice_D_text'],
                $row['choice_D_file'],
                $row['question_type'],
                $backup_reason,
                $created_at
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('questions_backup', "Failed to insert backup question: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['questions_backup'] = $count;
        $this->log("✓ Migrated {$count} questions_backup\n");
    }

    private function migrateAccessPermissions() {
        $this->log("Migrating access_permissions...");

        $query = "SELECT * FROM access_permissions";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('access_permissions', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO access_permissions (identifier, is_admin, user_id, item_id, item_type, access_level, access_times, times_accessed, granted_at, is_active, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $user_id = null; // New field
            $access_level = 'read'; // New field
            $expires_at = null; // New field

            // Try to find user_id by identifier
            $userQuery = "SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1";
            $userStmt = $this->newConnection->prepare($userQuery);
            $userStmt->bind_param('ss', $row['identifier'], $row['identifier']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userResult->num_rows > 0) {
                $userRow = $userResult->fetch_assoc();
                $user_id = $userRow['id'];
            }
            $userStmt->close();

            $stmt->bind_param(
                'sssssssssss',
                $row['identifier'],
                $row['is_admin'],
                $user_id,
                $row['item_id'],
                $row['item_type'],
                $access_level,
                $row['access_times'],
                $row['times_accessed'],
                $row['granted_at'],
                $row['is_active'],
                $expires_at
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('access_permissions', "Failed to insert access_permission ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['access_permissions'] = $count;
        $this->log("✓ Migrated {$count} access_permissions\n");
    }

    private function migrateUserAccess() {
        $this->log("Migrating user_access...");

        $query = "SELECT * FROM user_access";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('user_access', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO user_access (identifier, is_admin, item_id, item_type, access_times, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'sssssss',
                $row['identifier'],
                $row['is_admin'],
                $row['item_id'],
                $row['item_type'],
                $row['access_times'],
                $row['created_at'],
                $row['updated_at']
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('user_access', "Failed to insert user_access: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['user_access'] = $count;
        $this->log("✓ Migrated {$count} user_access\n");
    }

    private function migrateAccessToAllUsers() {
        $this->log("Migrating access_to_all_users...");

        $query = "SELECT * FROM access_to_all_users";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('access_to_all_users', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO access_to_all_users (id, folder_id, file_id, quiz_set_id, is_public, granted_at)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('access_to_all_users', "Failed to insert access_to_all_users ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['access_to_all_users'] = $count;
        $this->log("✓ Migrated {$count} access_to_all_users\n");
    }

    private function migrateGiveAccessToAllUsers() {
        $this->log("Migrating give_access_to_all_users...");

        $query = "SELECT * FROM give_access_to_all_users";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('give_access_to_all_users', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO give_access_to_all_users (id, folder_id, file_id, quiz_set_id, access_granted, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('give_access_to_all_users', "Failed to insert give_access_to_all_users ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['give_access_to_all_users'] = $count;
        $this->log("✓ Migrated {$count} give_access_to_all_users\n");
    }

    private function migrateGiveAccessToLoginUsers() {
        $this->log("Migrating give_access_to_login_users...");

        $query = "SELECT * FROM give_access_to_login_users";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('give_access_to_login_users', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO give_access_to_login_users (item_type, item_id, access_granted, created_at)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'ssss',
                $row['item_type'],
                $row['item_id'],
                $row['access_granted'],
                $row['created_at']
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('give_access_to_login_users', "Failed to insert give_access_to_login_users: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['give_access_to_login_users'] = $count;
        $this->log("✓ Migrated {$count} give_access_to_login_users\n");
    }

    private function migrateItemActivationStatus() {
        $this->log("Migrating item_activation_status...");

        $query = "SELECT * FROM item_activation_status";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('item_activation_status', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO item_activation_status (item_type, item_id, is_activated, updated_at)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'ssss',
                $row['item_type'],
                $row['item_id'],
                $row['is_activated'],
                $row['updated_at']
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('item_activation_status', "Failed to insert item_activation_status: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['item_activation_status'] = $count;
        $this->log("✓ Migrated {$count} item_activation_status\n");
    }

    private function migrateNotices() {
        $this->log("Migrating notices to system_notices...");

        $query = "SELECT * FROM notices";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('notices', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO system_notices (title, content, notice_type, priority, target_audience, expires_at, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $notice_type = 'info'; // New field
            $priority = 'medium'; // New field
            $target_audience = 'all'; // New field
            $expires_at = null; // New field
            $is_active = 1; // New field

            // Find a valid user ID for created_by
            $userQuery = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
            $userStmt = $this->newConnection->prepare($userQuery);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userResult->num_rows > 0) {
                $userRow = $userResult->fetch_assoc();
                $created_by = $userRow['id'];
            } else {
                // If no admin user, use the first available user
                $firstUserQuery = "SELECT id FROM users LIMIT 1";
                $firstUserStmt = $this->newConnection->prepare($firstUserQuery);
                $firstUserStmt->execute();
                $firstUserResult = $firstUserStmt->get_result();
                if ($firstUserResult->num_rows > 0) {
                    $firstUserRow = $firstUserResult->fetch_assoc();
                    $created_by = $firstUserRow['id'];
                } else {
                    $created_by = null; // Fallback to null
                }
                $firstUserStmt->close();
            }
            $userStmt->close();

            $updated_at = $row['created_at']; // Use created_at as updated_at

            // Map old notices structure to new structure
            $title = $row['title'];
            $content = $row['text_content'] ?? '';

            // If there's a file attachment, create a notice_attachment entry
            if (!empty($row['file_name']) && !empty($row['file_path'])) {
                // First insert the notice
                $stmt->bind_param(
                    'sssssiisss',
                    $title,
                    $content,
                    $notice_type,
                    $priority,
                    $target_audience,
                    $expires_at,
                    $is_active,
                    $created_by,
                    $row['created_at'],
                    $updated_at
                );

                if ($stmt->execute()) {
                    $notice_id = $stmt->insert_id;
                    $count++;

                    // Now insert the attachment
                    $attachStmt = $this->newConnection->prepare(
                        "INSERT INTO notice_attachments (notice_id, file_name, file_path, file_size, mime_type, file_type, uploaded_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $file_size = 0; // Unknown from old structure
                    $mime_type = 'application/octet-stream'; // Default
                    $file_type = 'doc'; // Default

                    $attachStmt->bind_param(
                        'isissss',
                        $notice_id,
                        $row['file_name'],
                        $row['file_path'],
                        $file_size,
                        $mime_type,
                        $file_type,
                        $row['created_at']
                    );

                    if (!$attachStmt->execute()) {
                        $this->recordError('notice_attachments', "Failed to insert attachment for notice: " . $attachStmt->error);
                    }
                    $attachStmt->close();
                } else {
                    $this->recordError('notices', "Failed to insert notice '{$row['id']}': " . $stmt->error);
                }
            } else {
                // No attachment, just insert the notice
                $stmt->bind_param(
                    'sssssiisss',
                    $title,
                    $content,
                    $notice_type,
                    $priority,
                    $target_audience,
                    $expires_at,
                    $is_active,
                    $created_by,
                    $row['created_at'],
                    $updated_at
                );

                if ($stmt->execute()) {
                    $count++;
                } else {
                    $this->recordError('notices', "Failed to insert notice '{$row['id']}': " . $stmt->error);
                }
            }
            $stmt->close();
        }

        $this->migratedCounts['system_notices'] = $count;
        $this->log("✓ Migrated {$count} notices to system_notices\n");
    }

    private function migratePasswordResetRequests() {
        $this->log("Migrating password_reset_requests...");

        $query = "SELECT * FROM password_reset_requests";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('password_reset_requests', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->newConnection->prepare(
                "INSERT INTO password_reset_requests (id, user_id, email, request_status, token, requested_at, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $token = null; // New field
            $approved_at = null; // New field

            $stmt->bind_param(
                'issssss',
                $row['id'],
                $row['user_id'],
                $row['email'],
                $row['request_status'],
                $token,
                $row['requested_at'],
                $approved_at
            );

            if ($stmt->execute()) {
                $count++;
            } else {
                $this->recordError('password_reset_requests', "Failed to insert password_reset_request ID {$row['id']}: " . $stmt->error);
            }
            $stmt->close();
        }

        $this->migratedCounts['password_reset_requests'] = $count;
        $this->log("✓ Migrated {$count} password_reset_requests\n");
    }

    private function migrateAdminUsers() {
        $this->log("Migrating admin_users...");

        $query = "SELECT * FROM admin_users";
        $result = $this->oldConnection->query($query);

        if (!$result) {
            $this->recordError('admin_users', $this->oldConnection->error);
            return;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            // Check if the user_id exists in the new users table
            $checkQuery = "SELECT id FROM users WHERE id = ?";
            $checkStmt = $this->newConnection->prepare($checkQuery);
            $checkStmt->bind_param('i', $row['user_id']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $stmt = $this->newConnection->prepare(
                    "INSERT INTO admin_users (user_id, full_name, email, assigned_at)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'ssss',
                    $row['user_id'],
                    $row['full_name'],
                    $row['email'],
                    $row['assigned_at']
                );

                if ($stmt->execute()) {
                    $count++;
                } else {
                    $this->recordError('admin_users', "Failed to insert admin_user: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $this->recordError('admin_users', "User ID {$row['user_id']} not found in users table, skipping admin_user record");
            }
            $checkStmt->close();
        }

        $this->migratedCounts['admin_users'] = $count;
        $this->log("✓ Migrated {$count} admin_users\n");
    }

    public function __destruct() {
        if ($this->oldConnection) {
            $this->oldConnection->close();
        }
        if ($this->newConnection) {
            $this->newConnection->close();
        }
    }
}

// Run migration
try {
    $migration = new DatabaseMigration($oldDbConfig, $newDbConfig);
    $migration->migrate();
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
