<?php
/**
 * Purchase Invoice Listing Page
 * Responsibility: Displays all purchase invoices for the authenticated user with search, view, and delete actions.
 */

$page_title = 'Purchase Invoice - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>
<div class="section1">
  <div class="title">Purchase Invoice</div>
  <div class="button-group">
    <input type="text" id="purchaseSearchInput" class="form-control form-control-sm d-inline-block" placeholder="Search..." style="width: 150px; vertical-align: middle;">
    <button class="btn light" id="btnPurchaseSearch">
      <span class="invoice-icon"><i class="fa-solid fa-magnifying-glass"></i></span> Search
    </button>
    <button class="btn green">
      <a href="purchaseform.php"><span class="invoice-icon"><i class="fa-solid fa-plus color: white"></i></span> Add New</a>
    </button>
  </div>
</div>

<div class="invoice-table">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox"></th>
        <th>Invoice No ⯆</th>
        <th>Company Name</th>
        <th>Date</th>
        <th>Payment Type</th>
        <th>Total</th>
        <th>Action </th>
      </tr>
    </thead>
    <tbody id="purchaseBody">
      <tr><td colspan="7">Loading...</td></tr>
    </tbody>
  </table>
</div>

<script>
(function(){
  /**
   * Fetches purchase invoices from the API and renders them into the table
   */
  async function loadPurchaseInvoices(searchTerm = ''){
    var tableBody = document.getElementById('purchaseBody');
    if(!tableBody) return;

    try {
      var apiEndpoint = `../api/invoices.php?type=purchase&limit=50&search=${encodeURIComponent(searchTerm)}`;
      var response = await fetch(apiEndpoint, { credentials: 'include' });
      var rawResponse = await response.text();

      var parsedData = {};
      try { parsedData = JSON.parse(rawResponse); } catch(parseError) { throw new Error('Non-JSON from invoices API'); }

      if(!response.ok || parsedData.success !== true){
        var errorMessage = (parsedData && (parsedData.error || parsedData.message)) || 'Failed to load purchase invoices';
        if(/Authentication required/i.test(errorMessage) || response.status === 401){
          showError('Your session has expired. Redirecting to login...');
          setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
          return;
        }
        throw new Error(errorMessage);
      }

      var invoiceRecords = (parsedData.data && parsedData.data.invoices) ? parsedData.data.invoices : [];

      if(invoiceRecords.length === 0){
        tableBody.innerHTML = '<tr><td colspan="7">No purchase invoices yet</td></tr>';
        return;
      }

      tableBody.innerHTML = invoiceRecords.map(function(invoice){
        var invoiceId = invoice.id;
        var invoiceNumber = invoice.invoice_number || '';
        var vendorName = invoice.customer_name || '';
        var invoiceDate = invoice.invoice_date || '';
        var formattedTotal = (Number(invoice.total_amount || 0)).toFixed(2);
        var printLink = 'print_purchase_restored.php?id=' + encodeURIComponent(invoiceId);

        // Payment Method Badge with Color Coding
        var paymentMethod = (invoice.payment_method || 'Cash').toLowerCase();
        var badgeClass = 'bg-secondary';
        var badgeIcon = 'fa-money-bill';

        if(paymentMethod === 'credit')      { badgeClass = 'bg-danger';  badgeIcon = 'fa-bell'; }
        else if(paymentMethod === 'cash')    { badgeClass = 'bg-success'; badgeIcon = 'fa-wallet'; }
        else if(paymentMethod === 'online')  { badgeClass = 'bg-primary'; badgeIcon = 'fa-globe'; }
        else if(paymentMethod === 'cheque')  { badgeClass = 'bg-warning text-dark'; badgeIcon = 'fa-money-check'; }

        var paymentBadge = '<span class="badge ' + badgeClass + '" style="font-size: 0.9rem; padding: 6px 12px;">' +
                           '<i class="fa-solid ' + badgeIcon + ' me-1"></i> ' + paymentMethod.toUpperCase() + '</span>';

        return '<tr>' +
          '<td><input type="checkbox"></td>' +
          '<td>' + invoiceNumber + '</td>' +
          '<td>' + vendorName + '</td>' +
          '<td>' + invoiceDate + '</td>' +
          '<td>' + paymentBadge + '</td>' +
          '<td>' + formattedTotal + '</td>' +
          '<td class="btn-group">' +
            '<a class="btn view" href="' + printLink + '" target="_blank"><span><i class="fa-regular fa-eye"></i></span> View / Print</a>' +
            '<button class="btn btn-delete btn-danger" data-id="' + invoiceId + '" style="background-color: #dc3545; color: white; margin-left: 5px;">' +
              '<i class="fa-solid fa-trash"></i>' +
            '</button>' +
          '</td>' +
        '</tr>';
      }).join('');

    } catch(loadError) {
      tableBody.innerHTML = '<tr><td colspan="7">Load failed: ' + loadError.message + '</td></tr>';
    }
  }

  /**
   * Permanently deletes a purchase invoice after user confirmation
   */
  window.deletePurchaseInvoice = async function(invoiceId) {
    if (!confirm('Are you sure you want to delete this purchase invoice? This action cannot be undone.')) return;
    try {
      var response = await fetch(`purchaseform.php?action=delete_purchase&id=${invoiceId}`, {
        method: 'POST',
        credentials: 'include'
      });
      var responseData = await response.json();
      if (responseData && responseData.success) {
        showSuccess('Invoice deleted successfully');
        loadPurchaseInvoices();
      } else {
        showError(responseData.error || 'Failed to delete');
      }
    } catch (deleteError) {
      showError('Error deleting invoice: ' + deleteError.message);
    }
  };

  // Delegate click events for dynamically generated delete buttons
  document.getElementById('purchaseBody').onclick = function(event) {
    var deleteButton = event.target.closest('.btn-delete');
    if (deleteButton) {
      var invoiceId = deleteButton.getAttribute('data-id');
      if (invoiceId) deletePurchaseInvoice(invoiceId);
    }
  };

  // Initial load
  loadPurchaseInvoices();

  // Search functionality
  var searchButton = document.getElementById('btnPurchaseSearch');
  var searchField = document.getElementById('purchaseSearchInput');
  if (searchButton && searchField) {
    searchButton.onclick = function() { loadPurchaseInvoices(searchField.value); };
    searchField.onkeyup = function(event) { if (event.key === 'Enter') loadPurchaseInvoices(searchField.value); };
  }
})();
</script>

<?php include '../includes/footer.php'; ?>
