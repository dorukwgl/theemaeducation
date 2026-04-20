<?php
include "GlobalConfigs.php";

// Set headers first
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit();
}

// Enable error display for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);

// Add error logging function
function logError($message)
{
    error_log(
        date("[Y-m-d H:i:s] ") . $message . PHP_EOL,
        3,
        "/tmp/grant_access_debug.log",
    );
}

logError("Script started - Method: " . $_SERVER["REQUEST_METHOD"]);

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    logError("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $conn->connect_error,
    ]);
    exit();
}

logError("Database connected successfully");

// Set charset
$conn->set_charset("utf8");

// Create access_permissions table if it doesn't exist
$createAccessTableQuery = "CREATE TABLE IF NOT EXISTS access_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    item_id INT NOT NULL,
    item_type ENUM('file', 'quiz_set') NOT NULL,
    access_times INT NOT NULL DEFAULT 0,
    times_accessed INT NOT NULL DEFAULT 0,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_permission (identifier, item_id, item_type)
)";

if (!$conn->query($createAccessTableQuery)) {
    logError("Failed to create access_permissions table: " . $conn->error);
} else {
    logError("Access permissions table creation/check successful");
}

// Create item_activation_status table if it doesn't exist
$createActivationTableQuery = "CREATE TABLE IF NOT EXISTS item_activation_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('file', 'quiz_set') NOT NULL,
    item_id INT NOT NULL,
    is_activated BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_item (item_type, item_id)
)";

if (!$conn->query($createActivationTableQuery)) {
    logError("Failed to create item_activation_status table: " . $conn->error);
} else {
    logError("Item activation status table creation/check successful");
}

// Function to get the first quiz set from the first folder
function getFirstQuizSet($conn)
{
    $query = "SELECT qs.id
             FROM quiz_sets qs
             JOIN folders f ON qs.folder_id = f.id
             ORDER BY f.id ASC, qs.id ASC
             LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row["id"];
    }
    return null;
}

// Automatically make first quiz set free for all users
$firstQuizSetId = getFirstQuizSet($conn);
if ($firstQuizSetId) {
    $stmt = $conn->prepare(
        "INSERT INTO access_permissions (identifier, is_admin, item_id, item_type, access_times)
        VALUES ('all_users', 0, ?, 'quiz_set', -1)
        ON DUPLICATE KEY UPDATE access_times = -1",
    );
    if ($stmt) {
        $stmt->bind_param("i", $firstQuizSetId);
        $stmt->execute();
        $stmt->close();
        logError("First quiz set setup completed");
    }
}

