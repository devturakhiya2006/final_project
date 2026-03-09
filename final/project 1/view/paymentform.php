<?php
/**
 * Inward Payment Creation Form
 * Responsibility: Allows users to record a new inward payment by selecting a customer,
 *                 entering payment amount, method, date, and optional reference/notes.
 */

$page_title = 'Create Payment';
$additional_css = ['main.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>

<div class="container-product" style="max-width:980px;margin:20px auto;">
  <div class="top-buttons" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
    <h3 style="margin:0;">Create Inward Payment</h3>
    <div>
      <a class="btn btn-secondary" href="payment.php">Close</a>
      <button id="btnSavePayment" class="btn btn-primary">Save</button>
    </div>
  </div>

  <div class="card" style="padding:16px;margin-top:12px;">
    <div class="form-row" style="max-width:420px;">
      <label for="payment_customer">Customer<span style="color:red">*</span></label>
      <select id="payment_customer"><option value="">Loading...</option></select>
      <input type="hidden" id="payment_customer_id" />
    </div>

    <div class="form-row" style="max-width:420px;">
      <label for="payment_date">Payment Date<span style="color:red">*</span></label>
      <input type="date" id="payment_date">
    </div>

    <div class="form-row" style="max-width:420px;">
      <label for="payment_amount">Amount<span style="color:red">*</span></label>
      <input type="number" id="payment_amount" placeholder="Enter amount" step="0.01" min="0">
    </div>

    <div class="form-row" style="max-width:420px;">
      <label for="payment_method">Payment Method<span style="color:red">*</span></label>
      <select id="payment_method">
        <option value="cash">Cash</option>
        <option value="credit">Credit</option>
        <option value="cheque">Cheque</option>
        <option value="online">Online</option>
      </select>
    </div>

    <div class="form-row" style="max-width:420px;">
      <label for="reference_number">Reference No (optional)</label>
      <input type="text" id="reference_number" placeholder="Cheque/Txn/Ref No">
    </div>

    <div class="form-row" style="max-width:600px;">
      <label for="payment_notes">Payment Note</label>
      <textarea id="payment_notes" rows="3" placeholder="Enter payment note (optional)"></textarea>
    </div>
  </div>
</div>

<script>
(function(){
  /* ═══════════════════════════════════════
   * Date Default & Customer Loading
   * ═══════════════════════════════════════ */

  // Set today's date as the default payment date
  var paymentDateField = document.getElementById('payment_date');
  if (paymentDateField && !paymentDateField.value) {
    var today = new Date();
    var formattedDate = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
    paymentDateField.value = formattedDate;
  }

  /**
   * Loads customer records into the customer dropdown and auto-selects if only one exists
   */
  async function loadCustomerDropdown() {
    try {
      var customerSelect       = document.getElementById('payment_customer');
      var hiddenCustomerIdField = document.getElementById('payment_customer_id');
      if (!customerSelect) return;
      customerSelect.innerHTML = '<option value="">Loading...</option>';

      var apiUrl = '../api/customers.php?type=customer';
      var response = await fetch(apiUrl, { credentials: 'include' });
      var rawResponseText = await response.text();
      var responseData = {};
      try { responseData = JSON.parse(rawResponseText); } catch (parseError) { throw new Error('Non-JSON from customers API'); }

      if (!response.ok || responseData.success !== true) {
        var errorMessage = (responseData && (responseData.error || responseData.message)) || 'Failed to load customers';
        if (/Authentication required/i.test(errorMessage) || response.status === 401) {
          showError('Your session has expired. Redirecting to login...');
          setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
          return;
        }
        throw new Error(errorMessage);
      }

      var customerList = (responseData.data && responseData.data.customers) ? responseData.data.customers : [];
      customerSelect.innerHTML = '<option value="">Select customer</option>' + customerList.map(function(customer) {
        var displayName = (customer.company_name && customer.company_name.trim().length > 0) ? customer.company_name : customer.name;
        return '<option value="' + customer.id + '">' + displayName + ' (ID:' + customer.id + ')</option>';
      }).join('');

      // Sync hidden customer ID field on selection change
      customerSelect.addEventListener('change', function() { if (hiddenCustomerIdField) hiddenCustomerIdField.value = customerSelect.value; });

      // Auto-select if only one customer exists
      if (customerList.length === 1) { customerSelect.value = String(customerList[0].id); if (hiddenCustomerIdField) hiddenCustomerIdField.value = customerSelect.value; }
    } catch (loadError) {
      var customerSelect = document.getElementById('payment_customer');
      if (customerSelect) { customerSelect.innerHTML = '<option value="">Load failed</option>'; }
    }
  }
  loadCustomerDropdown();

  /* ═══════════════════════════════════════
   * Save Payment Handler
   * ═══════════════════════════════════════ */
  var savePaymentButton = document.getElementById('btnSavePayment');
  if (savePaymentButton) {
    savePaymentButton.addEventListener('click', async function() {
      try {
        var customerId    = parseInt((document.getElementById('payment_customer_id') || {}).value || (document.getElementById('payment_customer') || {}).value || '');
        var paymentDate   = (document.getElementById('payment_date') || {}).value;
        var paymentAmount = parseFloat((document.getElementById('payment_amount') || {}).value || '0');
        var paymentMethod = (document.getElementById('payment_method') || {}).value || 'cash';
        var referenceNo   = (document.getElementById('reference_number') || {}).value || '';
        var paymentNotes  = (document.getElementById('payment_notes') || {}).value || '';

        // Validate required fields
        if (!customerId) { showToast('Please select a customer', 'error'); return; }
        if (!paymentDate) { showToast('Please select payment date', 'error'); return; }
        if (!(paymentAmount > 0)) { showToast('Please enter an amount greater than 0', 'error'); return; }

        var paymentPayload = {
          customer_id:      customerId,
          payment_date:     paymentDate,
          amount:           paymentAmount,
          payment_method:   paymentMethod,
          reference_number: referenceNo,
          notes:            paymentNotes
        };

        var fetchResponse = await fetch('../api/payments.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(paymentPayload)
        });

        var rawResponseText = await fetchResponse.text();
        var parsedResponse = {};
        try { parsedResponse = JSON.parse(rawResponseText); } catch (parseError) { throw new Error('Server returned non-JSON: ' + rawResponseText.slice(0, 200)); }

        if (!fetchResponse.ok || parsedResponse.success !== true) {
          var errorMessage = (parsedResponse && (parsedResponse.error || parsedResponse.message)) || 'Failed to save payment';
          if (/Authentication required/i.test(errorMessage) || fetchResponse.status === 401) {
            showError('Your session has expired. Redirecting to login...');
            setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
            return;
          }
          throw new Error(errorMessage);
        }

        showSuccess('Payment saved successfully');
        window.location.href = 'payment.php';
      } catch (saveError) {
        showError(saveError.message || 'Save failed');
      }
    });
  }
})();
</script>

<?php include '../includes/footer.php'; ?>
