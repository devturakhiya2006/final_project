<?php
/**
 * Sales Invoice Listing Page
 * Responsibility: Displays all sales invoices for the authenticated user with search, view, and delete actions.
 */

$page_title = 'Sales Invoice - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>
<div class="section1">
  <div class="title">Sales Invoice</div>
  <div class="button-group">
    <input type="text" id="salesSearchInput" class="form-control form-control-sm d-inline-block" placeholder="Search..." style="width: 150px; vertical-align: middle;">
    <button class="btn light" id="btnSalesSearch">
      <span class="invoice-icon"><i class="fa-solid fa-magnifying-glass"></i></span> Search
    </button>
    <button class="btn green">
      <a href="salesfrom.php"><span class="invoice-icon"><i class="fa-solid fa-plus color: white"></i></span> Add New</a>
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
    <tbody id="salesBody">
      <tr><td colspan="7">Loading...</td></tr>
    </tbody>
  </table>
</div>

<script>
(function(){
  /**
   * Fetches sales invoices from the API and renders them into the table
   */
  async function loadSalesInvoices(searchTerm = ''){
    var tableBody = document.getElementById('salesBody');
    if(!tableBody) return;

    try {
      var apiEndpoint = `../api/invoices.php?type=sales&limit=50&search=${encodeURIComponent(searchTerm)}`;
      var response = await fetch(apiEndpoint, { credentials: 'include' });
      var rawResponse = await response.text();

      var parsedData = {};
      try { parsedData = JSON.parse(rawResponse); } catch(parseError) { throw new Error('Non-JSON from invoices API'); }

      if(!response.ok || parsedData.success !== true){
        var errorMessage = (parsedData && (parsedData.error || parsedData.message)) || 'Failed to load invoices';
        if(/Authentication required/i.test(errorMessage) || response.status === 401){
          showError('Your session has expired. Redirecting to login...');
          setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
          return;
        }
        throw new Error(errorMessage);
      }

      var invoiceRecords = (parsedData.data && parsedData.data.invoices) ? parsedData.data.invoices : [];

      if(invoiceRecords.length === 0){
        tableBody.innerHTML = '<tr><td colspan="7">No sales invoices yet</td></tr>';
        return;
      }

      tableBody.innerHTML = invoiceRecords.map(function(invoice){
        var invoiceId = invoice.id;
        var invoiceNumber = invoice.invoice_number || '';
        var customerName = invoice.customer_name || '';
        var invoiceDate = invoice.invoice_date || '';
        var formattedTotal = (Number(invoice.total_amount || 0)).toFixed(2);
        var printLink = 'print_sales_restored.php?id=' + encodeURIComponent(invoiceId);

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
          '<td>' + customerName + '</td>' +
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
      tableBody.innerHTML = '<tr><td colspan="7">Load failed</td></tr>';
    }
  }

  /**
   * Permanently deletes a sales invoice after user confirmation
   */
  window.deleteSalesInvoice = async function(invoiceId) {
    if (!confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) return;
    try {
      var response = await fetch(`../api/invoices.php?type=sales&id=${invoiceId}`, {
        method: 'DELETE',
        credentials: 'include'
      });
      var responseData = await response.json();
      if (responseData && responseData.success) {
        showSuccess('Invoice deleted successfully');
        loadSalesInvoices();
      } else {
        showError(responseData.error || 'Failed to delete');
      }
    } catch (deleteError) {
      showError('Error deleting invoice: ' + deleteError.message);
    }
  };

  // Delegate click events for dynamically generated delete buttons
  document.getElementById('salesBody').onclick = function(event) {
    var deleteButton = event.target.closest('.btn-delete');
    if (deleteButton) {
      var invoiceId = deleteButton.getAttribute('data-id');
      if (invoiceId) deleteSalesInvoice(invoiceId);
    }
  };

  // Initial load
  loadSalesInvoices();

  // Search functionality
  var searchButton = document.getElementById('btnSalesSearch');
  var searchField = document.getElementById('salesSearchInput');
  if (searchButton && searchField) {
    searchButton.onclick = function() { loadSalesInvoices(searchField.value); };
    searchField.onkeyup = function(event) { if (event.key === 'Enter') loadSalesInvoices(searchField.value); };
  }
})();
</script>

<?php include '../includes/footer.php'; ?>
