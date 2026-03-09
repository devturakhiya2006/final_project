<?php
/**
 * Admin Settings & Configuration
 * Responsibility: Manages administrator profile (login credentials) and company branding settings.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);

$feedbackMessage = '';
$feedbackType = '';

// Current administrator identity
$currentAdminId = $_SESSION['admin_id'] ?? 1;

// Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Update Administrator Login Credentials
  if (isset($_POST['update_profile'])) {
    $updatedName = sanitize($_POST['name']);
    $updatedEmail = sanitize($_POST['email']);
    $newPassword = $_POST['new_password'];

    try {
      if (!empty($newPassword)) {
        $profileStmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
        $profileStmt->execute([$updatedName, $updatedEmail, $newPassword, $currentAdminId]);
      }
      else {
        $profileStmt = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $profileStmt->execute([$updatedName, $updatedEmail, $currentAdminId]);
      }
      $feedbackMessage = "Profile updated successfully!";
      $feedbackType = "success";

      // Refresh session values to reflect the change immediately
      $_SESSION['admin_name'] = $updatedName;
      $_SESSION['admin_email'] = $updatedEmail;

    }
    catch (PDOException $dbException) {
      error_log("Admin Settings Profile Update Error: " . $dbException->getMessage());
      $feedbackMessage = "Unable to update profile. Please try again.";
      $feedbackType = "error";
    }
  }

  // Update Company Branding and Configuration
  if (isset($_POST['update_settings'])) {
    $brandingFields = [
      'company_name' => $_POST['company_name'] ?? '',
      'company_address' => $_POST['company_address'] ?? '',
      'company_city' => $_POST['company_city'] ?? '',
      'company_pincode' => $_POST['company_pincode'] ?? '',
      'tax_id' => $_POST['tax_id'] ?? '',
      'currency' => $_POST['currency'] ?? 'INR',
      'timezone' => $_POST['timezone'] ?? ''
    ];

    try {
      $upsertStmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

      foreach ($brandingFields as $settingKey => $settingValue) {
        $upsertStmt->execute([$currentAdminId, $settingKey, $settingValue]);
      }
      $feedbackMessage = "Company settings saved successfully!";
      $feedbackType = "success";

    }
    catch (PDOException $dbException) {
      error_log("Admin Settings Branding Update Error: " . $dbException->getMessage());
      $feedbackMessage = "Unable to save company settings. Please try again.";
      $feedbackType = "error";
    }
  }
}

// Load Current Admin Profile
try {
  $adminProfileStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $adminProfileStmt->execute([$currentAdminId]);
  $adminProfile = $adminProfileStmt->fetch();
}
catch (PDOException $dbException) {
  error_log("Admin Settings Profile Load Error: " . $dbException->getMessage());
  $adminProfile = [];
}

// Load Current Company Settings
try {
  $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
  $settingsStmt->execute([$currentAdminId]);
  $settingsRows = $settingsStmt->fetchAll();
  $companySettings = [];
  foreach ($settingsRows as $row) {
    $companySettings[$row['setting_key']] = $row['setting_value'];
  }
}
catch (PDOException $dbException) {
  error_log("Admin Settings Config Load Error: " . $dbException->getMessage());
  $companySettings = [];
}

$adminDisplayName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Settings</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="settings.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
      .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
      .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
      .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
        <a class="nav-item" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item active" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Settings</h1>
        <div class="topbar-right">
          <div class="user-info"><div class="user-name"><?php echo $adminDisplayName; ?></div></div>
          <a href="logout.php"><button class="btn small logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
        </div>
      </header>

      <section class="panel">
        <?php if ($feedbackMessage): ?>
            <div class="alert <?php echo $feedbackType; ?>"><?php echo htmlspecialchars($feedbackMessage); ?></div>
        <?php
endif; ?>

        <!-- Settings Tabs -->
        <div class="tabs">
          <button class="tab active" onclick="showSettingsSection('profile')">Company Profile</button>
          <button class="tab" onclick="showSettingsSection('account')">Account Login</button>
        </div>

        <!-- Company Profile Form -->
        <form method="POST" id="section-profile">
            <input type="hidden" name="update_settings" value="1">
            <div class="section-title">General Information</div>
            <div class="section-subtitle">Basic details about your business used for invoices.</div>

            <div class="form-grid-two">
              <div class="form-group">
                <label class="form-label">Business Name</label>
                <input class="input" name="company_name" value="<?php echo htmlspecialchars($companySettings['company_name'] ?? $adminProfile['name'] ?? ''); ?>" />
              </div>
              <div class="form-group">
                <label class="form-label">Tax ID / GST Number</label>
                <input class="input" name="tax_id" value="<?php echo htmlspecialchars($companySettings['tax_id'] ?? $adminProfile['gst_number'] ?? ''); ?>" />
              </div>
            </div>

            <div class="form-grid-two">
              <div class="form-group">
                <label class="form-label">Address</label>
                <input class="input" name="company_address" value="<?php echo htmlspecialchars($companySettings['company_address'] ?? $adminProfile['address'] ?? ''); ?>" />
              </div>
              <div class="form-group">
                <label class="form-label">City</label>
                <input class="input" name="company_city" value="<?php echo htmlspecialchars($companySettings['company_city'] ?? $adminProfile['city'] ?? ''); ?>" />
              </div>
            </div>

            <div class="form-grid-two">
               <div class="form-group">
                <label class="form-label">Primary Currency</label>
                <select class="select" name="currency">
                  <option value="USD">USD - US Dollar ($)</option>
                  <option value="INR" selected>INR - Indian Rupee (₹)</option>
                </select>
              </div>
            </div>

            <div class="settings-footer">
              <button type="submit" class="btn primary">Save Company Settings</button>
            </div>
        </form>
        
        <!-- Account Login Form -->
        <form method="POST" id="section-account" style="display:none; margin-top: 20px;">
            <input type="hidden" name="update_profile" value="1">
            <div class="section-title">Login Details</div>
            <div class="section-subtitle">Update your admin login credentials.</div>
            
            <div class="form-grid-two">
              <div class="form-group">
                <label class="form-label">Your Name</label>
                <input class="input" name="name" value="<?php echo htmlspecialchars($adminProfile['name'] ?? ''); ?>" required />
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="input" name="email" value="<?php echo htmlspecialchars($adminProfile['email'] ?? ''); ?>" required />
              </div>
            </div>
            
            <div class="form-grid-two">
              <div class="form-group">
                <label class="form-label">New Password (leave blank to keep current)</label>
                <input class="input" type="password" name="new_password" placeholder="******" />
              </div>
            </div>

            <div class="settings-footer">
              <button type="submit" class="btn primary">Update Account</button>
            </div>
        </form>
      </section>
    </main>
  </div>

  <script>
      /**
       * Toggles between the Company Profile and Account Login settings sections
       */
      function showSettingsSection(sectionName) {
          document.getElementById('section-profile').style.display = sectionName === 'profile' ? 'block' : 'none';
          document.getElementById('section-account').style.display = sectionName === 'account' ? 'block' : 'none';
          
          const allTabs = document.querySelectorAll('.tab');
          allTabs.forEach(tab => tab.classList.remove('active'));
          event.target.classList.add('active');
      }
  </script>
</body>
</html>
