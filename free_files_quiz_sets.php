<?php
include "GlobalConfigs.php";

session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    session_unset();
    session_destroy();
    exit(0);
}

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Connection failed: " . $conn->connect_error,
    ]);
    session_unset();
    session_destroy();
    exit();
}

// Function to log errors to a file
function logError($message)
{
    $logFile = "free_files_quiz_sets_errors.log";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Parse JSON data from request body for POST requests
$rawData = file_get_contents("php://input");
$data = null;
if (!empty($rawData)) {
    $data = json_decode($rawData, true);
}

// Handle different actions based on request parameters
$action = "";
if (isset($_GET["action"])) {
    $action = $_GET["action"];
} elseif (isset($data["action"])) {
    $action = $data["action"];
}

switch ($action) {
    case "get_free_items":
        getFreeItems($conn);
        break;
    case "grant_access_to_all_users":
        grantAccessToAllUsers($conn, $data);
        break;
    case "grant_access_to_logged_in_users":
        grantAccessToLoggedInUsers($conn, $data);
        break;
    case "delete":
        deleteAccess($conn, $data);
        break;
    case "delete_registered_users_access":
        deleteRegisteredUsersAccess($conn, $data);
        break;
    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action",
        ]);
        session_unset();
        session_destroy();
        exit();
}

// Function to get free files and quiz sets for a folder
function getFreeItems($conn)
{
    $folderId = mysqli_real_escape_string($conn, $_GET["folder_id"]);
    $userId = isset($_GET["user_id"])
        ? mysqli_real_escape_string($conn, $_GET["user_id"])
        : null;

    try {
        // Get files with access for all users
        $filesAllUsersQuery = "
            SELECT f.*, 'All Users' as access_type
            FROM files f
            JOIN free_files_quiz_sets fa ON f.id = fa.item_id
            WHERE fa.folder_id = ? AND fa.item_type = 'file' AND fa.access_type = 'All Users'
        ";

        // Get files with access for registered users only
        $filesRegisteredQuery = "
            SELECT f.*, 'Registered Users Only' as access_type
            FROM files f
            JOIN free_files_quiz_sets fa ON f.id = fa.item_id
            WHERE fa.folder_id = ? AND fa.item_type = 'file' AND fa.access_type = 'Registered Users Only'
        ";

        // Get quiz sets with access for all users
        $quizSetsAllUsersQuery = "
            SELECT qs.*, 'All Users' as access_type
            FROM quiz_sets qs
            JOIN free_files_quiz_sets fa ON qs.id = fa.item_id
            WHERE fa.folder_id = ? AND fa.item_type = 'quiz_set' AND fa.access_type = 'All Users'
        ";

        // Get quiz sets with access for registered users only
        $quizSetsRegisteredQuery = "
            SELECT qs.*, 'Registered Users Only' as access_type
            FROM quiz_sets qs
            JOIN free_files_quiz_sets fa ON qs.id = fa.item_id
            WHERE fa.folder_id = ? AND fa.item_type = 'quiz_set' AND fa.access_type = 'Registered Users Only'
        ";

        // Prepare and execute queries for files
        $filesAllUsersStmt = $conn->prepare($filesAllUsersQuery);
        $filesAllUsersStmt->bind_param("i", $folderId);
        $filesAllUsersStmt->execute();
        $filesAllUsersResult = $filesAllUsersStmt->get_result();

        $filesRegisteredStmt = $conn->prepare($filesRegisteredQuery);
        $filesRegisteredStmt->bind_param("i", $folderId);
        $filesRegisteredStmt->execute();
        $filesRegisteredResult = $filesRegisteredStmt->get_result();

        // Prepare and execute queries for quiz sets
        $quizSetsAllUsersStmt = $conn->prepare($quizSetsAllUsersQuery);
        $quizSetsAllUsersStmt->bind_param("i", $folderId);
        $quizSetsAllUsersStmt->execute();
        $quizSetsAllUsersResult = $quizSetsAllUsersStmt->get_result();

        $quizSetsRegisteredStmt = $conn->prepare($quizSetsRegisteredQuery);
        $quizSetsRegisteredStmt->bind_param("i", $folderId);
        $quizSetsRegisteredStmt->execute();
        $quizSetsRegisteredResult = $quizSetsRegisteredStmt->get_result();

        // Combine results for files with both access types
        $files = [];
        while ($row = $filesAllUsersResult->fetch_assoc()) {
            $files[] = $row;
        }

        while ($row = $filesRegisteredResult->fetch_assoc()) {
            // Only add registered users' files if user is logged in
            if ($userId) {
                $files[] = $row;
            }
        }

        // Combine results for quiz sets with both access types
        $quizSets = [];
        while ($row = $quizSetsAllUsersResult->fetch_assoc()) {
            $quizSets[] = $row;
        }

        while ($row = $quizSetsRegisteredResult->fetch_assoc()) {
            // Only add registered users' quiz sets if user is logged in
            if ($userId) {
                $quizSets[] = $row;
            }
        }

        echo json_encode([
            "status" => "success",
            "files" => $files,
            "quiz_sets" => $quizSets,
        ]);
    } catch (Exception $e) {
        logError("Error in getFreeItems: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" =>
                "An error occurred while fetching free items: " .
                $e->getMessage(),
        ]);
    } finally {
        session_unset();
        session_destroy();
    }
}

