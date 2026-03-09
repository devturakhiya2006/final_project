<?php
/**
 * Admin User Detail Dashboard
 * Responsibility: Displays a comprehensive profile for a single user, including their
 *                 customers, vendors, products, sales/purchase invoices, and financial analytics.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

/* ── Validate User ID from Query String ── */
$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($targetUserId <= 0) {
    header("Location: customers.php");
    exit();
}

try {
    // 1. Fetch the user's profile
    $userProfileQuery = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userProfileQuery->execute([$targetUserId]);
    $userProfile = $userProfileQuery->fetch();

    if (!$userProfile) {
        header("Location: customers.php");
        exit();
    }

    /* ── 2. Fetch Associated Records ── */

    // Customers belonging to this user
    $customerListQuery = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? AND type = 'customer' ORDER BY created_at DESC");
    $customerListQuery->execute([$targetUserId]);
    $customerList = $customerListQuery->fetchAll();

    // Vendors belonging to this user
    $vendorListQuery = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? AND type = 'vendor' ORDER BY created_at DESC");
    $vendorListQuery->execute([$targetUserId]);
    $vendorList = $vendorListQuery->fetchAll();

    // Products belonging to this user
    $productListQuery = $pdo->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
    $productListQuery->execute([$targetUserId]);
    $productList = $productListQuery->fetchAll();

    // Sales invoices
    $salesInvoiceQuery = $pdo->prepare("SELECT * FROM sales_invoices WHERE user_id = ? ORDER BY invoice_date DESC");
    $salesInvoiceQuery->execute([$targetUserId]);
    $salesInvoiceList = $salesInvoiceQuery->fetchAll();

    // Purchase invoices
    $purchaseInvoiceQuery = $pdo->prepare("SELECT * FROM purchase_invoices WHERE user_id = ? ORDER BY invoice_date DESC");
    $purchaseInvoiceQuery->execute([$targetUserId]);
    $purchaseInvoiceList = $purchaseInvoiceQuery->fetchAll();

    /* ── 3. Financial Summary (Aggregated Calculations) ── */

    // Total sales revenue and GST collected
    $salesSummaryQuery = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(gst_amount) as gst, SUM(subtotal) as subtotal FROM sales_invoices WHERE user_id = ?");
    $salesSummaryQuery->execute([$targetUserId]);
    $salesSummary = $salesSummaryQuery->fetch();
    $totalSalesAmount = $salesSummary['total'] ?? 0;
    $salesGstCollected = $salesSummary['gst'] ?? 0;
    $salesSubtotal = $salesSummary['subtotal'] ?? 0;

    // Total purchase expenditure
    $purchaseSummaryQuery = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(subtotal) as subtotal FROM purchase_invoices WHERE user_id = ?");
    $purchaseSummaryQuery->execute([$targetUserId]);
    $purchaseSummary = $purchaseSummaryQuery->fetch();
    $totalPurchaseAmount = $purchaseSummary['total'] ?? 0;
    $purchaseSubtotal = $purchaseSummary['subtotal'] ?? 0;

    // Net profit = sales subtotal minus purchase subtotal
    $netProfit = $salesSubtotal - $purchaseSubtotal;

    /* ── 4. Analytical Reports ── */

    // Top 3 best-selling products
    $bestSellersQuery = $pdo->prepare("
        SELECT p.name, SUM(ii.quantity) as qty_sold, SUM(ii.total_amount) as total_revenue
        FROM sales_invoice_items ii
        JOIN products p ON ii.product_id = p.id
        JOIN sales_invoices i ON ii.invoice_id = i.id
        WHERE i.user_id = ?
        GROUP BY p.id
        ORDER BY qty_sold DESC
        LIMIT 3
    ");
    $bestSellersQuery->execute([$targetUserId]);
    $bestSellingProducts = $bestSellersQuery->fetchAll();

    // Top 3 highest-spending customers
    $topCustomersQuery = $pdo->prepare("
        SELECT c.name, SUM(i.total_amount) as total_spent
        FROM sales_invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.user_id = ?
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT 3
    ");
    $topCustomersQuery->execute([$targetUserId]);
    $topSpendingCustomers = $topCustomersQuery->fetchAll();

    // Bottom 3 lowest-spending customers
    $lowestCustomersQuery = $pdo->prepare("
        SELECT c.name, COALESCE(SUM(i.total_amount), 0) as total_spent
        FROM customers c
        LEFT JOIN sales_invoices i ON c.id = i.customer_id
        WHERE c.user_id = ? AND c.type = 'customer'
        GROUP BY c.id
        ORDER BY total_spent ASC
        LIMIT 3
    ");
    $lowestCustomersQuery->execute([$targetUserId]);
    $lowestSpendingCustomers = $lowestCustomersQuery->fetchAll();

    // Bottom 3 least-sold products
    $lowestSellersQuery = $pdo->prepare("
        SELECT p.name, SUM(ii.quantity) as qty_sold
        FROM products p
        LEFT JOIN sales_invoice_items ii ON p.id = ii.product_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY qty_sold ASC
        LIMIT 3
    ");
    $lowestSellersQuery->execute([$targetUserId]);
    $lowestSellingProducts = $lowestSellersQuery->fetchAll();

}
catch (PDOException $dbException) {
    error_log("Admin User Details Error: " . $dbException->getMessage());
    header("Location: customers.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dynamic Dashboard - <?php echo htmlspecialchars($userProfile['name']); ?></title>
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --reports: #8b5cf6;
            --bg-light: #f8fafc;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .action-card i {
            font-size: 2rem;
            color: var(--primary);
            opacity: 0.8;
            transition: all 0.2s;
        }

        .card-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            border-color: var(--primary);
        }

        .action-card.active {
            background: var(--primary);
            border-color: var(--primary);
        }

        .action-card.active i {
            color: white;
            opacity: 1;
        }

        .action-card.reports-style:hover i { color: var(--reports); }
        .action-card.reports-style.active { background: var(--reports); border-color: var(--reports); }
        .action-card.reports-style.active i { color: white; }

        .stat-label { color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; line-height: 1; }

        .content-section { display: none; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); animation: fadeIn 0.3s ease; }
        .content-section.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .table-responsive { overflow-x: auto; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .details-table th { background: #f8fafc; text-align: left; padding: 1rem; color: #64748b; font-weight: 600; font-size: 0.875rem; border-bottom: 2px solid #e2e8f0; }
        .details-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #334155; }

        .header-bar { display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem; }
        .back-link { background: #f1f5f9; color: #475569; padding: 0.6rem 1.2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: background 0.2s; }
        .back-link:hover { background: #e2e8f0; }
        
        /* Reports Styling */
        .report-grid-alt { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .report-tile { background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 1rem; }
        .report-tile i { font-size: 1.5rem; color: var(--primary); }
        .report-tile.green { border-color: var(--success); }
        .report-tile.green i { color: var(--success); }
        .report-tile.red { border-color: var(--danger); }
        .report-tile.red i { color: var(--danger); }
        .report-tile.yellow { border-color: var(--warning); }
        .report-tile.yellow i { color: var(--warning); }
    </style>
</head>
<body class="bg-light">
    <div class="app">
        <main class="main" style="margin-left: 0; width: 100%; padding: 2rem; max-width: 1400px; margin: 0 auto;">
            
            <div class="header-bar">
                <div>
                    <h1 style="font-weight: 800; font-size: 2rem; color: #1e293b; margin: 0;"><?php echo htmlspecialchars($userProfile['name']); ?></h1>
                    <p style="color: #64748b; margin: 0.5rem 0;"><i class="fa-solid fa-briefcase" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($userProfile['business_name'] ?: 'No Business Name'); ?> • <i class="fa-solid fa-envelope" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($userProfile['email']); ?></p>
                </div>
                <a href="customers.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Users</a>
            </div>

            <!-- Summary Cards (Clickable) -->
            <div class="stat-grid">
                <div class="action-card active" onclick="showSection('customers', this)">
                    <i class="fa-solid fa-users"></i>
                    <div class="card-info">
                        <span class="stat-label">Customers</span>
                        <span class="stat-value"><?php echo count($customerList); ?></span>
                    </div>
                </div>
                <div class="action-card" onclick="showSection('vendors', this)">
                    <i class="fa-solid fa-shop"></i>
                    <div class="card-info">
                        <span class="stat-label">Vendors</span>
                        <span class="stat-value"><?php echo count($vendorList); ?></span>
                    </div>
                </div>
                <div class="action-card" onclick="showSection('products', this)">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <div class="card-info">
                        <span class="stat-label">Products</span>
                        <span class="stat-value"><?php echo count($productList); ?></span>
                    </div>
                </div>
                <div class="action-card" onclick="showSection('sales', this)">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <div class="card-info">
                        <span class="stat-label">Sales Invoices</span>
                        <span class="stat-value"><?php echo count($salesInvoiceList); ?></span>
                    </div>
                </div>
                <div class="action-card" onclick="showSection('purchase', this)">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <div class="card-info">
                        <span class="stat-label">Purchase Invoices</span>
                        <span class="stat-value"><?php echo count($purchaseInvoiceList); ?></span>
                    </div>
                </div>
                <div class="action-card reports-style" onclick="showSection('reports', this)">
                    <i class="fa-solid fa-chart-pie"></i>
                    <div class="card-info" style="color: inherit;">
                        <span class="stat-label" style="color: inherit;">View Reports</span>
                        <span class="stat-value" style="color: inherit;">Insights</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div id="content-area">
                
                <!-- Customers Section -->
                <div id="sec-customers" class="content-section active">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-users-viewfinder" style="color: var(--primary); margin-right: 10px;"></i> Managed Customers</h2>
                    <div class="table-responsive">
                        <table class="details-table">
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Created At</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerList as $c): ?>
                                <tr><td>#<?php echo $c['id']; ?></td><td><?php echo htmlspecialchars($c['name']); ?></td><td><?php echo htmlspecialchars($c['email']); ?></td><td><?php echo htmlspecialchars($c['mobile']); ?></td><td><?php echo date('Y-m-d', strtotime($c['created_at'])); ?></td></tr>
                                <?php
endforeach;
if (empty($customerList))
    echo "<tr><td colspan='5'>No customers added.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Vendors Section -->
                <div id="sec-vendors" class="content-section">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-store" style="color: var(--primary); margin-right: 10px;"></i> Managed Vendors</h2>
                    <div class="table-responsive">
                        <table class="details-table">
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Created At</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendorList as $v): ?>
                                <tr><td>#<?php echo $v['id']; ?></td><td><?php echo htmlspecialchars($v['name']); ?></td><td><?php echo htmlspecialchars($v['email']); ?></td><td><?php echo htmlspecialchars($v['mobile']); ?></td><td><?php echo date('Y-m-d', strtotime($v['created_at'])); ?></td></tr>
                                <?php
endforeach;
if (empty($vendorList))
    echo "<tr><td colspan='5'>No vendors added.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Products Section -->
                <div id="sec-products" class="content-section">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-box" style="color: var(--primary); margin-right: 10px;"></i> Managed Products</h2>
                    <div class="table-responsive">
                        <table class="details-table">
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>HSN/SAC</th><th>Price</th><th>Stock</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productList as $p): ?>
                                <tr><td>#<?php echo $p['id']; ?></td><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['hsn_sac']); ?></td><td>₹<?php echo number_format($p['price'], 2); ?></td><td><?php echo $p['stock_quantity']; ?></td></tr>
                                <?php
endforeach;
if (empty($productList))
    echo "<tr><td colspan='5'>No products added.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales Invoices Section -->
                <div id="sec-sales" class="content-section">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-file-invoice" style="color: var(--primary); margin-right: 10px;"></i> Sales Invoices</h2>
                    <div class="table-responsive">
                        <table class="details-table">
                            <thead>
                                <tr><th>Inv No</th><th>Date</th><th>Total</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesInvoiceList as $si): ?>
                                <tr><td><?php echo $si['invoice_number']; ?></td><td><?php echo $si['invoice_date']; ?></td><td>₹<?php echo number_format($si['total_amount'], 2); ?></td><td><?php echo ucfirst($si['payment_status']); ?></td></tr>
                                <?php
endforeach;
if (empty($salesInvoiceList))
    echo "<tr><td colspan='4'>No sales invoices found.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Purchase Invoices Section -->
                <div id="sec-purchase" class="content-section">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-cart-flatbed" style="color: var(--primary); margin-right: 10px;"></i> Purchase Invoices</h2>
                    <div class="table-responsive">
                        <table class="details-table">
                            <thead>
                                <tr><th>Inv No</th><th>Date</th><th>Total</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchaseInvoiceList as $pi): ?>
                                <tr><td><?php echo $pi['invoice_number']; ?></td><td><?php echo $pi['invoice_date']; ?></td><td>₹<?php echo number_format($pi['total_amount'], 2); ?></td><td><?php echo ucfirst($pi['payment_status']); ?></td></tr>
                                <?php
endforeach;
if (empty($purchaseInvoiceList))
    echo "<tr><td colspan='4'>No purchase invoices found.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Reports Section -->
                <div id="sec-reports" class="content-section" style="padding: 0; background: transparent; box-shadow: none;">
                    
                    <!-- Financial Summary -->
                    <div style="background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin-bottom: 2rem;">
                        <h2 style="margin-bottom: 1.5rem;"><i class="fa-solid fa-sack-dollar" style="color: var(--reports); margin-right: 10px;"></i> Financial Overview</h2>
                        <div class="report-grid-alt">
                            <div class="report-tile">
                                <i class="fa-solid fa-money-bill-trend-up"></i>
                                <div class="card-info">
                                    <span class="stat-label">Total Sales (Incl. Tax)</span>
                                    <div class="stat-value">₹<?php echo number_format($totalSalesAmount, 2); ?></div>
                                </div>
                            </div>
                            <div class="report-tile red">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                                <div class="card-info">
                                    <span class="stat-label">Total Purchase</span>
                                    <div class="stat-value">₹<?php echo number_format($totalPurchaseAmount, 2); ?></div>
                                </div>
                            </div>
                            <div class="report-tile yellow">
                                <i class="fa-solid fa-percent"></i>
                                <div class="card-info">
                                    <span class="stat-label">Sales GST Collected</span>
                                    <div class="stat-value">₹<?php echo number_format($salesGstCollected, 2); ?></div>
                                </div>
                            </div>
                            <div class="report-tile green">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                <div class="card-info">
                                    <span class="stat-label">Net Profit</span>
                                    <div class="stat-value">₹<?php echo number_format($netProfit, 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytical Lists -->
                    <div style="background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                        
                        <!-- Top Customers -->
                        <div>
                            <h3 style="margin-bottom: 1rem; font-size: 1rem; color: #1e293b;"><i class="fa-solid fa-crown" style="color: var(--warning); margin-right: 8px;"></i> Top 3 Customers</h3>
                            <table class="details-table">
                                <thead><tr><th>Name</th><th>Spent</th></tr></thead>
                                <tbody>
                                    <?php foreach ($topSpendingCustomers as $tc): ?>
                                    <tr><td><?php echo htmlspecialchars($tc['name']); ?></td><td>₹<?php echo number_format($tc['total_spent'], 2); ?></td></tr>
                                    <?php
endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Best Sellers -->
                        <div>
                            <h3 style="margin-bottom: 1rem; font-size: 1rem; color: #1e293b;"><i class="fa-solid fa-fire" style="color: var(--danger); margin-right: 8px;"></i> Top 3 Sellers</h3>
                            <table class="details-table">
                                <thead><tr><th>Product</th><th>Sold</th></tr></thead>
                                <tbody>
                                    <?php foreach ($bestSellingProducts as $bs): ?>
                                    <tr><td><?php echo htmlspecialchars($bs['name']); ?></td><td><?php echo $bs['qty_sold']; ?></td></tr>
                                    <?php
endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Lowest Customers -->
                        <div>
                            <h3 style="margin-bottom: 1rem; font-size: 1rem; color: #1e293b;"><i class="fa-solid fa-arrow-trend-down" style="color: var(--secondary); margin-right: 8px;"></i> Lowest 3 Customers</h3>
                            <table class="details-table">
                                <thead><tr><th>Name</th><th>Spent</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lowestSpendingCustomers as $lc): ?>
                                    <tr><td><?php echo htmlspecialchars($lc['name']); ?></td><td>₹<?php echo number_format($lc['total_spent'], 2); ?></td></tr>
                                    <?php
endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Lowest Sellers -->
                        <div>
                            <h3 style="margin-bottom: 1rem; font-size: 1rem; color: #1e293b;"><i class="fa-solid fa-triangle-exclamation" style="color: var(--warning); margin-right: 8px;"></i> Lowest 3 Products</h3>
                            <table class="details-table">
                                <thead><tr><th>Product</th><th>Sold</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lowestSellingProducts as $ls): ?>
                                    <tr><td><?php echo htmlspecialchars($ls['name']); ?></td><td><?php echo (int)$ls['qty_sold']; ?></td></tr>
                                    <?php
endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
        function showSection(sectionId, cardElement) {
            // Remove active class from all cards
            document.querySelectorAll('.action-card').forEach(card => card.classList.remove('active'));
            // Add active class to clicked card
            cardElement.classList.add('active');

            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            // Show target section
            document.getElementById('sec-' + sectionId).classList.add('active');
        }
    </script>
</body>
</html>
