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

try {
    // Create a PDO instance for database connection
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
    );

    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If the connection fails, output the error message
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage(),
    ]);
    session_unset();
    session_destroy();
    exit();
} finally {
    // Ensure session is cleared after execution
    session_unset();
    session_destroy();
}
?>
