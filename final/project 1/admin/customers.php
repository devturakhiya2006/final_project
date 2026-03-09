<?php
/**
 * Admin Customer Management
 * Responsibility: Displays registered users and provides administrative actions (view, delete).
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Process Customer Deletion Request
if (isset($_GET['delete'])) {
  try {
    $targetUserId = (int)$_GET['delete'];
    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$targetUserId]);
    header("Location: customers.php?msg=deleted");
    exit;
  }
  catch (PDOException $dbException) {
    error_log("Admin Customer Delete Error: " . $dbException->getMessage());
    $operationError = "Unable to remove this customer. They may have linked records.";
  }
}

// Retrieve Full User Directory
try {
  $directoryQuery = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
  $userDirectory = $directoryQuery->fetchAll();
}
catch (PDOException $dbException) {
  error_log("Admin Customer List Error: " . $dbException->getMessage());
  $userDirectory = [];
  $operationError = "Unable to load the customer directory at this time.";
}

$adminDisplayName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Customers</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="customers.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="app">
    <!-- Navigation Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <span class="logo-dot"></span>
        <span class="logo-text">Go Invoice</span>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-item" href="index.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <a class="nav-item" href="invoices.php"><i class="fa-solid fa-file-invoice"></i><span>Invoices</span></a>
        <a class="nav-item active" href="customers.php"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        <a class="nav-item" href="products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Products</span></a>
        <a class="nav-item" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="nav-item" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Customers</h1>
        <div class="topbar-right">
          <div class="user-info">
             <div class="user-name"><?php echo $adminDisplayName; ?></div>
          </div>
          <a href="logout.php"><button class="btn small logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
        </div>
      </header>

      <?php if (!empty($operationError)): ?>
          <div style="background: #fef2f2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
              <?php echo htmlspecialchars($operationError); ?>
          </div>
      <?php
endif; ?>

      <!-- User Directory Table -->
      <section class="panel">
        <div class="panel-header">
          <h2 class="panel-title"><i class="fa-solid fa-users" style="color: #4f46e5; margin-right: 10px;"></i> All Customers</h2>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Mobile</th><th>Email</th><th>Business</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($userDirectory)): ?>
                  <?php foreach ($userDirectory as $user): ?>
                      <tr>
                        <td>#<?php echo (int)$user['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($user['name']); ?>
                            <?php if (!empty($user['business_name'])): ?>
                                <br><small><?php echo htmlspecialchars($user['business_name']); ?></small>
                            <?php
    endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge"><?php echo !empty($user['business_name']) ? htmlspecialchars($user['business_name']) : 'N/A'; ?></span></td>
                        <td>
                            <span class="status <?php echo($user['status'] === 'active') ? 'completed' : 'pending'; ?>">
                                <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                            </span>
                        </td>
                        <td>
                          <a href="user_details.php?id=<?php echo (int)$user['id']; ?>">
                            <button class="btn small outline"><i class="fa-solid fa-id-card"></i> View</button>
                          </a>
                          <a href="customers.php?delete=<?php echo (int)$user['id']; ?>" onclick="return confirm('Are you sure you want to remove this user? This action cannot be undone.')">
                            <button class="btn small logout" style="margin-left: 5px;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                          </a>
                        </td>
                      </tr>
                  <?php
  endforeach; ?>
              <?php
else: ?>
                  <tr><td colspan="7" style="text-align:center; padding: 20px;">No customers found.</td></tr>
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
