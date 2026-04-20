<?php
include "GlobalConfigs.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

$servername = DB_HOST;
$username = DB_USER;  
$password = DB_PASSWORD;
$database = DATABASE_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
    ]);
    exit();
}

// Fetch admins from admin_users table
$adminsQuery = "SELECT full_name, email FROM admin_users";
$adminsResult = $conn->query($adminsQuery);
$admins = [];

while ($row = $adminsResult->fetch_assoc()) {
    $admins[] = [
        "full_name" => $row["full_name"],
        "email" => $row["email"],
    ];
}

// Add hardcoded admin
$admins[] = [
    "full_name" => "Admin User",
    "email" => "admin@gmail.com",
];

echo json_encode([
    "success" => true,
    "admins" => $admins,
]);

$conn->close();
?>