// Function to grant access to files/quiz sets for all users
function grantAccessToAllUsers($conn, $data)
{
    if (!isset($data["folder_id"])) {
        echo json_encode([
            "status" => "error",
            "message" => "Folder ID is required",
        ]);
        session_unset();
        session_destroy();
        return;
    }

    $folderId = $data["folder_id"];
    $fileIds = isset($data["file_ids"]) ? $data["file_ids"] : [];
    $quizSetIds = isset($data["quiz_set_ids"]) ? $data["quiz_set_ids"] : [];

    try {
        $conn->begin_transaction();

        // Process file access grants
        foreach ($fileIds as $fileId) {
            // Check if access already exists
            $checkQuery =
                "SELECT id FROM free_files_quiz_sets WHERE folder_id = ? AND item_id = ? AND item_type = 'file' AND access_type = 'All Users'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $folderId, $fileId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows == 0) {
                // Insert new access record
                $insertQuery =
                    "INSERT INTO free_files_quiz_sets (folder_id, item_id, item_type, access_type) VALUES (?, ?, 'file', 'All Users')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ii", $folderId, $fileId);
                $insertStmt->execute();
            }
        }

        // Process quiz set access grants
        foreach ($quizSetIds as $quizSetId) {
            // Check if access already exists
            $checkQuery =
                "SELECT id FROM free_files_quiz_sets WHERE folder_id = ? AND item_id = ? AND item_type = 'quiz_set' AND access_type = 'All Users'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $folderId, $quizSetId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows == 0) {
                // Insert new access record
                $insertQuery =
                    "INSERT INTO free_files_quiz_sets (folder_id, item_id, item_type, access_type) VALUES (?, ?, 'quiz_set', 'All Users')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ii", $folderId, $quizSetId);
                $insertStmt->execute();
            }
        }

        $conn->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Access granted to all users successfully",
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        logError("Error in grantAccessToAllUsers: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" =>
                "An error occurred while granting access: " . $e->getMessage(),
        ]);
    } finally {
        session_unset();
        session_destroy();
    }
}

// Function to grant access to files/quiz sets for logged-in users only
function grantAccessToLoggedInUsers($conn, $data)
{
    if (!isset($data["folder_id"])) {
        echo json_encode([
            "status" => "error",
            "message" => "Folder ID is required",
        ]);
        session_unset();
        session_destroy();
        return;
    }

    $folderId = $data["folder_id"];
    $fileIds = isset($data["file_ids"]) ? $data["file_ids"] : [];
    $quizSetIds = isset($data["quiz_set_ids"]) ? $data["quiz_set_ids"] : [];

    try {
        $conn->begin_transaction();

        // Process file access grants
        foreach ($fileIds as $fileId) {
            // Check if access already exists
            $checkQuery =
                "SELECT id FROM free_files_quiz_sets WHERE folder_id = ? AND item_id = ? AND item_type = 'file' AND access_type = 'Registered Users Only'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $folderId, $fileId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows == 0) {
                // Insert new access record
                $insertQuery =
                    "INSERT INTO free_files_quiz_sets (folder_id, item_id, item_type, access_type) VALUES (?, ?, 'file', 'Registered Users Only')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ii", $folderId, $fileId);
                $insertStmt->execute();
            }
        }

        // Process quiz set access grants
        foreach ($quizSetIds as $quizSetId) {
            // Check if access already exists
            $checkQuery =
                "SELECT id FROM free_files_quiz_sets WHERE folder_id = ? AND item_id = ? AND item_type = 'quiz_set' AND access_type = 'Registered Users Only'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $folderId, $quizSetId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows == 0) {
                // Insert new access record
                $insertQuery =
                    "INSERT INTO free_files_quiz_sets (folder_id, item_id, item_type, access_type) VALUES (?, ?, 'quiz_set', 'Registered Users Only')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ii", $folderId, $quizSetId);
                $insertStmt->execute();
            }
        }

        $conn->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Access granted to logged-in users successfully",
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        logError("Error in grantAccessToLoggedInUsers: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" =>
                "An error occurred while granting access: " . $e->getMessage(),
        ]);
    } finally {
        session_unset();
        session_destroy();
    }
}

// Function to delete access for all users
function deleteAccess($conn, $data)
{
    if (
        !isset($data["item_id"]) ||
        !isset($data["item_type"]) ||
        !isset($data["folder_id"])
    ) {
        echo json_encode([
            "status" => "error",
            "message" => "Item ID, Item Type, and Folder ID are required",
        ]);
        session_unset();
        session_destroy();
        return;
    }

    $itemId = $data["item_id"];
    $itemType = $data["item_type"];
    $folderId = $data["folder_id"];

    try {
        $deleteQuery =
            "DELETE FROM free_files_quiz_sets WHERE folder_id = ? AND item_id = ? AND item_type = ? AND access_type = 'All Users'";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("iis", $folderId, $itemId, $itemType);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Access revoked successfully",
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "No access record found to delete",
            ]);
        }
    } catch (Exception $e) {
        logError("Error in deleteAccess: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" =>
                "An error occurred while deleting access: " . $e->getMessage(),
        ]);
    } finally {
        session_unset();
        session_destroy();
    }
}

// Function to delete access for registered users only
function deleteRegisteredUsersAccess($conn, $data)
{
    if (!isset($data["item_id"]) || !isset($data["item_type"])) {
        echo json_encode([
            "status" => "error",
            "message" => "Item ID and Item Type are required",
        ]);
        session_unset();
        session_destroy();
        return;
    }

    $itemId = $data["item_id"];
    $itemType = $data["item_type"];

    try {
        $deleteQuery =
            "DELETE FROM free_files_quiz_sets WHERE item_id = ? AND item_type = ? AND access_type = 'Registered Users Only'";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("is", $itemId, $itemType);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Access revoked successfully",
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "No access record found to delete",
            ]);
        }
    } catch (Exception $e) {
        logError("Error in deleteRegisteredUsersAccess: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" =>
                "An error occurred while deleting access: " . $e->getMessage(),
        ]);
    } finally {
        session_unset();
        session_destroy();
    }
}

// Close the database connection
$conn->close();
session_unset();
session_destroy();

?>
