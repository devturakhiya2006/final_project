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
header('Access-Control-Allow-Origin: *');
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
// For session-based auth, ensure cookies work with credentialed requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

if (!isset($_SESSION['user_id'])) {
    sendError('Authentication required. Please login first.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

// Safety check: ensure user_id is a positive integer
if (!is_numeric($user_id) || $user_id <= 0) {
    sendError('Invalid user session', 401);
}

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            handleGetProducts($db, $user_id);
            break;
        case 'POST':
            handleCreateProduct($db, $user_id);
            break;
        case 'PUT':
            handleUpdateProduct($db, $user_id);
            break;
        case 'DELETE':
            handleDeleteProduct($db, $user_id);
            break;
        default:
            sendError('Method not allowed');
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

function handleGetProducts($db, $user_id) {
    try {
        $type = $_GET['type'] ?? 'all'; // product, service, or all
        $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where_conditions = ["user_id = ?"];
        $params = [$user_id];

        if ($type !== 'all') {
            $where_conditions[] = "type = ?";
            $params[] = $type;
        }

        if (!empty($search)) {
            $where_conditions[] = "(name LIKE ? OR description LIKE ? OR hsn_sac LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Get total count (use fetchColumn)
        $count_sql = "SELECT COUNT(*) FROM products WHERE $where_clause";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get products; inline validated LIMIT/OFFSET to avoid driver bind issues
        $sql = "SELECT * FROM products WHERE $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        sendSuccess([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ]
        ]);
    } catch (Throwable $e) {
        sendError('Failed to fetch products: ' . $e->getMessage(), 500);
    }
}

function handleCreateProduct($db, $user_id) {
    try {
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $isMultipart = stripos($ct, 'multipart/form-data') !== false;
        $data = [];
        $attachment_path = '';

        if ($isMultipart) {
            // Read fields from form-data
            $data = $_POST;
            // Handle file upload if provided
            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = realpath(__DIR__ . '/../uploads');
                if ($uploadDir === false) {
                    // Try to create if not exists
                    @mkdir(__DIR__ . '/../uploads', 0775, true);
                    $uploadDir = realpath(__DIR__ . '/../uploads');
                }
                $prodDir = $uploadDir ? ($uploadDir . DIRECTORY_SEPARATOR . 'products') : false;
                if ($prodDir && !is_dir($prodDir)) { @mkdir($prodDir, 0775, true); }
                if ($prodDir && is_dir($prodDir)) {
                    $original = basename($_FILES['attachment']['name']);
                    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
                    $target = $prodDir . DIRECTORY_SEPARATOR . (time() . '_' . $safeName);
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                        // Store web path relative to api/
                        $attachment_path = '../uploads/products/' . basename($target);
                    }
                }
            }
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                sendError('Invalid JSON');
            }
        }

        $required_fields = ['name', 'price'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                sendError("Field '$field' is required");
            }
        }

        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $hsn_sac = trim($data['hsn_sac'] ?? '');
        $unit = trim($data['unit'] ?? 'pcs');
        $price = (float)$data['price'];
        $gst_rate = (float)($data['gst_rate'] ?? 18.00);
        $stock_quantity = (int)($data['stock_quantity'] ?? 0);
        $type = $data['type'] ?? 'product';

        // New optional fields
        $product_code = trim($data['product_code'] ?? '');
        $sku          = trim($data['sku'] ?? '');
        $batch_no     = trim($data['batch_no'] ?? '');
        $tax_type     = in_array(($data['tax_type'] ?? ''), ['inclusive','exclusive']) ? $data['tax_type'] : 'exclusive';
        $eligible_itc = isset($data['eligible_itc']) ? (int)!!$data['eligible_itc'] : 0;
        $stock_mode   = in_array(($data['stock_mode'] ?? ''), ['normal','batch']) ? $data['stock_mode'] : 'normal';
        $available_qty= (int)($data['available_qty'] ?? $stock_quantity);
        $mrp          = isset($data['mrp']) ? (float)$data['mrp'] : null;
        $purchase_price = isset($data['purchase_price']) ? (float)$data['purchase_price'] : null;
        $purchase_price_incl_tax = isset($data['purchase_price_incl_tax']) ? (float)$data['purchase_price_incl_tax'] : null;
        $sale_price   = isset($data['sale_price']) ? (float)$data['sale_price'] : null;
        $product_group= trim($data['product_group'] ?? '');
        $discount_type= in_array(($data['discount_type'] ?? ''), ['none','percent','amount']) ? $data['discount_type'] : 'none';
        if (!$attachment_path) {
            $attachment_path = trim($data['attachment_path'] ?? '');
        }
        $visible_all_docs = isset($data['visible_all_docs']) ? (int)!!$data['visible_all_docs'] : 1;
        $track_inventory  = isset($data['track_inventory']) ? (int)!!$data['track_inventory'] : 1;

        if (!in_array($type, ['product', 'service'])) {
            sendError('Type must be either product or service');
        }

        if ($price < 0) {
            sendError('Price cannot be negative');
        }

        $stmt = $db->prepare(
            "INSERT INTO products (
                user_id, name, description, hsn_sac, unit, price, gst_rate, stock_quantity, type,
                product_code, sku, batch_no, tax_type, eligible_itc, stock_mode, available_qty, mrp,
                purchase_price, purchase_price_incl_tax, sale_price, product_group, discount_type,
                attachment_path, visible_all_docs, track_inventory
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )"
        );

        $result = $stmt->execute([
            $user_id, $name, $description, $hsn_sac, $unit,
            $price, $gst_rate, $stock_quantity, $type,
            $product_code, $sku, $batch_no, $tax_type, $eligible_itc, $stock_mode, $available_qty, $mrp,
            $purchase_price, $purchase_price_incl_tax, $sale_price, $product_group, $discount_type,
            $attachment_path, $visible_all_docs, $track_inventory
        ]);

        if ($result) {
            $product_id = $db->lastInsertId();
            sendSuccess(['product_id' => $product_id], 'Product created successfully');
        } else {
            sendError('Failed to create product');
        }
    } catch (Throwable $e) {
        sendError('Failed to create product: ' . $e->getMessage(), 500);
    }
}

function handleUpdateProduct($db, $user_id) {
    $product_id = $_GET['id'] ?? '';
    if (empty($product_id)) {
        sendError('Product ID is required');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if product belongs to user
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);
    if (!$stmt->fetch()) {
        sendError('Product not found');
    }
    
    $fields = ['name', 'description', 'hsn_sac', 'unit', 'price', 'gst_rate', 'stock_quantity', 'type'];
    $update_fields = [];
    $params = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            if ($field === 'price' || $field === 'gst_rate') {
                $params[] = (float)$data[$field];
            } elseif ($field === 'stock_quantity') {
                $params[] = (int)$data[$field];
            } else {
                $params[] = trim($data[$field]);
            }
        }
    }
    
    if (empty($update_fields)) {
        sendError('No fields to update');
    }
    
    $params[] = $product_id;
    $params[] = $user_id;
    
    $sql = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        sendSuccess([], 'Product updated successfully');
    } else {
        sendError('Failed to update product');
    }
}

function handleDeleteProduct($db, $user_id) {
    $product_id = $_GET['id'] ?? '';
    if (empty($product_id)) {
        sendError('Product ID is required');
    }
    
    // Check if product belongs to user
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);
    if (!$stmt->fetch()) {
        sendError('Product not found');
    }
    
    $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$product_id, $user_id]);
    
    if ($result) {
        sendSuccess([], 'Product deleted successfully');
    } else {
        sendError('Failed to delete product');
    }
}
?>
