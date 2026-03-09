<?php
/**
 * Sales Invoice Creation Form
 * Responsibility: Provides a comprehensive form for creating sales invoices including
 *                 customer selection, invoice details, product line items with GST calculations,
 *                 payment method, and notes.
 */

$page_title = 'Create Sales Invoice';
$additional_css = ['main.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>

<!-- Layout Wrapper -->
<div class="main-form-container" style="max-width: 95%; margin: 20px auto; padding: 0 20px;">
  
  <!-- Header -->
  <div id="header">
    <h3>✏️ Create Sale Invoice</h3>
    <button id="create-invoice-btn">+ Create Another Invoice</button>
  </div>

  <div class="layout">
    <!-- Customer Information -->
    <div class="card">
      <div class="card-header">
        <h3>Customer Information</h3>
        <button type="button" onclick="window.location.href='customer.php'">+ Add Customer</button>
      </div>
      <!-- ... existing customer fields ... -->
      <div class="form-row">
        <label for="ms">M/S.<span style="color:red">*</span></label>
        <select id="ms" class="custom-select">
          <option value="">Select Customer...</option>
        </select>
      </div>

      <div class="form-row">
        <label for="address">Address</label>
        <textarea id="address" placeholder="Address"></textarea>
      </div>

      <div class="form-row">
        <label for="contact">Contact Person</label>
        <input type="text" id="contact" placeholder="Contact Person">
      </div>

      <div class="form-row">
        <label for="phone">Phone No</label>
        <input type="text" id="phone" placeholder="Phone No">
      </div>

      <div class="form-row">
        <label for="gstin">GSTIN / PAN</label>
        <input type="text" id="gstin" placeholder="GSTIN / PAN">
      </div>

      <div class="form-row">
        <label for="rev">Rev. Charge</label>
        <select id="rev" class="custom-select">
          <option>No</option>
          <option>Yes</option>
        </select>
      </div>

      <div class="form-check">
        <input type="checkbox" id="shipping" checked>
        <label for="shipping">Use Same Shipping Address</label>
      </div>

      <div class="form-row">
        <label for="supply">Place of Supply<span style="color:red">*</span></label>
        <input type="text" id="supply">
      </div>
    </div>

    <!-- Invoice Detail -->
    <div class="info-card" id="invoice-detail-card">
      <div class="info-card-header">
        <h3>Invoice Detail</h3>
      </div>
      <form class="info-grid">
        <label>Invoice Type</label>
        <select class="custom-select"><option>Regular</option></select>

        <label>Invoice Prefix</label>
        <input type="text" placeholder="Inv Pre." />

        <label>Invoice Number</label>
        <input type="text" placeholder="1" />

        <label>Invoice Postfix</label>
        <input type="text" placeholder="Inv Post." />

        <label>Date<span style="color:red">*</span></label>
        <input type="text" placeholder="Date*" value="03-Aug-2025" />

        <label>Challan No.</label>
        <input type="text" placeholder="Challan No." />

        <label>Challan Date</label>
        <input type="text" placeholder="dd/mm/yy" />

        <label>P.O. No.</label>
        <input type="text" placeholder="P.O. No." />

        <label>P.O. Date</label>
        <input type="text" placeholder="dd/mm/yy" />

        <label>L.R. No.</label>
        <input type="text" placeholder="L.R. No." />

        <label>E-Way No.</label>
        <input type="text" placeholder="E-Way No." />

        <label>Delivery Mode</label>
        <select class="custom-select"><option>Select Delivery Mode</option></select>
      </form>
    </div>
  </div>

  <div class="top-buttons" style="margin-top: 30px;">
    <h3>Products Items</h3>
    <button id="btnAddItem">+ Add Product</button>
    <button>+ Add Additional Charges</button>
  </div>
  
  <div class="table-responsive">
    <table id="itemsTable" class="table">
      <thead>
        <tr>
          <th>SR.</th>
          <th>PRODUCT / OTHER CHARGES</th>
          <th>HSN/SAC CODE</th>
          <th>QTY. / STOCK</th>
          <th>UOM</th>
          <th>PRICE (RS)</th>
          <th>DISCOUNT</th>
          <th>IGST</th>
          <th>CGST</th>
          <th>TOTAL</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>
            <select class="product-select custom-select" style="min-width: 200px;"><option value="">Select product...</option></select><br>
            <input type="number" class="product-id" placeholder="Product ID (DB)" style="margin-top:5px; border: 1px solid #ddd; border-radius: 4px; padding: 4px;">
            <textarea placeholder="Item Note..." style="margin-top:5px; border: 1px solid #ddd; border-radius: 4px; padding: 4px;"></textarea>
          </td>
          <td><input class="hsn-input" placeholder="HSN/SAC" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input type="number" class="qty-input" placeholder="Qty." style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td>
            <select class="uom-select custom-select" style="min-width: 80px;">
              <option value="pcs">PCS</option>
              <option value="nos">NOS</option>
              <option value="kg">KG</option>
              <option value="gm">GM</option>
              <option value="mtr">MTR</option>
              <option value="box">BOX</option>
              <option value="pkt">PKT</option>
              <option value="set">SET</option>
              <option value="sqft">SQFT</option>
              <option value="sqmtr">SQMTR</option>
              <option value="ltr">LTR</option>
              <option value="ml">ML</option>
              <option value="unit">UNIT</option>
              <option value="bag">BAG</option>
              <option value="can">CAN</option>
              <option value="case">CASE</option>
              <option value="doz">DOZ</option>
              <option value="ft">FT</option>
              <option value="inch">INCH</option>
              <option value="km">KM</option>
              <option value="roll">ROLL</option>
            </select>
          </td>
          <td><input type="number" class="price-input" placeholder="Price" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input type="number" class="discount-input" value="0" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input type="number" class="gst-input" value="9.00" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input type="number" class="cgst-input" value="9.00" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input class="line-total" placeholder="Total" readonly style="border: 1px solid #ddd; border-radius: 4px; padding: 6px; background: #f9f9f9; font-weight: bold;"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Minimal DB fields to save invoice -->
  <div class="save-meta card" id="saveMeta" style="margin: 30px 0; max-width: 100%;">
    <h4 style="margin-bottom:20px; color: #333; font-weight: 600;">Save Details</h4>
    <div class="form-row" style="max-width:500px;">
      <label for="customer_select">Customer<span style="color:red">*</span></label>
      <select id="customer_select" class="custom-select"><option value="">Loading...</option></select>
      <input type="hidden" id="customer_id" />
    </div>
    <div class="form-row" style="max-width:500px;">
      <label for="invoice_date">Invoice Date<span style="color:red">*</span></label>
      <input type="date" id="invoice_date">
    </div>
    <div class="form-row" style="max-width:500px;">
      <label for="due_date">Due Date</label>
      <input type="date" id="due_date">
    </div>
    <div class="form-row" style="max-width:500px;">
      <label for="payment_method">Payment Method</label>
      <select id="payment_method" class="custom-select">
        <option value="cash">Cash</option>
        <option value="credit">Credit</option>
        <option value="cheque">Cheque</option>
        <option value="online">Online</option>
      </select>
    </div>
    <div class="form-row" style="max-width:500px;">
      <label for="paid_amount">Amount Received</label>
      <input type="number" id="paid_amount" step="0.01" placeholder="0.00">
    </div>
    <div class="form-row" style="max-width:100%;">
      <label for="notes">Notes</label>
      <textarea id="notes" placeholder="Notes (optional)"></textarea>
    </div>
  </div>

  <div class="footer-buttons" style="margin-bottom: 50px;">
    <button class="btn-back"><a href="salesinvoice.php" style="color: inherit; text-decoration: none;">&lt; Back</a></button>
    <div style="display: flex; gap: 10px;">
      <button class="btn-discard" style="background: #ef4444; color: white;"><a href="" style="color: white; text-decoration: none;">Discard</a></button>
      <button class="btn-save-print" style="background: #10b981; color: white;"><a href="" style="color: white; text-decoration: none;">Save & Print</a></button>
      <button id="btnSaveSales" type="button" class="btn-save" style="background: #02b386; color: white; padding: 10px 25px; border-radius: 8px; font-weight: 600;">Save</button>
    </div>
  </div>
</div>

<style>
/* Addition styling for dropdowns to make them look premium */
.custom-select {
  appearance: none;
  background-color: #fff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 30px 8px 10px;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.75rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  transition: all 0.2s ease;
  cursor: pointer;
}
.custom-select:focus {
  border-color: #02b386;
  box-shadow: 0 0 0 3px rgba(2, 179, 134, 0.1);
  outline: none;
}
.table-responsive {
  overflow-x: auto;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #ddd;
  margin-top: 10px;
}
.table td select {
    margin: 0;
}
</style>

<script>
(function(){
  /* ═══════════════════════════════════════
   * Date Defaults & Payment Method Logic
   * ═══════════════════════════════════════ */

  // Set today's date as the default invoice date
  var invoiceDateField = document.getElementById('invoice_date');
  if (invoiceDateField && !invoiceDateField.value) {
    var today = new Date();
    var formattedDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    invoiceDateField.value = formattedDate;
  }

  // Handle Payment Method change: credit = 0 received, cash/etc = user fills
  document.getElementById('payment_method').addEventListener('change', function() {
    var selectedMethod = this.value;
    var amountReceivedField = document.getElementById('paid_amount');
    if (selectedMethod === 'credit') {
      amountReceivedField.value = '0';
    } else {
      amountReceivedField.value = '';
    }
  });

  /* ═══════════════════════════════════════
   * Customer Dropdown Loading & Auto-fill
   * ═══════════════════════════════════════ */
  var customerLookupMap = {};

  /**
   * Loads customer records into both the top "M/S" and bottom "Save Details" dropdowns,
   * and wires auto-fill behavior for address, contact, phone, GSTIN, and supply fields.
   */
  async function loadCustomerDropdowns() {
    try {
      var topCustomerSelect    = document.getElementById('ms');
      var bottomCustomerSelect = document.getElementById('customer_select');
      var hiddenCustomerIdField = document.getElementById('customer_id');

      if (!topCustomerSelect) return;
      topCustomerSelect.innerHTML = '<option value="">Loading...</option>';
      
      var apiUrl = '../api/customers.php?type=customer';
      var response = await fetch(apiUrl, { credentials: 'include' });
      var responseData = await response.json();

      if (!response.ok || responseData.success !== true) {
        throw new Error(responseData.error || 'Failed to load customers');
      }

      var customerList = (responseData.data && responseData.data.customers) ? responseData.data.customers : [];
      customerLookupMap = {};
      customerList.forEach(function(customer) { customerLookupMap[String(customer.id)] = customer; });

      var dropdownOptionsHtml = '<option value="">Select Customer...</option>' + customerList.map(function(customer) {
        var displayName = (customer.company_name && customer.company_name.trim().length > 0) ? customer.company_name : customer.name;
        return '<option value="' + customer.id + '">' + displayName + '</option>';
      }).join('');

      topCustomerSelect.innerHTML = dropdownOptionsHtml;
      if (bottomCustomerSelect) bottomCustomerSelect.innerHTML = dropdownOptionsHtml;

      /**
       * When a customer is selected, auto-fill address, contact, phone, GSTIN, and supply fields
       */
      function onCustomerSelected(selectedId) {
        var selectedCustomer = customerLookupMap[selectedId];
        if (selectedCustomer) {
          var fullAddress = (selectedCustomer.address || '') + (selectedCustomer.city ? ', ' + selectedCustomer.city : '') + (selectedCustomer.state ? ', ' + selectedCustomer.state : '') + (selectedCustomer.pincode ? ' - ' + selectedCustomer.pincode : '');
          document.getElementById('address').value = fullAddress.trim();
          document.getElementById('contact').value = selectedCustomer.name || '';
          document.getElementById('phone').value   = selectedCustomer.mobile || '';
          document.getElementById('gstin').value   = selectedCustomer.gst_number || selectedCustomer.pan_number || '';
          if (document.getElementById('supply')) document.getElementById('supply').value = selectedCustomer.state || '';

          if (hiddenCustomerIdField) hiddenCustomerIdField.value = selectedId;
          if (topCustomerSelect) topCustomerSelect.value = selectedId;
          if (bottomCustomerSelect) bottomCustomerSelect.value = selectedId;
        } else {
          // Clear all auto-filled fields when no customer is selected
          ['address', 'contact', 'phone', 'gstin', 'supply'].forEach(function(fieldId) {
            var el = document.getElementById(fieldId); if (el) el.value = '';
          });
          if (hiddenCustomerIdField) hiddenCustomerIdField.value = '';
          if (topCustomerSelect) topCustomerSelect.value = '';
          if (bottomCustomerSelect) bottomCustomerSelect.value = '';
        }
      }

      topCustomerSelect.addEventListener('change', function() { onCustomerSelected(topCustomerSelect.value); });
      if (bottomCustomerSelect) bottomCustomerSelect.addEventListener('change', function() { onCustomerSelected(bottomCustomerSelect.value); });

    } catch (loadError) {
      console.error('Customer dropdown load error:', loadError);
      if (document.getElementById('ms')) document.getElementById('ms').innerHTML = '<option value="">Load failed</option>';
    }
  }
  loadCustomerDropdowns();

  /* ═══════════════════════════════════════
   * Line Item Row Total Calculation
   * ═══════════════════════════════════════ */

  /**
   * Calculates the total for a single line item row based on Qty, Price, Discount, and GST rates
   */
  function calculateRowTotal(tableRow) {
    var quantity   = parseFloat((tableRow.querySelector('.qty-input') || {}).value || '0');
    var unitPrice  = parseFloat((tableRow.querySelector('.price-input') || {}).value || '0');
    var discount   = parseFloat((tableRow.querySelector('.discount-input') || {}).value || '0');

    var subtotal    = quantity * unitPrice;
    var taxableBase = Math.max(0, subtotal - (isNaN(discount) ? 0 : discount));

    var igstRate = parseFloat((tableRow.querySelector('.gst-input') || {}).value || '0');
    var cgstRate = parseFloat((tableRow.querySelector('.cgst-input') || {}).value || '0');

    var totalTaxAmount = (taxableBase * (igstRate / 100)) + (taxableBase * (cgstRate / 100));
    var lineTotal      = taxableBase + totalTaxAmount;

    var lineTotalField = tableRow.querySelector('.line-total');
    if (lineTotalField) lineTotalField.value = lineTotal.toFixed(2);
  }

  /* ═══════════════════════════════════════
   * Product Dropdown Loading & Auto-fill
   * ═══════════════════════════════════════ */
  var productLookupMap = {};

  /**
   * Fetches product records and populates all product dropdown selects in the items table
   */
  async function loadProductDropdowns() {
    try {
      var responseData;
      if (window.apiHandler && typeof window.apiHandler.getProducts === 'function') {
        responseData = await window.apiHandler.getProducts();
      } else {
        var isLiveServer = /^(127\.0\.0\.1|localhost):55\d{2}$/.test(window.location.host);
        var apiUrl = isLiveServer
          ? 'http://localhost/copy/project%201/api/products.php'
          : '../api/products.php';
        var fetchResponse = await fetch(apiUrl, { credentials: 'include' });
        responseData = await fetchResponse.json();
      }
      if (!responseData || responseData.success !== true) {
        throw new Error((responseData && (responseData.error || responseData.message)) || 'Failed to load products');
      }
      var productList = (responseData.data && responseData.data.products) ? responseData.data.products : [];
      productLookupMap = {};
      productList.forEach(function(product) { productLookupMap[String(product.id)] = product; });

      var dropdownOptionsHtml = ['<option value="">Select product...</option>']
        .concat(productList.map(function(product) { return '<option value="' + product.id + '">' + product.name + ' (ID:' + product.id + ')</option>'; }))
        .join('');
      document.querySelectorAll('select.product-select').forEach(function(selectElement) {
        selectElement.innerHTML = dropdownOptionsHtml;
        selectElement.value = '';
        bindProductSelectAutoFill(selectElement);
      });
    } catch (loadError) {
      console.error('Products load failed:', loadError);
      document.querySelectorAll('select.product-select').forEach(function(selectElement) {
        selectElement.innerHTML = '<option value="">Load failed</option>';
      });
    }
  }

  /**
   * Binds a product dropdown so that when a product is selected,
   * HSN, price, and GST fields in the same row are auto-filled
   */
  function bindProductSelectAutoFill(selectElement) {
    if (!selectElement) return;
    selectElement.addEventListener('change', function() {
      var tableRow  = selectElement.closest('tr');
      var productId = selectElement.value || '';
      var product   = productLookupMap[productId];

      var hiddenIdField = tableRow && tableRow.querySelector('.product-id');
      if (hiddenIdField) hiddenIdField.value = productId;

      if (product) {
        var hsnField   = tableRow.querySelector('.hsn-input');   if (hsnField)   hsnField.value = product.hsn_sac || '';
        var priceField = tableRow.querySelector('.price-input'); if (priceField) priceField.value = (product.sale_price ?? product.price) || '';

        var totalGstRate = product.gst_rate != null ? product.gst_rate : 18;
        var igstField = tableRow.querySelector('.gst-input');  if (igstField) igstField.value = (totalGstRate / 2).toFixed(2);
        var cgstField = tableRow.querySelector('.cgst-input'); if (cgstField) cgstField.value = (totalGstRate / 2).toFixed(2);
      }
      calculateRowTotal(tableRow);
    }, { once: false });
  }

  /**
   * Wires up input listeners on a row so that any change to qty/price/discount/GST recalculates the total
   */
  function wireRowCalculationListeners(tableRow) {
    ['.qty-input', '.price-input', '.discount-input', '.gst-input', '.cgst-input'].forEach(function(selector) {
      var inputElement = tableRow.querySelector(selector);
      if (inputElement) { inputElement.addEventListener('input', function() { calculateRowTotal(tableRow); }); }
    });
    calculateRowTotal(tableRow);
  }

  /**
   * Adds a new blank line item row by cloning the first row template
   */
  function addLineItemRow() {
    var tableBody = document.querySelector('#itemsTable tbody');
    if (!tableBody) return;
    var existingRows = tableBody.querySelectorAll('tr');
    var templateRow  = existingRows[0];
    var clonedRow    = templateRow.cloneNode(true);

    // Reset all inputs in the cloned row
    Array.from(clonedRow.querySelectorAll('input, textarea, select')).forEach(function(inputElement) {
      if (inputElement.classList.contains('gst-input'))       { /* keep default GST rate */ }
      else if (inputElement.classList.contains('cgst-input')) { inputElement.value = '9.00'; }
      else if (inputElement.classList.contains('line-total')) { inputElement.value = ''; }
      else if (inputElement.tagName === 'SELECT')            { /* keep first option */ }
      else { inputElement.value = ''; }
    });

    // Update SR number
    var srNumberCell = clonedRow.querySelector('td:first-child');
    if (srNumberCell) { srNumberCell.textContent = String(existingRows.length + 1); }

    // Repopulate product select dropdown in cloned row
    var productSelect = clonedRow.querySelector('select.product-select');
    if (productSelect) {
      if (Object.keys(productLookupMap).length > 0) {
        var productList = Object.values(productLookupMap);
        var dropdownOptionsHtml = ['<option value="">Select product...</option>']
          .concat(productList.map(function(product) { return '<option value="' + product.id + '">' + product.name + ' (ID:' + product.id + ')</option>'; }))
          .join('');
        productSelect.innerHTML = dropdownOptionsHtml;
        productSelect.value = '';
      } else {
        productSelect.innerHTML = '<option value="">Select product...</option>';
      }
      bindProductSelectAutoFill(productSelect);
    }
    tableBody.appendChild(clonedRow);
    wireRowCalculationListeners(clonedRow);
  }

  /* ── Wire initial row and add-item button ── */
  var firstRow = document.querySelector('#itemsTable tbody tr');
  if (firstRow) {
    wireRowCalculationListeners(firstRow);
    var firstProductSelect = firstRow.querySelector('select.product-select');
    if (firstProductSelect) bindProductSelectAutoFill(firstProductSelect);
  }
  var addItemButton = document.getElementById('btnAddItem');
  if (addItemButton) addItemButton.addEventListener('click', function(e) { e.preventDefault(); addLineItemRow(); });

  /* ── Load products after DOM ready ── */
  loadProductDropdowns();

  /* ═══════════════════════════════════════
   * Save Invoice Handler
   * ═══════════════════════════════════════ */
  var saveInvoiceButton = document.getElementById('btnSaveSales');
  if (!saveInvoiceButton) return;

  saveInvoiceButton.addEventListener('click', async function() {
    try {
      var customerId    = parseInt((document.getElementById('customer_id') || {}).value || (document.getElementById('customer_select') || {}).value || '');
      var invoiceDate   = (document.getElementById('invoice_date') || {}).value;
      var dueDate       = (document.getElementById('due_date') || {}).value || null;
      var paymentMethod = (document.getElementById('payment_method') || {}).value || 'cash';
      var paidAmount    = (document.getElementById('paid_amount') || {}).value;
      var notes         = (document.getElementById('notes') || {}).value || '';

      if (!customerId || !invoiceDate) {
        showToast('Customer ID and Invoice Date are required', 'error');
        return;
      }

      // Collect all line items from the items table
      var lineItems = [];
      var itemRows  = document.querySelectorAll('#itemsTable tbody tr');
      itemRows.forEach(function(tableRow) {
        var productId = parseInt((tableRow.querySelector('.product-id') || {}).value || '');
        var quantity  = parseInt((tableRow.querySelector('.qty-input') || {}).value || '0');
        var unitPrice = parseFloat((tableRow.querySelector('.price-input') || {}).value || '0');
        var igstRate  = parseFloat((tableRow.querySelector('.gst-input') || {}).value || '0');
        var cgstRate  = parseFloat((tableRow.querySelector('.cgst-input') || {}).value || '0');
        var totalGstRate = igstRate + cgstRate;
        if (quantity > 0 && unitPrice > 0) {
          lineItems.push({ product_id: (isNaN(productId) ? null : productId), quantity: quantity, unit_price: unitPrice, gst_rate: totalGstRate });
        }
      });

      if (lineItems.length === 0) {
        showToast('Please add at least one valid item', 'error');
        return;
      }

      var invoicePayload = {
        customer_id:    customerId,
        invoice_date:   invoiceDate,
        due_date:       dueDate,
        payment_method: paymentMethod,
        paid_amount:    paidAmount !== '' ? parseFloat(paidAmount) : null,
        notes:          notes,
        items:          lineItems
      };

      var isLiveServer = /^(127\.0\.0\.1|localhost):55\d{2}$/.test(window.location.host);
      var postUrl = isLiveServer
        ? 'http://localhost/copy/project%201/api/invoices.php?type=sales'
        : '../api/invoices.php?type=sales';

      var fetchResponse = await fetch(postUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invoicePayload),
        credentials: 'include'
      });

      var rawResponseText = await fetchResponse.text();
      var parsedResponse;
      try { parsedResponse = JSON.parse(rawResponseText); }
      catch (parseError) { throw new Error('Server returned non-JSON: ' + rawResponseText.slice(0, 200)); }

      if (!fetchResponse.ok || parsedResponse.success !== true) {
        var errorMessage = (parsedResponse && (parsedResponse.error || parsedResponse.message)) || 'Failed to save invoice';
        if (/Authentication required/i.test(errorMessage) || fetchResponse.status === 401) {
          var loginUrl = isLiveServer
            ? 'http://localhost/sem5goinvoice/project%201/front/login.html'
            : '../front/login.html';
          showError('Your session has expired. Redirecting to login...');
          setTimeout(function() { window.location.href = loginUrl; }, 2000);
          return;
        }
        throw new Error(errorMessage);
      }

      var invoiceNumber = (parsedResponse.data && parsedResponse.data.invoice_number) ? parsedResponse.data.invoice_number : '';
      showSuccess('Invoice saved successfully! #' + invoiceNumber);
      setTimeout(function() { window.location.href = 'salesinvoice.php'; }, 1500);
    } catch (saveError) {
      showError(saveError.message || 'Save failed');
    }
  });

})();
</script>
<?php include '../includes/footer.php'; ?>
