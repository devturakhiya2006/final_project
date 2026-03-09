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

// Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!function_exists('sendResponse')) {
    function sendResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
if (!function_exists('sendError')) {
    function sendError($message, $status = 400) {
        sendResponse(['success' => false, 'error' => $message], $status);
    }
}
if (!function_exists('sendSuccess')) {
    function sendSuccess($data = [], $message = 'Success') {
        sendResponse(['success' => true, 'message' => $message, 'data' => $data]);
    }
}

if (!function_exists('getDB')) {
    function getDB() {
        global $pdo;
        if (!$pdo) {
            sendError('Database not initialized', 500);
        }
        return $pdo;
    }
}

if (!isset($_SESSION['user_id'])) {
    sendError('Authentication required. Please login first.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    $db = getDB();
    switch ($method) {
        case 'GET': handleGetCustomers($db, $user_id); break;
        case 'POST': handleCreateCustomer($db, $user_id); break;
        case 'PUT': handleUpdateCustomer($db, $user_id); break;
        case 'DELETE': handleDeleteCustomer($db, $user_id); break;
        default: sendError('Method not allowed');
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

function handleGetCustomers($db, $user_id) {
    try {
        $action = $_GET['action'] ?? '';
        // LOGIC_FIX_FINAL_V6
        if ($action === 'get_outstanding') {
            $id = $_GET['id'] ?? '';
            $type = $_GET['type'] ?? 'customer';
            if (empty($id)) sendError('ID is required');

            $stmt = $db->prepare("SELECT opening_balance FROM customers WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $opening_balance = (float)($stmt->fetchColumn() ?: 0);

            $table = ($type === 'vendor') ? 'purchase_invoices' : 'sales_invoices';
            $cust_field = ($type === 'vendor') ? 'vendor_id' : 'customer_id';
            
            $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM $table WHERE $cust_field = ? AND user_id = ? AND TRIM(LOWER(payment_method)) = 'credit'");
            $stmt->execute([$id, $user_id]);
            $credit_total = (float)($stmt->fetchColumn() ?: 0);

            sendSuccess(['outstanding' => $opening_balance + $credit_total]);
            return;
        }

        $type = $_GET['type'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $where_conditions = ["user_id = ?"];
        $params = [$user_id];
        if ($type !== 'all') { $where_conditions[] = "type = ?"; $params[] = $type; }
        if (!empty($search)) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
            $sp = "%$search%"; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        }
        $where_clause = implode(' AND ', $where_conditions);
        $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE $where_clause");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM customers WHERE $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $customers = $stmt->fetchAll();

        sendSuccess(['customers' => $customers, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int)ceil($total / $limit)]]);
    } catch (Throwable $e) { sendError('Failed to fetch customers: ' . $e->getMessage(), 500); }
}

function handleCreateCustomer($db, $user_id) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) sendError('Invalid JSON');
        $name = trim($data['name'] ?? ''); $type = $data['type'] ?? '';
        if (!$name || !$type) sendError('Name and type are required');
        $stmt = $db->prepare("INSERT INTO customers (user_id, name, type, email, mobile, gst_number, address, city, state, pincode, company_name, is_registered, pan_number, opening_balance, fax, website, credit_limit, credit_due_date, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user_id, $name, $type, trim($data['email'] ?? ''), trim($data['mobile'] ?? ''), trim($data['gst_number'] ?? ''), trim($data['address'] ?? ''), trim($data['city'] ?? ''), trim($data['state'] ?? ''), trim($data['pincode'] ?? ''), trim($data['company_name'] ?? ''), (int)!!($data['is_registered'] ?? 0), trim($data['pan_number'] ?? ''), (float)($data['opening_balance'] ?? 0), trim($data['fax'] ?? ''), trim($data['website'] ?? ''), (float)($data['credit_limit'] ?? 0), $data['credit_due_date'] ?: null, trim($data['note'] ?? '')]);
        sendSuccess(['customer_id' => $db->lastInsertId()], 'Customer created successfully');
    } catch (Throwable $e) { sendError('Failed to create customer: ' . $e->getMessage(), 500); }
}

function handleUpdateCustomer($db, $user_id) {
    try {
        $id = $_GET['id'] ?? ''; if (!$id) sendError('ID is required');
        $data = json_decode(file_get_contents('php://input'), true);
        $fields = ['name','type','email','mobile','gst_number','address','city','state','pincode','company_name','is_registered','pan_number','opening_balance','fax','website','credit_limit','credit_due_date','note'];
        $upd = []; $params = [];
        foreach ($fields as $f) { if (isset($data[$f])) { $upd[] = "$f = ?"; $params[] = $data[$f]; } }
        if (empty($upd)) sendError('No fields to update');
        $params[] = $id; $params[] = $user_id;
        $stmt = $db->prepare("UPDATE customers SET " . implode(',', $upd) . " WHERE id = ? AND user_id = ?");
        $stmt->execute($params);
        sendSuccess([], 'Customer updated successfully');
    } catch (Throwable $e) { sendError('Failed to update: ' . $e->getMessage(), 500); }
}

function handleDeleteCustomer($db, $user_id) {
    try {
        $id = $_GET['id'] ?? ''; if (!$id) sendError('ID is required');
        $stmt = $db->prepare("DELETE FROM customers WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        sendSuccess([], 'Customer deleted successfully');
    } catch (Throwable $e) { sendError('Failed to delete: ' . $e->getMessage(), 500); }
}
?>
