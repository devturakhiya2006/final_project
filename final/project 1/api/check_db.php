<?php
/**
 * Database Connection Diagnostics
 * Responsibility: A developer-facing utility to confirm database connectivity and table integrity.
 * Note: This file should be restricted or removed in production environments.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';

try {
    $connectionCheck = $pdo->query("SELECT DATABASE() as active_database");
    $activeDatabaseName = $connectionCheck->fetch()['active_database'];
    echo "✅ Connected to Database: <strong>" . htmlspecialchars($activeDatabaseName) . "</strong><br><br>";

    $recordQuery = $pdo->query("SELECT * FROM support_inquiries ORDER BY id DESC LIMIT 20");
    $inquiryRecords = $recordQuery->fetchAll();
    echo "📋 Total records in <code>support_inquiries</code>: <strong>" . count($inquiryRecords) . "</strong><br>";
    echo "<pre>" . htmlspecialchars(print_r($inquiryRecords, true)) . "</pre>";

}
catch (PDOException $dbException) {
    error_log("DB Diagnostics Error: " . $dbException->getMessage());
    echo "❌ Unable to complete database diagnostics. Please check the error log for details.";
}
?>
