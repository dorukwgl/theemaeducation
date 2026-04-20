<?php
include "GlobalConfigs.php";

session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(
        json_encode([
            "success" => false,
            "message" => "Connection failed: " . $conn->connect_error,
        ])
    );
}

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    request_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Handle POST request to approve a password reset
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["request_id"]) || empty($_POST["request_id"])) {
        echo json_encode([
            "success" => false,
            "message" => "Request ID is required",
        ]);
        session_unset();
        session_destroy();
        exit();
    }

    $request_id = filter_var($_POST["request_id"], FILTER_SANITIZE_NUMBER_INT);

    // Fetch the reset request and user details
    $stmt = $conn->prepare("SELECT pr.user_id, pr.email, u.full_name
                           FROM password_reset_requests pr
                           JOIN users u ON pr.user_id = u.id
                           WHERE pr.id = ? AND pr.request_status = 'pending'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        $user_id = $request["user_id"];
        $email = $request["email"];
        $full_name = $request["full_name"];

        // Generate a new random password
        $new_password = bin2hex(random_bytes(8)); // Generate a 16-character random password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        // Update the user's password in the users table
        $update_user_stmt = $conn->prepare(
            "UPDATE users SET password = ? WHERE id = ?",
        );
        $update_user_stmt->bind_param("si", $hashed_password, $user_id);

        if ($update_user_stmt->execute()) {
            // Update the reset request status to 'approved'
            $update_request_stmt = $conn->prepare(
                "UPDATE password_reset_requests SET request_status = 'approved' WHERE id = ?",
            );
            $update_request_stmt->bind_param("i", $request_id);

            if ($update_request_stmt->execute()) {
                // In a real app, you'd email the new password to the user
                // For now, we'll return it in the response
                echo json_encode([
                    "success" => true,
                    "message" => "Password reset approved. New password for $full_name ($email): $new_password",
                    "new_password" => $new_password,
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Failed to update request status",
                ]);
            }
            $update_request_stmt->close();
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to update user password",
            ]);
        }
        $update_user_stmt->close();
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Request not found or already processed",
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method",
    ]);
}

session_unset();
session_destroy();
$conn->close();
?>
