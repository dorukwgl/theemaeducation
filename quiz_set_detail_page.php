<?php
include "GlobalConfigs.php";

// Start the session
session_start();

// Disable HTML error reporting and ensure clean JSON output
// Update with actual path
error_reporting(E_ALL);

// Set headers first
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Content-Type: application/json");

// Custom error handler to log errors
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    return true;
});

// Database connection
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
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed",
    ]);
    error_log("Database connection failed: " . $e->getMessage());
    exit();
}

$method = $_SERVER["REQUEST_METHOD"];

// Handle preflight OPTIONS request
if ($method === "OPTIONS") {
    http_response_code(200);
    // Clear session data
    session_unset();
    session_destroy();
    exit();
}

// Initialize data array
$data = [];

// Handle different request types
if ($method === "POST") {
    // Check if it's file upload (multipart/form-data)
    if (!empty($_FILES)) {
        // Handle file upload
        handleFileUploadOnly();
        // Clear session data
        session_unset();
        session_destroy();
        exit();
    } elseif (!empty($_POST)) {
        $data = $_POST;
    } else {
        // Get JSON data
        $json_input = file_get_contents("php://input");
        if (!empty($json_input)) {
            $data = json_decode($json_input, true) ?? [];
        }
    }
} else {
    // For GET and DELETE requests
    if ($method === "GET") {
        $data = $_GET;
    } else {
        $json_input = file_get_contents("php://input");
        if (!empty($json_input)) {
            $data = json_decode($json_input, true) ?? [];
        }
    }
}

// Log request data for debugging
error_log("Method: $method");
error_log("Data: " . print_r($data, true));
if (!empty($_FILES)) {
    error_log("FILES: " . print_r($_FILES, true));
}

function getMimeType($file) {
    if (class_exists('finfo')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return $finfo->file($file);
        } catch (Exception $e) {
            error_log("Error getting MIME type: " . $e->getMessage());
        }
    }
    // Fallback to file extension if finfo fails
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'mpeg' => 'video/mpeg',
    ];
    return $mimeTypes[$ext] ?? 'application/octet-stream';
}

// Route requests
switch ($method) {
    case "GET":
        getQuestions($pdo, $data);
        // Clear session data
        session_unset();
        session_destroy();
        break;
    case "POST":
        $action = $data["action"] ?? "add";
        if ($action === "delete") {
            deleteQuestion($pdo, $data["id"] ?? null);
        } else {
            try {
                addOrEditQuestion($pdo, $data);
            } catch (Exception $e) {
                error_log("Error adding or editing question: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "error" => "Failed to add or edit question",
                    "error_details" => $e->getMessage(),
                    "stack_trace" => $e->getTraceAsString(),
                ]);
            }
        }
        // Clear session data
        session_unset();
        session_destroy();
        break;
    case "DELETE":
        if (isset($data["action"]) && $data["action"] === "delete_quiz_set") {
            deleteQuizSet($pdo, $data["id"] ?? null);
        } else {
            deleteQuestion($pdo, $data["id"] ?? null);
        }
        // Clear session data
        session_unset();
        session_destroy();
        break;
    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "error" => "Invalid request method",
        ]);
        // Clear session data
        session_unset();
        session_destroy();
        break;
}

function handleFileUploadOnly()
{
    $baseDir = __DIR__;
    $uploadDir = "questions/";

    // Create upload directory if it doesn't exist
    if (!is_dir($baseDir . "/" . $uploadDir)) {
        if (!mkdir($baseDir . "/" . $uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Failed to create upload directory",
            ]);
            return;
        }
    }

    // Handle single file upload
    $uploadedFile = null;
    foreach ($_FILES as $fileKey => $file) {
        if ($file["error"] === UPLOAD_ERR_OK) {
            $uploadedFile = handleFileUpload($fileKey, $uploadDir, $baseDir);
            break; // Handle only the first file
        }
    }

    if ($uploadedFile) {
        echo json_encode(["success" => true, "filename" => $uploadedFile]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "No file uploaded or upload failed",
        ]);
    }
}

