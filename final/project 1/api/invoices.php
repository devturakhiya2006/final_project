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

// Handle CORS (support credentials for session-based auth)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    // Reflect the requesting origin to allow cookies
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Ensure errors are not echoed into JSON responses
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// JSON helpers (local to this endpoint)
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

// Ensure access to PDO instance from config/database.php
if (!function_exists('getDB')) {
    function getDB() {
        global $pdo;
        if (!$pdo) {
            sendError('Database not initialized', 500);
        }
        return $pdo;
    }
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendError('Authentication required. Please login first.', 401);
}

$user_id = $_SESSION['user_id'];

// Safety check: ensure user_id is a positive integer
if (!is_numeric($user_id) || $user_id <= 0) {
    sendError('Invalid user session', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$invoice_type = $_GET['type'] ?? 'sales'; // sales or purchase

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            handleGetInvoices($db, $user_id, $invoice_type);
            break;
        case 'POST':
            handleCreateInvoice($db, $user_id, $invoice_type);
            break;
        case 'PUT':
            handleUpdateInvoice($db, $user_id, $invoice_type);
            break;
        case 'DELETE':
            handleDeleteInvoice($db, $user_id, $invoice_type);
            break;
        default:
            sendError('Method not allowed');
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

function handleGetInvoices($db, $user_id, $invoice_type) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $table = $invoice_type === 'sales' ? 'sales_invoices' : 'purchase_invoices';
    $customer_table = 'customers';
    $customer_field = $invoice_type === 'sales' ? 'customer_id' : 'vendor_id';
    
    $where_conditions = ["i.user_id = ?"];
    $params = [$user_id];
    
    if ($status !== 'all') {
        $where_conditions[] = "i.payment_status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR c.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM {$table} i 
                  LEFT JOIN {$customer_table} c ON i.{$customer_field} = c.id 
                  WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get invoices with customer details
    $sql = "SELECT i.*, c.name as customer_name, c.email as customer_email, c.mobile as customer_mobile
            FROM {$table} i 
            LEFT JOIN {$customer_table} c ON i.{$customer_field} = c.id 
            WHERE {$where_clause} 
            ORDER BY i.created_at DESC 
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    sendSuccess([
        'invoices' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleCreateInvoice($db, $user_id, $invoice_type) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($ct, 'multipart/form-data') !== false;
    
    if ($isMultipart) {
        $data = $_POST;
        if (isset($data['items']) && is_string($data['items'])) {
            $data['items'] = json_decode($data['items'], true);
        }
        
        $qr_code_path = null;
        if (!empty($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = realpath(__DIR__ . '/../uploads');
            if ($uploadDir === false) {
                @mkdir(__DIR__ . '/../uploads', 0775, true);
                $uploadDir = realpath(__DIR__ . '/../uploads');
            }
            $qrDir = $uploadDir ? ($uploadDir . DIRECTORY_SEPARATOR . 'qrcodes') : false;
            if ($qrDir && !is_dir($qrDir)) { @mkdir($qrDir, 0775, true); }
            if ($qrDir && is_dir($qrDir)) {
                $original = basename($_FILES['qr_code']['name']);
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
                $target = $qrDir . DIRECTORY_SEPARATOR . (time() . '_' . $safeName);
                if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target)) {
                    $qr_code_path = '../uploads/qrcodes/' . basename($target);
                }
            }
        }
        $data['qr_code_path'] = $qr_code_path;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?: []; 
        if(isset($data['items']) && is_string($data['items'])) $data['items'] = json_decode($data['items'], true);
    }
    
    $required_fields = ['customer_id', 'invoice_date', 'items'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            sendError("Field '$field' is required");
        }
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        sendError('Invoice items are required');
    }
    
    $customer_id = (int)$data['customer_id'];
    $invoice_date = $data['invoice_date'];
    $due_date = $data['due_date'] ?? null;
    $payment_method = $data['payment_method'] ?? 'cash';
    $paid_amount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : null;
    $notes = trim($data['notes'] ?? '');
    $items = $data['items'];
    
    // Validate customer exists and belongs to user
    $stmt = $db->prepare("SELECT id FROM customers WHERE id = ? AND user_id = ?");
    $stmt->execute([$customer_id, $user_id]);
    if (!$stmt->fetch()) {
        sendError('Customer not found');
    }
    
    // Generate invoice number
    $invoice_number = generateInvoiceNumber($db, $user_id, $invoice_type);
    
    // Calculate totals
    $subtotal = 0;
    $total_gst = 0;
    
    foreach ($items as $item) {
        $quantity = (int)$item['quantity'];
        $unit_price = (float)$item['unit_price'];
        $gst_rate = (float)($item['gst_rate'] ?? 18.00);
        
        $item_total = $quantity * $unit_price;
        $item_gst = $item_total * ($gst_rate / 100);
        
        $subtotal += $item_total;
        $total_gst += $item_gst;
    }
    
    $total_amount = $subtotal + $total_gst;
    
    // Automatic Payment Status Logic
    if ($paid_amount === null) {
        if (in_array($payment_method, ['cash', 'cheque', 'online'])) {
            $paid_amount = $total_amount;
            $payment_status = 'paid';
        } else {
            $paid_amount = 0;
            $payment_status = 'pending';
        }
    } else {
        if ($paid_amount >= $total_amount) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'pending';
        }
    }
    
    $db->beginTransaction();
    
    try {
        // Insert invoice
        $table = $invoice_type === 'sales' ? 'sales_invoices' : 'purchase_invoices';
        $customer_field = $invoice_type === 'sales' ? 'customer_id' : 'vendor_id';
        
        if ($invoice_type === 'purchase') {
            $stmt = $db->prepare("
                INSERT INTO {$table} (user_id, invoice_number, {$customer_field}, invoice_date, due_date, 
                                     subtotal, gst_amount, total_amount, paid_amount, payment_status, payment_method, notes,
                                     bank_name, account_number, ifsc_code, upi_id, qr_code_path, bank_branch, account_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, $invoice_number, $customer_id, $invoice_date, $due_date,
                $subtotal, $total_gst, $total_amount, $paid_amount, $payment_status, $payment_method, $notes,
                $data['bank_name'] ?? null, $data['account_number'] ?? null, $data['ifsc_code'] ?? null, $data['upi_id'] ?? null, $data['qr_code_path'] ?? null, $data['bank_branch'] ?? null, $data['account_name'] ?? null
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO {$table} (user_id, invoice_number, {$customer_field}, invoice_date, due_date, 
                                     subtotal, gst_amount, total_amount, paid_amount, payment_status, payment_method, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id, $invoice_number, $customer_id, $invoice_date, $due_date,
                $subtotal, $total_gst, $total_amount, $paid_amount, $payment_status, $payment_method, $notes
            ]);
        }
        
        $invoice_id = $db->lastInsertId();
        
        // Insert invoice items
        $items_table = $invoice_type === 'sales' ? 'sales_invoice_items' : 'purchase_invoice_items';
        
        $stmt = $db->prepare("
            INSERT INTO {$items_table} (invoice_id, product_id, quantity, unit_price, gst_rate, gst_amount, total_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            // Allow null product_id when user doesn't select a product from catalog
            $product_id = (isset($item['product_id']) && $item['product_id'] !== '' && $item['product_id'] !== null)
                ? (int)$item['product_id']
                : null;
            $quantity = (int)$item['quantity'];
            $unit_price = (float)$item['unit_price'];
            $gst_rate = (float)($item['gst_rate'] ?? 18.00);
            
            $item_total = $quantity * $unit_price;
            $item_gst = $item_total * ($gst_rate / 100);
            $item_total_with_gst = $item_total + $item_gst;
            
            $stmt->execute([
                $invoice_id, $product_id, $quantity, $unit_price, 
                $gst_rate, $item_gst, $item_total_with_gst
            ]);
        }
        
        $db->commit();
        sendSuccess(['invoice_id' => $invoice_id, 'invoice_number' => $invoice_number], 'Invoice created successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        sendError('Failed to create invoice: ' . $e->getMessage());
    }
}

function handleUpdateInvoice($db, $user_id, $invoice_type) {
    $invoice_id = $_GET['id'] ?? '';
    if (empty($invoice_id)) {
        sendError('Invoice ID is required');
    }
    
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($ct, 'multipart/form-data') !== false;

    if ($isMultipart) {
        $data = $_POST;
        if (isset($data['items']) && is_string($data['items'])) {
            $data['items'] = json_decode($data['items'], true);
        }
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?: []; 
        if(isset($data['items']) && is_string($data['items'])) $data['items'] = json_decode($data['items'], true);
    }
    
    $table = $invoice_type === 'sales' ? 'sales_invoices' : 'purchase_invoices';
    
    // Check if invoice belongs to user
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$invoice_id, $user_id]);
    if (!$stmt->fetch()) {
        sendError('Invoice not found');
    }
    
    $fields = ['payment_status', 'payment_method', 'notes', 'paid_amount'];
    $update_fields = [];
    $params = [];
    
    // If paid_amount is being updated, recalculate status if not provided
    if (isset($data['paid_amount']) && !isset($data['payment_status'])) {
        $stmt_total = $db->prepare("SELECT total_amount FROM {$table} WHERE id = ?");
        $stmt_total->execute([$invoice_id]);
        $total = (float)($stmt_total->fetch()['total_amount'] ?? 0);
        $p_amt = (float)$data['paid_amount'];
        
        if ($p_amt >= $total) $data['payment_status'] = 'paid';
        elseif ($p_amt > 0) $data['payment_status'] = 'partial';
        else $data['payment_status'] = 'pending';
    }
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = trim($data[$field]);
        }
    }
    
    if (empty($update_fields)) {
        sendError('No fields to update');
    }
    
    $params[] = $invoice_id;
    $params[] = $user_id;
    
    $sql = "UPDATE {$table} SET " . implode(', ', $update_fields) . " WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        sendSuccess([], 'Invoice updated successfully');
    } else {
        sendError('Failed to update invoice');
    }
}

