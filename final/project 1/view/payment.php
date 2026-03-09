<?php
/**
 * Payment Listing Page
 * Responsibility: Displays a table of all recorded inward payments for the authenticated user.
 */

$page_title = 'Payment - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>
<div class="section1">
  <div class="title">Payment</div>
  <div class="button-group">
    <button class="btn green"><a href="paymentform.php"><span class="invoice-icon"><i class="fa-solid fa-plus color: white"></i></span> Add New</a></button>
  </div>
</div>

<div class="invoice-table">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox"></th>
        <th>Receipt No ⯆</th>
        <th>Company Name</th>
        <th>Payment Date</th>
        <th>Payment Type</th>
        <th>Amount </th>
      </tr>
    </thead>
    <tbody id="paymentsBody">
      <tr><td colspan="6">Loading...</td></tr>
    </tbody>
  </table>
</div>

<script>
(function(){
  /**
   * Fetches payment records from the API and renders them into the payments table
   */
  async function loadPaymentRecords(){
    var tableBody = document.getElementById('paymentsBody');
    if(!tableBody) return;

    try {
      var apiEndpoint = '../api/payments.php';
      var response = await fetch(apiEndpoint, { credentials: 'include' });
      var rawResponse = await response.text();

      var parsedData = {};
      try { parsedData = JSON.parse(rawResponse); } catch(parseError) { throw new Error('Non-JSON from payments API'); }

      if(!response.ok || parsedData.success !== true){
        var errorMessage = (parsedData && (parsedData.error || parsedData.message)) || 'Failed to load payments';
        if(/Authentication required/i.test(errorMessage) || response.status === 401){
          showError('Your session has expired. Redirecting to login...');
          setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
          return;
        }
        throw new Error(errorMessage);
      }

      var paymentRecords = (parsedData.data && parsedData.data.payments) ? parsedData.data.payments : [];

      if(paymentRecords.length === 0){
        tableBody.innerHTML = '<tr><td colspan="6">No payments yet</td></tr>';
        return;
      }

      tableBody.innerHTML = paymentRecords.map(function(payment){
        var paymentDate = payment.payment_date || '';
        var paymentMethod = payment.payment_method ? (payment.payment_method.charAt(0).toUpperCase() + payment.payment_method.slice(1)) : '';
        var formattedAmount = (Number(payment.amount || 0)).toFixed(2);
        var companyName = payment.customer_name || '';

        return '<tr>' +
          '<td><input type="checkbox"></td>' +
          '<td>' + payment.id + '</td>' +
          '<td>' + companyName + '</td>' +
          '<td>' + paymentDate + '</td>' +
          '<td>' + paymentMethod + '</td>' +
          '<td>' + formattedAmount + '</td>' +
        '</tr>';
      }).join('');

    } catch(loadError) {
      tableBody.innerHTML = '<tr><td colspan="6">Load failed</td></tr>';
    }
  }

  loadPaymentRecords();
})();
</script>

<?php include '../includes/footer.php'; ?>