// Handle GET requests
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $action = $_GET["action"] ?? "";

    // Fetch access permissions
    if ($action == "get_access_permissions") {
        logError("GET request for access permissions");
        $identifier = $_GET["identifier"] ?? "";

        if (empty($identifier)) {
            logError("Empty identifier in GET request");
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Identifier is required",
            ]);
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT ap.item_id, ap.item_type, ap.access_times, ap.times_accessed,
                    CASE
                        WHEN ap.item_type = 'file' THEN f.name
                        WHEN ap.item_type = 'quiz_set' THEN qs.name
                    END AS item_name
             FROM access_permissions ap
             LEFT JOIN files f ON ap.item_type = 'file' AND ap.item_id = f.id
             LEFT JOIN quiz_sets qs ON ap.item_type = 'quiz_set' AND ap.item_id = qs.id
             WHERE ap.identifier = ? OR ap.identifier = 'all_users'
             ORDER BY ap.granted_at DESC",
        );

        if (!$stmt) {
            logError("Failed to prepare GET statement: " . $conn->error);
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to prepare statement: " . $conn->error,
            ]);
            exit();
        }

        $stmt->bind_param("s", $identifier);

        if (!$stmt->execute()) {
            logError("Failed to execute GET query: " . $stmt->error);
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to execute query: " . $stmt->error,
            ]);
            $stmt->close();
            exit();
        }

        $stmt->bind_result(
            $item_id,
            $item_type,
            $access_times,
            $times_accessed,
            $item_name,
        );
        $permissions = [];

        while ($stmt->fetch()) {
            $permissions[] = [
                "item_id" => $item_id,
                "item_type" => $item_type,
                "access_times" => $access_times,
                "times_accessed" => $times_accessed,
                "item_name" => $item_name,
            ];
        }

        $stmt->close();

        logError(
            "GET request completed successfully - " .
                count($permissions) .
                " permissions found",
        );
        echo json_encode([
            "status" => "success",
            "data" => $permissions,
            "count" => count($permissions),
        ]);
        exit();
    }

    // Fetch all activated items
    if ($action == "get_all_activations") {
        logError("GET request for all activations");
        try {
            $stmt = $conn->prepare(
                "SELECT i.item_type, i.item_id, i.is_activated,
                        CASE
                            WHEN i.item_type = 'file' THEN f.name
                            WHEN i.item_type = 'quiz_set' THEN qs.name
                        END AS item_name
                 FROM item_activation_status i
                 LEFT JOIN files f ON i.item_type = 'file' AND i.item_id = f.id
                 LEFT JOIN quiz_sets qs ON i.item_type = 'quiz_set' AND i.item_id = qs.id
                 WHERE i.is_activated = TRUE",
            );
            if (!$stmt) {
                logError(
                    "Failed to prepare get_all_activations statement: " .
                        $conn->error,
                );
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to prepare statement: " . $conn->error,
                ]);
                exit();
            }

            if (!$stmt->execute()) {
                logError(
                    "Failed to execute get_all_activations query: " .
                        $stmt->error,
                );
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to execute query: " . $stmt->error,
                ]);
                $stmt->close();
                exit();
            }

            $stmt->bind_result($item_type, $item_id, $is_activated, $item_name);
            $activations = [];

            while ($stmt->fetch()) {
                $activations[] = [
                    "item_type" => $item_type,
                    "item_id" => $item_id,
                    "is_activated" => (bool) $is_activated,
                    "item_name" => $item_name,
                ];
            }

            $stmt->close();

            logError(
                "GET all activations completed successfully - " .
                    count($activations) .
                    " activated items found",
            );
            echo json_encode([
                "status" => "success",
                "data" => $activations,
                "count" => count($activations),
            ]);
            exit();
        } catch (Exception $e) {
            logError("Error in get_all_activations: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Fetch failed: " . $e->getMessage(),
            ]);
            exit();
        }
    }

    // Fetch all permissions
    if ($action == "get_all_permissions") {
        logError("GET request for all permissions");
        try {
            $stmt = $conn->prepare(
                "SELECT ap.identifier, ap.is_admin, ap.item_id, ap.item_type, ap.access_times, ap.times_accessed,
                        CASE
                            WHEN ap.item_type = 'file' THEN f.name
                            WHEN ap.item_type = 'quiz_set' THEN qs.name
                        END AS item_name
                 FROM access_permissions ap
                 LEFT JOIN files f ON ap.item_type = 'file' AND ap.item_id = f.id
                 LEFT JOIN quiz_sets qs ON ap.item_type = 'quiz_set' AND ap.item_id = qs.id",
            );
            if (!$stmt) {
                logError(
                    "Failed to prepare get_all_permissions statement: " .
                        $conn->error,
                );
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to prepare statement: " . $conn->error,
                ]);
                exit();
            }

            if (!$stmt->execute()) {
                logError(
                    "Failed to execute get_all_permissions query: " .
                        $stmt->error,
                );
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to execute query: " . $stmt->error,
                ]);
                $stmt->close();
                exit();
            }

            $stmt->bind_result(
                $identifier,
                $is_admin,
                $item_id,
                $item_type,
                $access_times,
                $times_accessed,
                $item_name,
            );
            $permissions = [];

            while ($stmt->fetch()) {
                $permissions[] = [
                    "identifier" => $identifier,
                    "is_admin" => (bool) $is_admin,
                    "item_id" => $item_id,
                    "item_type" => $item_type,
                    "access_times" => $access_times,
                    "times_accessed" => $times_accessed,
                    "item_name" => $item_name,
                ];
            }

            $stmt->close();

            logError(
                "GET all permissions completed successfully - " .
                    count($permissions) .
                    " permissions found",
            );
            echo json_encode([
                "status" => "success",
                "data" => $permissions,
                "count" => count($permissions),
            ]);
            exit();
        } catch (Exception $e) {
            logError("Error in get_all_permissions: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Fetch failed: " . $e->getMessage(),
            ]);
            exit();
        }
    }
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw input for JSON data
    $rawInput = file_get_contents("php://input");
    logError("Raw POST input: " . $rawInput);

    $jsonData = json_decode($rawInput, true);
    $action = $_POST["action"] ?? ($jsonData["action"] ?? "");

    logError("POST action: " . $action);

    // Handle update activation status
    if ($action == "update_activation") {
        logError("POST request for update activation");

        $item_type = $_POST["item_type"] ?? ($jsonData["item_type"] ?? "");
        $item_id = (int) ($_POST["item_id"] ?? ($jsonData["item_id"] ?? 0));
        $is_activated =
            $_POST["is_activated"] ?? ($jsonData["is_activated"] ?? false);

        if (is_string($is_activated)) {
            $is_activated = $is_activated === "true" || $is_activated === "1";
        }
        $is_activated = (bool) $is_activated;

        logError(
            "Update activation params - item_type: $item_type, item_id: $item_id, is_activated: " .
                ($is_activated ? "true" : "false"),
        );

        if (!in_array($item_type, ["file", "quiz_set"]) || $item_id <= 0) {
            logError("Invalid update activation parameters");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid item type or ID",
            ]);
            exit();
        }

        if ($item_type === "quiz_set" && $item_id == $firstQuizSetId) {
            logError("Cannot update activation for free quiz set ID $item_id");
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Cannot update activation for free quiz set",
            ]);
            exit();
        }

        if ($item_type === "file") {
            $itemCheck = $conn->prepare("SELECT id FROM files WHERE id = ?");
        } else {
            $itemCheck = $conn->prepare(
                "SELECT id FROM quiz_sets WHERE id = ?",
            );
        }

        if (!$itemCheck) {
            logError(
                "Failed to prepare item check for $item_type ID $item_id: " .
                    $conn->error,
            );
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database error during item check",
            ]);
            exit();
        }

        $itemCheck->bind_param("i", $item_id);
        if (!$itemCheck->execute()) {
            logError("Failed to execute item check: " . $itemCheck->error);
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Failed to execute item check",
            ]);
            $itemCheck->close();
            exit();
        }

        $itemCheck->bind_result($found_id);
        $itemExists = $itemCheck->fetch();
        $itemCheck->close();

        if (!$itemExists) {
            logError(
                ucfirst($item_type) . " with ID $item_id not found in database",
            );
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" =>
                    ucfirst($item_type) .
                    " with ID $item_id not found in database",
            ]);
            exit();
        }

        logError("Item validation passed, proceeding with activation update");

        $stmt = $conn->prepare(
            "INSERT INTO item_activation_status (item_type, item_id, is_activated)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_activated = VALUES(is_activated), updated_at = CURRENT_TIMESTAMP",
        );

        if (!$stmt) {
            logError(
                "Failed to prepare update_activation statement: " .
                    $conn->error,
            );
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Failed to prepare statement: " . $conn->error,
            ]);
            exit();
        }

        $stmt->bind_param("sii", $item_type, $item_id, $is_activated);

        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            logError(
                "Activation status updated for $item_type ID $item_id (affected rows: $affected_rows)",
            );
            echo json_encode([
                "success" => true,
                "message" => "Activation status updated successfully",
                "item_type" => $item_type,
                "item_id" => $item_id,
                "is_activated" => $is_activated,
            ]);
        } else {
            logError(
                "Failed to update activation for $item_type ID $item_id: " .
                    $stmt->error,
            );
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Failed to update activation: " . $stmt->error,
            ]);
        }

        $stmt->close();
        exit();
    }

    // Handle batch activation update
    if ($action == "batch_activate") {
        logError("POST request for batch activation");

        $items = $_POST["items"] ?? ($jsonData["items"] ?? []);
        $is_activated =
            $_POST["is_activated"] ?? ($jsonData["is_activated"] ?? true);

        if (is_string($is_activated)) {
            $is_activated = $is_activated === "true" || $is_activated === "1";
        }
        $is_activated = (bool) $is_activated;

        if (empty($items) || !is_array($items)) {
            logError("Invalid items data for batch activation");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Items array is required",
            ]);
            exit();
        }

        $successCount = 0;
        $totalItems = count($items);
        $errors = [];

        logError("Processing batch activation for $totalItems items");

        foreach ($items as $item) {
            $item_id = (int) ($item["item_id"] ?? ($item["id"] ?? 0));
            $item_type = $item["item_type"] ?? ($item["type"] ?? "");

            logError("Processing batch item - ID: $item_id, Type: $item_type");

            if ($item_id <= 0 || !in_array($item_type, ["file", "quiz_set"])) {
                $error = "Invalid item: ID=$item_id, Type=$item_type";
                $errors[] = $error;
                logError($error);
                continue;
            }

            if ($item_type === "quiz_set" && $item_id == $firstQuizSetId) {
                $error = "Skipped free quiz set ID $item_id";
                $errors[] = $error;
                logError($error);
                continue;
            }

            if ($item_type === "file") {
                $itemCheck = $conn->prepare(
                    "SELECT id FROM files WHERE id = ?",
                );
            } else {
                $itemCheck = $conn->prepare(
                    "SELECT id FROM quiz_sets WHERE id = ?",
                );
            }

            if (!$itemCheck) {
                $error =
                    "Failed to prepare item check for $item_type ID $item_id: " .
                    $conn->error;
                $errors[] = $error;
                logError($error);
                continue;
            }

            $itemCheck->bind_param("i", $item_id);

            if (!$itemCheck->execute()) {
                $error =
                    "Failed to execute item check for $item_type ID $item_id: " .
                    $itemCheck->error;
                $errors[] = $error;
                logError($error);
                $itemCheck->close();
                continue;
            }

            $itemCheck->bind_result($found_id);
            $itemExists = $itemCheck->fetch();
            $itemCheck->close();

            if (!$itemExists) {
                $error =
                    ucfirst($item_type) .
                    " with ID $item_id not found in database";
                $errors[] = $error;
                logError($error);
                continue;
            }

            logError(
                "Item validation passed for $item_type ID $item_id, proceeding with activation",
            );

            $stmt = $conn->prepare(
                "INSERT INTO item_activation_status (item_type, item_id, is_activated)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_activated = VALUES(is_activated), updated_at = CURRENT_TIMESTAMP",
            );

            if (!$stmt) {
                $error =
                    "Failed to prepare activation statement for $item_type ID $item_id: " .
                    $conn->error;
                $errors[] = $error;
                logError($error);
                continue;
            }

            $stmt->bind_param("sii", $item_type, $item_id, $is_activated);

            if ($stmt->execute()) {
                $successCount++;
                logError(
                    "Successfully updated activation for $item_type ID $item_id",
                );
            } else {
                $error =
                    "Failed to update activation for $item_type ID $item_id: " .
                    $stmt->error;
                $errors[] = $error;
                logError($error);
            }

            $stmt->close();
        }

        if ($successCount > 0) {
            $message = "Updated activation for $successCount out of $totalItems items";
            if (!empty($errors)) {
                $message .= ". Some errors occurred.";
            }
            logError("Batch activation success: $message");
            echo json_encode([
                "success" => true,
                "message" => $message,
                "success_count" => $successCount,
                "total_items" => $totalItems,
                "errors" => $errors,
            ]);
        } else {
            $errorMessage =
                "Failed to activate any items (" .
                count($errors) .
                " failures)";
            logError("Batch activation failed: $errorMessage");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => $errorMessage,
                "errors" => $errors,
            ]);
        }
        exit();
    }

    // Handle delete activation
    if ($action == "delete_activation") {
        logError("POST request for delete activation");

        $item_type = $_POST["item_type"] ?? ($jsonData["item_type"] ?? "");
        $item_id = (int) ($_POST["item_id"] ?? ($jsonData["item_id"] ?? 0));

        logError(
            "Delete activation params - item_type: $item_type, item_id: $item_id",
        );

        if (!in_array($item_type, ["file", "quiz_set"]) || $item_id <= 0) {
            logError("Invalid delete activation parameters");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid item type or ID",
            ]);
            exit();
        }

        if ($item_type === "quiz_set" && $item_id == $firstQuizSetId) {
            logError("Cannot delete activation for free quiz set ID $item_id");
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Cannot delete activation for free quiz set",
            ]);
            exit();
        }

        $stmt = $conn->prepare(
            "DELETE FROM item_activation_status WHERE item_type = ? AND item_id = ?",
        );

        if (!$stmt) {
            logError(
                "Failed to prepare delete_activation statement: " .
                    $conn->error,
            );
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Failed to prepare statement: " . $conn->error,
            ]);
            exit();
        }

        $stmt->bind_param("si", $item_type, $item_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                logError(
                    "Activation deleted successfully for $item_type ID $item_id",
                );
                echo json_encode([
                    "success" => true,
                    "message" => "Activation deleted successfully",
                    "item_type" => $item_type,
                    "item_id" => $item_id,
                ]);
            } else {
                logError(
                    "No activation found to delete for $item_type ID $item_id",
                );
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "No activation found for the specified item",
                ]);
            }
        } else {
            logError(
                "Failed to delete activation for $item_type ID $item_id: " .
                    $stmt->error,
            );
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Failed to delete activation: " . $stmt->error,
            ]);
        }

        $stmt->close();
        exit();
    }

    if ($action == "batch_delete") {
        logError("POST request for batch delete activation");

        $items = $_POST["items"] ?? ($jsonData["items"] ?? []);

        if (empty($items) || !is_array($items)) {
            logError("Invalid items data for batch delete");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Items array is required",
            ]);
            exit();
        }

        $successCount = 0;
        $totalItems = count($items);
        $errors = [];
        $fileIds = [];
        $quizSetIds = [];

        // Group items by type
        foreach ($items as $item) {
            $item_id = (int) ($item["item_id"] ?? ($item["id"] ?? 0));
            $item_type = $item["item_type"] ?? ($item["type"] ?? "");
            if ($item_id <= 0 || !in_array($item_type, ["file", "quiz_set"])) {
                $errors[] = "Invalid item: ID=$item_id, Type=$item_type";
                logError("Invalid item: ID=$item_id, Type=$item_type");
                continue;
            }
            if ($item_type === "quiz_set" && $item_id == $firstQuizSetId) {
                $errors[] = "Cannot delete free quiz set ID $item_id";
                logError("Cannot delete free quiz set ID $item_id");
                continue;
            }
            if ($item_type === "file") {
                $fileIds[] = $item_id;
            } else {
                $quizSetIds[] = $item_id;
            }
        }

        $conn->begin_transaction();
        try {
            // Delete file activations
            if (!empty($fileIds)) {
                $fileIdsStr = implode(",", array_fill(0, count($fileIds), "?"));
                $stmt = $conn->prepare(
                    "DELETE FROM item_activation_status WHERE item_type = 'file' AND item_id IN ($fileIdsStr)",
                );
                if ($stmt) {
                    $stmt->bind_param(
                        str_repeat("i", count($fileIds)),
                        ...$fileIds,
                    );
                    if ($stmt->execute()) {
                        $successCount += $stmt->affected_rows;
                        logError("Deleted $successCount file activations");
                    } else {
                        $errors[] =
                            "Failed to delete file activations: " .
                            $stmt->error;
                        logError(
                            "Failed to delete file activations: " .
                                $stmt->error,
                        );
                    }
                    $stmt->close();
                } else {
                    $errors[] =
                        "Failed to prepare file delete statement: " .
                        $conn->error;
                    logError("Failed to prepare file delete: " . $conn->error);
                }
            }

            // Delete quiz set activations
            if (!empty($quizSetIds)) {
                $quizSetIdsStr = implode(
                    ",",
                    array_fill(0, count($quizSetIds), "?"),
                );
                $stmt = $conn->prepare(
                    "DELETE FROM item_activation_status WHERE item_type = 'quiz_set' AND item_id IN ($quizSetIdsStr)",
                );
                if ($stmt) {
                    $stmt->bind_param(
                        str_repeat("i", count($quizSetIds)),
                        ...$quizSetIds,
                    );
                    if ($stmt->execute()) {
                        $successCount += $stmt->affected_rows;
                        logError("Deleted $successCount quiz set activations");
                    } else {
                        $errors[] =
                            "Failed to delete quiz set activations: " .
                            $stmt->error;
                        logError(
                            "Failed to delete quiz set activations: " .
                                $stmt->error,
                        );
                    }
                    $stmt->close();
                } else {
                    $errors[] =
                        "Failed to prepare quiz set delete statement: " .
                        $conn->error;
                    logError(
                        "Failed to prepare quiz set delete: " . $conn->error,
                    );
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Transaction failed: " . $e->getMessage();
            logError("Transaction failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Transaction failed: " . $e->getMessage(),
                "errors" => $errors,
            ]);
            exit();
        }

        if ($successCount > 0) {
            $message = "Deleted activation for $successCount out of $totalItems items";
            if (!empty($errors)) {
                $message .= ". Some errors occurred.";
            }
            logError("Batch delete success: $message");
            echo json_encode([
                "success" => true,
                "message" => $message,
                "success_count" => $successCount,
                "total_items" => $totalItems,
                "errors" => $errors,
            ]);
        } else {
            $errorMessage =
                "No activations deleted (" . count($errors) . " failures)";
            logError("Batch delete failed: $errorMessage");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => $errorMessage,
                "errors" => $errors,
            ]);
        }
        exit();
    }

    // Handle delete_access_permission action
    if ($action == "delete_access_permission") {
        logError("Delete access permission action");
        $identifier = $_POST["identifier"] ?? ($jsonData["identifier"] ?? "");
        $item_id = (int) ($_POST["item_id"] ?? ($jsonData["item_id"] ?? 0));
        $item_type = $_POST["item_type"] ?? ($jsonData["item_type"] ?? "");

        logError(
            "Delete params - identifier: $identifier, item_id: $item_id, item_type: $item_type",
        );

        if (
            empty($identifier) ||
            $item_id <= 0 ||
            !in_array($item_type, ["file", "quiz_set"])
        ) {
            logError("Invalid delete parameters");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid parameters for deletion",
            ]);
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT identifier FROM access_permissions WHERE identifier = ? AND item_id = ? AND item_type = ?",
        );
        $stmt->bind_param("sis", $identifier, $item_id, $item_type);
        $stmt->execute();
        $stmt->bind_result($found_identifier);
        $permissionExists = $stmt->fetch();
        $stmt->close();

        if (!$permissionExists) {
            logError("Permission not found for deletion");
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Permission not found",
            ]);
            exit();
        }

        if ($identifier === "all_users") {
            logError("Attempted to delete system permission");
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Cannot delete system permissions",
            ]);
            exit();
        }

        $deleteStmt = $conn->prepare(
            "DELETE FROM access_permissions WHERE identifier = ? AND item_id = ? AND item_type = ?",
        );
        $deleteStmt->bind_param("sis", $identifier, $item_id, $item_type);

        if ($deleteStmt->execute()) {
            if ($deleteStmt->affected_rows > 0) {
                logError("Delete successful");
                echo json_encode([
                    "success" => true,
                    "message" => "Access permission deleted successfully",
                ]);
            } else {
                logError("No matching permission found for deletion");
                echo json_encode([
                    "success" => false,
                    "message" => "No matching permission found",
                ]);
            }
        } else {
            logError("Delete failed: " . $deleteStmt->error);
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" =>
                    "Failed to delete permission: " . $deleteStmt->error,
            ]);
        }

        $deleteStmt->close();
        exit();
    }

    // Handle grant access (default POST action)
    logError("Grant access action");
    $identifier = $_POST["identifier"] ?? ($jsonData["identifier"] ?? "");
    $is_admin =
        ($_POST["is_admin"] ?? ($jsonData["is_admin"] ?? "")) === "true"
            ? 1
            : 0;
    $itemsJson = $_POST["items"] ?? ($jsonData["items"] ?? "");
    $access_times = isset($_POST["access_times"])
        ? (int) $_POST["access_times"]
        : (int) ($jsonData["access_times"] ?? 0);

    if (is_array($itemsJson)) {
        $items = $itemsJson;
    } else {
        $items = json_decode($itemsJson, true);
    }

    logError(
        "Grant params - identifier: $identifier, is_admin: $is_admin, access_times: $access_times",
    );
    logError("Items data: " . json_encode($items));

    if (empty($identifier) || empty($items) || $access_times <= 0) {
        logError("Invalid grant parameters");
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" =>
                "Identifier, items, and valid access times are required",
        ]);
        exit();
    }

    if (!is_array($items)) {
        logError("Invalid items data - not an array");
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid items data",
        ]);
        exit();
    }

    if ($is_admin) {
        logError("Checking admin existence");
        $adminCheck = $conn->prepare(
            "SELECT email FROM admin_users WHERE email = ?",
        );
        if (!$adminCheck) {
            logError("Failed to prepare admin check: " . $conn->error);
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database error during admin check",
            ]);
            exit();
        }

        $adminCheck->bind_param("s", $identifier);
        $adminCheck->execute();
        $adminCheck->bind_result($admin_email);
        $isValidAdmin =
            $adminCheck->fetch() || $identifier === "admin@gmail.com";
        $adminCheck->close();

        if (!$isValidAdmin) {
            logError("Admin not found: $identifier");
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Admin not found",
            ]);
            exit();
        }
        logError("Admin validation passed");
    } else {
        logError("Checking user existence");
        $userCheck = $conn->prepare("SELECT email FROM users WHERE email = ?");
        if (!$userCheck) {
            logError("Failed to prepare user check: " . $conn->error);
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database error during user check",
            ]);
            exit();
        }

        $userCheck->bind_param("s", $identifier);
        $userCheck->execute();
        $userCheck->bind_result($user_email);
        $userExists = $userCheck->fetch();
        $userCheck->close();

        if (!$userExists) {
            logError("User not found: $identifier");
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "User not found",
            ]);
            exit();
        }
        logError("User validation passed");
    }

    $stmt = $conn->prepare(
        "INSERT INTO access_permissions (identifier, is_admin, item_id, item_type, access_times)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE access_times = VALUES(access_times), times_accessed = 0",
    );

    if (!$stmt) {
        logError("Failed to prepare insert statement: " . $conn->error);
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to prepare statement: " . $conn->error,
        ]);
        exit();
    }

    $successCount = 0;
    $totalItems = count($items);
    $errors = [];

    logError("Processing $totalItems items");

    foreach ($items as $item) {
        $item_id = (int) ($item["item_id"] ?? ($item["id"] ?? 0));
        $item_type = $item["item_type"] ?? ($item["type"] ?? "");

        logError("Processing item - ID: $item_id, Type: $item_type");

        if ($item_id <= 0 || !in_array($item_type, ["file", "quiz_set"])) {
            $error = "Invalid item: ID=$item_id, Type=$item_type";
            $errors[] = $error;
            logError($error);
            continue;
        }

        if ($item_type === "file") {
            $itemCheck = $conn->prepare("SELECT id FROM files WHERE id = ?");
        } else {
            $itemCheck = $conn->prepare(
                "SELECT id FROM quiz_sets WHERE id = ?",
            );
        }

        if (!$itemCheck) {
            $error =
                "Failed to prepare item check for $item_type ID $item_id: " .
                $conn->error;
            $errors[] = $error;
            logError($error);
            continue;
        }

        $itemCheck->bind_param("i", $item_id);
        $itemCheck->execute();
        $itemCheck->bind_result($found_id);
        $itemExists = $itemCheck->fetch();
        $itemCheck->close();

        if (!$itemExists) {
            $error = ucfirst($item_type) . " with ID $item_id not found";
            $errors[] = $error;
            logError($error);
            continue;
        }
        logError("Item validation passed for $item_type ID $item_id");

        $stmt->bind_param(
            "sissi",
            $identifier,
            $is_admin,
            $item_id,
            $item_type,
            $access_times,
        );

        if ($stmt->execute()) {
            $successCount++;
            logError("Successfully granted access to $item_type ID $item_id");
        } else {
            $error =
                "Failed to grant access to $item_type ID $item_id: " .
                $stmt->error;
            $errors[] = $error;
            logError($error);
        }
    }

    $stmt->close();

    logError(
        "Processing completed - Success: $successCount, Total: $totalItems",
    );

    if ($successCount > 0) {
        $message = "Access granted/updated for $successCount out of $totalItems items";
        if (!empty($errors)) {
            $message .=
                ". Errors: " . implode(", ", array_slice($errors, 0, 3));
        }
        logError("Success response: $message");
        echo json_encode([
            "success" => true,
            "message" => $message,
            "success_count" => $successCount,
            "total_items" => $totalItems,
        ]);
    } else {
        $errorMessage = "Failed to grant access to any items";
        logError("Error response: $errorMessage");
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => $errorMessage,
            "errors" => $errors,
        ]);
    }
    exit();
}

// Handle unsupported methods
logError("Unsupported method: " . $_SERVER["REQUEST_METHOD"]);
http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed"]);

$conn->close();
?>
