<?php
/**
 * Admin Support Inquiry Management
 * Responsibility: Lists all support and partner inquiries with review and removal capabilities.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Process Inquiry Deletion Request
if (isset($_GET['delete'])) {
  try {
    $targetInquiryId = (int)$_GET['delete'];
    $deleteStmt = $pdo->prepare("DELETE FROM support_inquiries WHERE id = ?");
    $deleteStmt->execute([$targetInquiryId]);
    header("Location: support.php?msg=deleted");
    exit;
  }
  catch (PDOException $dbException) {
    error_log("Admin Support Delete Error: " . $dbException->getMessage());
    $operationError = "Unable to remove this inquiry at this time.";
  }
}

// Retrieve All Inquiries in Reverse Chronological Order
try {
  $inquiryListQuery = $pdo->query("SELECT * FROM support_inquiries ORDER BY created_at DESC");
  $inquiryList = $inquiryListQuery->fetchAll();
}
catch (PDOException $dbException) {
  error_log("Admin Support List Error: " . $dbException->getMessage());
  $inquiryList = [];
  $operationError = "Unable to load the inquiry list at this time.";
}

$adminDisplayName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Support Inquiries</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .source-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .source-partner { background: #e0e7ff; color: #4338ca; }
    .source-contact { background: #fef3c7; color: #92400e; }
    .message-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
    .message-cell:hover { white-space: normal; }
    .action-btn {
      padding: 6px 10px; border-radius: 6px; margin-right: 5px; font-size: 14px;
      border: 1px solid #e2e8f0; background: white; cursor: pointer;
      display: inline-flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s;
    }
    .action-btn:hover { background: #f8fafc; color: #4f46e5; border-color: #4f46e5; }
    .action-btn.delete:hover { color: #dc2626; border-color: #dc2626; background: #fff1f2; }
  </style>
</head>
<body>
  <div class="app">
    <!-- Navigation Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo"><span class="logo-dot"></span><span class="logo-text">Go Invoice</span></div>
      <nav class="sidebar-nav">
        <a class="nav-item" href="index.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <a class="nav-item" href="invoices.php"><i class="fa-solid fa-file-invoice"></i><span>Invoices</span></a>
        <a class="nav-item" href="customers.php"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        <a class="nav-item" href="products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Products</span></a>
        <a class="nav-item" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="nav-item active" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Support Inquiries</h1>
        <div class="topbar-right">
          <div class="user-info"><div class="user-name"><?php echo $adminDisplayName; ?></div></div>
          <a href="logout.php"><button class="btn small logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
        </div>
      </header>

      <?php if (!empty($operationError)): ?>
          <div style="background: #fef2f2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
              <?php echo htmlspecialchars($operationError); ?>
          </div>
      <?php
endif; ?>

      <!-- Inquiry Directory Table -->
      <section class="panel">
        <div class="panel-header">
          <h2 class="panel-title"><i class="fa-solid fa-headset" style="color: #4f46e5; margin-right: 10px;"></i> All Inquiries</h2>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr><th>ID</th><th>Source</th><th>Name</th><th>Contact Details</th><th>Profession/City</th><th>Subject/Message</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (!empty($inquiryList)): ?>
                  <?php foreach ($inquiryList as $inquiry): ?>
                      <tr>
                        <td>#<?php echo (int)$inquiry['id']; ?></td>
                        <td>
                          <span class="source-badge <?php echo($inquiry['source'] === 'partner') ? 'source-partner' : 'source-contact'; ?>">
                            <?php echo htmlspecialchars($inquiry['source']); ?>
                          </span>
                        </td>
                        <td><?php echo htmlspecialchars($inquiry['name']); ?></td>
                        <td>
                          <?php echo htmlspecialchars($inquiry['email']); ?><br>
                          <small><?php echo htmlspecialchars($inquiry['phone']); ?></small>
                        </td>
                        <td>
                          <?php if ($inquiry['source'] === 'partner'): ?>
                            <?php echo htmlspecialchars($inquiry['profession']); ?><br>
                            <small><?php echo htmlspecialchars($inquiry['city']); ?></small>
                          <?php
    else: ?>
                            -
                          <?php
    endif; ?>
                        </td>
                        <td class="message-cell">
                          <?php if ($inquiry['source'] === 'contact'): ?>
                            <strong><?php echo htmlspecialchars($inquiry['subject']); ?>:</strong>
                          <?php
    endif; ?>
                          <?php echo htmlspecialchars($inquiry['message']); ?>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($inquiry['created_at'])); ?></td>
                        <td>
                          <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>?subject=Re: <?php echo rawurlencode($inquiry['subject'] ?? 'Support Inquiry'); ?>" title="Reply">
                            <button class="action-btn"><i class="fa-solid fa-envelope"></i></button>
                          </a>
                          <a href="support.php?delete=<?php echo (int)$inquiry['id']; ?>" onclick="return confirm('Are you sure you want to delete this inquiry?')" title="Delete">
                            <button class="action-btn delete"><i class="fa-solid fa-trash-can"></i></button>
                          </a>
                        </td>
                      </tr>
                  <?php
  endforeach; ?>
              <?php
else: ?>
                  <tr><td colspan="8" style="text-align:center; padding: 20px;">No inquiries found.</td></tr>
              <?php
endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
