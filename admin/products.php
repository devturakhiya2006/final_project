<?php
/**
 * Admin Product Catalog
 * Responsibility: Displays all products across all users with removal capability.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Process Product Deletion Request
if (isset($_GET['delete'])) {
  try {
    $targetProductId = (int)$_GET['delete'];
    $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $deleteStmt->execute([$targetProductId]);
    header("Location: products.php?msg=deleted");
    exit;
  }
  catch (PDOException $dbException) {
    error_log("Admin Product Delete Error: " . $dbException->getMessage());
    $operationError = "Unable to remove this product. It may be referenced in existing invoices.";
  }
}

// Retrieve Full Product Catalog with Owner Details
try {
  $catalogQuery = $pdo->query("
        SELECT product.*, owner.name as owner_name 
        FROM products product 
        JOIN users owner ON product.user_id = owner.id 
        ORDER BY product.created_at DESC
    ");
  $productCatalog = $catalogQuery->fetchAll();
}
catch (PDOException $dbException) {
  error_log("Admin Product List Error: " . $dbException->getMessage());
  $productCatalog = [];
  $operationError = "Unable to load the product catalog at this time.";
}

$adminDisplayName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Products</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="products.css" />
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
        <a class="nav-item" href="customers.php"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        <a class="nav-item active" href="products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Products</span></a>
        <a class="nav-item" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="nav-item" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Products</h1>
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

      <!-- Product Catalog Table -->
      <section class="panel">
        <div class="panel-header">
          <h2 class="panel-title"><i class="fa-solid fa-boxes-stacked" style="color: #4f46e5; margin-right: 10px;"></i> All Products</h2>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Owner (User)</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (!empty($productCatalog)): ?>
                  <?php foreach ($productCatalog as $product): ?>
                      <tr>
                        <td>#<?php echo (int)$product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>₹<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo (int)$product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit'] ?? 'pcs'); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($product['owner_name']); ?></span></td>
                        <td>
                          <button class="btn small outline view-product-btn" data-id="<?php echo (int)$product['id']; ?>"><i class="fa-solid fa-eye"></i> View</button>
                          <a href="products.php?delete=<?php echo (int)$product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')">
                            <button class="btn small logout" style="margin-left: 5px;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                          </a>
                        </td>
                      </tr>
                  <?php
  endforeach; ?>
              <?php
else: ?>
                  <tr><td colspan="6" style="text-align:center; padding: 20px;">No products found.</td></tr>
              <?php
endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Product View/Edit Modal -->
  <div id="productModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Product Details</h2>
        <span class="close-btn">&times;</span>
      </div>
      <form id="productForm">
        <input type="hidden" id="prod_id" name="id">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group full-width">
              <label class="modal-label">Product Name</label>
              <input type="text" id="prod_name" name="name" class="modal-input" required>
            </div>
            <div class="form-group">
              <label class="modal-label">Price (₹)</label>
              <input type="number" id="prod_price" name="price" step="0.01" class="modal-input" required>
            </div>
            <div class="form-group">
              <label class="modal-label">GST Rate (%)</label>
              <input type="number" id="prod_gst" name="gst_rate" step="0.01" class="modal-input">
            </div>
            <div class="form-group">
              <label class="modal-label">Stock Quantity</label>
              <input type="number" id="prod_stock" name="stock_quantity" class="modal-input">
            </div>
            <div class="form-group">
              <label class="modal-label">Unit</label>
              <input type="text" id="prod_unit" name="unit" class="modal-input" placeholder="e.g. pcs, pkt">
            </div>
            <div class="form-group">
              <label class="modal-label">HSN/SAC</label>
              <input type="text" id="prod_hsn" name="hsn_sac" class="modal-input">
            </div>
            <div class="form-group full-width">
              <label class="modal-label">Description</label>
              <textarea id="prod_desc" name="description" class="modal-input" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn outline close-modal">Cancel</button>
          <button type="submit" class="btn">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('productModal');
      const closeBtns = document.querySelectorAll('.close-btn, .close-modal');
      const viewBtns = document.querySelectorAll('.view-product-btn');
      const productForm = document.getElementById('productForm');

      // Open Modal and Fetch Data
      viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const productId = this.getAttribute('data-id');
          
          fetch(`api/get_product_details.php?id=${productId}`)
            .then(response => response.json())
            .then(result => {
              if (result.success) {
                const p = result.data;
                document.getElementById('prod_id').value = p.id;
                document.getElementById('prod_name').value = p.name;
                document.getElementById('prod_price').value = p.price;
                document.getElementById('prod_gst').value = p.gst_rate;
                document.getElementById('prod_stock').value = p.stock_quantity;
                document.getElementById('prod_unit').value = p.unit || 'pcs';
                document.getElementById('prod_hsn').value = p.hsn_sac || '';
                document.getElementById('prod_desc').value = p.description || '';
                
                modal.style.display = 'block';
              } else {
                alert('Error: ' + result.message);
              }
            })
            .catch(error => {
              console.error('Fetch error:', error);
              alert('Failed to load product details.');
            });
        });
      });

      // Close Modal
      closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          modal.style.display = 'none';
        });
      });

      window.addEventListener('click', (event) => {
        if (event.target == modal) {
          modal.style.display = 'none';
        }
      });

      // Handle Form Submission
      productForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('api/update_product_details.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            alert('Product updated successfully!');
            location.reload(); // Refresh to show changes
          } else {
            alert('Update failed: ' + result.message);
          }
        })
        .catch(error => {
          console.error('Submit error:', error);
          alert('An error occurred while saving.');
        });
      });
    });
  </script>
</body>
</html>
