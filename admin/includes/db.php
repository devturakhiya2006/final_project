<?php
/**
 * Admin Database Configuration
 * Manages specialized database connection for the administrative panel.
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
    // Record technical details for administrative review
    error_log("Admin DB Connection failure: " . $exception->getMessage());

    // Display a clean, humanized message
    die("<div style='font-family: sans-serif; padding: 20px; text-align: center;'>
            <h2>System Maintenance</h2>
            <p>Our administrative services are temporarily unavailable. Please contact the system administrator.</p>
         </div>");
}
