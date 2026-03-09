<?php
// Prevent any unwanted output (like whitespace or warnings) from breaking JSON
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once __DIR__ . '/dashboard_analytics.php';

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

// Handle CORS
// Credentialed CORS for cross-origin frontend (Live Server)
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: http://localhost');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Ensure errors are not echoed into JSON responses
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// JSON helpers (kept local to this endpoint)
if (!function_exists('sendResponse')) {
    function sendResponse($data, $status = 200) {
        // Clear any previous output (whitespace, notices, BOMs)
        ob_clean();
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
if (!function_exists('sendError')) {
    function sendError($message, $status = 400) {
        sendResponse(['error' => $message], $status);
    }
}
if (!function_exists('sendSuccess')) {
    function sendSuccess($data, $message = 'Success') {
        sendResponse(['success' => true, 'message' => $message, 'data' => $data]);
    }
}

// Access the PDO created in config/database.php
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

// New actions for snapshots
$action = $_GET['action'] ?? '';


if ($action === 'save_snapshot' || $action === 'get_snapshots') {
    try {
        $db = getDB();
        // Ensure snapshots table exists
        $db->exec("CREATE TABLE IF NOT EXISTS dashboard_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            period_type ENUM('day','week','month') NOT NULL,
            period_key VARCHAR(16) NOT NULL,
            sales_total DECIMAL(12,2) DEFAULT 0,
            sales_gst DECIMAL(12,2) DEFAULT 0,
            purchases_total DECIMAL(12,2) DEFAULT 0,
            purchases_gst DECIMAL(12,2) DEFAULT 0,
            customers_count INT DEFAULT 0,
            products_count INT DEFAULT 0,
            top_customers TEXT NULL,
            top_products TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_snap_user (user_id, period_type, period_key)
        ) ENGINE=InnoDB");

        if ($action === 'save_snapshot') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload || !isset($payload['period_type']) || !isset($payload['period_key'])) {
                sendError('Invalid payload');
            }
            $pt = $payload['period_type'];
            $pk = $payload['period_key'];
            $stats = $payload['stats'] ?? [];
            $sales_total = (float)($stats['sales']['total'] ?? 0);
            $sales_gst = (float)($stats['sales']['gst'] ?? 0);
            $purchases_total = (float)($stats['purchases']['total'] ?? 0);
            $purchases_gst = (float)($stats['purchases']['gst'] ?? 0);
            $customers_count = (int)($payload['customers'] ?? ($stats['customers'] ?? 0));
            $products_count = (int)($payload['products'] ?? ($stats['products'] ?? 0));
            $top_customers = json_encode($payload['top_customers'] ?? []);
            $top_products = json_encode($payload['top_products'] ?? []);

            $stmt = $db->prepare("INSERT INTO dashboard_snapshots
                (user_id, period_type, period_key, sales_total, sales_gst, purchases_total, purchases_gst, customers_count, products_count, top_customers, top_products)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $pt, $pk, $sales_total, $sales_gst, $purchases_total, $purchases_gst, $customers_count, $products_count, $top_customers, $top_products]);
            sendSuccess(['saved' => true]);
        }

        if ($action === 'get_snapshots') {
            $period_type = $_GET['period_type'] ?? 'month';
            $limit = (int)($_GET['limit'] ?? 12);
            if ($limit < 1 || $limit > 100) { $limit = 12; }
            $stmt = $db->prepare("SELECT period_type, period_key, sales_total, sales_gst, purchases_total, purchases_gst, customers_count, products_count, top_customers, top_products, created_at
                FROM dashboard_snapshots WHERE user_id = ? AND period_type = ? ORDER BY created_at DESC LIMIT $limit");
            $stmt->execute([$user_id, $period_type]);
            $rows = $stmt->fetchAll();
            sendSuccess($rows);
        }
    } catch (Exception $e) {
        sendError('Server error: ' . $e->getMessage(), 500);
    }
    exit;
}

