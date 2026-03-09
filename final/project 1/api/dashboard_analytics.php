<?php
/**
 * Dashboard Analytics Functions
 * Responsibility: Additional analytics queries for the dashboard — least selling products,
 *                 low stock alerts, top vendors, and overdue invoice tracking.
 */

/**
 * Retrieves the bottom 5 least-sold products ranked by total quantity ascending
 */
function getLeastSellingProducts($db, $user_id)
{
    try {
        $leastSellersQuery = $db->prepare("
            SELECT
                p.id,
                p.name,
                p.price,
                COALESCE(SUM(sii.quantity), 0) AS total_quantity
            FROM products p
            LEFT JOIN sales_invoice_items sii ON p.id = sii.product_id
            LEFT JOIN sales_invoices si ON sii.invoice_id = si.id AND si.user_id = p.user_id
            WHERE p.user_id = ?
            GROUP BY p.id, p.name, p.price
            ORDER BY total_quantity ASC
            LIMIT 1
        ");
        $leastSellersQuery->execute([$user_id]);
        return $leastSellersQuery->fetchAll();
    }
    catch (PDOException $dbException) {
        error_log("Dashboard - Least Selling Products Error: " . $dbException->getMessage());
        return [];
    }
}

/**
 * Retrieves products with stock quantity at or below 10 units
 */
function getLowStockProducts($db, $user_id)
{
    try {
        $lowStockQuery = $db->prepare("
            SELECT
                p.id,
                p.name,
                p.stock_quantity,
                p.price
            FROM products p
            WHERE p.user_id = ? AND p.stock_quantity <= 10
            ORDER BY p.stock_quantity ASC
            LIMIT 1
        ");
        $lowStockQuery->execute([$user_id]);
        return $lowStockQuery->fetchAll();
    }
    catch (PDOException $dbException) {
        error_log("Dashboard - Low Stock Products Error: " . $dbException->getMessage());
        return [];
    }
}

/**
 * Retrieves the top 5 vendors ranked by total purchase amount
 */
function getTopVendors($db, $user_id)
{
    try {
        $topVendorsQuery = $db->prepare("
            SELECT
                c.id,
                c.name,
                c.email,
                COUNT(pi.id) AS invoice_count,
                COALESCE(SUM(pi.total_amount), 0) AS total_amount
            FROM customers c
            LEFT JOIN purchase_invoices pi ON c.id = pi.vendor_id AND c.user_id = pi.user_id
            WHERE c.user_id = ? AND c.type = 'vendor'
            GROUP BY c.id, c.name, c.email
            ORDER BY total_amount DESC
            LIMIT 1
        ");
        $topVendorsQuery->execute([$user_id]);
        return $topVendorsQuery->fetchAll();
    }
    catch (PDOException $dbException) {
        error_log("Dashboard - Top Vendors Error: " . $dbException->getMessage());
        return [];
    }
}

/**
 * Retrieves sales invoices with pending or partial payment status
 */
function getSalesInvoicesDue($db, $user_id)
{
    try {
        $salesDueQuery = $db->prepare("
            SELECT
                si.id,
                si.invoice_number,
                si.invoice_date,
                si.total_amount,
                si.payment_status,
                c.name AS customer_name
            FROM sales_invoices si
            LEFT JOIN customers c ON si.customer_id = c.id
            WHERE si.user_id = ? AND si.payment_status IN ('pending', 'partial')
            ORDER BY si.invoice_date ASC
            LIMIT 1
        ");
        $salesDueQuery->execute([$user_id]);
        return $salesDueQuery->fetchAll();
    }
    catch (PDOException $dbException) {
        error_log("Dashboard - Sales Invoices Due Error: " . $dbException->getMessage());
        return [];
    }
}

/**
 * Retrieves purchase invoices with pending or partial payment status
 */
function getPurchaseInvoicesDue($db, $user_id)
{
    try {
        $purchaseDueQuery = $db->prepare("
            SELECT
                pi.id,
                pi.invoice_number,
                pi.invoice_date,
                pi.total_amount,
                pi.payment_status,
                c.name AS vendor_name
            FROM purchase_invoices pi
            LEFT JOIN customers c ON pi.vendor_id = c.id
            WHERE pi.user_id = ? AND pi.payment_status IN ('pending', 'partial')
            ORDER BY pi.invoice_date ASC
            LIMIT 1
        ");
        $purchaseDueQuery->execute([$user_id]);
        return $purchaseDueQuery->fetchAll();
    }
    catch (PDOException $dbException) {
        error_log("Dashboard - Purchase Invoices Due Error: " . $dbException->getMessage());
        return [];
    }
}
