<?php
include "GlobalConfigs.php";

// Enable error reporting for debugging (disable in production)

// CORS headers
header("Access-Control-Allow-Origin: *");
header(
    "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With",
);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Content-Type: application/json");

// Handle preflight OPTIONS request for CORS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

// Database configuration
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
    ]);
    exit();
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

//======================================================================
// Handle POST request (INSERT)
//======================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Check if this is a tunneled PUT request
    if (isset($_POST["_method"]) && $_POST["_method"] === "PUT") {
        // Handle tunneled PUT request
        $id = filter_var($_POST["id"] ?? 0, FILTER_VALIDATE_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid or missing user ID for update.",
            ]);
            exit();
        }

        // Fetch existing user data using bind_result
        $stmt = $conn->prepare(
            "SELECT full_name, email, phone, image FROM users WHERE id = ?",
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Use bind_result instead of get_result
        $existing_name = $existing_email = $existing_phone = $existing_image = null;
        $stmt->bind_result(
            $existing_name,
            $existing_email,
            $existing_phone,
            $existing_image,
        );

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "User not found.",
            ]);
            $stmt->close();
            exit();
        }
        $stmt->close();

        // Create user array for compatibility
        $user = [
            "full_name" => $existing_name,
            "email" => $existing_email,
            "phone" => $existing_phone,
            "image" => $existing_image,
        ];

        // Update fields
        $full_name = $_POST["full_name"] ?? $user["full_name"];
        $email = $_POST["email"] ?? $user["email"];
        $phone = $_POST["phone"] ?? $user["phone"];

        // Handle image upload for update
        $image_path = $user["image"]; // Keep existing image by default
        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] === UPLOAD_ERR_OK
        ) {
            $upload_dir = "Uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            $image_name =
                uniqid("user_") . "_" . basename($_FILES["image"]["name"]);
            $target_path = $upload_dir . $image_name;

            if (
                move_uploaded_file($_FILES["image"]["tmp_name"], $target_path)
            ) {
                // Delete old image if it exists
                if (!empty($user["image"]) && file_exists($user["image"])) {
                    unlink($user["image"]);
                }
                $image_path = $target_path;
            }
        }

        // Handle password update
        $password_sql = "";
        $params = [$full_name, $email, $phone, $image_path];
        $types = "ssss";

        if (!empty($_POST["password"])) {
            $hashed_password = password_hash(
                $_POST["password"],
                PASSWORD_BCRYPT,
            );
            $password_sql = ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        $params[] = $id;
        $types .= "i";

        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, image = ? $password_sql WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "User updated successfully",
            ]);
        } else {
            $message =
                $conn->errno === 1062
                    ? "Email or phone already exists."
                    : "Update failed: " . $conn->error;
            http_response_code(400);
            echo json_encode(["success" => false, "message" => $message]);
        }
        $stmt->close();
        exit();
    }

    // Regular POST (INSERT) logic
    $full_name = filter_var($_POST["full_name"] ?? "", FILTER_UNSAFE_RAW);
    $email = filter_var($_POST["email"] ?? "", FILTER_VALIDATE_EMAIL);
    $phone = filter_var($_POST["phone"] ?? "", FILTER_UNSAFE_RAW);
    $password_raw = $_POST["password"] ?? "";

    if (
        !$full_name ||
        !$email ||
        !preg_match("/^\+?[0-9]{10,15}$/", $phone) ||
        empty($password_raw)
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid input data. All fields are required.",
        ]);
        exit();
    }

    $password_hashed = password_hash($password_raw, PASSWORD_BCRYPT);
    $image_path = "";

    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] === UPLOAD_ERR_OK
    ) {
        $upload_dir = "Uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $image_name =
            uniqid("user_") . "_" . basename($_FILES["image"]["name"]);
        $target_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_path)) {
            $image_path = $target_path;
        } else {
            error_log("Image upload failed for POST.");
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Image upload failed.",
            ]);
            exit();
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO users (full_name, email, phone, password, image) VALUES (?, ?, ?, ?, ?)",
    );
    $stmt->bind_param(
        "sssss",
        $full_name,
        $email,
        $phone,
        $password_hashed,
        $image_path,
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "User added successfully",
        ]);
    } else {
        $message =
            $conn->errno === 1062
                ? "A user with this email or phone already exists."
                : "Registration failed.";
        error_log("POST execute failed: " . $stmt->error);
        http_response_code(409);
        echo json_encode(["success" => false, "message" => $message]);
    }
    $stmt->close();
    exit();
}

