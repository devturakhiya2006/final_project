<?php
/**
 * Database Configuration
 * Responsibility: Establishes a secure connection to the MySQL database.
 */

$host = 'localhost';
$dbName = 'goinvoice_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (PDOException $exception) {
    // Log the actual error internally for the developer
    error_log("Database Connection Error: " . $exception->getMessage());

    // Provide a human-friendly response to the end user
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'We are currently experiencing technical difficulties connecting to our services. Please try again in a few minutes.'
    ]);
    exit;
}
