<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE"); // Add DELETE
header("Content-Type: application/json");
require_once 'config.php';
 
$method = $_SERVER['REQUEST_METHOD'];

function handleUpdate($pdo) {
    try {
        // For PUT, PHP does not populate $_POST by default when using raw PUT requests.
        // If the client uses multipart/form-data with method override, it will still hit POST on server side.
        // Assuming client sends PUT as form-data via frameworks that populate $_POST/$_FILES.
        $putId = $_POST['id'] ?? null;
        if (!$putId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id for update']);
            return;
        }

        $title = $_POST['title'] ?? '';
        $text_content = $_POST['text_content'] ?? '';

        // Remove existing rows for this id
        $pdo->prepare('DELETE FROM notices WHERE id = ?')->execute([$putId]);

        // Storage directories
        $storageDirAbs = __DIR__ . '/Uploads/';
        $storageDirRel = 'Uploads/';
        if (!is_dir($storageDirAbs)) {
            mkdir($storageDirAbs, 0777, true);
        }

        $hasFiles = isset($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name']) && count(array_filter($_FILES['files']['name'])) > 0;

        if (!$hasFiles) {
            $stmt = $pdo->prepare('INSERT INTO notices (id, title, text_content, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$putId, $title, $text_content]);
        } else {
            $fileCount = count($_FILES['files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $err = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                $origName = $_FILES['files']['name'][$i] ?? '';
                $tmpName = $_FILES['files']['tmp_name'][$i] ?? '';

                if ($err === UPLOAD_ERR_NO_FILE || $origName === '') {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                    ];
                    throw new Exception('Upload error: ' . ($uploadErrors[$err] ?? 'Unknown error'));
                }
                if (!is_uploaded_file($tmpName)) {
                    throw new Exception('Possible file upload attack detected.');
                }

                $base = pathinfo($origName, PATHINFO_FILENAME);
                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
                $newName = $putId . '_' . uniqid() . '_' . $safeBase . ($ext ? ('.' . $ext) : '');

                $targetAbs = $storageDirAbs . $newName;
                $targetRel = $storageDirRel . $newName;

                if (!move_uploaded_file($tmpName, $targetAbs)) {
                    throw new Exception('Failed to move uploaded file');
                }

                $stmt = $pdo->prepare('INSERT INTO notices (id, title, text_content, file_name, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$putId, $title, $text_content, $origName, $targetRel]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Error updating notice: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function handlePost($pdo) {
    try {
        $id = $_POST['id'] ?? uniqid();
        $title = $_POST['title'] ?? '';
        $text_content = $_POST['text_content'] ?? '';

        // Storage directories (absolute for moving files, relative for DB)
        $storageDirAbs = __DIR__ . '/Uploads/';
        $storageDirRel = 'Uploads/';
        if (!is_dir($storageDirAbs)) {
            mkdir($storageDirAbs, 0777, true);
        }

        $hasFiles = isset($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name']) && count(array_filter($_FILES['files']['name'])) > 0;

        if (!$hasFiles) {
            // No files uploaded, just insert the notice metadata
            $stmt = $pdo->prepare('INSERT INTO notices (id, title, text_content, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$id, $title, $text_content]);
        } else {
            // Handle multiple file uploads
            $fileCount = count($_FILES['files']['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                $err = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                $origName = $_FILES['files']['name'][$i] ?? '';
                $tmpName = $_FILES['files']['tmp_name'][$i] ?? '';

                if ($err === UPLOAD_ERR_NO_FILE || $origName === '') {
                    continue; // skip empty slot
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                    ];
                    echo json_encode(['success' => false, 'error' => 'Upload error: ' . ($uploadErrors[$err] ?? 'Unknown error')]);
                    throw new Exception('Upload error: ' . ($uploadErrors[$err] ?? 'Unknown error'));
                }
                if (!is_uploaded_file($tmpName)) {
                    throw new Exception('Possible file upload attack detected.');
                }

                // Sanitize and create a unique target name
                $base = pathinfo($origName, PATHINFO_FILENAME);
                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
                $newName = $id . '_' . uniqid() . '_' . $safeBase . ($ext ? ('.' . $ext) : '');

                $targetAbs = $storageDirAbs . $newName;
                $targetRel = $storageDirRel . $newName; // store this in DB

                if (!move_uploaded_file($tmpName, $targetAbs)) {
                    throw new Exception('Failed to move uploaded file');
                }

                $stmt = $pdo->prepare('INSERT INTO notices (id, title, text_content, file_name, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$id, $title, $text_content, $origName, $targetRel]);
            }
        }
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        error_log("Error adding notice: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error processing request: ' . $e->getMessage()]);
    }
}

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query('SELECT * FROM notices ORDER BY created_at DESC');
            $notices = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $notices[$row['id']] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'text_content' => $row['text_content'],
                    'files' => []
                ];
                if ($row['file_name'] && $row['file_path']) {
                    $notices[$row['id']]['files'][] = [
                        'file_name' => $row['file_name'],
                        'file_path' => $row['file_path']
                    ];
                }
            }
            echo json_encode(array_values($notices));
        } catch (Exception $e) {
            error_log("Error fetching notices: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;

    case 'POST':
        $action = $_POST["action"];
        $title = $_POST['title'];
        if ($action == "create")
            return handlePost($pdo);

        else if ($action == "update")
            return handleUpdate($pdo);

        else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
        }
        break;

    case 'DELETE':
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing id parameter']);
                return;
            }
            $stmt = $pdo->prepare('DELETE FROM notices WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notice not found']);
            }
        } catch (Exception $e) {
            error_log("Error deleting notice: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        break;
}