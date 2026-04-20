<?php
include "GlobalConfigs.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit();
}

// Enable error logging for debugging
error_reporting(E_ALL);

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception(
            "Database connection failed: " . $conn->connect_error,
        );
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "error_code" => "DB_CONNECTION_FAILED",
    ]);
    exit();
}

// Function to get the first quiz set from the first folder (using compatible method)
function getFirstQuizSet($conn)
{
    static $firstQuizSetId = null;
    if ($firstQuizSetId === null) {
        $query = "SELECT qs.id
                 FROM quiz_sets qs
                 JOIN folders f ON qs.folder_id = f.id
                 ORDER BY f.id ASC, qs.id ASC
                 LIMIT 1";
        $result = $conn->query($query);
        if ($result === false) {
            error_log("getFirstQuizSet query failed: " . $conn->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        $firstQuizSetId = $row ? (int) $row["id"] : 0;
        error_log("First quiz set ID: $firstQuizSetId");
        $result->free();
    }
    return $firstQuizSetId;
}

// Check request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method",
        "error_code" => "INVALID_METHOD",
    ]);
    exit();
}

// Get and validate input parameters
$identifier = isset($_POST["identifier"]) ? trim($_POST["identifier"]) : "";
$is_admin = isset($_POST["is_admin"])
    ? ($_POST["is_admin"] === "true"
        ? 1
        : 0)
    : 0;
$item_id = isset($_POST["item_id"]) ? (int) $_POST["item_id"] : 0;
$item_type = isset($_POST["item_type"]) ? trim($_POST["item_type"]) : "";

error_log("Received POST data: " . json_encode($_POST));
error_log(
    "Processed: identifier='$identifier', is_admin=$is_admin, item_id=$item_id, item_type='$item_type'",
);

// Validate required parameters
if ($item_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid item_id: must be a positive integer",
        "error_code" => "INVALID_ITEM_ID",
        "received_item_id" => $_POST["item_id"] ?? "not_set",
    ]);
    $conn->close();
    exit();
}

if (!in_array($item_type, ["file", "quiz_set"])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid item_type: must be 'file' or 'quiz_set'",
        "error_code" => "INVALID_ITEM_TYPE",
        "received_item_type" => $item_type,
    ]);
    $conn->close();
    exit();
}

// Handle special cases first
// 1. Guest users
if (empty($identifier) || strtolower($identifier) === "guest") {
    echo json_encode([
        "success" => true,
        "message" => "Guest access granted",
        "is_guest" => true,
        "remaining_access" => -1,
    ]);
    $conn->close();
    exit();
}

// 2. Admin users
if ($is_admin) {
    echo json_encode([
        "success" => true,
        "message" => "Admin access granted",
        "is_admin" => true,
        "remaining_access" => -1,
    ]);
    $conn->close();
    exit();
}

// 3. First quiz set (free access)
if ($item_type === "quiz_set") {
    $firstQuizSetId = getFirstQuizSet($conn);
    if ($firstQuizSetId > 0 && $item_id === $firstQuizSetId) {
        echo json_encode([
            "success" => true,
            "message" => "Access granted - free first quiz set",
            "is_free" => true,
            "remaining_access" => -1,
        ]);
        $conn->close();
        exit();
    }
}

// Check specific user permissions using bind_result method
try {
    $checkStmt = $conn->prepare(
        "SELECT access_times, times_accessed
         FROM access_permissions
         WHERE identifier = ?
         AND is_admin = ?
         AND item_id = ?
         AND item_type = ?",
    );

    if (!$checkStmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $checkStmt->bind_param(
        "siss",
        $identifier,
        $is_admin,
        $item_id,
        $item_type,
    );

    if (!$checkStmt->execute()) {
        throw new Exception(
            "Failed to execute statement: " . $checkStmt->error,
        );
    }

    $checkStmt->bind_result($access_times, $times_accessed);
    $userPermissionFound = $checkStmt->fetch();
    $checkStmt->close();

    // If no specific user permission, check for 'all_users' permission
    if (!$userPermissionFound) {
        $checkStmt = $conn->prepare(
            "SELECT access_times, times_accessed
             FROM access_permissions
             WHERE identifier = 'all_users'
             AND is_admin = ?
             AND item_id = ?
             AND item_type = ?",
        );

        if (!$checkStmt) {
            throw new Exception(
                "Failed to prepare all_users statement: " . $conn->error,
            );
        }

        $checkStmt->bind_param("iss", $is_admin, $item_id, $item_type);

        if (!$checkStmt->execute()) {
            throw new Exception(
                "Failed to execute all_users statement: " . $checkStmt->error,
            );
        }

        $checkStmt->bind_result($access_times, $times_accessed);
        $allUsersPermissionFound = $checkStmt->fetch();
        $checkStmt->close();

        if ($allUsersPermissionFound) {
            $userPermissionFound = true;
            $effectiveIdentifier = "all_users";
        }
    } else {
        $effectiveIdentifier = $identifier;
    }

    // Process permission if found
    if ($userPermissionFound) {
        $access_times = (int) $access_times;
        $times_accessed = (int) $times_accessed;

        error_log(
            "Found permission for $effectiveIdentifier: access_times=$access_times, times_accessed=$times_accessed",
        );

        // Check if access is allowed
        if ($access_times === -1 || $times_accessed < $access_times) {
            // Update access count
            $updateStmt = $conn->prepare(
                "UPDATE access_permissions
                 SET times_accessed = times_accessed + 1
                 WHERE identifier = ?
                 AND is_admin = ?
                 AND item_id = ?
                 AND item_type = ?",
            );

            if (!$updateStmt) {
                throw new Exception(
                    "Failed to prepare update statement: " . $conn->error,
                );
            }

            $updateStmt->bind_param(
                "siss",
                $effectiveIdentifier,
                $is_admin,
                $item_id,
                $item_type,
            );

            if ($updateStmt->execute()) {
                $new_times_accessed = $times_accessed + 1;
                echo json_encode([
                    "success" => true,
                    "message" => "Access granted",
                    "remaining_access" =>
                        $access_times === -1
                            ? -1
                            : $access_times - $new_times_accessed,
                    "times_accessed" => $new_times_accessed,
                ]);
            } else {
                throw new Exception(
                    "Failed to update access count: " . $updateStmt->error,
                );
            }

            $updateStmt->close();
        } else {
            // Access limit reached
            echo json_encode([
                "success" => false,
                "message" => "Access limit reached",
                "error_code" => "ACCESS_LIMIT_REACHED",
                "times_accessed" => $times_accessed,
                "access_times" => $access_times,
            ]);
        }
    } else {
        // No permission found - create new permission with unlimited access
        $insertStmt = $conn->prepare(
            "INSERT INTO access_permissions (identifier, is_admin, item_id, item_type, access_times, times_accessed)
             VALUES (?, ?, ?, ?, -1, 1)",
        );

        if (!$insertStmt) {
            throw new Exception(
                "Failed to prepare insert statement: " . $conn->error,
            );
        }

        $insertStmt->bind_param(
            "siss",
            $identifier,
            $is_admin,
            $item_id,
            $item_type,
        );

        if ($insertStmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Access granted (new permission created)",
                "remaining_access" => -1,
                "times_accessed" => 1,
            ]);
        } else {
            throw new Exception(
                "Failed to create permission: " . $insertStmt->error,
            );
        }

        $insertStmt->close();
    }
} catch (Exception $e) {
    error_log("Error in access check: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Internal server error",
        "error_code" => "INTERNAL_ERROR",
        "debug_message" => $e->getMessage(), // Remove this in production
    ]);
}

$conn->close();
?>
