<?php
include "GlobalConfigs.php";

session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    error_log(
        "Database connection failed at " .
            date("Y-m-d H:i:s") .
            ": " .
            $conn->connect_error,
    );
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
    ]);
    session_unset();
    session_destroy();
    exit();
}

session_unset();
session_destroy();
?>