function getQuestions($pdo, $data)
{
    $quizSetId = $data["quiz_set_id"] ?? 0;
    $fileToStream = $data["file"] ?? null;

    if ($fileToStream) {
        streamFile($fileToStream, __DIR__);
        return;
    }

    if ($quizSetId == 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Quiz Set ID is required",
        ]);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, quiz_set_id, question_type, question, optional_text, question_file, correct_answer,
                    choice_A_text, choice_A_file, choice_B_text, choice_B_file,
                    choice_C_text, choice_C_file, choice_D_text, choice_D_file,
                    question_word_formatting, optional_word_formatting,
                    choice_A_word_formatting, choice_B_word_formatting,
                    choice_C_word_formatting, choice_D_word_formatting
             FROM questions WHERE quiz_set_id = :quiz_set_id ORDER BY id",
        );
        $stmt->bindParam(":quiz_set_id", $quizSetId, PDO::PARAM_INT);
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $baseDir = __DIR__;
        $uploadDir = "questions/";

        foreach ($questions as &$question) {
            $question["question_type"] =
                $question["question_type"] ?? "Reading";

            $processFilePath = function ($filePath) use ($baseDir, $uploadDir) {
                if (empty($filePath)) {
                    return "";
                }
                $filename = basename($filePath);
                $relativePath = $uploadDir . $filename;
                $fullPath = $baseDir . "/" . $relativePath;
                return file_exists($fullPath) ? $relativePath : "";
            };

            $question["question_file"] = $processFilePath(
                $question["question_file"],
            );
            $question["choice_A_file"] = $processFilePath(
                $question["choice_A_file"],
            );
            $question["choice_B_file"] = $processFilePath(
                $question["choice_B_file"],
            );
            $question["choice_C_file"] = $processFilePath(
                $question["choice_C_file"],
            );
            $question["choice_D_file"] = $processFilePath(
                $question["choice_D_file"],
            );

            // Ensure all text fields are strings
            $question["question"] = $question["question"] ?? "";
            $question["optional_text"] = $question["optional_text"] ?? "";
            $question["correct_answer"] = $question["correct_answer"] ?? "";
            $question["choice_A_text"] = $question["choice_A_text"] ?? "";
            $question["choice_B_text"] = $question["choice_B_text"] ?? "";
            $question["choice_C_text"] = $question["choice_C_text"] ?? "";
            $question["choice_D_text"] = $question["choice_D_text"] ?? "";

            // Parse JSON formatting data
            $question["question_word_formatting"] =
                json_decode(
                    $question["question_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
            $question["optional_word_formatting"] =
                json_decode(
                    $question["optional_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
            $question["choice_A_word_formatting"] =
                json_decode(
                    $question["choice_A_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
            $question["choice_B_word_formatting"] =
                json_decode(
                    $question["choice_B_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
            $question["choice_C_word_formatting"] =
                json_decode(
                    $question["choice_C_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
            $question["choice_D_word_formatting"] =
                json_decode(
                    $question["choice_D_word_formatting"] ?? "[]",
                    true,
                ) ?:
                [];
        }

        http_response_code(200);
        echo json_encode(["success" => true, "questions" => $questions]);
    } catch (PDOException $e) {
        error_log("Failed to fetch questions: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to fetch questions",
        ]);
    }
}

function validateWordFormatting($formatting, $text)
{
    if (!is_array($formatting)) {
        error_log("Invalid word formatting: not an array");
        return false;
    }

    // Allow empty formatting
    if (empty($formatting)) {
        return true;
    }

    $wordCount = count(
        array_filter(explode(" ", trim($text)), fn($word) => !empty($word)),
    );
    if (count($formatting) > $wordCount) {
        error_log(
            "Word formatting length mismatch: expected <= $wordCount, got " .
                count($formatting),
        );
        return false;
    }

    foreach ($formatting as $wordFormat) {
        if (
            !is_array($wordFormat) ||
            !isset($wordFormat["bold"]) ||
            !isset($wordFormat["underline"]) ||
            !is_bool($wordFormat["bold"]) ||
            !is_bool($wordFormat["underline"])
        ) {
            error_log(
                "Invalid word formatting structure: " .
                    print_r($wordFormat, true),
            );
            return false;
        }
    }
    return true;
}

function handleFileUpload($fileKey, $uploadDir, $baseDir)
{
    if (
        !isset($_FILES[$fileKey]) ||
        $_FILES[$fileKey]["error"] == UPLOAD_ERR_NO_FILE
    ) {
        return "";
    }

    $file = $_FILES[$fileKey];
    error_log("File upload for $fileKey: " . print_r($file, true));

    if ($file["error"] !== UPLOAD_ERR_OK) {
        error_log("Upload error for $fileKey: " . $file["error"]);
        return "";
    }

    // Get file type using our custom function
    $fileType = getMimeType($file["tmp_name"]);
    $fileExtension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    // Validate file type
    $allowedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/gif",
        "application/pdf",
        "audio/mpeg",
        "audio/mp3",
        "audio/wav",
        "audio/x-wav",
        "audio/aac",
        "audio/ogg",
        "video/mp4",
        "video/quicktime",
        "video/mpeg",
    ];

    // Additional check for common extensions if mime type fails
    $allowedExtensions = [
        "jpg",
        "jpeg",
        "png",
        "gif",
        "pdf",
        "mp3",
        "wav",
        "aac",
        "ogg",
        "mp4",
        "mov",
        "mpeg",
    ];

    if (
        !in_array($fileType, $allowedTypes) &&
        !in_array($fileExtension, $allowedExtensions)
    ) {
        error_log(
            "Invalid file type: $fileType, extension: $fileExtension for $fileKey",
        );
        return "";
    }

    // Rest of the function remains the same...
    $fullUploadDir = $baseDir . "/" . $uploadDir;
    if (!is_dir($fullUploadDir)) {
        if (!mkdir($fullUploadDir, 0755, true)) {
            error_log("Failed to create upload directory: $uploadDir");
            return "";
        }
    }

    // Generate unique filename
    $filename =
        uniqid() .
        "_" .
        preg_replace("/[^A-Za-z0-9._-]/", "", basename($file["name"]));
    $targetPath = $fullUploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        error_log("File saved successfully: $targetPath");
        return $uploadDir . $filename;
    } else {
        error_log(
            "Failed to move uploaded file: " .
                $file["name"] .
                " to $targetPath",
        );
        return "";
    }
}

function streamFile($filePath, $baseDir)
{
    $fullPath = $baseDir . "/" . $filePath;
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "File not found"]);
        return;
    }

    $fileType = mime_content_type($fullPath);
    header("Content-Type: " . $fileType);
    header("Content-Length: " . filesize($fullPath));

    $fp = fopen($fullPath, "rb");
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    exit();
}

function addOrEditQuestion($pdo, $data)
{
    error_log("addOrEditQuestion called with data: " . print_r($data, true));

    // Validate required fields
    if (empty($data["quiz_set_id"]) || empty($data["question"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required fields (quiz_set_id, question)",
        ]);
        return;
    }

    // Set defaults
    $data["question_type"] = $data["question_type"] ?? "Reading";
    $data["optional_text"] = $data["optional_text"] ?? "";
    $data["question_file"] = $data["question_file"] ?? "";
    $data["correct_answer"] = $data["correct_answer"] ?? "A";

    // Validate question type
    if (!in_array($data["question_type"], ["Reading", "Listening"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" =>
                "Invalid question type. Allowed types are: Reading, Listening",
        ]);
        return;
    }

    // Validate correct answer
    if (
        !empty($data["correct_answer"]) &&
        !in_array($data["correct_answer"], ["A", "B", "C", "D"])
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid correct answer. Must be A, B, C, or D",
        ]);
        return;
    }

    // Extract and validate data
    $formatting = $data["formatting"] ?? [];
    $choices = $data["choices"] ?? [];

    // Validate word formatting
    if (
        !validateWordFormatting(
            $formatting["question_word_formatting"] ?? [],
            $data["question"] ?? "",
        )
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid question word formatting data",
        ]);
        return;
    }
    if (
        !validateWordFormatting(
            $formatting["optional_word_formatting"] ?? [],
            $data["optional_text"] ?? "",
        )
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid optional text word formatting data",
        ]);
        return;
    }

    // Process choices with defaults
    $choiceLabels = ["A", "B", "C", "D"];
    foreach ($choiceLabels as $label) {
        $choice = $choices[$label] ?? [
            "choice_text" => "",
            "choice_file" => "",
            "word_formatting" => [],
        ];
        $data["choice_{$label}_text"] = $choice["choice_text"] ?? "";
        $data["choice_{$label}_file"] = $choice["choice_file"] ?? "";
        $data["choice_{$label}_word_formatting"] = json_encode(
            $choice["word_formatting"] ?? [],
        );

        if (
            !validateWordFormatting(
                $choice["word_formatting"] ?? [],
                $choice["choice_text"] ?? "",
            )
        ) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Invalid choice $label word formatting data",
            ]);
            return;
        }
    }

    // Encode formatting data
    $data["question_word_formatting"] = json_encode(
        $formatting["question_word_formatting"] ?? [],
    );
    $data["optional_word_formatting"] = json_encode(
        $formatting["optional_word_formatting"] ?? [],
    );

    try {
        if (!empty($data["id"])) {
            // Update existing question
            $stmt = $pdo->prepare(
                "UPDATE questions SET
                    quiz_set_id = :quiz_set_id,
                    question_type = :question_type,
                    question = :question,
                    optional_text = :optional_text,
                    question_file = :question_file,
                    correct_answer = :correct_answer,
                    choice_A_text = :choice_A_text,
                    choice_A_file = :choice_A_file,
                    choice_A_word_formatting = :choice_A_word_formatting,
                    choice_B_text = :choice_B_text,
                    choice_B_file = :choice_B_file,
                    choice_B_word_formatting = :choice_B_word_formatting,
                    choice_C_text = :choice_C_text,
                    choice_C_file = :choice_C_file,
                    choice_C_word_formatting = :choice_C_word_formatting,
                    choice_D_text = :choice_D_text,
                    choice_D_file = :choice_D_file,
                    choice_D_word_formatting = :choice_D_word_formatting,
                    question_word_formatting = :question_word_formatting,
                    optional_word_formatting = :optional_word_formatting
                WHERE id = :id",
            );
            $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
            $message = "Question updated successfully";
        } else {
            // Insert new question
            $stmt = $pdo->prepare(
                "INSERT INTO questions (
                    quiz_set_id, question_type, question, optional_text, question_file, correct_answer,
                    choice_A_text, choice_A_file, choice_A_word_formatting,
                    choice_B_text, choice_B_file, choice_B_word_formatting,
                    choice_C_text, choice_C_file, choice_C_word_formatting,
                    choice_D_text, choice_D_file, choice_D_word_formatting,
                    question_word_formatting, optional_word_formatting
                ) VALUES (
                    :quiz_set_id, :question_type, :question, :optional_text, :question_file, :correct_answer,
                    :choice_A_text, :choice_A_file, :choice_A_word_formatting,
                    :choice_B_text, :choice_B_file, :choice_B_word_formatting,
                    :choice_C_text, :choice_C_file, :choice_C_word_formatting,
                    :choice_D_text, :choice_D_file, :choice_D_word_formatting,
                    :question_word_formatting, :optional_word_formatting
                )",
            );
            $message = "Question added successfully";
        }

        // Bind parameters
        $stmt->bindParam(":quiz_set_id", $data["quiz_set_id"], PDO::PARAM_INT);
        $stmt->bindParam(
            ":question_type",
            $data["question_type"],
            PDO::PARAM_STR,
        );
        $stmt->bindParam(":question", $data["question"], PDO::PARAM_STR);
        $stmt->bindParam(
            ":optional_text",
            $data["optional_text"],
            PDO::PARAM_STR,
        );
        $stmt->bindParam(
            ":question_file",
            $data["question_file"],
            PDO::PARAM_STR,
        );
        $stmt->bindParam(
            ":correct_answer",
            $data["correct_answer"],
            PDO::PARAM_STR,
        );

        foreach ($choiceLabels as $label) {
            $stmt->bindParam(
                ":choice_{$label}_text",
                $data["choice_{$label}_text"],
                PDO::PARAM_STR,
            );
            $stmt->bindParam(
                ":choice_{$label}_file",
                $data["choice_{$label}_file"],
                PDO::PARAM_STR,
            );
            $stmt->bindParam(
                ":choice_{$label}_word_formatting",
                $data["choice_{$label}_word_formatting"],
                PDO::PARAM_STR,
            );
        }

        $stmt->bindParam(
            ":question_word_formatting",
            $data["question_word_formatting"],
            PDO::PARAM_STR,
        );
        $stmt->bindParam(
            ":optional_word_formatting",
            $data["optional_word_formatting"],
            PDO::PARAM_STR,
        );

        $stmt->execute();

        $questionId = !empty($data["id"]) ? $data["id"] : $pdo->lastInsertId();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => $message,
            "question_id" => $questionId,
        ]);

        error_log("Successfully saved question ID: $questionId");
    } catch (PDOException $e) {
        error_log("Failed to save question: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to save question: " . $e->getMessage(),
        ]);
    }
}

