<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
ini_set('display_errors', 0); // Prevent errors from displaying
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php_errors.log'); // Update with actual path
error_log("Upload request received: " . print_r($_FILES, true));

// Check if file is uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    session_unset();
    session_destroy();
    exit;
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
    session_unset();
    session_destroy();
    exit;
}

// Log file details
error_log("File details: Name=" . $file['name'] . ", Size=" . $file['size'] . ", Type=" . $file['type']);

// Validate file type
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf',
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/ogg',
    'video/mp4', 'video/quicktime', 'video/mpeg'
];
$fileType = mime_content_type($file['tmp_name']);
error_log("Detected MIME type: $fileType");

if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid file type: $fileType"]);
    session_unset();
    session_destroy();
    exit;
}

// Ensure upload directory exists
$targetDir = 'questions/';
$baseDir = __DIR__;
if (!is_dir($baseDir . '/' . $targetDir)) {
    if (!mkdir($baseDir . '/' . $targetDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        session_unset();
        session_destroy();
        exit;
    }
}

// Verify directory permissions
if (!is_writable($baseDir . '/' . $targetDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload directory is not writable']);
    session_unset();
    session_destroy();
    exit;
}

// Generate unique filename with sanitized input
$filename = uniqid() . '_' . preg_replace("/[^A-Za-z0-9._-]/", '', basename($file['name']));
$targetPath = $baseDir . '/' . $targetDir . $filename;

// Stream file to disk with progress feedback
$input = fopen($file['tmp_name'], 'rb');
$output = fopen($targetPath, 'wb');
if (!$input || !$output) {
    error_log("Failed to open streams for file: " . $file['name']);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to open file streams']);
    session_unset();
    session_destroy();
    exit;
}

$fileSize = $file['size'];
$bytesRead = 0;
$chunkSize = 8192; // 8KB chunks
$lastReportedProgress = 0.0; // Track last reported progress percentage
while (!feof($input)) {
    $buffer = fread($input, $chunkSize);
    $bytesRead += strlen($buffer);
    fwrite($output, $buffer);

    // Log progress in 1% increments
    $currentProgress = ($bytesRead / $fileSize) * 100;
    $currentProgressPercent = floor($currentProgress * 100) / 100; // Round to 2 decimal places
    if ($currentProgressPercent >= $lastReportedProgress + 1 || $currentProgressPercent == 100.0) {
        error_log("Upload progress for file: $currentProgressPercent%");
        $lastReportedProgress = $currentProgressPercent;
    }
}

fclose($input);
fclose($output);

if (file_exists($targetPath)) {
    error_log("File saved: $targetPath");
    http_response_code(200);
    echo json_encode(['success' => true, 'filename' => $targetDir . $filename]);
    session_unset();
    session_destroy();
} else {
    error_log("Failed to save file: " . $file['name'] . " to $targetPath");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    session_unset();
    session_destroy();
}
?>