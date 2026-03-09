<?php
/**
 * Purchase Invoice Creation Form
 * Responsibility: Provides a comprehensive form for creating purchase invoices including
 *                 vendor selection, invoice details, product line items with GST calculations,
 *                 vendor bank details, QR code upload, and save/discard actions.
 */

$page_title = 'Create Purchase Invoice';

/* ── Legacy Action Redirect ── */
if (isset($_GET['action']) && ($_GET['action'] === 'list_purchases' || $_GET['action'] === 'delete_purchase')) {
  header('Location: ../api/invoices.php?type=purchase&' . $_SERVER['QUERY_STRING']);
  exit;
}

$additional_css = ['main.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>

<!-- Layout Wrapper -->
<div class="main-form-container">
  
  <!-- Header -->
  <div id="header">
    <h3>✏️ Create Purchase Invoice</h3>
    <button id="create-invoice-btn">+ Create Another Invoice</button>
  </div>

  <div class="layout">
    <div class="card">
      <div class="card-header">
        <h3>Vendor Information</h3>
        <button type="button" onclick="window.location.href='customer.php'">+ Add Vendor</button>
      </div>

      <div class="form-row">
        <label for="ms">M/S.<span style="color:red">*</span></label>
        <select id="ms" class="custom-select">
          <option value="">Select Vendor...</option>
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

    <div class="info-card" id="invoice-detail-card">
      <div class="info-card-header">
        <h3>Purchase Invoice Detail</h3>
      </div>
      <form class="info-grid">
        <label>Purchase Invoice Type</label>
        <select class="custom-select"><option>Regular</option></select>

        <label>Invoice Number</label>
        <input type="text" placeholder="1" />

        <label>Date<span style="color:red">*</span></label>
        <input type="text" placeholder="Date*" value="03-Aug-2025" />

        <label>Challan No.</label>
        <input type="text" placeholder="Challan No." />

        <label>Challan Date</label>
        <input type="text" placeholder="dd/mm/yy" />

        <label>L.R. No.</label>
        <input type="text" placeholder="L.R. No." />

        <label>E-Way No.</label>
        <input type="text" placeholder="E-Way No." />

        <label>Enter Date</label>
        <input type="text" placeholder="dd/mm/yy" />

        <label>Delivery Mode</label>
        <select class="custom-select"><option>Select Delivery Mode</option></select>
      </form>
    </div>
  </div>

  <div class="top-buttons" style="margin-top: 30px;">
    <h3>Products Items</h3>
    <button id="btnAddItemPurchase">+ Add Product</button>
    <button>+ Add Additional Charges</button>
  </div>
  
  <div class="table-responsive">
    <table id="itemsTablePurchase" class="table">
      <thead>
        <tr>
          <th>SR.</th>
          <th>PRODUCT / OTHER CHARGES</th>
          <th>HSN/SAC CODE</th>
          <th>PRODUCT ID (DB)</th>
          <th>QTY.</th>
          <th>UOM</th>
          <th>PRICE (RS)</th>
          <th>IGST (%)</th>
          <th>CGST (%)</th>
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
          <td><input class="product-id-display" placeholder="ID" readonly style="border: 1px solid #ddd; border-radius: 4px; padding: 6px; background: #f9f9f9; width: 60px;"></td>
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
          <td><input type="number" class="gst-input" value="9.00" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td><input type="number" class="cgst-input" value="9.00" style="border: 1px solid #ddd; border-radius: 4px; padding: 6px;"></td>
          <td>
            <div style="display:flex; gap:6px; align-items:center;">
              <input class="line-total" placeholder="Total" readonly style="border: 1px solid #ddd; border-radius: 4px; padding: 6px; background: #f9f9f9; font-weight: bold;">
              <button type="button" class="btn-calc-row" style="padding:4px 8px; background: #f2f2f2; border: 1px solid #ddd; border-radius: 4px;">Calc</button>
            </div>
          </td>
        </tr>
      <tr class="highlight-row">
        <td colspan="3">Total Inv. Val</td>
        <td>0</td>
        <td></td>
        <td>0</td>
        <td>0</td>
        <td>0</td>
        <td>0</td>
        <td>0</td>
      </tr>
    </tbody>
  </table>

  <!--div class="section">
    <div class="left">
      <div class="the-row">
        <label>Due Date</label>
        <input type="text" placeholder="18-Aug-2025">
        </br>
      </div>
      <label>Title</label>
      <input type="text" value="Terms and Conditions">

      <label>Detail</label>
      <textarea rows="4">Subject to our home Jurisdiction.
Our Responsibility Ceases as soon as goods leaves our Premises.</textarea>

      <button class="notes-button">+ Add Notes</button>

      <label>Document Note / Remarks</label>
      <textarea></textarea>
      <small><em>Not Visible on Print</em></small>
    </div>

    <div class="right">
      <div class="the-row">
        <label>Total Taxable</label>
        <input type="text" value="0">
      </div>
      <div class="the-row">
        <label>Total Tax</label>
        <input type="text" value="0"> 
      </div>

      <div class="toggle-group">
        <label>TCS</label>
        <span>Rs</span>
        <span>%</span>
        <button>-</button>
        <button>+</button>
        <div class="input-group">
          <input type="text" value="0">
        </div>
      </div>

      <div class="toggle-group">
        <label>Discount</label>
        <span>Rs</span>
        <span>%</span>
        <button>-</button>
        <button>+</button>
        <input type="text" value="0">
      </div>

      <label>Round Off</label>
      <div class="toggle-group">
        <button style="background:#00c37e; color:white;">Yes</button>
        <button>No</button>
      </div>

      <div class="total-box">Grand Total 0</div>

      <p>Total in words<br><strong>ZERO RUPEES ONLY</strong></p>

      <label>Payment Type<span style="color:red">*</span></label>
      <div class="payment-type">
        <button class="CREDIT">CREDIT</button>
        <button class="CASH">CASH</button>
        <button class="CHEQUE">CHEQUE</button>
        <button class="ONLINE">ONLINE</button>
      </div>

      <div class="smart-suggestion">
        <span>Smart Suggestion</span>
        <button>+</button>
      </div>
    </div>
  </div-->


  <div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <!-- Minimal DB fields to save purchase invoice -->
    <div class="save-meta card" id="saveMetaPurchase" style="margin: 30px 0; flex: 1; min-width: 400px;">
      <h4 style="margin-bottom:20px; color: #333; font-weight: 600;">Save Details (purchase)</h4>
      <div class="form-row" style="max-width:500px;">
        <label for="vendor_select">Vendor<span style="color:red">*</span></label>
        <select id="vendor_select" class="custom-select"><option value="">Loading...</option></select>
        <input type="hidden" id="customer_id_purchase" />
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="invoice_date_purchase">Invoice Date<span style="color:red">*</span></label>
        <input type="date" id="invoice_date_purchase">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="due_date_purchase">Due Date</label>
        <input type="date" id="due_date_purchase">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="payment_method_purchase">Payment Method</label>
        <select id="payment_method_purchase" class="custom-select">
          <option value="cash">Cash</option>
          <option value="credit">Credit</option>
          <option value="cheque">Cheque</option>
          <option value="online">Online</option>
        </select>
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="paid_amount_purchase">Amount Paid</label>
        <input type="number" id="paid_amount_purchase" step="0.01" placeholder="0.00">
      </div>
      <div class="form-row" style="max-width:100%;">
        <label for="notes_purchase">Notes</label>
        <textarea id="notes_purchase" placeholder="Notes (optional)"></textarea>
      </div>
    </div>

    <!-- Vendor Bank Details -->
    <div class="bank-meta card" id="bankMetaPurchase" style="margin: 30px 0; flex: 1; min-width: 400px;">
      <h4 style="margin-bottom:20px; color: #333; font-weight: 600;">Vendor Bank Details</h4>
      <div class="form-row" style="max-width:500px;">
        <label for="bank_name">Bank Name</label>
        <input type="text" id="bank_name" placeholder="Bank Name">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="bank_branch">Branch</label>
        <input type="text" id="bank_branch" placeholder="Branch">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="account_name">Account Name</label>
        <input type="text" id="account_name" placeholder="Account Name">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="account_number">Account Number</label>
        <input type="text" id="account_number" placeholder="Account Number">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="ifsc_code">IFSC Code</label>
        <input type="text" id="ifsc_code" placeholder="IFSC Code">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="upi_id">UPI ID</label>
        <input type="text" id="upi_id" placeholder="UPI ID">
      </div>
      <div class="form-row" style="max-width:500px;">
        <label for="qr_code">Upload QR Code</label>
        <input type="file" id="qr_code" accept="image/*" style="border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: white;">
      </div>
      <p style="font-size: 12px; color: #666; margin-top: 10px;">This QR code and bank info will appear on the printed purchase bill.</p>
    </div>
  </div>

  <div class="footer-buttons" style="margin-bottom: 50px;">
    <button class="btn-back"><a href="purchase.php" style="color: inherit; text-decoration: none;">&lt; Back</a></button>
    <div style="display: flex; gap: 10px;">
      <button class="btn-discard" style="background: #ef4444; color: white;"><a href="" style="color: white; text-decoration: none;">Discard</a></button>
      <button class="btn-save-print" style="background: #10b981; color: white;"><a href="" style="color: white; text-decoration: none;">Save & Print</a></button>
      <button id="btnSavePurchase" type="button" class="btn-save" style="background: #02b386; color: white; padding: 10px 25px; border-radius: 8px; font-weight: 600;">Save</button>
    </div>
  </div>
</div>

<script>
  (function(){
    /* ═══════════════════════════════════════
     * Date Defaults & Payment Method Logic
     * ═══════════════════════════════════════ */

    // Set today's date as the default purchase invoice date
    var invoiceDateField = document.getElementById('invoice_date_purchase');
    if (invoiceDateField && !invoiceDateField.value) {
      var today = new Date();
      var formattedDate = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
      invoiceDateField.value = formattedDate;
    }

    // Handle Payment Method change: credit = 0 paid, cash/etc = user fills
    document.getElementById('payment_method_purchase').addEventListener('change', function() {
      var selectedMethod = this.value;
      var amountPaidField = document.getElementById('paid_amount_purchase');
      if (selectedMethod === 'credit') {
        amountPaidField.value = '0';
      } else {
        amountPaidField.value = '';
      }
    });

    /* ═══════════════════════════════════════
     * Vendor Dropdown Loading & Auto-fill
     * ═══════════════════════════════════════ */
    var vendorLookupMap = {};

    /**
     * Loads vendor records into both the top "M/S" and bottom "Save Details" dropdowns,
     * and wires auto-fill behavior for address, contact, phone, GSTIN, and supply fields.
     */
    async function loadVendorDropdowns() {
      try {
        var topVendorSelect     = document.getElementById('ms');
        var bottomVendorSelect  = document.getElementById('vendor_select');
        var hiddenVendorIdField = document.getElementById('customer_id_purchase');

        if (!topVendorSelect) return;
        topVendorSelect.innerHTML = '<option value="">Loading...</option>';

        var isLiveServer = /^(127\.0\.0\.1|localhost):55\d{2}$/.test(window.location.host);
        var apiUrl = isLiveServer
          ? 'http://localhost/copy/project%201/api/customers.php?type=vendor'
          : '../api/customers.php?type=vendor';

        var response = await fetch(apiUrl, { credentials: 'include' });
        var responseData = await response.json();

        if (!response.ok || responseData.success !== true) {
          throw new Error(responseData.error || 'Failed to load vendors');
        }

        var vendorList = (responseData.data && responseData.data.customers) ? responseData.data.customers : [];
        vendorLookupMap = {};
        vendorList.forEach(function(vendor) { vendorLookupMap[String(vendor.id)] = vendor; });

        var dropdownOptionsHtml = '<option value="">Select Vendor...</option>' + vendorList.map(function(vendor) {
          var displayName = (vendor.company_name && vendor.company_name.trim().length > 0) ? vendor.company_name : vendor.name;
          return '<option value="' + vendor.id + '">' + displayName + '</option>';
        }).join('');

        topVendorSelect.innerHTML = dropdownOptionsHtml;
        if (bottomVendorSelect) bottomVendorSelect.innerHTML = dropdownOptionsHtml;

        /**
         * When a vendor is selected, auto-fill address, contact, phone, GSTIN, and supply fields
         */
        function onVendorSelected(selectedId) {
          var selectedVendor = vendorLookupMap[selectedId];
          if (selectedVendor) {
            var fullAddress = (selectedVendor.address || '') + (selectedVendor.city ? ', ' + selectedVendor.city : '') + (selectedVendor.state ? ', ' + selectedVendor.state : '') + (selectedVendor.pincode ? ' - ' + selectedVendor.pincode : '');
            document.getElementById('address').value = fullAddress.trim();
            document.getElementById('contact').value = selectedVendor.name || '';
            document.getElementById('phone').value   = selectedVendor.mobile || '';
            document.getElementById('gstin').value   = selectedVendor.gst_number || selectedVendor.pan_number || '';
            if (document.getElementById('supply')) document.getElementById('supply').value = selectedVendor.state || '';

            if (hiddenVendorIdField) hiddenVendorIdField.value = selectedId;
            if (topVendorSelect) topVendorSelect.value = selectedId;
            if (bottomVendorSelect) bottomVendorSelect.value = selectedId;
          } else {
            // Clear all auto-filled fields when no vendor is selected
            ['address', 'contact', 'phone', 'gstin', 'supply'].forEach(function(fieldId) {
              var el = document.getElementById(fieldId); if (el) el.value = '';
            });
            if (hiddenVendorIdField) hiddenVendorIdField.value = '';
            if (topVendorSelect) topVendorSelect.value = '';
            if (bottomVendorSelect) bottomVendorSelect.value = '';
          }
        }

        topVendorSelect.addEventListener('change', function() { onVendorSelected(topVendorSelect.value); });
        if (bottomVendorSelect) bottomVendorSelect.addEventListener('change', function() { onVendorSelected(bottomVendorSelect.value); });

      } catch (loadError) {
        console.error('Vendor dropdown load error:', loadError);
        if (document.getElementById('ms')) document.getElementById('ms').innerHTML = '<option value="">Load failed</option>';
      }
    }
    loadVendorDropdowns();

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
          var priceField = tableRow.querySelector('.price-input'); if (priceField) priceField.value = (product.purchase_price ?? product.price) || '';

          var totalGstRate = product.gst_rate != null ? product.gst_rate : 18;
          var igstField = tableRow.querySelector('.gst-input');  if (igstField) igstField.value = (totalGstRate / 2).toFixed(2);
          var cgstField = tableRow.querySelector('.cgst-input'); if (cgstField) cgstField.value = (totalGstRate / 2).toFixed(2);
        }
        calculateRowTotal(tableRow);
      }, { once: false });
    }

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
      var tableBody = document.querySelector('#itemsTablePurchase tbody');
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
    var firstRow = document.querySelector('#itemsTablePurchase tbody tr');
    if (firstRow) {
      wireRowCalculationListeners(firstRow);
      var firstProductSelect = firstRow.querySelector('select.product-select');
      if (firstProductSelect) bindProductSelectAutoFill(firstProductSelect);
    }
    var addItemButton = document.getElementById('btnAddItemPurchase');
    if (addItemButton) addItemButton.addEventListener('click', function(e) { e.preventDefault(); addLineItemRow(); });

    /* ── Load products after DOM ready ── */
    loadProductDropdowns();

    /* ═══════════════════════════════════════
     * Save Purchase Invoice Handler
     * ═══════════════════════════════════════ */
    var savePurchaseButton = document.getElementById('btnSavePurchase');
    if (!savePurchaseButton) return;

    savePurchaseButton.addEventListener('click', async function() {
      try {
        var vendorId      = parseInt((document.getElementById('customer_id_purchase') || {}).value || (document.getElementById('vendor_select') || {}).value || '');
        var invoiceDate   = (document.getElementById('invoice_date_purchase') || {}).value;
        var dueDate       = (document.getElementById('due_date_purchase') || {}).value || null;
        var paymentMethod = (document.getElementById('payment_method_purchase') || {}).value || 'cash';
        var paidAmount    = (document.getElementById('paid_amount_purchase') || {}).value;
        var purchaseNotes = (document.getElementById('notes_purchase') || {}).value || '';

        if (!vendorId || !invoiceDate) {
          showToast('Vendor and Invoice Date are required', 'error');
          return;
        }

        // Collect all line items from the purchase items table
        var lineItems = [];
        var firstInvalidPriceField = null;
        var itemRows = document.querySelectorAll('#itemsTablePurchase tbody tr:not(.highlight-row)');

        itemRows.forEach(function(tableRow) {
          var productIdRaw = ((tableRow.querySelector('.product-id') || {}).value || '').trim();
          var productId    = productIdRaw === '' ? null : parseInt(productIdRaw.replace(/,/g, ''));
          var quantityStr  = ((tableRow.querySelector('.qty-input') || {}).value || '0').toString().replace(/\s|,/g, '');
          var priceStr     = ((tableRow.querySelector('.price-input') || {}).value || '0').toString().replace(/\s|,/g, '');
          var igstStr      = ((tableRow.querySelector('.gst-input') || {}).value || '0').toString().replace(/\s|,/g, '');
          var cgstStr      = ((tableRow.querySelector('.cgst-input') || {}).value || '0').toString().replace(/\s|,/g, '');

          var quantity     = parseFloat(quantityStr);
          var unitPrice    = parseFloat(priceStr);
          var totalGstRate = parseFloat(igstStr) + parseFloat(cgstStr);

          // Allow missing Product ID; require Qty and Price
          if (quantity > 0 && unitPrice > 0) {
            lineItems.push({ product_id: productId, quantity: quantity, unit_price: unitPrice, gst_rate: totalGstRate });
          } else if (quantity > 0 && (!unitPrice || unitPrice <= 0)) {
            if (!firstInvalidPriceField) { firstInvalidPriceField = tableRow.querySelector('.price-input'); }
          }
        });

        if (lineItems.length === 0) {
          showToast('Please add at least one valid item', 'error');
          if (firstInvalidPriceField && typeof firstInvalidPriceField.focus === 'function') {
            setTimeout(function() { firstInvalidPriceField.focus(); }, 0);
          }
          return;
        }

        // Build FormData to support file upload (QR code)
        var purchaseFormData = new FormData();
        purchaseFormData.append('customer_id', vendorId);
        purchaseFormData.append('invoice_date', invoiceDate);
        purchaseFormData.append('due_date', dueDate || '');
        purchaseFormData.append('payment_method', paymentMethod);
        purchaseFormData.append('paid_amount', paidAmount !== '' ? paidAmount : '');
        purchaseFormData.append('notes', purchaseNotes);
        purchaseFormData.append('items', JSON.stringify(lineItems));

        // Append Vendor Bank Details
        purchaseFormData.append('bank_name',      (document.getElementById('bank_name') || {}).value || '');
        purchaseFormData.append('bank_branch',     (document.getElementById('bank_branch') || {}).value || '');
        purchaseFormData.append('account_name',    (document.getElementById('account_name') || {}).value || '');
        purchaseFormData.append('account_number',  (document.getElementById('account_number') || {}).value || '');
        purchaseFormData.append('ifsc_code',       (document.getElementById('ifsc_code') || {}).value || '');
        purchaseFormData.append('upi_id',          (document.getElementById('upi_id') || {}).value || '');

        // Append QR Code file if user uploaded one
        var qrCodeInput = document.getElementById('qr_code');
        if (qrCodeInput && qrCodeInput.files[0]) {
          purchaseFormData.append('qr_code', qrCodeInput.files[0]);
        }

        var fetchResponse = await fetch('../api/invoices.php?type=purchase', {
          method: 'POST',
          body: purchaseFormData,
          credentials: 'include'
        });

        var rawResponseText = await fetchResponse.text();
        var parsedResponse;
        try { parsedResponse = JSON.parse(rawResponseText); }
        catch (parseError) { throw new Error('Server returned non-JSON: ' + rawResponseText.slice(0, 200)); }

        if (!fetchResponse.ok || parsedResponse.success !== true) {
          var errorMessage = (parsedResponse && (parsedResponse.error || parsedResponse.message)) || 'Failed to save purchase invoice';
          if (/Authentication required/i.test(errorMessage) || fetchResponse.status === 401) {
            showError('Your session has expired. Redirecting to login...');
            setTimeout(function() { window.location.href = '../front/login.html'; }, 2000);
            return;
          }
          throw new Error(errorMessage);
        }

        var invoiceNumber = (parsedResponse.data && parsedResponse.data.invoice_number) ? parsedResponse.data.invoice_number : '';
        showSuccess('Purchase invoice saved: #' + invoiceNumber);
        setTimeout(function() { window.location.href = 'purchase.php'; }, 1500);
      } catch (saveError) {
        showError(saveError.message || 'Save failed');
      }
    });
  })();
  </script>

<?php include '../includes/footer.php'; ?>
