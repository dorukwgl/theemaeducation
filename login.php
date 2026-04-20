<?php
include "GlobalConfigs.php";

error_reporting(E_ALL);
// Set a valid path for error logging

// Start session and clear previous session data
session_start();
session_unset();
session_destroy();

// Handle CORS for any IP
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Database configuration
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Use persistent connection with retry logic
$maxRetries = 3;
$retryDelay = 1; // seconds
for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    $conn = new mysqli("p:$servername", $username, $password, $database);
    if (!$conn->connect_error) {
        break; // Connection successful
    }
    error_log(
        "Database connection attempt $attempt failed: " . $conn->connect_error,
    );
    if ($attempt === $maxRetries) {
        echo json_encode([
            "success" => false,
            "message" => "Database unavailable",
        ]);
        exit();
    }
    sleep($retryDelay);
}

// Parse input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);
if ($input === null) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(["success" => false, "message" => "Invalid input format"]);
    exit();
}

$email = $input["email"] ?? ($_POST["email"] ?? "");
$passwordInput = $input["password"] ?? ($_POST["password"] ?? "");

if (empty($email) || empty($passwordInput)) {
    error_log(
        "Missing credentials: email=$email, password=" .
            (empty($passwordInput) ? "empty" : "provided"),
    );
    echo json_encode(["success" => false, "message" => "Missing credentials"]);
    exit();
}

// Admin credentials
$adminEmail = "admin@gmail.com";
$adminPassword = "admin@gmail.com";
$adminName = "Admin User";

// Response function
function sendResponse($role, $name, $image = "")
{
    global $email;
    session_start();
    $_SESSION["user_email"] = $email;
    $_SESSION["user_role"] = $role;
    $_SESSION["user_name"] = $name;

    echo json_encode([
        "success" => true,
        "role" => $role,
        "name" => $name,
        "image" => $image,
        "email" => $email,
    ]);
    exit();
}

// Step 1: Check users table
$sql =
    "SELECT id, full_name, password, image, role FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Server error"]);
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $full_name, $password_hash, $image, $role);

if ($stmt->fetch()) {
    if (password_verify($passwordInput, $password_hash)) {
        sendResponse($role, $full_name, $image);
    } else {
        error_log("Password verification failed for email: $email");
        echo json_encode([
            "success" => false,
            "message" => "Invalid credentials",
        ]);
        exit();
    }
}
$stmt->close();

// Step 2: Handle hardcoded admin
if ($email === $adminEmail && $passwordInput === $adminPassword) {
    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
    $stmtInsert = $conn->prepare("
        INSERT INTO users (full_name, email, phone, password, role)
        VALUES (?, ?, '0000000000', ?, 'admin')
        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), role='admin'
    ");
    if (!$stmtInsert) {
        error_log("Admin insert prepare failed: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Server error"]);
        exit();
    }
    $stmtInsert->bind_param("sss", $adminName, $adminEmail, $hashedPassword);
    $stmtInsert->execute();
    $stmtInsert->close();
    sendResponse("admin", $adminName);
}

// Step 3: Check admin_users table
$sql = "SELECT full_name FROM admin_users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Admin users prepare failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Server error"]);
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($admin_full_name);

if ($stmt->fetch()) {
    $stmt->close();
    $sqlUser =
        "SELECT full_name, password, image FROM users WHERE email = ? LIMIT 1";
    $userStmt = $conn->prepare($sqlUser);
    if (!$userStmt) {
        error_log("User table check prepare failed: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Server error"]);
        exit();
    }
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $userStmt->bind_result($u_full_name, $u_password, $u_image);

    if ($userStmt->fetch() && password_verify($passwordInput, $u_password)) {
        sendResponse("admin", $admin_full_name, $u_image);
    } else {
        error_log("Admin user password verification failed for email: $email");
        echo json_encode([
            "success" => false,
            "message" => "Invalid credentials",
        ]);
        exit();
    }
}
$stmt->close();

error_log("User not found: $email");
echo json_encode(["success" => false, "message" => "User not found"]);
$conn->close();
