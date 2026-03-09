<?php
/**
 * Pending Credit Payments Page
 * Responsibility: Displays all outstanding credit invoices and provides an email reminder modal to notify customers.
 */

$page_title = 'Pending Payments - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>
<div class="section1">
  <div class="title">Pending Credit Payments</div>
  <div class="button-group">
    <a href="dashbord.php" class="btn btn-secondary me-2">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
    <input type="text" id="pendingSearchInput" class="form-control form-control-sm d-inline-block" placeholder="Search..." style="width: 150px; vertical-align: middle;">
    <button class="btn light" id="btnPendingSearch">
      <span class="invoice-icon"><i class="fa-solid fa-magnifying-glass"></i></span> Search
    </button>
  </div>
</div>

<div class="invoice-table">
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Invoice No</th>
        <th>Customer Name</th>
        <th>Date</th>
        <th>Total Amount</th>
        <th>Paid Amount</th>
        <th>Balance</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="pendingBody">
      <tr><td colspan="7" class="text-center">Loading pending payments...</td></tr>
    </tbody>
  </table>
</div>

<!-- Email Reminder Modal -->
<div class="modal fade" id="emailReminderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-envelope"></i> Send Payment Reminder</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="reminderForm">
          <div class="mb-3">
            <label class="form-label">To (Customer Email)</label>
            <input type="email" class="form-control" id="reminderTo" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" class="form-control" id="reminderSubject" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea class="form-control" id="reminderMessage" rows="8" required></textarea>
          </div>
          <div class="d-grid">
            <a id="btnRealSend" href="#" class="btn btn-primary">Send Reminder</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  /**
   * Fetches pending (credit) invoices and renders them into the table
   */
  async function loadPendingPayments(searchTerm = ''){
    var tableBody = document.getElementById('pendingBody');
    if(!tableBody) return;

    try {
      var apiEndpoint = `../api/invoices.php?type=sales&status=pending&limit=100&search=${encodeURIComponent(searchTerm)}`;
      var response = await fetch(apiEndpoint, { credentials: 'include' });
      var parsedData = await response.json();

      if(!response.ok || parsedData.success !== true){
        throw new Error(parsedData.error || 'Failed to load pending payments');
      }

      var pendingInvoices = (parsedData.data && parsedData.data.invoices) ? parsedData.data.invoices : [];

      if(pendingInvoices.length === 0){
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center">No pending credit payments found.</td></tr>';
        return;
      }

      tableBody.innerHTML = pendingInvoices.map(function(invoice){
        var invoiceNumber = invoice.invoice_number || '';
        var customerName = invoice.customer_name || '';
        var customerEmail = invoice.customer_email || '';
        var invoiceDate = invoice.invoice_date || '';
        var totalAmount = Number(invoice.total_amount || 0);
        var paidAmount = Number(invoice.paid_amount || 0);
        var outstandingBalance = (totalAmount - paidAmount).toFixed(2);
        var viewLink = 'print_sales_restored.php?id=' + encodeURIComponent(invoice.id);

        return '<tr>' +
          '<td>' + invoiceNumber + '</td>' +
          '<td>' + customerName + '</td>' +
          '<td>' + invoiceDate + '</td>' +
          '<td>₹' + totalAmount.toFixed(2) + '</td>' +
          '<td>₹' + paidAmount.toFixed(2) + '</td>' +
          '<td class="text-danger fw-bold">₹' + outstandingBalance + '</td>' +
          '<td>' +
            '<div class="btn-group">' +
              '<a class="btn btn-sm btn-outline-primary" href="' + viewLink + '" target="_blank" title="View"><i class="fa-regular fa-eye"></i></a>' +
              '<button class="btn btn-sm btn-outline-info ms-1 btn-reminder" ' +
                'data-inv="' + invoiceNumber + '" ' +
                'data-name="' + customerName + '" ' +
                'data-email="' + customerEmail + '" ' +
                'data-balance="' + outstandingBalance + '" ' +
                'title="Send Reminder"><i class="fa-solid fa-envelope"></i></button>' +
            '</div>' +
          '</td>' +
        '</tr>';
      }).join('');

    } catch(loadError){
      tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error: ' + loadError.message + '</td></tr>';
    }
  }

  // Initial data load
  loadPendingPayments();

  // Search functionality
  var searchButton = document.getElementById('btnPendingSearch');
  var searchField = document.getElementById('pendingSearchInput');
  if (searchButton && searchField) {
    searchButton.onclick = function() { loadPendingPayments(searchField.value); };
    searchField.onkeyup = function(event) { if (event.key === 'Enter') loadPendingPayments(searchField.value); };
  }

  // Email Reminder Modal Logic
  var recipientField = document.getElementById('reminderTo');
  var subjectField = document.getElementById('reminderSubject');
  var messageField = document.getElementById('reminderMessage');
  var sendButton = document.getElementById('btnRealSend');
  var reminderModalInstance = null;

  /**
   * Updates the mailto: link on the send button whenever form fields change
   */
  function refreshMailtoLink() {
      var recipientEmail = recipientField ? recipientField.value : '';
      var emailSubject = subjectField ? subjectField.value : '';
      var emailBody = messageField ? messageField.value : '';
      if (sendButton) {
          sendButton.href = `mailto:${recipientEmail}?subject=${encodeURIComponent(emailSubject)}&body=${encodeURIComponent(emailBody)}`;
      }
  }

  [recipientField, subjectField, messageField].forEach(function(field) {
      if (field) field.addEventListener('input', refreshMailtoLink);
  });

  // Log the reminder attempt to the server
  if (sendButton) {
    sendButton.addEventListener('click', function() {
      fetch('../api/send_reminder.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ to: recipientField.value, subject: subjectField.value, message: messageField.value }),
          credentials: 'include'
      }).catch(function(logError) { console.error('Logging failed:', logError); });

      setTimeout(function() {
          if (reminderModalInstance) reminderModalInstance.hide();
      }, 1000);
    });
  }

  /**
   * Opens the email reminder modal pre-filled with customer and invoice details
   */
  window.openEmailReminderModal = function(reminderData) {
      var customerEmail = reminderData.email || '';
      var invoiceNumber = reminderData.inv || '';
      var customerName = reminderData.name || '';
      var balanceDue = reminderData.balance || '';

      if(!customerEmail) {
          alert('Customer has no email address!');
          return;
      }

      recipientField.value = customerEmail;
      subjectField.value = 'Payment Pending - Invoice #' + invoiceNumber;

      var reminderMessage = `Hello ${customerName},\n\nThis is a friendly reminder that your payment for invoice #${invoiceNumber} is still pending. \n\nOutstanding Balance: ₹${balanceDue}\n\nPlease settle the payment at your earliest convenience.\n\nThank you,\n<?php echo htmlspecialchars($user_name); ?>`;
      messageField.value = reminderMessage;

      refreshMailtoLink();

      if (!reminderModalInstance) {
          reminderModalInstance = new bootstrap.Modal(document.getElementById('emailReminderModal'));
      }
      reminderModalInstance.show();
  };

  // Delegate click events for dynamically generated reminder buttons
  document.addEventListener('click', function(event) {
      var reminderButton = event.target.closest('.btn-reminder');
      if (reminderButton) {
          window.openEmailReminderModal(reminderButton.dataset);
      }
  });

})();
</script>

<style>
.section1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #fff;
    margin-bottom: 20px;
}
.title {
    font-size: 24px;
    font-weight: 600;
    color: #333;
}
.invoice-table {
    padding: 0 20px;
}
.btn-secondary {
    background-color: #6c757d;
    color: white;
    text-decoration: none;
    padding: 5px 15px;
    border-radius: 4px;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-secondary:hover {
    background-color: #5a6268;
    color: white;
}
.btn-group {
    display: flex;
    gap: 5px;
}
</style>

<?php include '../includes/footer.php'; ?>
