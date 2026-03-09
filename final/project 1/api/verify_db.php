<?php
/**
 * Database Write Verification
 * Responsibility: Developer-facing utility that tests INSERT capability against the support_inquiries table.
 * Note: This file should be restricted or removed in production environments.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';

try {
    $testName = "Verification Test " . date('Y-m-d H:i:s');
    $testEmail = "verify@goinvoice-test.com";
    $testSource = "system_check";
    $testMessage = "Automated write verification entry";

    $insertSql = "INSERT INTO support_inquiries (name, email, source, message) VALUES (?, ?, ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);
    $writeResult = $insertStmt->execute([$testName, $testEmail, $testSource, $testMessage]);

    if ($writeResult) {
        $newRecordId = $pdo->lastInsertId();
        echo "✅ Write test passed! Inserted Record ID: <strong>" . (int)$newRecordId . "</strong><br><br>";

        $verifyStmt = $pdo->prepare("SELECT * FROM support_inquiries WHERE id = ?");
        $verifyStmt->execute([$newRecordId]);
        $verifiedRecord = $verifyStmt->fetch();
        echo "📋 Verified Record:<br><pre>" . htmlspecialchars(print_r($verifiedRecord, true)) . "</pre>";
    }
    else {
        echo "❌ Write test failed. INSERT did not return success.";
    }

}
catch (PDOException $dbException) {
    error_log("DB Write Verification Error: " . $dbException->getMessage());
    echo "❌ Database write verification failed. Please check the error log for details.";
}
?>
