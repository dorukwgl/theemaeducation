<?php
include "GlobalConfigs.php";

session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    session_unset();
    session_destroy();
    exit();
}

// Database connection parameters
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

try {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception(
            "Database connection failed: " . $conn->connect_error,
        );
    }

    $conn->set_charset("utf8");

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate and sanitize input
        $identifier = isset($_POST["identifier"])
            ? trim($_POST["identifier"])
            : "";
        $is_admin =
            isset($_POST["is_admin"]) && $_POST["is_admin"] === "true" ? 1 : 0;
        $item_id = isset($_POST["item_id"]) ? intval($_POST["item_id"]) : 0;
        $item_type = isset($_POST["item_type"])
            ? trim($_POST["item_type"])
            : "";

        error_log(
            "Check access - Identifier: $identifier, Is Admin: $is_admin, Item ID: $item_id, Item Type: $item_type",
        );

        if (empty($identifier)) {
            throw new Exception("Identifier is required");
        }

        if ($item_id <= 0) {
            throw new Exception("Invalid item ID: $item_id");
        }

        if (!in_array($item_type, ["file", "quiz_set"])) {
            throw new Exception("Invalid item type: $item_type");
        }

        // Admins have unrestricted access
        if ($is_admin) {
            echo json_encode([
                "success" => true,
                "can_access" => true,
                "has_permission" => true,
                "is_active" => 1,
                "access_times" => -1,
                "times_accessed" => 0,
                "message" => "Admin access granted",
            ]);
            session_unset();
            session_destroy();
            $conn->close();
            exit();
        }

        // Check activation status
        $stmt = $conn->prepare(
            "SELECT is_activated FROM item_activation_status WHERE item_type = ? AND item_id = ?",
        );
        if (!$stmt) {
            throw new Exception(
                "Failed to prepare activation check query: " . $conn->error,
            );
        }

        $stmt->bind_param("si", $item_type, $item_id);
        if (!$stmt->execute()) {
            throw new Exception(
                "Failed to execute activation check query: " . $stmt->error,
            );
        }

        $stmt->bind_result($is_activated);
        $is_active = $stmt->fetch() && $is_activated == 1 ? 1 : 0;
        $stmt->close();

        error_log(
            "Item ID: $item_id, Item Type: $item_type, Is Active: $is_active",
        );

        // For guest users, allow access only if activated
        if ($identifier === "guest") {
            echo json_encode([
                "success" => true,
                "can_access" => $is_active == 1,
                "has_permission" => false,
                "is_active" => $is_active,
                "access_times" => -1,
                "times_accessed" => 0,
                "message" =>
                    $is_active == 1
                        ? "Guest access granted"
                        : "Item not activated by admin",
            ]);
            session_unset();
            session_destroy();
            $conn->close();
            exit();
        }

        // Check permissions for non-guest users
        $stmt = $conn->prepare(
            "SELECT access_times, times_accessed
             FROM access_permissions
             WHERE identifier = ? AND is_admin = ? AND item_id = ? AND item_type = ?",
        );

        if (!$stmt) {
            throw new Exception(
                "Failed to prepare access check query: " . $conn->error,
            );
        }

        $stmt->bind_param("siis", $identifier, $is_admin, $item_id, $item_type);

        if (!$stmt->execute()) {
            throw new Exception(
                "Failed to execute access check query: " . $stmt->error,
            );
        }

        $stmt->bind_result($access_times, $times_accessed);
        if ($stmt->fetch()) {
            $access_times = intval($access_times);
            $times_accessed = intval($times_accessed);

            $has_permission =
                $access_times == -1 ||
                $access_times == 0 ||
                $times_accessed < $access_times;
            $can_access = $has_permission && $is_active == 1;

            error_log(
                "Item ID: $item_id, Has Permission: $has_permission, Can Access: $can_access, Access Times: $access_times, Times Accessed: $times_accessed",
            );

            echo json_encode([
                "success" => true,
                "can_access" => $can_access,
                "has_permission" => $has_permission,
                "is_active" => $is_active,
                "access_times" => $access_times,
                "times_accessed" => $times_accessed,
                "message" => $can_access
                    ? "Access granted"
                    : ($has_permission
                        ? "Item not activated by admin"
                        : "Access limit reached"),
            ]);
        } else {
            error_log(
                "No permission found for Item ID: $item_id, Identifier: $identifier",
            );
            echo json_encode([
                "success" => true,
                "can_access" => false,
                "has_permission" => false,
                "is_active" => $is_active,
                "access_times" => 0,
                "times_accessed" => 0,
                "message" => "No access permission found",
            ]);
        }

        $stmt->close();
    } else {
        throw new Exception("Only POST method allowed");
    }
} catch (Exception $e) {
    error_log("Check access error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "can_access" => false,
        "has_permission" => false,
        "is_active" => 0,
        "access_times" => 0,
        "times_accessed" => 0,
        "message" => "Server error: " . $e->getMessage(),
    ]);
} finally {
    session_unset();
    session_destroy();
    if (isset($conn)) {
        $conn->close();
    }
}
?>
