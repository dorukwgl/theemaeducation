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
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed.",
    ]);
    exit();
}

$action = $_GET["action"] ?? "";
$folder_id = isset($_GET["folder_id"]) ? (int) $_GET["folder_id"] : 0;

if ($action === "give_access") {
    $item_type = $_POST["item_type"] ?? "";
    $item_id = $_POST["item_id"] ?? 0;

    if (empty($item_type) || empty($item_id)) {
        echo json_encode(["status" => "error", "message" => "Invalid input"]);
        exit();
    }

    try {
        $table = "give_access_to_login_users";
        $stmt = $pdo->prepare("INSERT INTO $table (item_type, item_id, access_granted)
                               VALUES (:item_type, :item_id, 1)
                               ON DUPLICATE KEY UPDATE access_granted = 1");
        $stmt->bindParam(":item_type", $item_type);
        $stmt->bindParam(":item_id", $item_id);
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Access granted successfully",
        ]);
    } catch (PDOException $e) {
        error_log("Error granting access: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Error granting access",
        ]);
    }
} elseif ($action === "revoke_access") {
    $item_type = $_POST["item_type"] ?? "";
    $item_id = $_POST["item_id"] ?? 0;

    if (empty($item_type) || empty($item_id)) {
        echo json_encode(["status" => "error", "message" => "Invalid input"]);
        exit();
    }

    try {
        $table = "give_access_to_login_users";
        $stmt = $pdo->prepare(
            "DELETE FROM $table WHERE item_type = :item_type AND item_id = :item_id",
        );
        $stmt->bindParam(":item_type", $item_type);
        $stmt->bindParam(":item_id", $item_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Access revoked successfully",
            ]);
        } else {
            echo json_encode([
                "status" => "success",
                "message" => "Access entry not found or already revoked",
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error revoking access: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Error revoking access",
        ]);
    }
} elseif ($action === "get_granted_access_items") {
    if (empty($folder_id)) {
        echo json_encode([
            "status" => "error",
            "message" => "Folder ID is required",
        ]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                'file' AS item_type,
                f.id,
                f.name,
                f.file_path,
                f.icon_path
            FROM files f
            INNER JOIN give_access_to_login_users gatu ON f.id = gatu.item_id
            WHERE gatu.item_type = 'file' AND f.folder_id = :folder_id

            UNION ALL

            SELECT
                'quiz_set' AS item_type,
                qs.id,
                qs.name,
                NULL AS file_path,
                qs.icon_path
            FROM quiz_sets qs
            INNER JOIN give_access_to_login_users gatu ON qs.id = gatu.item_id
            WHERE gatu.item_type = 'quiz_set' AND qs.folder_id = :folder_id
        ");
        $stmt->bindParam(":folder_id", $folder_id, PDO::PARAM_INT);
        $stmt->execute();

        $grantedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $grantedItems]);
    } catch (PDOException $e) {
        error_log("Error fetching granted access items: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Error fetching granted access items",
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$pdo = null;
?>
