<?php
include "GlobalConfigs.php";

// --- CONFIGURATION ---
// IMPORTANT: Set this to the public base URL of your API directory.
// It should point to the folder where your 'questions/' folder is accessible.
// Example: "https://www.theemaeducation.com/api/"
$baseUrl = "https://yourdomain.com/path/to/your/api/";

// --- DATABASE CONNECTION ---
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

echo "<pre>"; // For clean browser output
echo "Migration Script Started...\n";
echo "Using Base URL: " . htmlspecialchars($baseUrl) . "\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful.\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// List of all columns that may contain a file path
$fileColumns = [
    "question_file",
    "choice_A_file",
    "choice_B_file",
    "choice_C_file",
    "choice_D_file",
];

try {
    // Begin a transaction
    $pdo->beginTransaction();

    // Fetch all questions
    $stmt = $pdo->query(
        "SELECT id, " . implode(", ", $fileColumns) . " FROM questions",
    );
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($questions) . " questions to process.\n\n";

    $updateCount = 0;

    foreach ($questions as $question) {
        $updates = [];
        $params = [":id" => $question["id"]];

        foreach ($fileColumns as $column) {
            $filePath = $question[$column];

            // Check if the path is not empty and is a relative path (doesn't already start with http)
            if (!empty($filePath) && strpos($filePath, "http") !== 0) {
                // Prepend the base URL
                $newUrl = $baseUrl . ltrim($filePath, "/");
                $updates[] = "$column = :$column";
                $params[":$column"] = $newUrl;
            }
        }

        // If there are any paths to update for this question, run the update query
        if (!empty($updates)) {
            $updateSql =
                "UPDATE questions SET " .
                implode(", ", $updates) .
                " WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);

            echo "Updated Question ID: {$question["id"]}. Migrated " .
                count($updates) .
                " file path(s).\n";
            $updateCount++;
        }
    }

    // Commit the transaction
    $pdo->commit();

    echo "\n-------------------------------------\n";
    echo "Migration Complete!\n";
    echo "Successfully updated records for $updateCount questions.\n";
    echo "IMPORTANT: You should now DELETE this migrate.php file from your server.\n";
} catch (Exception $e) {
    // Roll back the transaction if something failed
    $pdo->rollBack();
    die("An error occurred during migration: " . $e->getMessage());
}

echo "</pre>";
?>