try {
    $db = getDB();
    
    // Get dashboard statistics
    $stats = getDashboardStats($db, $user_id);
    $recent_invoices = getRecentInvoices($db, $user_id);
    $outstanding_amounts = getOutstandingAmounts($db, $user_id);
    $top_customers = getTopCustomers($db, $user_id);
    $top_products = getTopProducts($db, $user_id);

    $least_selling_products = getLeastSellingProducts($db, $user_id);
    $low_stock_products = getLowStockProducts($db, $user_id);
    $top_vendors = getTopVendors($db, $user_id);
    $sales_invoices_due = getSalesInvoicesDue($db, $user_id);
    $purchase_invoices_due = getPurchaseInvoicesDue($db, $user_id);
    
    sendSuccess([
        'stats' => $stats,
        'recent_invoices' => $recent_invoices,
        'outstanding_amounts' => $outstanding_amounts,
        'top_customers' => $top_customers,
        'top_products' => $top_products,
        'least_selling_products' => $least_selling_products,
        'low_stock_products' => $low_stock_products,
        'top_vendors' => $top_vendors,
        'sales_invoices_due' => $sales_invoices_due,
        'purchase_invoices_due' => $purchase_invoices_due
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

function getDashboardStats($db, $user_id) {
    $current_month = date('Y-m');
    
    // Sales stats for current month
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(gst_amount), 0) as total_gst,
            COUNT(*) as invoice_count
        FROM sales_invoices 
        WHERE user_id = ? AND DATE_FORMAT(invoice_date, '%Y-%m') = ?
    ");
    $stmt->execute([$user_id, $current_month]);
    $sales_stats = $stmt->fetch();
    
    // Purchase stats for current month
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_purchases,
            COALESCE(SUM(gst_amount), 0) as total_gst,
            COUNT(*) as invoice_count
        FROM purchase_invoices 
        WHERE user_id = ? AND DATE_FORMAT(invoice_date, '%Y-%m') = ?
    ");
    $stmt->execute([$user_id, $current_month]);
    $purchase_stats = $stmt->fetch();
    
    // Total customers and products
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM customers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_customers = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_products = $stmt->fetch()['total'];
    
    return [
        'sales' => [
            'total' => (float)$sales_stats['total_sales'],
            'gst' => (float)$sales_stats['total_gst'],
            'count' => (int)$sales_stats['invoice_count']
        ],
        'purchases' => [
            'total' => (float)$purchase_stats['total_purchases'],
            'gst' => (float)$purchase_stats['total_gst'],
            'count' => (int)$purchase_stats['invoice_count']
        ],
        'customers' => (int)$total_customers,
        'products' => (int)$total_products
    ];
}

function getRecentInvoices($db, $user_id) {
    $stmt = $db->prepare("
        SELECT 
            'sales' as type,
            si.id,
            si.invoice_number,
            si.invoice_date,
            si.total_amount,
            si.payment_status,
            c.name as customer_name
        FROM sales_invoices si
        LEFT JOIN customers c ON si.customer_id = c.id
        WHERE si.user_id = ?
        
        UNION ALL
        
        SELECT 
            'purchase' as type,
            pi.id,
            pi.invoice_number,
            pi.invoice_date,
            pi.total_amount,
            pi.payment_status,
            c.name as customer_name
        FROM purchase_invoices pi
        LEFT JOIN customers c ON pi.vendor_id = c.id
        WHERE pi.user_id = ?
        
        ORDER BY invoice_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

function getOutstandingAmounts($db, $user_id) {
    // Sales outstanding (money to receive)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN total_amount ELSE 0 END), 0) as partial_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount
        FROM sales_invoices 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $sales_outstanding = $stmt->fetch();
    
    // Purchase outstanding (money to pay)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN total_amount ELSE 0 END), 0) as partial_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount
        FROM purchase_invoices 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $purchase_outstanding = $stmt->fetch();
    
    return [
        'sales' => [
            'pending' => (float)$sales_outstanding['pending_amount'],
            'partial' => (float)$sales_outstanding['partial_amount'],
            'paid' => (float)$sales_outstanding['paid_amount']
        ],
        'purchases' => [
            'pending' => (float)$purchase_outstanding['pending_amount'],
            'partial' => (float)$purchase_outstanding['partial_amount'],
            'paid' => (float)$purchase_outstanding['paid_amount']
        ]
    ];
}

function getTopCustomers($db, $user_id) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            COUNT(si.id) as invoice_count,
            COALESCE(SUM(si.total_amount), 0) as total_amount
        FROM customers c
        LEFT JOIN sales_invoices si ON c.id = si.customer_id AND c.user_id = si.user_id
        WHERE c.user_id = ? AND c.type = 'customer'
        GROUP BY c.id, c.name, c.email
        ORDER BY total_amount DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getTopProducts($db, $user_id) {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            COALESCE(SUM(sii.quantity), 0) as total_quantity,
            COALESCE(SUM(sii.total_amount), 0) as total_amount
        FROM products p
        LEFT JOIN sales_invoice_items sii ON p.id = sii.product_id
        LEFT JOIN sales_invoices si ON sii.invoice_id = si.id AND si.user_id = p.user_id
        WHERE p.user_id = ?
        GROUP BY p.id, p.name, p.price
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