//======================================================================
// Handle GET request (SELECT) - FIXED
//======================================================================
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $stmt = $conn->prepare(
        "SELECT id, full_name, email, phone, image FROM users ORDER BY id DESC",
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to prepare query",
        ]);
        exit();
    }

    $stmt->execute();

    // Use bind_result instead of get_result
    $id = $full_name = $email = $phone = $image = null;
    $stmt->bind_result($id, $full_name, $email, $phone, $image);

    $users = [];
    while ($stmt->fetch()) {
        $users[] = [
            "id" => $id,
            "full_name" => $full_name,
            "email" => $email,
            "phone" => $phone,
            "image" => $image,
        ];
    }

    $stmt->close();
    echo json_encode(["success" => true, "users" => $users]);
    exit();
}

//======================================================================
// Handle DELETE request (DELETE) - Already using bind_result correctly
//======================================================================
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    try {
        // Get ID from query parameter
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

        if (!$id || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid user ID provided.",
            ]);
            exit();
        }

        // Start transaction
        $conn->begin_transaction();

        // First, get the user's image path
        $stmt = $conn->prepare("SELECT image FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception(
                "Failed to prepare select statement: " . $conn->error,
            );
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception(
                "Failed to execute select statement: " . $stmt->error,
            );
        }

        // Use bind_result and fetch instead of get_result
        $image_to_delete = null;
        $stmt->bind_result($image_to_delete);

        if (!$stmt->fetch()) {
            $stmt->close();
            $conn->rollback();
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "User not found.",
            ]);
            exit();
        }

        $stmt->close();

        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception(
                "Failed to prepare delete statement: " . $conn->error,
            );
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception(
                "Failed to execute delete statement: " . $stmt->error,
            );
        }

        if ($stmt->affected_rows > 0) {
            // Commit the transaction
            $conn->commit();

            // Delete the image file if it exists
            if (!empty($image_to_delete) && file_exists($image_to_delete)) {
                if (!unlink($image_to_delete)) {
                    error_log(
                        "Warning: Failed to delete image file: " .
                            $image_to_delete,
                    );
                }
            }

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "User deleted successfully.",
            ]);
        } else {
            throw new Exception("No user was deleted. User may not exist.");
        }

        $stmt->close();
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }

        error_log(
            "DELETE operation failed for ID " .
                ($id ?? "unknown") .
                ": " .
                $e->getMessage(),
        );
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Delete operation failed: " . $e->getMessage(),
        ]);
    }
    exit();
}

// Handle PUT request (for completeness, though you're using POST tunneling) - FIXED
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $input_data = file_get_contents("php://input");
    parse_str($input_data, $put_vars);

    $id = filter_var($put_vars["id"] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid or missing user ID for update.",
        ]);
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT full_name, email, phone, image FROM users WHERE id = ?",
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Use bind_result and fetch instead of get_result
    $existing_name = $existing_email = $existing_phone = $existing_image = null;
    $stmt->bind_result(
        $existing_name,
        $existing_email,
        $existing_phone,
        $existing_image,
    );

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // Create user array for compatibility
    $user = [
        "full_name" => $existing_name,
        "email" => $existing_email,
        "phone" => $existing_phone,
        "image" => $existing_image,
    ];

    $full_name = $put_vars["full_name"] ?? $user["full_name"];
    $email = $put_vars["email"] ?? $user["email"];
    $phone = $put_vars["phone"] ?? $user["phone"];

    $password_sql = "";
    $params = [$full_name, $email, $phone];
    $types = "sss";

    if (!empty($put_vars["password"])) {
        $hashed_password = password_hash(
            $put_vars["password"],
            PASSWORD_BCRYPT,
        );
        $password_sql = ", password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }

    $params[] = $id;
    $types .= "i";

    $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? $password_sql WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "User updated successfully",
        ]);
    } else {
        $message =
            $conn->errno === 1062
                ? "Email or phone already exists."
                : "Update failed: " . $conn->error;
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $message]);
    }
    $stmt->close();
    exit();
}

// Fallback for unsupported methods
http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed"]);

// Close connection
$conn->close();
?>
