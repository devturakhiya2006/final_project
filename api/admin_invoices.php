<?php
/**
 * Admin Invoices API
 * Responsibility: Provides administrative overrides for viewing and updating invoice details across the system.
 */

require_once '../config/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Security: Enforce administrative authentication
 */
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendFailureResponse('Administrative authentication is required to access these management functions.', 401);
}

// Global error handling configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$targetInvoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $database = getDatabaseConnection();

    // Direct the request to the appropriate administrative routine
    switch ($requestMethod) {
        case 'GET':
            if ($targetInvoiceId > 0) {
                fetchDetailedInvoiceRecord($database, $targetInvoiceId);
            }
            else {
                sendFailureResponse('A valid invoice identification is required.');
            }
            break;

        case 'PUT':
            if ($targetInvoiceId > 0) {
                applyAdministrativeInvoiceUpdates($database, $targetInvoiceId);
            }
            else {
                sendFailureResponse('An invoice identification is mandatory for applying updates.');
            }
            break;

        default:
            sendFailureResponse('The requested management operation is not supported.', 405);
            break;
    }
}
catch (Exception $exception) {
    // Record internal failure and provide humanized feedback
    error_log("Admin Invoices API Internal Failure: " . $exception->getMessage());
    sendFailureResponse('A system error occurred while processing the administrative request.', 500);
}

/**
 * Retrieves a comprehensive invoice record including line items and customer association
 */
function fetchDetailedInvoiceRecord($database, $targetInvoiceId)
{
    try {
        // Retrieve the primary invoice data joined with customer contact info
        $invoiceSql = "
            SELECT invoice.*, contact.name as customer_name, contact.email as customer_email, 
                   contact.mobile as customer_mobile, contact.address as customer_address
            FROM sales_invoices invoice
            LEFT JOIN customers contact ON invoice.customer_id = contact.id 
            WHERE invoice.id = ?
        ";

        $invoiceStatement = $database->prepare($invoiceSql);
        $invoiceStatement->execute([$targetInvoiceId]);
        $invoiceData = $invoiceStatement->fetch(PDO::FETCH_ASSOC);

        if (!$invoiceData) {
            sendFailureResponse('The requested invoice record could not be located.', 404);
        }

        // Retrieve all associated line items and their product names
        $itemsSql = "
            SELECT line_item.*, product.name as product_name
            FROM sales_invoice_items line_item 
            LEFT JOIN products product ON line_item.product_id = product.id 
            WHERE line_item.invoice_id = ?
            ORDER BY line_item.id ASC
        ";

        $itemsStatement = $database->prepare($itemsSql);
        $itemsStatement->execute([$targetInvoiceId]);
        $invoiceData['items'] = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

        sendSuccessResponse($invoiceData, 'Detailed invoice records retrieved successfully.');

    }
    catch (PDOException $pdoException) {
        error_log("Database Retrieval Error (Admin): " . $pdoException->getMessage());
        sendFailureResponse('Internal error encountered while fetching invoice details.', 500);
    }
}

/**
 * Applies administrative modifications to an invoice and its line items within a transaction
 */
function applyAdministrativeInvoiceUpdates($database, $targetInvoiceId)
{
    try {
        $rawInput = file_get_contents('php://input');
        $invoicePayload = json_decode($rawInput, true);

        if (!$invoicePayload) {
            sendFailureResponse('The update data provided is missing or invalid.');
        }

        // Begin transaction to ensure data consistency across tables
        $database->beginTransaction();

        // 1. Update the primary invoice record
        $updateSql = "
            UPDATE sales_invoices SET 
                invoice_date = ?, 
                payment_status = ?, 
                payment_method = ?, 
                notes = ?, 
                subtotal = ?, 
                gst_amount = ?, 
                total_amount = ?, 
                paid_amount = ? 
            WHERE id = ?
        ";

        $updateStatement = $database->prepare($updateSql);
        $updateStatement->execute([
            $invoicePayload['invoice_date'],
            $invoicePayload['payment_status'],
            $invoicePayload['payment_method'],
            $invoicePayload['notes'],
            $invoicePayload['subtotal'],
            $invoicePayload['gst_amount'],
            $invoicePayload['total_amount'],
            $invoicePayload['paid_amount'],
            $targetInvoiceId
        ]);

        // 2. Synchronize line items (Clean sweep and re-insert for consistency)
        if (isset($invoicePayload['items']) && is_array($invoicePayload['items'])) {
            $deleteStatement = $database->prepare("DELETE FROM sales_invoice_items WHERE invoice_id = ?");
            $deleteStatement->execute([$targetInvoiceId]);

            $insertItemSql = "
                INSERT INTO sales_invoice_items 
                (invoice_id, product_id, quantity, unit_price, gst_rate, gst_amount, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $insertItemStatement = $database->prepare($insertItemSql);

            foreach ($invoicePayload['items'] as $item) {
                $insertItemStatement->execute([
                    $targetInvoiceId,
                    ($item['product_id'] > 0 ? $item['product_id'] : null),
                    $item['quantity'],
                    $item['unit_price'],
                    $item['gst_rate'],
                    $item['gst_amount'],
                    $item['total_amount']
                ]);
            }
        }

        $database->commit();
        sendSuccessResponse([], 'The invoice and all associated items have been updated successfully.');

    }
    catch (Exception $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        error_log("Database Update Error (Admin): " . $exception->getMessage());
        sendFailureResponse('A critical database error occurred while applying invoice modifications.', 500);
    }
}

/**
 * Returns the active PDO database instance
 */
function getDatabaseConnection()
{
    global $pdo;
    return $pdo;
}

/**
 * Standard failure response helper
 */
function sendFailureResponse($message, $httpCode = 400)
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Standard success response helper
 */
function sendSuccessResponse($responseData = [], $successMessage = 'Success')
{
    echo json_encode(['success' => true, 'message' => $successMessage, 'data' => $responseData]);
    exit;
}
?>
