<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Utility: Handle file upload
function uploadIcon($fileKey = 'icon') {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'Uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = basename($_FILES[$fileKey]['name']);
        $filePath = $uploadDir . uniqid('folder_') . '_' . $fileName;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $filePath)) {
            return $filePath;
        }
    }
    return '';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $iconPath = uploadIcon();

        $stmt = $conn->prepare("INSERT INTO folders (name, icon_path) VALUES (?, ?)");
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("ss", $name, $iconPath);
        $success = $stmt->execute();
        $stmt->close();

        echo $success
            ? json_encode(['success' => 'Folder added successfully'])
            : json_encode(['error' => 'Error adding folder: ' . $conn->error]);
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $iconPath = uploadIcon();

        if ($iconPath) {
            $stmt = $conn->prepare("UPDATE folders SET name = ?, icon_path = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $iconPath, $id);
        } else {
            $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
        }

        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }

        $success = $stmt->execute();
        $stmt->close();

        echo $success
            ? json_encode(['success' => 'Folder updated successfully'])
            : json_encode(['error' => 'Error updating folder: ' . $conn->error]);
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // Fetch icon path before deleting record
        $stmtSelect = $conn->prepare("SELECT icon_path FROM folders WHERE id = ?");
        if (!$stmtSelect) {
            echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $stmtSelect->bind_param("i", $id);
        $stmtSelect->execute();
        $stmtSelect->bind_result($iconPath);
        $stmtSelect->fetch();
        $stmtSelect->close();

        // Delete icon file if exists
        if ($iconPath && file_exists($iconPath)) {
            unlink($iconPath);
        }

        // Delete folder record
        $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        echo $success
            ? json_encode(['success' => 'Folder deleted successfully'])
            : json_encode(['error' => 'Error deleting folder: ' . $conn->error]);
        exit;
    }
}

// Handle GET: return all folders with full icon URL
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/";

    $result = $conn->query("SELECT id, name, icon_path FROM folders ORDER BY id DESC");
    if (!$result) {
        echo json_encode(['error' => 'Query failed: ' . $conn->error]);
        exit;
    }

    $folders = [];
    while ($row = $result->fetch_assoc()) {
        $row['icon_url'] = $row['icon_path'] ? $baseURL . $row['icon_path'] : null;
        $folders[] = $row;
    }

    echo json_encode($folders);
    exit;
}