<?php
/**
 * Customer / Vendor Management Page
 * Responsibility: Lists all customers and vendors with CRUD operations (Add, Edit, View, Delete),
 *                 search, outstanding balance lookups, and type-based stat counts.
 */

$page_title = 'Customer/Vendor - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>

<!-- ═══════════════════════════════════════════════════
     Page Header: Stat Badges & Action Buttons
     ═══════════════════════════════════════════════════ -->
<div class="section1">
  <div class="title">Customer / Vendor</div>
  <div class="stats" style="width: 100%;">
    <div class="stat-items">
      <div class="stat-box">
        <p>Total</p>
        <strong id="statTotal">0</strong>
      </div>
      <div class="stat-box">
        <p>Customer</p>
        <strong id="statCustomer">0</strong>
      </div>
      <div class="stat-box">
        <p>Vendor</p>
        <strong id="statVendor">0</strong>
      </div>
      <div class="stat-box">
        <p>Customer Vendor</p>
        <strong id="statBoth">0</strong>
      </div>
    </div>
    <div class="button-group">
      <input type="text" id="custSearchInput" class="form-control d-inline-block w-auto me-2" placeholder="Search name/email/phone..." style="vertical-align: middle; height: 38px;">
      <button class="btn light" id="btnSearch">Search</button>
      <button class="btn green" type="button" data-bs-toggle="modal" data-bs-target="#addCustomerModal">+ Add New</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     Customer / Vendor Listing Table
     ═══════════════════════════════════════════════════ -->