function handleDeleteInvoice($db, $user_id, $invoice_type) {
    $invoice_id = $_GET['id'] ?? '';
    if (empty($invoice_id)) {
        sendError('Invoice ID is required');
    }
    
    $table = $invoice_type === 'sales' ? 'sales_invoices' : 'purchase_invoices';
    
    // Check if invoice belongs to user
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$invoice_id, $user_id]);
    if (!$stmt->fetch()) {
        sendError('Invoice not found');
    }
    
    $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$invoice_id, $user_id]);
    
    if ($result) {
        sendSuccess([], 'Invoice deleted successfully');
    } else {
        sendError('Failed to delete invoice');
    }
}

function generateInvoiceNumber($db, $user_id, $invoice_type) {
    // Get user's invoice prefix and start number
    $prefix_stmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'invoice_prefix'");
    $prefix_stmt->execute([$user_id]);
    $prefix = $prefix_stmt->fetch()['setting_value'] ?? 'INV';
    
    $start_stmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'invoice_start_number'");
    $start_stmt->execute([$user_id]);
    $start_number = (int)($start_stmt->fetch()['setting_value'] ?? 1001);
    
    // Get the last invoice number for this user
    $table = $invoice_type === 'sales' ? 'sales_invoices' : 'purchase_invoices';
    $stmt = $db->prepare("SELECT invoice_number FROM {$table} WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $last_invoice = $stmt->fetch();
    
    if ($last_invoice) {
        $last_number = (int)substr($last_invoice['invoice_number'], strlen($prefix));
        $next_number = $last_number + 1;
    } else {
        $next_number = $start_number;
    }
    
    return $prefix . $next_number;
}
?>

