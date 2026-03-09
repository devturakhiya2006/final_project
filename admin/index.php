<?php
/**
 * Admin Dashboard
 * Responsibility: Displays top-level business metrics (revenue, invoices, pending payments,
 *                 active customers) and a table of recent invoices for quick admin overview.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

/* ── Fetch Dashboard Statistics ── */
try {
  // 1. Total Revenue (sum of all sales invoices)
  $revenueQuery = $pdo->query("SELECT SUM(total_amount) as total FROM sales_invoices");
  $totalRevenue = $revenueQuery->fetch()['total'] ?? 0;

  // 2. Invoices Sent (total sales invoice count)
  $invoiceCountQuery = $pdo->query("SELECT COUNT(*) as count FROM sales_invoices");
  $invoiceCount = $invoiceCountQuery->fetch()['count'] ?? 0;

  // 3. Pending Payment (unpaid invoice total)
  $pendingQuery = $pdo->query("SELECT SUM(total_amount) as pending FROM sales_invoices WHERE payment_status = 'pending'");
  $pendingPaymentTotal = $pendingQuery->fetch()['pending'] ?? 0;

  // 4. Active Customers
  $customerCountQuery = $pdo->query("SELECT COUNT(*) as count FROM customers");
  $activeCustomerCount = $customerCountQuery->fetch()['count'] ?? 0;

  // 5. Recent Invoices (latest 5 with customer names)
  $recentInvoicesQuery = $pdo->query("
        SELECT invoice.*, contact.name as customer_name 
        FROM sales_invoices invoice 
        JOIN customers contact ON invoice.customer_id = contact.id 
        ORDER BY invoice.created_at DESC 
        LIMIT 5
    ");
  $recentInvoices = $recentInvoicesQuery->fetchAll();

}
catch (PDOException $dbException) {
  // Graceful fallback if tables don't exist yet
  $totalRevenue = 0;
  $invoiceCount = 0;
  $pendingPaymentTotal = 0;
  $activeCustomerCount = 0;
  $recentInvoices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Dashboard</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="app">
    <!-- LEFT SIDEBAR -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <span class="logo-dot"></span>
        <span class="logo-text">Go Invoice</span>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-item active" href="index.php">
          <i class="fa-solid fa-gauge-high"></i>
          <span>Dashboard</span>
        </a>
        <a class="nav-item" href="invoices.php">
          <i class="fa-solid fa-file-invoice"></i>
          <span>Invoices</span>
        </a>
        <a class="nav-item" href="customers.php">
          <i class="fa-solid fa-users"></i>
          <span>Customers</span>
        </a>
        <a class="nav-item" href="products.php">
          <i class="fa-solid fa-boxes-stacked"></i>
          <span>Products</span>
        </a>
        <a class="nav-item" href="reports.php">
          <i class="fa-solid fa-chart-line"></i>
          <span>Reports</span>
        </a>
        <a class="nav-item" href="support.php">
          <i class="fa-solid fa-headset"></i>
          <span>Support</span>
        </a>
        <a class="nav-item" href="settings.php">
          <i class="fa-solid fa-gear"></i>
          <span>Settings</span>
        </a>
      </nav>
    </aside>

    <!-- MAIN AREA -->
    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Dashboard</h1>
        <div class="topbar-right">
          <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
              <div class="user-name"><?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Super Admin'; ?></div>
              <div class="user-role">Administrator</div>
            </div>
          </div>
          <a href="logout.php"><button class="btn small logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
        </div>
      </header>

      <!-- Four summary cards -->
      <section class="cards">
        <div class="card">
          <div class="card-label">Total Revenue</div>
          <div class="card-value">₹<?php echo number_format($totalRevenue, 2); ?></div>
        </div>
        <div class="card">
          <div class="card-label">Invoices Sent</div>
          <div class="card-value"><?php echo number_format($invoiceCount); ?></div>
        </div>
        <div class="card">
          <div class="card-label">Pending Payment</div>
          <div class="card-value">₹<?php echo number_format($pendingPaymentTotal, 2); ?></div>
        </div>
        <div class="card">
          <div class="card-label">Active Customers</div>
          <div class="card-value"><?php echo number_format($activeCustomerCount); ?></div>
        </div>
      </section>

      <!-- Two panels: table + chart -->
      <div class="grid-2">
        <!-- Recent Invoice panel -->
        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Recent Invoices</h2>
            <div class="panel-actions">
              <a href="invoices.php" class="link">View All</a>
            </div>
          </div>

          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Invoice#</th>
                  <th>Customer</th>
                  <th>Date</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($recentInvoices) > 0): ?>
                    <?php foreach ($recentInvoices as $inv): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                          <td><?php echo htmlspecialchars($inv['customer_name']); ?></td>
                          <td><?php echo date('M d, Y', strtotime($inv['invoice_date'])); ?></td>
                          <td>₹<?php echo number_format($inv['total_amount'], 2); ?></td>
                          <td>
                            <span class="status <?php echo strtolower($inv['payment_status']); ?>">
                                <?php echo ucfirst($inv['payment_status']); ?>
                            </span>
                          </td>
                        </tr>
                    <?php
  endforeach; ?>
                <?php
else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 20px;">No recent invoices found.</td></tr>
                <?php
endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Recent Trend (Placeholder for now) -->
        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Recent Trend</h2>
            <div class="dropdown">
              <button class="dropdown-btn">Weekly <span class="caret"></span></button>
            </div>
          </div>
          <div class="chart">
             <!-- Simplified placeholder chart -->
             <div style="display: flex; align-items: flex-end; justify-content: space-around; height: 100%; padding: 0 10px;">
                 <div style="width: 10%; background: #e0e0e0; height: 40%; border-radius: 4px;"></div>
                 <div style="width: 10%; background: #e0e0e0; height: 70%; border-radius: 4px;"></div>
                 <div style="width: 10%; background: #e0e0e0; height: 50%; border-radius: 4px;"></div>
                 <div style="width: 10%; background: #4a90e2; height: 90%; border-radius: 4px;"></div>
                 <div style="width: 10%; background: #e0e0e0; height: 60%; border-radius: 4px;"></div>
             </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