<div class="invoice-table">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox"></th>
        <th>Name</th>
        <th>Get Outstanding</th>
        <th>Phone</th>
        <th>Type</th>
        <th>State</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="customersTbody">
      <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
    </tbody>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════
     Add / Edit Customer Modal
     ═══════════════════════════════════════════════════ -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCustomerLabel">Add Customer/Vendor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="container-fluid">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Type</label>
              <select class="form-select" id="c_type">
                <option value="customer">Customer</option>
                <option value="vendor">Vendor</option>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="c_is_registered">
                <label class="form-check-label" for="c_is_registered">Registered (GST)</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Company Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_company_name" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_name" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="c_email" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_mobile" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">GST Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_gst" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">PAN Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_pan" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_state" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">City <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_city" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Pincode <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="c_pincode" required />
            </div>
            <div class="col-12">
              <label class="form-label">Address <span class="text-danger">*</span></label>
              <textarea class="form-control" id="c_address" rows="2" required></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Opening Balance</label>
              <input type="number" step="0.01" class="form-control" id="c_opening_balance" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Credit Limit</label>
              <input type="number" step="0.01" class="form-control" id="c_credit_limit" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Credit Due Date</label>
              <input type="date" class="form-control" id="c_credit_due_date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Fax</label>
              <input type="text" class="form-control" id="c_fax" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Website</label>
              <input type="url" class="form-control" id="c_website" placeholder="https://example.com" />
            </div>
            <div class="col-12">
              <label class="form-label">Custom Fields (JSON)</label>
              <textarea class="form-control" id="c_custom_fields" rows="2" placeholder='{"field1":"value","field2":"value"}'></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Note</label>
              <textarea class="form-control" id="c_note" rows="2"></textarea>
            </div>
          </div>
          <div id="c_msg" class="mt-2" style="display:none;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnSaveCustomer">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     View Customer Details Modal (Read-only)
     ═══════════════════════════════════════════════════ -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCustomerLabel">Customer Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewCustomerBody">
          <div><strong>Company:</strong> <span id="v_company"></span></div>
          <div><strong>Name:</strong> <span id="v_name"></span></div>
          <div><strong>Type:</strong> <span id="v_type"></span></div>
          <div><strong>Mobile:</strong> <span id="v_mobile"></span></div>
          <div><strong>Email:</strong> <span id="v_email"></span></div>
          <div><strong>GST:</strong> <span id="v_gst"></span></div>
          <div><strong>PAN:</strong> <span id="v_pan"></span></div>
          <div><strong>State:</strong> <span id="v_state"></span></div>
          <div><strong>City:</strong> <span id="v_city"></span></div>
          <div><strong>Pincode:</strong> <span id="v_pincode"></span></div>
          <div><strong>Address:</strong> <span id="v_address"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    loadCustomerRecords();

    /* ── Save / Update button handler ── */
    var saveButton = document.getElementById('btnSaveCustomer');
    if (saveButton) {
      saveButton.addEventListener('click', async function() {
        // Collect all form field values into a structured payload
        var customerPayload = {
          company_name:    document.getElementById('c_company_name')?.value?.trim() || '',
          name:            document.getElementById('c_name')?.value?.trim() || '',
          type:            document.getElementById('c_type')?.value || 'customer',
          is_registered:   document.getElementById('c_is_registered')?.checked ? 1 : 0,
          email:           document.getElementById('c_email')?.value?.trim() || '',
          mobile:          document.getElementById('c_mobile')?.value?.trim() || '',
          gst_number:      document.getElementById('c_gst')?.value?.trim() || '',
          pan_number:      document.getElementById('c_pan')?.value?.trim() || '',
          address:         document.getElementById('c_address')?.value?.trim() || '',
          city:            document.getElementById('c_city')?.value?.trim() || '',
          state:           document.getElementById('c_state')?.value?.trim() || '',
          pincode:         document.getElementById('c_pincode')?.value?.trim() || '',
          opening_balance: parseFloat(document.getElementById('c_opening_balance')?.value) || 0,
          credit_limit:    parseFloat(document.getElementById('c_credit_limit')?.value) || 0,
          credit_due_date: document.getElementById('c_credit_due_date')?.value || null,
          fax:             document.getElementById('c_fax')?.value?.trim() || '',
          website:         document.getElementById('c_website')?.value?.trim() || '',
          note:            document.getElementById('c_note')?.value?.trim() || ''
        };

        // Parse optional custom fields JSON
        var customFieldsRaw = document.getElementById('c_custom_fields')?.value?.trim();
        if (customFieldsRaw) {
          try { customerPayload.custom_fields = JSON.parse(customFieldsRaw); } catch (jsonError) {}
        }

        var validationMessage = document.getElementById('c_msg');
        if (validationMessage) { validationMessage.style.display = 'none'; validationMessage.className = ''; validationMessage.textContent = ''; }

        // Validate all mandatory fields
        var mandatoryFields = [
          { id: 'company_name', label: 'Company Name' },
          { id: 'name',         label: 'Name' },
          { id: 'email',        label: 'Email' },
          { id: 'mobile',       label: 'Mobile' },
          { id: 'gst_number',   label: 'GST Number' },
          { id: 'pan_number',   label: 'PAN Number' },
          { id: 'state',        label: 'State' },
          { id: 'city',         label: 'City' },
          { id: 'pincode',      label: 'Pincode' },
          { id: 'address',      label: 'Address' }
        ];

        for (var i = 0; i < mandatoryFields.length; i++) {
          if (!customerPayload[mandatoryFields[i].id]) {
            showToast(mandatoryFields[i].label + ' is required', 'error');
            return;
          }
        }

        try {
          saveButton.disabled = true;
          saveButton.textContent = 'Saving...';

          var apiResponse;
          if (window.currentCustomerEditId) {
            apiResponse = await apiHandler.updateCustomer(window.currentCustomerEditId, customerPayload);
          } else {
            apiResponse = await apiHandler.createCustomer(customerPayload);
          }

          if (apiResponse && apiResponse.success) {
            showSuccess(window.currentCustomerEditId ? 'Customer updated successfully!' : 'Customer added successfully!');

            // Reset all form fields after successful save
            var formFieldIds = ['c_company_name','c_name','c_email','c_mobile','c_gst','c_pan','c_address','c_city','c_state','c_pincode','c_opening_balance','c_credit_limit','c_credit_due_date','c_fax','c_website','c_custom_fields','c_note'];
            formFieldIds.forEach(function(fieldId) {
              var fieldElement = document.getElementById(fieldId);
              if (fieldElement) fieldElement.value = '';
            });
            var registeredCheckbox = document.getElementById('c_is_registered');
            if (registeredCheckbox) registeredCheckbox.checked = false;

            // Close modal and refresh the listing
            setTimeout(function() {
              var addModalElement = document.getElementById('addCustomerModal');
              if (addModalElement) {
                var addModal = bootstrap.Modal.getInstance(addModalElement) || new bootstrap.Modal(addModalElement);
                addModal.hide();
              }
              window.currentCustomerEditId = null;
              var modalTitleElement = document.getElementById('addCustomerLabel');
              if (modalTitleElement) modalTitleElement.textContent = 'Add Customer/Vendor';
              var saveBtnElement = document.getElementById('btnSaveCustomer');
              if (saveBtnElement) saveBtnElement.textContent = 'Save';
              loadCustomerRecords();
            }, 600);
          } else {
            showError(apiResponse.error || 'Save failed');
          }
        } catch (saveError) {
          showError(saveError.message || 'Save failed');
        } finally {
          saveButton.disabled = false;
          saveButton.textContent = 'Save';
        }
      });
    }
  });

  /**
   * Fetches customer records from the API and renders the listing table.
   * Also updates stat badges and wires up row action buttons.
   */
  async function loadCustomerRecords(searchTerm) {
    searchTerm = searchTerm || '';
    try {
      var apiUrl = `../api/customers.php?search=${encodeURIComponent(searchTerm)}`;
      var response = await fetch(apiUrl, { credentials: 'include' });
      var responseData = await response.json();
      var customerList = (responseData && responseData.success) ? (responseData.data.customers || []) : [];
      var tableBody = document.getElementById('customersTbody');
      if (!tableBody) return;

      if (!customerList.length) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No records found</td></tr>';
        return;
      }

      // Cache customers for quick edit/view lookups
      window.customersCache = customerList;

      tableBody.innerHTML = customerList.map(function(customer) {
        var customerType = (customer.type || '').toString();
        var capitalizedType = customerType.charAt(0).toUpperCase() + customerType.slice(1);

        return `
          <tr>
            <td><input type="checkbox" /></td>
            <td>${escapeHtml(customer.name || '')}</td>
            <td><a href="#" class="btn-outstanding" data-id="${customer.id}" data-type="${escapeHtml(customer.type || 'customer')}">Get Outstanding</a></td>
            <td>${escapeHtml(customer.mobile || '')}</td>
            <td>${escapeHtml(capitalizedType)}</td>
            <td>${escapeHtml(customer.state || '')}</td>
            <td class="btn-group">
              <button class="btn btn-edit" data-id="${customer.id}">Edit</button>
              <button class="btn btn-view" data-id="${customer.id}">View</button>
              <button class="btn btn-delete btn-danger" data-id="${customer.id}" style="background-color: #dc3545; color: white;">Delete</button>
            </td>
          </tr>`;
      }).join('');

      // Delegate click events on the table body for all row actions
      tableBody.onclick = function(clickEvent) {
        var clickedElement = clickEvent.target;
        if (!(clickedElement instanceof HTMLElement)) return;
        var recordId = clickedElement.getAttribute('data-id');
        if (clickedElement.classList.contains('btn-edit') && recordId) {
          openEditCustomerModal(recordId);
        } else if (clickedElement.classList.contains('btn-view') && recordId) {
          openViewCustomerModal(recordId);
        } else if (clickedElement.classList.contains('btn-outstanding') && recordId) {
          clickEvent.preventDefault();
          fetchOutstandingBalance(clickedElement, recordId, clickedElement.getAttribute('data-type'));
        } else if (clickedElement.classList.contains('btn-delete') && recordId) {
          deleteCustomerRecord(recordId);
        }
      };

      // Update stat badge counts
      try {
        var totalCount    = customerList.length;
        var customerCount = customerList.filter(function(c) { return c.type === 'customer'; }).length;
        var vendorCount   = customerList.filter(function(c) { return c.type === 'vendor'; }).length;
        var bothCount     = customerList.filter(function(c) { return c.type === 'both' || (c.is_customer && c.is_vendor); }).length;

        document.getElementById('statTotal').textContent    = totalCount;
        document.getElementById('statCustomer').textContent = customerCount;
        document.getElementById('statVendor').textContent   = vendorCount;
        document.getElementById('statBoth').textContent     = bothCount;
      } catch (statError) {}

    } catch (loadError) {
      var tableBody = document.getElementById('customersTbody');
      if (tableBody) tableBody.innerHTML = `<tr><td colspan="7" class="text-danger">${escapeHtml(loadError.message || 'Failed to load')}</td></tr>`;
    }
  }

  /**
   * Fetches the outstanding balance for a specific customer/vendor and displays it inline
   */
  async function fetchOutstandingBalance(linkElement, customerId, customerType) {
    var originalLinkText = linkElement.textContent;
    linkElement.textContent = 'Loading...';
    try {
      var apiUrl = `../api/customers.php?action=get_outstanding&id=${customerId}&type=${customerType}`;
      var response = await fetch(apiUrl, { credentials: 'include' });
      var responseData = await response.json();

      if (responseData && responseData.success) {
        var outstandingAmount = parseFloat(responseData.data.outstanding || 0);
        if (outstandingAmount > 0) {
          linkElement.innerHTML = `<span class="text-danger fw-bold">₹ ${outstandingAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>`;
        } else {
          linkElement.innerHTML = `<span class="text-success">₹ 0.00</span>`;
        }
      } else {
        linkElement.textContent = 'Error';
      }
    } catch (fetchError) {
      linkElement.textContent = 'Error';
    }
  }

  /**
   * Opens the Add/Edit modal pre-filled with an existing customer's data for editing
   */
  function openEditCustomerModal(customerId) {
    var customerRecord = (window.customersCache || []).find(function(c) { return String(c.id) === String(customerId); });
    if (!customerRecord) return;

    // Helper: sets field value or checkbox state
    var setFieldValue = function(fieldId, value) {
      var element = document.getElementById(fieldId);
      if (element != null) {
        if (element.type === 'checkbox') { element.checked = !!value; }
        else { element.value = value ?? ''; }
      }
    };

    setFieldValue('c_company_name',   customerRecord.company_name || '');
    setFieldValue('c_name',           customerRecord.name || '');
    setFieldValue('c_type',           customerRecord.type || 'customer');
    setFieldValue('c_is_registered',  customerRecord.is_registered ? 1 : 0);
    setFieldValue('c_email',          customerRecord.email || '');
    setFieldValue('c_mobile',         customerRecord.mobile || '');
    setFieldValue('c_gst',            customerRecord.gst_number || '');
    setFieldValue('c_pan',            customerRecord.pan_number || '');
    setFieldValue('c_address',        customerRecord.address || '');
    setFieldValue('c_city',           customerRecord.city || '');
    setFieldValue('c_state',          customerRecord.state || '');
    setFieldValue('c_pincode',        customerRecord.pincode || '');
    setFieldValue('c_opening_balance', customerRecord.opening_balance);
    setFieldValue('c_credit_limit',   customerRecord.credit_limit);
    setFieldValue('c_credit_due_date', customerRecord.credit_due_date || '');
    setFieldValue('c_fax',            customerRecord.fax || '');
    setFieldValue('c_website',        customerRecord.website || '');
    setFieldValue('c_note',           customerRecord.note || '');
    try {
      document.getElementById('c_custom_fields').value = customerRecord.custom_fields
        ? (typeof customerRecord.custom_fields === 'string' ? customerRecord.custom_fields : JSON.stringify(customerRecord.custom_fields))
        : '';
    } catch (jsonError) {}

    window.currentCustomerEditId = customerRecord.id;
    var titleElement = document.getElementById('addCustomerLabel');
    if (titleElement) titleElement.textContent = 'Edit Customer/Vendor';
    var buttonElement = document.getElementById('btnSaveCustomer');
    if (buttonElement) buttonElement.textContent = 'Update';
    var modalElement = document.getElementById('addCustomerModal');
    if (modalElement) { (bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement)).show(); }
  }

  /**
   * Opens the read-only view modal showing a customer's details
   */
  function openViewCustomerModal(customerId) {
    var customerRecord = (window.customersCache || []).find(function(c) { return String(c.id) === String(customerId); });
    if (!customerRecord) return;

    var setDisplayText = function(elementId, value) {
      var element = document.getElementById(elementId);
      if (element) element.textContent = (value ?? '');
    };

    setDisplayText('v_company', customerRecord.company_name || '');
    setDisplayText('v_name',    customerRecord.name || '');
    setDisplayText('v_type',    (customerRecord.type || '').toString());
    setDisplayText('v_mobile',  customerRecord.mobile || '');
    setDisplayText('v_email',   customerRecord.email || '');
    setDisplayText('v_gst',     customerRecord.gst_number || '');
    setDisplayText('v_pan',     customerRecord.pan_number || '');
    setDisplayText('v_state',   customerRecord.state || '');
    setDisplayText('v_city',    customerRecord.city || '');
    setDisplayText('v_pincode', customerRecord.pincode || '');
    setDisplayText('v_address', customerRecord.address || '');

    // Append opening balance if available
    var detailsContainer = document.getElementById('viewCustomerBody');
    if (detailsContainer && customerRecord.opening_balance) {
      var balanceRow = document.createElement('div');
      balanceRow.innerHTML = `<strong>Opening Balance:</strong> ₹${customerRecord.opening_balance}`;
      detailsContainer.appendChild(balanceRow);
    }

    var viewModalElement = document.getElementById('viewCustomerModal');
    if (viewModalElement) { (bootstrap.Modal.getInstance(viewModalElement) || new bootstrap.Modal(viewModalElement)).show(); }
  }

  /**
   * Permanently deletes a customer/vendor record after user confirmation
   */
  async function deleteCustomerRecord(customerId) {
    if (!confirm('Are you sure you want to delete this customer/vendor? This action cannot be undone.')) return;
    try {
      var deleteResponse = await apiHandler.deleteCustomer(customerId);
      if (deleteResponse && deleteResponse.success) {
        showSuccess('Deleted successfully');
        loadCustomerRecords();
      } else {
        showError(deleteResponse.error || 'Delete failed');
      }
    } catch (deleteError) {
      showError(deleteError.message || 'Delete failed');
    }
  }

  /**
   * Escapes HTML special characters to prevent XSS in dynamic table rendering
   */
  function escapeHtml(rawString) {
    return (rawString || '').toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
</script>
<?php include '../includes/footer.php'; ?>