function deleteQuestion($pdo, $id)
{
    if (empty($id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Question ID is required",
        ]);
        return;
    }

    try {
        // Check if question exists
        $checkStmt = $pdo->prepare("SELECT id FROM questions WHERE id = :id");
        $checkStmt->bindParam(":id", $id, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "No question found with the provided ID",
            ]);
            error_log("Attempted to delete non-existent question ID: $id");
            return;
        }

        // Delete the question
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Question deleted successfully",
            ]);
            error_log("Successfully deleted question ID: $id");
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Failed to delete question",
            ]);
            error_log("Failed to delete question ID: $id - No rows affected");
        }
    } catch (PDOException $e) {
        error_log("Delete error for question ID $id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to delete question due to database error",
        ]);
    }
}

function deleteQuizSet($pdo, $id)
{
    if (empty($id)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Quiz Set ID is required",
        ]);
        return;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM quiz_sets WHERE id = :id");
        $checkStmt->bindParam(":id", $id, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "No quiz set found with the provided ID",
            ]);
            error_log("Attempted to delete non-existent quiz set ID: $id");
            return;
        }

        // First delete all questions in the quiz set
        $deleteQuestionsStmt = $pdo->prepare(
            "DELETE FROM questions WHERE quiz_set_id = :id",
        );
        $deleteQuestionsStmt->bindParam(":id", $id, PDO::PARAM_INT);
        $deleteQuestionsStmt->execute();

        // Then delete the quiz set
        $stmt = $pdo->prepare("DELETE FROM quiz_sets WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Quiz set deleted successfully",
            ]);
            error_log("Successfully deleted quiz set ID: $id");
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Failed to delete quiz set",
            ]);
            error_log("Failed to delete quiz set ID: $id - No rows affected");
        }
    } catch (PDOException $e) {
        error_log("Delete error for quiz set ID $id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to delete quiz set due to database error",
        ]);
    }
}