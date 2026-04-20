<?php
// CORS and Content-Type headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

try {
    include 'db.php'; // Your DB connection file

    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed at " . date('Y-m-d H:i:s') . ": " . ($conn ? $conn->connect_error : "No connection"));
        echo json_encode(["success" => false, "message" => "Database connection failed"]);
        exit;
    }

    // Support JSON input as well as form-data POST
    if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_POST)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && is_array($input)) {
            $_POST = $input;
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $action = isset($_POST['action']) ? trim($_POST['action']) : 'grant';

        error_log("POST Data at " . date('Y-m-d H:i:s') . ": user_id=$user_id, full_name=$full_name, email=$email, action=$action");

        if (empty($user_id) || !is_numeric($user_id) || $user_id <= 0) {
            echo json_encode(["success" => false, "message" => "Valid positive user ID is required"]);
            exit;
        }

        if ($action === 'grant') {
            if (empty($full_name) || empty($email)) {
                echo json_encode(["success" => false, "message" => "Full name and email are required"]);
                exit;
            }

            // Validate user exists
            $checkUserQuery = "SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($checkUserQuery);
            if ($stmt === false) {
                error_log("SQL error preparing user check at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: User check failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                echo json_encode(["success" => false, "message" => "User does not exist"]);
                $stmt->close();
                exit;
            }

            // Bind result variables
            $stmt->bind_result($db_id, $db_full_name, $db_email);
            $stmt->fetch();
            $stmt->close();

            // Check if user is already an admin
            $checkAdminQuery = "SELECT user_id FROM admin_users WHERE user_id = ? LIMIT 1";
            $stmt = $conn->prepare($checkAdminQuery);
            if ($stmt === false) {
                error_log("SQL error preparing admin check at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Admin check failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo json_encode(["success" => false, "message" => "User is already an admin"]);
                $stmt->close();
                exit;
            }
            $stmt->close();

            // Insert admin using user table's full_name and email
            $insertQuery = "INSERT INTO admin_users (user_id, full_name, email, assigned_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            if ($stmt === false) {
                error_log("SQL error preparing insert at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Insert failed"]);
                exit;
            }
            $stmt->bind_param("iss", $user_id, $db_full_name, $db_email);
            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Admin access granted",
                    "full_name" => $db_full_name,
                    "email" => $db_email
                ]);
            } else {
                error_log("SQL insert failed at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Insert failed"]);
            }
            $stmt->close();

            
            // Update user table to set role to 'admin'
            $updateQuery = "UPDATE users SET role = 'admin' WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            if ($stmt === false) {
                error_log("SQL error preparing user update at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: User update failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                // do nothing
            } else {
                error_log("SQL user update failed at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: User update failed"]);
                exit;
            }
            $stmt->close();
            


        } elseif ($action === 'remove') {
            // Check if user is an admin
            $checkAdminQuery = "SELECT user_id, full_name FROM admin_users WHERE user_id = ? LIMIT 1";
            $stmt = $conn->prepare($checkAdminQuery);
            if ($stmt === false) {
                error_log("SQL error preparing admin check at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Admin check failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                echo json_encode(["success" => false, "message" => "User is not an admin"]);
                $stmt->close();
                exit;
            }

            $stmt->bind_result($admin_user_id, $admin_full_name);
            $stmt->fetch();
            $stmt->close();

            // first change user role to 'user' in users table
            
            // Change user role to 'user'
            $updateUserQuery = "UPDATE users SET role = 'user' WHERE id = ?";
            $stmt = $conn->prepare($updateUserQuery);
            if ($stmt === false) {
                error_log("SQL error preparing user update at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: User update failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                // do nothing
            } else {
                error_log("SQL user update failed at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: User update failed"]);
                exit;
            }
            $stmt->close();

            // Delete admin entry
            $deleteQuery = "DELETE FROM admin_users WHERE user_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            if ($stmt === false) {
                error_log("SQL error preparing delete at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Delete failed"]);
                exit;
            }
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Admin access removed",
                    "full_name" => $admin_full_name
                ]);
            } else {
                error_log("SQL delete failed at " . date('Y-m-d H:i:s') . ": " . $conn->error);
                echo json_encode(["success" => false, "message" => "SQL error: Delete failed"]);
            }
            $stmt->close();

        } else {
            echo json_encode(["success" => false, "message" => "Invalid action specified"]);
        }

    } elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
        // Fetch all admins
        $fetchAdminsQuery = "SELECT id, user_id, full_name, email, assigned_at FROM admin_users";
        $result = $conn->query($fetchAdminsQuery);
        if ($result === false) {
            error_log("SQL error at " . date('Y-m-d H:i:s') . ": " . $conn->error);
            echo json_encode(["success" => false, "message" => "SQL error: Fetch admins failed"]);
            exit;
        }

        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
        echo json_encode(["success" => true, "admins" => $admins]);
        $result->free();

    } else {
        echo json_encode(["success" => false, "message" => "Invalid request method"]);
    }

} catch (Exception $e) {
    error_log("Server error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error"]);
}

$conn->close();
exit;
?>
