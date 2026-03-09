<?php
/**
 * Admin Invoice Management
 * Responsibility: Lists all sales invoices across all users, with view/edit and delete capabilities.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Process Invoice Deletion Request
if (isset($_GET['delete'])) {
  try {
    $targetInvoiceId = (int)$_GET['delete'];

    // Remove associated line items first to maintain referential integrity
    $deleteItemsStmt = $pdo->prepare("DELETE FROM sales_invoice_items WHERE sales_invoice_id = ?");
    $deleteItemsStmt->execute([$targetInvoiceId]);

    $deleteInvoiceStmt = $pdo->prepare("DELETE FROM sales_invoices WHERE id = ?");
    $deleteInvoiceStmt->execute([$targetInvoiceId]);

    header("Location: invoices.php?msg=deleted");
    exit;
  }
  catch (PDOException $dbException) {
    error_log("Admin Invoice Delete Error: " . $dbException->getMessage());
    $operationError = "Unable to remove this invoice. It may have linked payment records.";
  }
}

// Retrieve Complete Invoice Registry with Customer and Owner Details
try {
  $invoiceRegistryQuery = $pdo->query("
        SELECT invoice.*, 
               contact.name as customer_name, 
               owner.name as owner_name 
        FROM sales_invoices invoice 
        JOIN customers contact ON invoice.customer_id = contact.id 
        JOIN users owner ON invoice.user_id = owner.id 
        ORDER BY invoice.created_at DESC
    ");
  $invoiceRegistry = $invoiceRegistryQuery->fetchAll();
}
catch (PDOException $dbException) {
  error_log("Admin Invoice List Error: " . $dbException->getMessage());
  $invoiceRegistry = [];
  $operationError = "Unable to load the invoice registry at this time.";
}

$adminDisplayName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Invoices</title>
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="invoices.css" />
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
        <a class="nav-item active" href="invoices.php"><i class="fa-solid fa-file-invoice"></i><span>Invoices</span></a>
        <a class="nav-item" href="customers.php"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        <a class="nav-item" href="products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Products</span></a>
        <a class="nav-item" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="nav-item" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main">
      <header class="topbar">
        <h1 class="page-title">Invoices</h1>
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

      <!-- Invoice Registry Table -->
      <section class="panel">
        <div class="panel-header">
          <h2 class="panel-title"><i class="fa-solid fa-file-invoice" style="color: #4f46e5; margin-right: 10px;"></i> All Invoices</h2>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Invoice#</th><th>Date</th><th>Customer</th><th>Amount</th><th>Created By (User)</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($invoiceRegistry)): ?>
                  <?php foreach ($invoiceRegistry as $invoice): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                        <td>₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($invoice['owner_name']); ?></span></td>
                        <td>
                            <span class="status <?php echo strtolower($invoice['payment_status']); ?>">
                                <?php echo ucfirst($invoice['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                          <button class="btn small outline btn-view-invoice" data-id="<?php echo (int)$invoice['id']; ?>"><i class="fa-solid fa-eye"></i> View</button>
                          <a href="invoices.php?delete=<?php echo (int)$invoice['id']; ?>" onclick="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.')">
                            <button class="btn small logout" style="margin-left: 5px;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                          </a>
                        </td>
                      </tr>
                  <?php
  endforeach; ?>
              <?php
else: ?>
                  <tr><td colspan="7" style="text-align:center; padding: 20px;">No invoices found.</td></tr>
              <?php
endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Invoice Detail & Edit Modal -->
  <div id="invoiceModal" class="modal-overlay">
    <div class="modal-content-wrapper">
      <div class="modal-header">
        <h3 id="modalTitle">Edit Invoice Details</h3>
        <button class="close-modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="editInvoiceForm">
          <input type="hidden" id="edit_invoice_id">
          <div class="form-grid">
            <div class="form-group">
              <label>Invoice Number</label>
              <input type="text" id="edit_invoice_number" readonly class="readonly-input">
            </div>
            <div class="form-group">
              <label>Customer</label>
              <input type="text" id="edit_customer_name" readonly class="readonly-input">
            </div>
            <div class="form-group">
              <label>Date</label>
              <input type="date" id="edit_invoice_date" required>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select id="edit_payment_status" required>
                <option value="pending">Pending</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
              </select>
            </div>
            <div class="form-group">
              <label>Payment Method</label>
              <select id="edit_payment_method" required>
                <option value="cash">Cash</option>
                <option value="online">Online</option>
                <option value="cheque">Cheque</option>
                <option value="credit">Credit</option>
              </select>
            </div>
            <div class="form-group">
              <label>Paid Amount (₹)</label>
              <input type="number" id="edit_paid_amount" step="0.01" min="0">
            </div>
          </div>

          <!-- Line Items Section -->
          <div class="items-section">
            <h4>Invoice Items</h4>
            <div class="items-table-wrapper">
              <table border="0" id="itemsTable">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th width="100">Qty</th>
                    <th width="150">Rate (₹)</th>
                    <th width="100">GST %</th>
                    <th width="150">Total (₹)</th>
                    <th width="50"></th>
                  </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
              </table>
            </div>
            <button type="button" class="btn small outline" id="addRowBtn" style="margin-top: 10px;">
              <i class="fa-solid fa-plus"></i> Add Item
            </button>
          </div>

          <!-- Financial Summary -->
          <div class="summary-section">
            <div class="summary-row"><label>Subtotal:</label><span id="display_subtotal">₹0.00</span></div>
            <div class="summary-row"><label>Total GST:</label><span id="display_gst">₹0.00</span></div>
            <div class="summary-row grand-total"><label>Grand Total:</label><span id="display_grand">₹0.00</span></div>
          </div>

          <div class="form-group" style="margin-top: 20px;">
            <label>Notes</label>
            <textarea id="edit_notes" rows="3" placeholder="Additional info..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn outline close-modal">Cancel</button>
        <button type="button" class="btn primary" id="saveInvoiceBtn">Update Changes</button>
      </div>
    </div>
  </div>

  <style>
    /* Modal Styles */
    .modal-overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
      display: none; justify-content: center; align-items: center; z-index: 9999;
    }
    #invoiceModal .modal-content-wrapper {
      background: white; width: 90%; max-width: 900px; max-height: 90vh;
      border-radius: 12px; display: flex; flex-direction: column; overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    #invoiceModal .modal-header {
      padding: 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
      display: flex; justify-content: space-between; align-items: center;
    }
    #invoiceModal .modal-header h3 { margin: 0; font-size: 1.25rem; color: #1e293b; font-weight: 600; }
    #invoiceModal .close-modal { 
      background: none; border: none; font-size: 1.5rem; color: #64748b; 
      cursor: pointer; padding: 0.5rem; transition: color 0.2s;
    }
    #invoiceModal .close-modal:hover { color: #0f172a; }
    #invoiceModal .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
    #invoiceModal .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
    #invoiceModal .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #475569; margin-bottom: 0.5rem; }
    #invoiceModal .form-group input, #invoiceModal .form-group select, #invoiceModal .form-group textarea {
      width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 6px;
      font-size: 0.875rem; transition: border-color 0.2s;
    }
    #invoiceModal .form-group input:focus { border-color: #4f46e5; outline: none; }
    #invoiceModal .readonly-input { background: #f1f5f9; cursor: not-allowed; }
    #invoiceModal .items-section h4 { color: #1e293b; margin-bottom: 1rem; font-weight: 600; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; }
    #invoiceModal .items-table-wrapper { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    #invoiceModal #itemsTable { width: 100%; border-collapse: collapse; }
    #invoiceModal #itemsTable th { background: #f8fafc; text-align: left; padding: 0.75rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
    #invoiceModal #itemsTable td { padding: 0.5rem; border-top: 1px solid #f1f5f9; }
    #invoiceModal .remove-row { color: #ef4444; border: none; background: none; cursor: pointer; padding: 0.5rem; }
    #invoiceModal .summary-section { margin-top: 2rem; display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; }
    #invoiceModal .summary-row { font-size: 0.875rem; color: #64748b; }
    #invoiceModal .summary-row label { width: 100px; display: inline-block; }
    #invoiceModal .grand-total { border-top: 2px solid #e2e8f0; padding-top: 0.5rem; font-size: 1.125rem; font-weight: 700; color: #1e293b; }
    #invoiceModal .modal-footer {
      padding: 1.25rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0;
      display: flex; justify-content: flex-end; gap: 1rem;
    }
    #invoiceModal .btn { cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
    #invoiceModal .btn.primary { background: #4f46e5; color: white; border: none; padding: 0.625rem 1.5rem; border-radius: 6px; font-weight: 500; }
    #invoiceModal .btn.primary:hover { background: #4338ca; }
    #invoiceModal .btn.outline { background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.625rem 1.5rem; border-radius: 6px; }
    #invoiceModal .btn.outline:hover { background: #f8fafc; border-color: #94a3b8; }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const invoiceModal = document.getElementById('invoiceModal');
      const lineItemsContainer = document.getElementById('itemsBody');

      // Open the detail modal for a selected invoice
      document.querySelectorAll('.btn-view-invoice').forEach(viewButton => {
        viewButton.addEventListener('click', async () => {
          try {
            const invoiceId = viewButton.dataset.id;
            const response = await fetch(`../api/admin_invoices.php?id=${invoiceId}`);
            const result = await response.json();
            
            if (result.success) {
              populateInvoiceModal(result.data);
              invoiceModal.style.display = 'flex';
            } else {
              alert('Unable to load invoice details: ' + (result.error || 'Unknown error'));
            }
          } catch (fetchError) {
            alert('A connection error occurred while loading invoice details.');
          }
        });
      });

      // Close modal handlers
      document.querySelectorAll('.close-modal').forEach(closeButton => {
        closeButton.addEventListener('click', () => {
          invoiceModal.style.display = 'none';
          lineItemsContainer.innerHTML = '';
        });
      });

      /**
       * Populates the invoice edit modal with server data
       */
      function populateInvoiceModal(invoiceData) {
        document.getElementById('edit_invoice_id').value = invoiceData.id;
        document.getElementById('edit_invoice_number').value = invoiceData.invoice_number;
        document.getElementById('edit_customer_name').value = invoiceData.customer_name;
        document.getElementById('edit_invoice_date').value = invoiceData.invoice_date;
        document.getElementById('edit_payment_status').value = invoiceData.payment_status;
        document.getElementById('edit_payment_method').value = invoiceData.payment_method;
        document.getElementById('edit_paid_amount').value = invoiceData.paid_amount;
        document.getElementById('edit_notes').value = invoiceData.notes || '';
        
        invoiceData.items.forEach(lineItem => addLineItemRow(lineItem));
        recalculateFinancialTotals();
      }

      /**
       * Adds a single line item row to the invoice items table
       */
      function addLineItemRow(existingItem = null) {
        const tableRow = document.createElement('tr');
        tableRow.className = 'item-row';
        tableRow.innerHTML = `
          <td><input type="text" class="item_name" value="${existingItem ? existingItem.product_name : ''}"> <input type="hidden" class="product_id" value="${existingItem ? existingItem.product_id : '0'}"></td>
          <td><input type="number" class="item_qty" value="${existingItem ? existingItem.quantity : 1}" min="1"></td>
          <td><input type="number" class="item_rate" value="${existingItem ? existingItem.unit_price : 0}" min="0" step="0.01"></td>
          <td><input type="number" class="item_gst" value="${existingItem ? existingItem.gst_rate : 18}" min="0" step="0.01"></td>
          <td class="item_total">₹${existingItem ? existingItem.total_amount : '0.00'}</td>
          <td><button type="button" class="remove-row"><i class="fa-solid fa-trash-can"></i></button></td>
        `;
        lineItemsContainer.appendChild(tableRow);
        
        tableRow.querySelector('.remove-row').onclick = () => { tableRow.remove(); recalculateFinancialTotals(); };
        tableRow.querySelectorAll('input').forEach(input => input.addEventListener('input', recalculateFinancialTotals));
      }

      document.getElementById('addRowBtn').onclick = () => addLineItemRow();

      /**
       * Recalculates subtotal, GST, and grand total from all visible line items
       */
      function recalculateFinancialTotals() {
        let runningSubtotal = 0;
        let runningGstTotal = 0;
        
        document.querySelectorAll('.item-row').forEach(row => {
          const itemQuantity = parseFloat(row.querySelector('.item_qty').value) || 0;
          const itemRate     = parseFloat(row.querySelector('.item_rate').value) || 0;
          const itemGstRate  = parseFloat(row.querySelector('.item_gst').value) || 0;
          
          const taxableAmount = itemQuantity * itemRate;
          const gstAmount     = taxableAmount * (itemGstRate / 100);
          const lineTotal     = taxableAmount + gstAmount;
          
          runningSubtotal += taxableAmount;
          runningGstTotal += gstAmount;
          
          row.querySelector('.item_total').innerText = '₹' + lineTotal.toFixed(2);
          row.dataset.gst_amount = gstAmount;
          row.dataset.total_with_gst = lineTotal;
        });
        
        const grandTotal = runningSubtotal + runningGstTotal;
        document.getElementById('display_subtotal').innerText = '₹' + runningSubtotal.toFixed(2);
        document.getElementById('display_gst').innerText = '₹' + runningGstTotal.toFixed(2);
        document.getElementById('display_grand').innerText = '₹' + grandTotal.toFixed(2);
      }

      /**
       * Persists invoice changes back to the server
       */
      document.getElementById('saveInvoiceBtn').onclick = async () => {
        try {
          const invoiceId = document.getElementById('edit_invoice_id').value;
          const collectedItems = [];
          
          document.querySelectorAll('.item-row').forEach(row => {
            collectedItems.push({
              product_id: parseInt(row.querySelector('.product_id').value),
              quantity: row.querySelector('.item_qty').value,
              unit_price: row.querySelector('.item_rate').value,
              gst_rate: row.querySelector('.item_gst').value,
              gst_amount: row.dataset.gst_amount,
              total_amount: row.dataset.total_with_gst
            });
          });

          const updatePayload = {
            invoice_date: document.getElementById('edit_invoice_date').value,
            payment_status: document.getElementById('edit_payment_status').value,
            payment_method: document.getElementById('edit_payment_method').value,
            paid_amount: document.getElementById('edit_paid_amount').value,
            notes: document.getElementById('edit_notes').value,
            subtotal: parseFloat(document.getElementById('display_subtotal').innerText.replace('₹','')),
            gst_amount: parseFloat(document.getElementById('display_gst').innerText.replace('₹','')),
            total_amount: parseFloat(document.getElementById('display_grand').innerText.replace('₹','')),
            items: collectedItems
          };

          const response = await fetch(`../api/admin_invoices.php?id=${invoiceId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updatePayload)
          });
          
          const result = await response.json();
          if (result.success) {
            alert('Invoice updated successfully!');
            window.location.reload();
          } else {
            alert('Update failed: ' + (result.error || 'Unknown error'));
          }
        } catch (saveError) {
          alert('A connection error occurred while saving the invoice.');
        }
      };
    });
  </script>
</body>
</html>
