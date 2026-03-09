<?php
/**
 * API: Update Product Details
 * Responsibility: Updates product information based on administrative input.
 */

require_once '../includes/auth_session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Extract and sanitize inputs
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
$stock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
$unit = isset($_POST['unit']) ? sanitize($_POST['unit']) : 'pcs';
$hsn = isset($_POST['hsn_sac']) ? sanitize($_POST['hsn_sac']) : '';
$gst_rate = isset($_POST['gst_rate']) ? (float)$_POST['gst_rate'] : 18.00;
$description = isset($_POST['description']) ? sanitize($_POST['description']) : '';

if ($id <= 0 || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing or invalid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE products 
        SET name = ?, price = ?, stock_quantity = ?, unit = ?, hsn_sac = ?, gst_rate = ?, description = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$name, $price, $stock, $unit, $hsn, $gst_rate, $description, $id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made or product not found.']);
    }
} catch (PDOException $e) {
    error_log("API Update Product Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred while updating the product.']);
}
