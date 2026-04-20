<?php
include "GlobalConfigs.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed.",
    ]);
    exit();
}

$action = $_GET["action"] ?? "";

if ($action === "get_access_granted") {
    $folder_id = $_GET["folder_id"] ?? 0;
    try {
        $stmt = $conn->prepare("
            SELECT g.id, g.folder_id, g.file_id, g.quiz_set_id,
                   COALESCE(f.name, q.name) as name,
                   COALESCE(f.icon_path, q.icon_path) as icon_path,
                   f.file_path
            FROM give_access_to_all_users g
            LEFT JOIN files f ON g.file_id = f.id
            LEFT JOIN quiz_sets q ON g.quiz_set_id = q.id
            WHERE g.folder_id = :folder_id AND g.access_granted = 1
        ");
        $stmt->execute(["folder_id" => $folder_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $items]);
    } catch (Exception $e) {
        error_log("Error fetching access granted items: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Error fetching access granted items",
        ]);
    }
    exit();
}

if ($action === "delete_access") {
    $input = json_decode(file_get_contents("php://input"), true);
    $access_id = $input["access_id"] ?? 0;

    if ($access_id <= 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid access ID",
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare(
            "DELETE FROM give_access_to_all_users WHERE id = :access_id",
        );
        $stmt->execute(["access_id" => $access_id]);
        echo json_encode([
            "status" => "success",
            "message" => "Access removed successfully",
        ]);
    } catch (Exception $e) {
        error_log("Error deleting access: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Error deleting access",
        ]);
    }
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$file_ids = $input["file_ids"] ?? [];
$quiz_set_ids = $input["quiz_set_ids"] ?? [];
$folder_id = $input["folder_id"] ?? 0;

if (empty($file_ids) && empty($quiz_set_ids)) {
    echo json_encode(["status" => "error", "message" => "No items selected"]);
    exit();
}

try {
    $conn->beginTransaction();

    foreach ($file_ids as $file_id) {
        $stmt = $conn->prepare(
            "INSERT INTO give_access_to_all_users (folder_id, file_id, quiz_set_id, access_granted) VALUES (:folder_id, :file_id, NULL, 1)",
        );
        $stmt->execute(["folder_id" => $folder_id, "file_id" => $file_id]);
    }

    foreach ($quiz_set_ids as $quiz_set_id) {
        $stmt = $conn->prepare(
            "INSERT INTO give_access_to_all_users (folder_id, file_id, quiz_set_id, access_granted) VALUES (:folder_id, NULL, :quiz_set_id, 1)",
        );
        $stmt->execute([
            "folder_id" => $folder_id,
            "quiz_set_id" => $quiz_set_id,
        ]);
    }

    $conn->commit();
    echo json_encode([
        "status" => "success",
        "message" => "Access granted successfully",
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error granting access: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Error granting access: " . $e->getMessage(),
    ]);
}

$conn = null;
?>
