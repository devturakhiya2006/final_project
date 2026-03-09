<?php
/**
 * API: Get Product Details
 * Responsibility: Fetches full details for a specialized product ID.
 */

require_once '../includes/auth_session.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Ensure we have a valid product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product identifier provided.']);
    exit;
}

try {
    // Fetch product details. We don't join users here as we just need product fields for editing.
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode(['success' => true, 'data' => $product]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
    }
}
catch (PDOException $e) {
    error_log("API Get Product Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred while fetching product details.']);
}
