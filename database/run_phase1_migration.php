<?php
/**
 * Phase 1 Migration Runner
 * Executes the access control schema updates
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EMA\Config\Config;
use EMA\Utils\Logger;

// Load configuration
try {
    Config::load();
} catch (Exception $e) {
    echo "Error loading configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Get database configuration
$dbConfig = Config::get('database');
$host = $dbConfig['host'];
$port = $dbConfig['port'];
$dbname = $dbConfig['name'];
$username = $dbConfig['user'];
$password = $dbConfig['password'];

echo "==========================================\n";
echo "Phase 1: Access Control Schema Migration\n";
echo "==========================================\n\n";

try {
    // Create database connection
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
    ]);

    echo "✓ Connected to database: $dbname\n\n";

    // Read migration file
    $migrationFile = __DIR__ . '/migrations/2025_04_30_phase1_access_control_schema.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    // Split SQL into individual statements while preserving order
    $statements = [];
    $currentStatement = '';
    $lines = explode("\n", $sql);
    $inCommentBlock = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        // Handle comment blocks
        if (strpos($trimmedLine, '/*') === 0) {
            $inCommentBlock = true;
            continue;
        }
        if (strpos($trimmedLine, '*/') !== false) {
            $inCommentBlock = false;
            continue;
        }
        if ($inCommentBlock) {
            continue;
        }

        // Skip empty lines and single-line comments
        if ($trimmedLine === '' || strpos($trimmedLine, '--') === 0) {
            continue;
        }

        // Build current statement
        $currentStatement .= $line . "\n";

        // If line ends with semicolon, we have a complete statement
        if (strpos($trimmedLine, ';') !== false) {
            $cleanStatement = trim($currentStatement);
            // Remove trailing semicolon for PDO
            $cleanStatement = rtrim($cleanStatement, ';');

            // Only process ALTER, CREATE, UPDATE, INSERT statements
            $firstWord = strtoupper(substr($cleanStatement, 0, 6));
            if (in_array($firstWord, ['ALTER ', 'CREATE', 'UPDATE', 'INSERT'])) {
                $statements[] = $cleanStatement;
            }

            $currentStatement = '';
        }
    }

    echo "Found " . count($statements) . " SQL statements to execute\n\n";

    // Execute each statement
    $executedCount = 0;
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);

        // Skip comments and empty statements
        if (empty($statement) ||
            strpos($statement, '--') === 0 ||
            strpos($statement, '/*') === 0) {
            continue;
        }

        // Skip verification queries and transaction statements
        if (strpos($statement, 'SELECT') === 0 ||
            strpos($statement, 'START TRANSACTION') === 0 ||
            strpos($statement, 'COMMIT') === 0 ||
            strpos($statement, 'ROLLBACK') === 0) {
            continue;
        }

        try {
            echo "Executing statement " . ($executedCount + 1) . ": ";
            echo substr($statement, 0, 60) . "...\n";

            $pdo->exec($statement);
            $executedCount++;

            echo "  ✓ Success\n";
        } catch (PDOException $e) {
            // Check if it's a "duplicate column" or "duplicate index" error
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "  ⚠ Already exists (skipping)\n";
                continue;
            }

            echo "  ✗ Error: " . $e->getMessage() . "\n";
            echo "\n⚠ Migration failed! Rolling back changes...\n";

            // Attempt to rollback
            try {
                $pdo->exec('ROLLBACK');
                echo "✓ Changes rolled back\n";
            } catch (Exception $rollbackError) {
                echo "✗ Rollback failed: " . $rollbackError->getMessage() . "\n";
            }

            exit(1);
        }
    }

    echo "\n==========================================\n";
    echo "Migration Summary\n";
    echo "==========================================\n";
    echo "Total statements executed: $executedCount\n";
    echo "Status: ✓ COMPLETED SUCCESSFULLY\n\n";

    // Verification queries
    echo "==========================================\n";
    echo "Verification\n";
    echo "==========================================\n\n";

    // Check files table structure
    echo "Files table structure:\n";
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'files'
        AND COLUMN_NAME IN ('access_type', 'status')
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$dbname]);
    $columns = $stmt->fetchAll();

    foreach ($columns as $column) {
        echo "  - {$column['COLUMN_NAME']}: {$column['COLUMN_TYPE']}";
        echo " (DEFAULT: {$column['COLUMN_DEFAULT']}, NULLABLE: {$column['IS_NULLABLE']})\n";
    }

    echo "\nQuiz Sets table structure:\n";
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'quiz_sets'
        AND COLUMN_NAME IN ('access_type', 'status', 'is_published')
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$dbname]);
    $columns = $stmt->fetchAll();

    foreach ($columns as $column) {
        echo "  - {$column['COLUMN_NAME']}: {$column['COLUMN_TYPE']}";
        echo " (DEFAULT: {$column['COLUMN_DEFAULT']}, NULLABLE: {$column['IS_NULLABLE']})\n";
    }

    echo "\nIndexes created:\n";
    $stmt = $pdo->prepare("
        SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('files', 'quiz_sets')
        AND INDEX_NAME LIKE 'idx_%_status%'
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
    ");
    $stmt->execute([$dbname]);
    $indexes = $stmt->fetchAll();

    foreach ($indexes as $index) {
        echo "  - {$index['INDEX_NAME']}: {$index['COLUMN_NAME']} (pos: {$index['SEQ_IN_INDEX']})\n";
    }

    // Sample data verification
    echo "\nSample data verification:\n";
    echo "Files (first 3):\n";
    $stmt = $pdo->query("SELECT id, name, access_type, status FROM files LIMIT 3");
    $files = $stmt->fetchAll();

    if (count($files) > 0) {
        foreach ($files as $file) {
            echo "  - ID: {$file['id']}, Name: {$file['name']}, Access: {$file['access_type']}, Status: {$file['status']}\n";
        }
    } else {
        echo "  (No files found)\n";
    }

    echo "\nQuiz Sets (first 3):\n";
    $stmt = $pdo->query("SELECT id, name, access_type, status, is_published FROM quiz_sets LIMIT 3");
    $quizSets = $stmt->fetchAll();

    if (count($quizSets) > 0) {
        foreach ($quizSets as $quizSet) {
            echo "  - ID: {$quizSet['id']}, Name: {$quizSet['name']}, Access: {$quizSet['access_type']}, Status: {$quizSet['status']}, Published: {$quizSet['is_published']}\n";
        }
    } else {
        echo "  (No quiz sets found)\n";
    }

    echo "\n==========================================\n";
    echo "✓ Phase 1 migration completed successfully!\n";
    echo "==========================================\n";

} catch (Exception $e) {
    echo "\n==========================================\n";
    echo "✗ Migration Failed\n";
    echo "==========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";

    // Log the error
    try {
        Logger::error('Phase 1 migration failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    } catch (Exception $logError) {
        echo "Could not log error: " . $logError->getMessage() . "\n";
    }

    exit(1);
}