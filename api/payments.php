<?php
require_once '../config/database.php';

// Configure session for CORS with credentials
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

session_start();

// Headers (CORS with credentials similar to invoices.php)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);

function sendResponse($data, $status = 200){ http_response_code($status); echo json_encode($data); exit; }
function sendError($msg, $status = 400){ sendResponse(['success'=>false,'error'=>$msg], $status); }

// For session-based auth, ensure credentials are in headers
if (!isset($_SESSION['user_id'])) { sendError('Authentication required. Please login first.', 401); }
$user_id = (int)$_SESSION['user_id'];

// Safety check: ensure user_id is a positive integer
if ($user_id <= 0) { sendError('Invalid user session', 401); }

$method = $_SERVER['REQUEST_METHOD'];
// Use $pdo provided by database.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    sendError('Database connection failed');
}
$db = $pdo;

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) { sendError('Invalid JSON'); }

    $customer_id = isset($payload['customer_id']) && $payload['customer_id'] !== '' ? (int)$payload['customer_id'] : null;
    $invoice_id  = isset($payload['invoice_id']) && $payload['invoice_id'] !== '' ? (int)$payload['invoice_id'] : null;
    $amount      = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;
    $payment_date= isset($payload['payment_date']) ? trim($payload['payment_date']) : '';
    $payment_method = isset($payload['payment_method']) ? strtolower(trim($payload['payment_method'])) : '';
    $reference_number = isset($payload['reference_number']) ? trim($payload['reference_number']) : null;
    $notes = isset($payload['notes']) ? trim($payload['notes']) : null;

    if (!$customer_id) { sendError('customer_id is required'); }
    if (!$payment_date) { sendError('payment_date is required'); }
    if ($amount <= 0) { sendError('amount must be > 0'); }

    $allowed = ['cash','credit','cheque','online'];
    if (!in_array($payment_method, $allowed, true)) { sendError('payment_method invalid'); }

    // Insert payment
    $stmt = $db->prepare("INSERT INTO payments (user_id, invoice_id, customer_id, amount, payment_date, payment_method, reference_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ");
    $stmt->execute([$user_id, $invoice_id, $customer_id, $amount, $payment_date, $payment_method, $reference_number, $notes]);
    $id = (int)$db->lastInsertId();

    sendResponse(['success'=>true, 'data'=>['id'=>$id]]);
}

if ($method === 'GET') {
    // Simple list of payments for current user
    $stmt = $db->prepare("SELECT p.*, c.name AS customer_name FROM payments p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 200");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success'=>true,'data'=>['payments'=>$rows]]);
}

sendError('Method not allowed', 405);
