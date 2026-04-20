<?php
include "GlobalConfigs.php";

session_start();
ob_start(); // Start output buffering
// Comment out or remove debugging settings in production

header("Access-Control-Allow-Origin: *");
header(
    "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With",
);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    session_unset();
    session_destroy();
    ob_end_clean();
    exit();
}

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    error_log(
        "Database connection failed at " .
            date("Y-m-d H:i:s") .
            ": " .
            $conn->connect_error,
    );
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error,
    ]);
    session_unset();
    session_destroy();
    ob_end_clean();
    exit();
}
$conn->set_charset("utf8mb4");

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$conn->query("CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    icon_path VARCHAR(255),
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$conn->query("CREATE TABLE IF NOT EXISTS quiz_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT,
    name VARCHAR(255) NOT NULL,
    icon_path VARCHAR(255),
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$conn->query("CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_set_id INT,
    question_text TEXT NOT NULL,
    FOREIGN KEY (quiz_set_id) REFERENCES quiz_sets(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$action = $_GET["action"] ?? ($_POST["action"] ?? "");

function sanitizeFileName($filename)
{
    $filename = basename($filename);
    $filename = preg_replace("/[^A-Za-z0-9.\-_ ()]/", "_", $filename);
    $filename = preg_replace("/_+/", "_", $filename);
    $filename = trim($filename, "_");
    return $filename;
}

function sendJsonResponse($status, $message, $data = null)
{
    ob_end_clean();
    $response = ["status" => $status, "message" => $message];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    session_unset();
    session_destroy();
    exit();
}

switch ($action) {
    case "add_file":
        try {
            if (!isset($_POST["folder_id"])) {
                sendJsonResponse("error", "Missing folder_id");
            }
            if (empty($_FILES["file"])) {
                sendJsonResponse("error", "No file uploaded");
            }
            if ($_FILES["file"]["error"] != UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE =>
                        "File exceeds upload_max_filesize (" .
                        ini_get("upload_max_filesize") .
                        ")",
                    UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE in form",
                    UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
                    UPLOAD_ERR_NO_FILE => "No file was uploaded",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload",
                ];
                $error_msg =
                    $upload_errors[$_FILES["file"]["error"]] ??
                    "Unknown upload error (code: {$_FILES["file"]["error"]})";
                sendJsonResponse("error", "File upload error: $error_msg");
            }

            $folder_id = intval($_POST["folder_id"]);
            $submitted_file_name = $_POST["name"] ?? $_FILES["file"]["name"];
            $file_name = sanitizeFileName($submitted_file_name);

            // Validate file extension
            $allowed_extensions = [
                "pdf",
                "doc",
                "docx",
                "txt",
                "rtf",
                "odt",
                "mp3",
                "wav",
                "aac",
                "ogg",
                "flac",
                "m4a",
                "mp4",
                "mov",
                "avi",
                "mkv",
                "wmv",
                "flv",
                "jpg",
                "jpeg",
                "png",
                "gif",
                "zip",
                "rar",
                "7z",
                "xls",
                "xlsx",
                "ppt",
                "pptx",
                "csv",
                "json",
                "xml",
                "html",
            ];
            $original_extension = strtolower(
                pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION),
            );
            if (!in_array($original_extension, $allowed_extensions)) {
                sendJsonResponse(
                    "error",
                    "Invalid file extension: $original_extension. Allowed: " .
                        implode(", ", $allowed_extensions),
                );
            }

            $max_file_size = 1000 * 1024 * 1024;
            if ($_FILES["file"]["size"] > $max_file_size) {
                sendJsonResponse("error", "File size exceeds 1000MB limit");
            }

            $unique_file_name_base =
                time() .
                "_" .
                uniqid() .
                "_" .
                sanitizeFileName(
                    pathinfo($submitted_file_name, PATHINFO_FILENAME),
                );
            $upload_dir = "Uploads/files/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) {
                    sendJsonResponse(
                        "error",
                        "Failed to create upload directory: $upload_dir",
                    );
                }
            }
            $file_path =
                $upload_dir .
                $unique_file_name_base .
                "." .
                $original_extension;

            if (!move_uploaded_file($_FILES["file"]["tmp_name"], $file_path)) {
                sendJsonResponse(
                    "error",
                    "Failed to move uploaded file to $file_path. Check permissions.",
                );
            }

            $icon_path_db = null;
            if (
                !empty($_FILES["icon"]) &&
                $_FILES["icon"]["error"] == UPLOAD_ERR_OK
            ) {
                $icon_upload_dir = "Uploads/icons/";
                if (!is_dir($icon_upload_dir)) {
                    if (!mkdir($icon_upload_dir, 0775, true)) {
                        sendJsonResponse(
                            "error",
                            "Failed to create icon directory: $icon_upload_dir",
                        );
                    }
                }
                $icon_original_extension = strtolower(
                    pathinfo($_FILES["icon"]["name"], PATHINFO_EXTENSION),
                );
                $icon_unique_name =
                    time() .
                    "_icon_" .
                    uniqid() .
                    "_" .
                    sanitizeFileName(
                        pathinfo($_FILES["icon"]["name"], PATHINFO_FILENAME),
                    );
                $icon_full_path =
                    $icon_upload_dir .
                    $icon_unique_name .
                    "." .
                    $icon_original_extension;

                if (
                    !move_uploaded_file(
                        $_FILES["icon"]["tmp_name"],
                        $icon_full_path,
                    )
                ) {
                    sendJsonResponse(
                        "error",
                        "Failed to move icon file to $icon_full_path",
                    );
                } else {
                    $icon_path_db = $icon_full_path;
                }
            }

            $stmt = $conn->prepare(
                "INSERT INTO files (folder_id, name, file_path, icon_path) VALUES (?, ?, ?, ?)",
            );
            if (!$stmt) {
                unlink($file_path);
                throw new Exception("Prepare failed: " . $conn->error);
            }

            if (
                !$stmt->bind_param(
                    "isss",
                    $folder_id,
                    $file_name,
                    $file_path,
                    $icon_path_db,
                )
            ) {
                unlink($file_path);
                throw new Exception("Bind param failed: " . $stmt->error);
            }

            if ($stmt->execute()) {
                sendJsonResponse("success", "File added successfully", [
                    "id" => $stmt->insert_id,
                ]);
            } else {
                unlink($file_path);
                if ($icon_path_db && file_exists($icon_path_db)) {
                    unlink($icon_path_db);
                }
                throw new Exception(
                    "Database insertion failed: " . $stmt->error,
                );
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to add file: " . $e->getMessage(),
            );
        }
        break;

    case "get_files":
        try {
            if (!isset($_GET["folder_id"])) {
                throw new Exception("Folder ID required for get_files");
            }
            $requested_folder_id = intval($_GET["folder_id"]);
            $stmt = $conn->prepare(
                "SELECT id, folder_id, name, file_path, icon_path FROM files WHERE folder_id = ? ORDER BY id DESC",
            );

            if (!$stmt) {
                throw new Exception(
                    "Prepare failed (get_files): " . $conn->error,
                );
            }

            if (!$stmt->bind_param("i", $requested_folder_id)) {
                throw new Exception(
                    "Bind param failed (get_files): " . $stmt->error,
                );
            }

            if (!$stmt->execute()) {
                throw new Exception(
                    "Execute failed (get_files): " . $stmt->error,
                );
            }

            $stmt->store_result();
            $stmt->bind_result(
                $id,
                $folder_id_db,
                $name,
                $file_path_db,
                $icon_path_db,
            );

            $data = [];
            while ($stmt->fetch()) {
                $data[] = [
                    "id" => $id,
                    "folder_id" => $folder_id_db,
                    "name" => $name,
                    "file_path" => $file_path_db,
                    "icon_path" => $icon_path_db,
                ];
            }
            $stmt->close();

            sendJsonResponse("success", "Files retrieved successfully", $data);
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to get files: " . $e->getMessage(),
            );
        }
        break;

    case "get_all_files":
        try {
            $stmt = $conn->prepare(
                "SELECT id, folder_id, name, file_path, icon_path FROM files ORDER BY id DESC",
            );
            if (!$stmt) {
                throw new Exception(
                    "Prepare failed (get_all_files): " . $conn->error,
                );
            }
            if (!$stmt->execute()) {
                throw new Exception(
                    "Execute failed (get_all_files): " . $stmt->error,
                );
            }

            $stmt->store_result();
            $stmt->bind_result(
                $id,
                $folder_id_db,
                $name,
                $file_path_db,
                $icon_path_db,
            );
            $data = [];
            while ($stmt->fetch()) {
                $data[] = [
                    "id" => $id,
                    "folder_id" => $folder_id_db,
                    "name" => $name,
                    "file_path" => $file_path_db,
                    "icon_path" => $icon_path_db,
                ];
            }
            $stmt->close();

            sendJsonResponse(
                "success",
                "All files retrieved successfully",
                $data,
            );
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to get all files: " . $e->getMessage(),
            );
        }
        break;

    case "delete_file":
        try {
            $file_id_to_delete = isset($_POST["id"])
                ? intval($_POST["id"])
                : (isset($_GET["id"])
                    ? intval($_GET["id"])
                    : 0);

            if ($file_id_to_delete <= 0) {
                throw new Exception("Valid file ID required for delete_file");
            }

            $stmt_select = $conn->prepare(
                "SELECT file_path, icon_path FROM files WHERE id = ?",
            );
            if (!$stmt_select) {
                throw new Exception("Select prepare failed: " . $conn->error);
            }
            if (!$stmt_select->bind_param("i", $file_id_to_delete)) {
                throw new Exception(
                    "Select bind failed: " . $stmt_select->error,
                );
            }
            if (!$stmt_select->execute()) {
                throw new Exception(
                    "Select execute failed: " . $stmt_select->error,
                );
            }

            $stmt_select->store_result();
            if ($stmt_select->num_rows == 0) {
                $stmt_select->close();
                throw new Exception(
                    "File not found with ID: $file_id_to_delete",
                );
            }

            $file_path_to_unlink = null;
            $icon_path_to_unlink = null;
            $stmt_select->bind_result(
                $file_path_to_unlink,
                $icon_path_to_unlink,
            );
            $stmt_select->fetch();
            $stmt_select->close();

            if ($file_path_to_unlink && file_exists($file_path_to_unlink)) {
                if (!@unlink($file_path_to_unlink)) {
                    error_log("Failed to delete file: $file_path_to_unlink");
                }
            }
            if ($icon_path_to_unlink && file_exists($icon_path_to_unlink)) {
                if (!@unlink($icon_path_to_unlink)) {
                    error_log("Failed to delete icon: $icon_path_to_unlink");
                }
            }

            $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
            if (!$stmt_delete) {
                throw new Exception("Delete prepare failed: " . $conn->error);
            }
            if (!$stmt_delete->bind_param("i", $file_id_to_delete)) {
                throw new Exception(
                    "Delete bind failed: " . $stmt_delete->error,
                );
            }

            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                    sendJsonResponse("success", "File deleted successfully");
                } else {
                    throw new Exception("File not found or already deleted");
                }
            } else {
                throw new Exception(
                    "Failed to delete file from database: " .
                        $stmt_delete->error,
                );
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to delete file: " . $e->getMessage(),
            );
        }
        break;

    case "edit_file":
        try {
            $file_id_to_edit = isset($_POST["id"])
                ? intval($_POST["id"])
                : (isset($_GET["id"])
                    ? intval($_GET["id"])
                    : 0);
            $new_file_name = isset($_POST["name"])
                ? sanitizeFileName($_POST["name"])
                : (isset($_GET["name"])
                    ? sanitizeFileName($_GET["name"])
                    : "");

            if ($file_id_to_edit <= 0 || empty($new_file_name)) {
                throw new Exception(
                    "Valid file ID and name required for edit_file",
                );
            }

            // Check if file exists
            $check_stmt = $conn->prepare(
                "SELECT icon_path FROM files WHERE id = ?",
            );
            if (!$check_stmt) {
                throw new Exception("Check prepare failed: " . $conn->error);
            }
            if (!$check_stmt->bind_param("i", $file_id_to_edit)) {
                throw new Exception("Check bind failed: " . $check_stmt->error);
            }
            if (!$check_stmt->execute()) {
                throw new Exception(
                    "Check execute failed: " . $check_stmt->error,
                );
            }

            $check_stmt->store_result();
            if ($check_stmt->num_rows == 0) {
                $check_stmt->close();
                throw new Exception("File not found with ID: $file_id_to_edit");
            }

            $current_icon_path = null;
            $check_stmt->bind_result($current_icon_path);
            $check_stmt->fetch();
            $check_stmt->close();

            $icon_path_db = $current_icon_path; // Retain current icon if no new one uploaded
            if (
                !empty($_FILES["icon"]) &&
                $_FILES["icon"]["error"] == UPLOAD_ERR_OK
            ) {
                $icon_upload_dir = "Uploads/icons/";
                if (!is_dir($icon_upload_dir)) {
                    if (!mkdir($icon_upload_dir, 0775, true)) {
                        throw new Exception(
                            "Failed to create icon directory: $icon_upload_dir",
                        );
                    }
                }
                $icon_original_extension = strtolower(
                    pathinfo($_FILES["icon"]["name"], PATHINFO_EXTENSION),
                );
                $icon_unique_name =
                    time() .
                    "_icon_" .
                    uniqid() .
                    "_" .
                    sanitizeFileName(
                        pathinfo($_FILES["icon"]["name"], PATHINFO_FILENAME),
                    );
                $icon_full_path =
                    $icon_upload_dir .
                    $icon_unique_name .
                    "." .
                    $icon_original_extension;

                if (
                    !move_uploaded_file(
                        $_FILES["icon"]["tmp_name"],
                        $icon_full_path,
                    )
                ) {
                    throw new Exception(
                        "Failed to move icon file to $icon_full_path",
                    );
                }

                // Delete old icon if it exists
                if ($current_icon_path && file_exists($current_icon_path)) {
                    if (!@unlink($current_icon_path)) {
                        error_log(
                            "Failed to delete old file icon: $current_icon_path",
                        );
                    }
                }

                $icon_path_db = $icon_full_path;
            }

            // Update file with new name and icon path (if updated)
            $stmt = $conn->prepare(
                "UPDATE files SET name = ?, icon_path = ? WHERE id = ?",
            );
            if (!$stmt) {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception("Update prepare failed: " . $conn->error);
            }
            if (
                !$stmt->bind_param(
                    "ssi",
                    $new_file_name,
                    $icon_path_db,
                    $file_id_to_edit,
                )
            ) {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception("Update bind failed: " . $stmt->error);
            }

            if ($stmt->execute()) {
                sendJsonResponse("success", "File updated successfully");
            } else {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception("Failed to update file: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to update file: " . $e->getMessage(),
            );
        }
        break;

    case "add_quiz_set":
        try {
            if (!isset($_POST["folder_id"]) || !isset($_POST["name"])) {
                throw new Exception("Missing parameters for add_quiz_set");
            }
            $folder_id_qs = intval($_POST["folder_id"]);
            $quiz_name = sanitizeFileName($_POST["name"]);
            $icon_path_qs_db = null;

            if (
                !empty($_FILES["icon"]) &&
                $_FILES["icon"]["error"] == UPLOAD_ERR_OK
            ) {
                $icon_upload_dir_qs = "Uploads/icons/";
                if (!is_dir($icon_upload_dir_qs)) {
                    if (!mkdir($icon_upload_dir_qs, 0775, true)) {
                        throw new Exception(
                            "Failed to create quiz icon directory",
                        );
                    }
                }
                $icon_original_extension_qs = strtolower(
                    pathinfo($_FILES["icon"]["name"], PATHINFO_EXTENSION),
                );
                $icon_unique_name_qs =
                    time() .
                    "_quizicon_" .
                    uniqid() .
                    "_" .
                    sanitizeFileName(
                        pathinfo($_FILES["icon"]["name"], PATHINFO_FILENAME),
                    );
                $icon_full_path_qs =
                    $icon_upload_dir_qs .
                    $icon_unique_name_qs .
                    "." .
                    $icon_original_extension_qs;

                if (
                    !move_uploaded_file(
                        $_FILES["icon"]["tmp_name"],
                        $icon_full_path_qs,
                    )
                ) {
                    throw new Exception(
                        "Failed to move quiz icon file. Check permissions and path: " .
                            $icon_full_path_qs,
                    );
                } else {
                    $icon_path_qs_db = $icon_full_path_qs;
                }
            }

            $stmt = $conn->prepare(
                "INSERT INTO quiz_sets (folder_id, name, icon_path) VALUES (?, ?, ?)",
            );
            if (!$stmt) {
                throw new Exception("Quiz add prepare failed: " . $conn->error);
            }
            if (
                !$stmt->bind_param(
                    "iss",
                    $folder_id_qs,
                    $quiz_name,
                    $icon_path_qs_db,
                )
            ) {
                throw new Exception("Quiz add bind failed: " . $stmt->error);
            }

            if ($stmt->execute()) {
                sendJsonResponse("success", "Quiz set added successfully", [
                    "id" => $stmt->insert_id,
                ]);
            } else {
                if ($icon_path_qs_db && file_exists($icon_path_qs_db)) {
                    @unlink($icon_path_qs_db);
                }
                throw new Exception("Failed to add quiz set: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to add quiz set: " . $e->getMessage(),
            );
        }
        break;

    case "get_quiz_sets":
        try {
            if (!isset($_GET["folder_id"])) {
                throw new Exception("Folder ID required for get_quiz_sets");
            }
            $requested_folder_id_qs = intval($_GET["folder_id"]);
            $stmt = $conn->prepare(
                "SELECT id, folder_id, name, icon_path FROM quiz_sets WHERE folder_id = ? ORDER BY id DESC",
            );

            if (!$stmt) {
                throw new Exception("Quiz get prepare failed: " . $conn->error);
            }
            if (!$stmt->bind_param("i", $requested_folder_id_qs)) {
                throw new Exception("Quiz get bind failed: " . $stmt->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Quiz get execute failed: " . $stmt->error);
            }

            $stmt->store_result();
            $stmt->bind_result(
                $id_qs,
                $folder_id_qs_db,
                $name_qs,
                $icon_path_qs,
            );
            $data_qs = [];
            while ($stmt->fetch()) {
                $data_qs[] = [
                    "id" => $id_qs,
                    "folder_id" => $folder_id_qs_db,
                    "name" => $name_qs,
                    "icon_path" => $icon_path_qs,
                ];
            }
            $stmt->close();

            sendJsonResponse(
                "success",
                "Quiz sets retrieved successfully",
                $data_qs,
            );
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to get quiz sets: " . $e->getMessage(),
            );
        }
        break;

    case "get_all_quiz_sets":
        try {
            $stmt = $conn->prepare(
                "SELECT id, folder_id, name, icon_path FROM quiz_sets ORDER BY id DESC",
            );
            if (!$stmt) {
                throw new Exception(
                    "All quiz get prepare failed: " . $conn->error,
                );
            }
            if (!$stmt->execute()) {
                throw new Exception(
                    "All quiz get execute failed: " . $stmt->error,
                );
            }

            $stmt->store_result();
            $stmt->bind_result(
                $id_qs,
                $folder_id_qs_db,
                $name_qs,
                $icon_path_qs,
            );
            $data_aqs = [];
            while ($stmt->fetch()) {
                $data_aqs[] = [
                    "id" => $id_qs,
                    "folder_id" => $folder_id_qs_db,
                    "name" => $name_qs,
                    "icon_path" => $icon_path_qs,
                ];
            }
            $stmt->close();

            sendJsonResponse(
                "success",
                "All quiz sets retrieved successfully",
                $data_aqs,
            );
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to get all quiz sets: " . $e->getMessage(),
            );
        }
        break;

    case "delete_quiz_set":
        try {
            $quiz_id_to_delete = isset($_POST["id"])
                ? intval($_POST["id"])
                : (isset($_GET["id"])
                    ? intval($_GET["id"])
                    : 0);

            if ($quiz_id_to_delete <= 0) {
                throw new Exception("Valid quiz ID required for delete");
            }

            $stmt_select_qs = $conn->prepare(
                "SELECT icon_path FROM quiz_sets WHERE id = ?",
            );
            if (!$stmt_select_qs) {
                throw new Exception("Select prepare failed: " . $conn->error);
            }
            if (!$stmt_select_qs->bind_param("i", $quiz_id_to_delete)) {
                throw new Exception(
                    "Select bind failed: " . $stmt_select_qs->error,
                );
            }
            if (!$stmt_select_qs->execute()) {
                throw new Exception(
                    "Select execute failed: " . $stmt_select_qs->error,
                );
            }

            $stmt_select_qs->store_result();
            if ($stmt_select_qs->num_rows == 0) {
                $stmt_select_qs->close();
                throw new Exception(
                    "Quiz set not found with ID: $quiz_id_to_delete",
                );
            }

            $icon_path_qs_to_unlink = null;
            $stmt_select_qs->bind_result($icon_path_qs_to_unlink);
            $stmt_select_qs->fetch();
            $stmt_select_qs->close();

            if (
                $icon_path_qs_to_unlink &&
                file_exists($icon_path_qs_to_unlink)
            ) {
                if (!@unlink($icon_path_qs_to_unlink)) {
                    error_log(
                        "Failed to delete quiz icon: $icon_path_qs_to_unlink",
                    );
                }
            }

            $stmt_delete_qs = $conn->prepare(
                "DELETE FROM quiz_sets WHERE id = ?",
            );
            if (!$stmt_delete_qs) {
                throw new Exception("Delete prepare failed: " . $conn->error);
            }
            if (!$stmt_delete_qs->bind_param("i", $quiz_id_to_delete)) {
                throw new Exception(
                    "Delete bind failed: " . $stmt_delete_qs->error,
                );
            }
            if ($stmt_delete_qs->execute()) {
                if ($stmt_delete_qs->affected_rows > 0) {
                    sendJsonResponse(
                        "success",
                        "Quiz set deleted successfully",
                    );
                } else {
                    throw new Exception(
                        "Quiz set not found or already deleted",
                    );
                }
            } else {
                throw new Exception(
                    "Failed to delete quiz set: " . $stmt_delete_qs->error,
                );
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to delete quiz set: " . $e->getMessage(),
            );
        }
        break;

    case "edit_quiz_set":
        try {
            $quiz_id_to_edit = isset($_POST["id"])
                ? intval($_POST["id"])
                : (isset($_GET["id"])
                    ? intval($_GET["id"])
                    : 0);
            $new_quiz_name = isset($_POST["name"])
                ? sanitizeFileName($_POST["name"])
                : (isset($_GET["name"])
                    ? sanitizeFileName($_GET["name"])
                    : "");

            if ($quiz_id_to_edit <= 0 || empty($new_quiz_name)) {
                throw new Exception(
                    "Valid quiz ID and name required for edit_quiz_set",
                );
            }

            // Check if quiz set exists
            $check_stmt = $conn->prepare(
                "SELECT icon_path FROM quiz_sets WHERE id = ?",
            );
            if (!$check_stmt) {
                throw new Exception("Check prepare failed: " . $conn->error);
            }
            if (!$check_stmt->bind_param("i", $quiz_id_to_edit)) {
                throw new Exception("Check bind failed: " . $check_stmt->error);
            }
            if (!$check_stmt->execute()) {
                throw new Exception(
                    "Check execute failed: " . $check_stmt->error,
                );
            }

            $check_stmt->store_result();
            if ($check_stmt->num_rows == 0) {
                $check_stmt->close();
                throw new Exception(
                    "Quiz set not found with ID: $quiz_id_to_edit",
                );
            }

            $current_icon_path = null;
            $check_stmt->bind_result($current_icon_path);
            $check_stmt->fetch();
            $check_stmt->close();

            $icon_path_db = $current_icon_path; // Retain current icon if no new one uploaded
            if (
                !empty($_FILES["icon"]) &&
                $_FILES["icon"]["error"] == UPLOAD_ERR_OK
            ) {
                $icon_upload_dir = "Uploads/icons/";
                if (!is_dir($icon_upload_dir)) {
                    if (!mkdir($icon_upload_dir, 0775, true)) {
                        throw new Exception(
                            "Failed to create icon directory: $icon_upload_dir",
                        );
                    }
                }
                $icon_original_extension = strtolower(
                    pathinfo($_FILES["icon"]["name"], PATHINFO_EXTENSION),
                );
                $icon_unique_name =
                    time() .
                    "_quizicon_" .
                    uniqid() .
                    "_" .
                    sanitizeFileName(
                        pathinfo($_FILES["icon"]["name"], PATHINFO_FILENAME),
                    );
                $icon_full_path =
                    $icon_upload_dir .
                    $icon_unique_name .
                    "." .
                    $icon_original_extension;

                if (
                    !move_uploaded_file(
                        $_FILES["icon"]["tmp_name"],
                        $icon_full_path,
                    )
                ) {
                    throw new Exception(
                        "Failed to move icon file to $icon_full_path",
                    );
                }

                // Delete old icon if it exists
                if ($current_icon_path && file_exists($current_icon_path)) {
                    if (!@unlink($current_icon_path)) {
                        error_log(
                            "Failed to delete old quiz icon: $current_icon_path",
                        );
                    }
                }

                $icon_path_db = $icon_full_path;
            }

            // Update quiz set with new name and icon path (if updated)
            $stmt = $conn->prepare(
                "UPDATE quiz_sets SET name = ?, icon_path = ? WHERE id = ?",
            );
            if (!$stmt) {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception("Update prepare failed: " . $conn->error);
            }
            if (
                !$stmt->bind_param(
                    "ssi",
                    $new_quiz_name,
                    $icon_path_db,
                    $quiz_id_to_edit,
                )
            ) {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception("Update bind failed: " . $stmt->error);
            }

            if ($stmt->execute()) {
                sendJsonResponse("success", "Quiz set updated successfully");
            } else {
                if (
                    $icon_path_db &&
                    $icon_path_db != $current_icon_path &&
                    file_exists($icon_path_db)
                ) {
                    @unlink($icon_path_db); // Cleanup new icon on failure
                }
                throw new Exception(
                    "Failed to update quiz set: " . $stmt->error,
                );
            }
        } catch (Exception $e) {
            sendJsonResponse(
                "error",
                "Failed to update quiz set: " . $e->getMessage(),
            );
        }
        break;

    default:
        sendJsonResponse(
            "error",
            "Invalid action specified: " . htmlspecialchars($action),
        );
}

$conn->close();
session_unset();
session_destroy();
ob_end_clean();
?>
