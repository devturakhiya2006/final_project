<?php
/**
 * Product / Service Management Page
 * Responsibility: Lists all products and services with CRUD operations (Add, Edit, View, Delete),
 *                 search, price display, tax calculations, and inventory tracking configuration.
 */

$page_title = 'Products / Services - GoInvoice';
$additional_css = ['main.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
  header('Location: ../front/login.html');
  exit;
}
?>
<div class="section1">
  <div class="title">Product / Services</div>
  <div class="button-group">
    <input type="text" id="prodSearchInput" class="form-control d-inline-block w-auto me-2" placeholder="Search name/HSN/desc..." style="vertical-align: middle;">
    <button class="btn light" id="btnProdSearch"><span class="invoice-icon"><i class="fa-solid fa-magnifying-glass"></i></span> Search</button>
    <button class="btn green" type="button" data-bs-toggle="modal" data-bs-target="#addProductModal"><span class="invoice-icon"><i class="fa-solid fa-plus color: white"></i></span> Add New</button>
  </div>
</div>

<div class="invoice-table">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox"></th>
        <th>Name</th>
        <th>Price</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="productsTbody">
      <!-- Filled by JS -->
    </tbody>
  </table>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addProductLabel">Add Product / Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="container-fluid">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Product Code</label>
              <input type="text" class="form-control" id="p_code" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Type</label>
              <select class="form-select" id="p_type">
                <option value="product">Product</option>
                <option value="service">Service</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Name<span style="color:red">*</span></label>
              <input type="text" class="form-control" id="p_name" />
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="p_description" rows="2"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">HSN / SAC<span style="color:red">*</span></label>
              <input type="text" class="form-control" id="p_hsn" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Unit</label>
              <select class="form-select" id="p_unit">
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
            </div>
            <div class="col-md-4">
              <label class="form-label">SKU / Batch No.</label>
              <input type="text" class="form-control" id="p_sku" placeholder="SKU or Batch No." />
            </div>
            <div class="col-md-6">
              <label class="form-label">GST Rate (%)<span style="color:red">*</span></label>
              <input type="number" step="0.01" class="form-control" id="p_gst" value="18" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Stock Qty</label>
              <input type="number" step="1" class="form-control" id="p_stock" value="0" />
            </div>

            <div class="col-md-6">
              <label class="form-label">Tax Type</label>
              <select class="form-select" id="p_tax_type">
                <option value="exclusive">Exclusive</option>
                <option value="inclusive">Inclusive</option>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="p_itc">
                <label class="form-check-label" for="p_itc">Eligible for ITC</label>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Manage Stock</label>
              <select class="form-select" id="p_stock_mode">
                <option value="normal">Normal</option>
                <option value="batch">Batch No</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Available Quantity</label>
              <input type="number" step="1" class="form-control" id="p_available_qty" value="0" />
            </div>

            <div class="col-md-4">
              <label class="form-label">Sale Price</label>
              <input type="number" step="0.01" class="form-control" id="p_sale_price" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Price</label>
              <input type="number" step="0.01" class="form-control" id="p_purchase" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Price (Incl. Tax)</label>
              <input type="number" step="0.01" class="form-control" id="p_purchase_incl" readonly />
            </div>

            <div class="col-md-6">
              <label class="form-label">Sale Price (Incl. Tax)</label>
              <input type="number" step="0.01" class="form-control" id="p_sale_price_incl" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label">Product Group</label>
              <input type="text" class="form-control" id="p_group" />
            </div>

            <div class="col-md-6">
              <label class="form-label">Discount Type</label>
              <select class="form-select" id="p_discount_type">
                <option value="none">None</option>
                <option value="percent">Percent</option>
                <option value="amount">Amount</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Attachment</label>
              <input type="file" class="form-control" id="p_attachment" />
            </div>

            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="p_visible_docs" checked>
                <label class="form-check-label" for="p_visible_docs">Visible in all documents</label>
              </div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="p_track_inventory" checked>
                <label class="form-check-label" for="p_track_inventory">Track Inventory</label>
              </div>
            </div>
          </div>
          <div id="p_msg" class="mt-2" style="display:none;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnSaveProduct">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-labelledby="viewProductLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewProductLabel">Product / Service Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div><strong>Name:</strong> <span id="pv_name"></span></div>
        <div><strong>Type:</strong> <span id="pv_type"></span></div>
        <div><strong>Description:</strong> <span id="pv_desc"></span></div>
        <div><strong>HSN/SAC:</strong> <span id="pv_hsn"></span></div>
        <div><strong>Unit:</strong> <span id="pv_unit"></span></div>
        <div><strong>Price:</strong> <span id="pv_price"></span></div>
        <div><strong>GST %:</strong> <span id="pv_gst"></span></div>
        <div><strong>Stock Qty:</strong> <span id="pv_stock"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    loadProducts();

    /* ── Search Functionality ── */
    var searchButton = document.getElementById('btnProdSearch');
    var searchInput = document.getElementById('prodSearchInput');
    if (searchButton && searchInput) {
      searchButton.onclick = function() { loadProducts(searchInput.value); };
      searchInput.onkeyup = function(e) { if (e.key === 'Enter') loadProducts(searchInput.value); };
    }

    /* ── Save Button Handler ── */
    var saveBtn = document.getElementById('btnSaveProduct');
    if (saveBtn) {
      saveBtn.addEventListener('click', onSaveProduct);
    }

    /* ── Auto-calculate inclusive tax prices when inputs change ── */
    ['p_purchase','p_gst','p_tax_type'].forEach(function(fieldId){
      var el = document.getElementById(fieldId);
      if (el) el.addEventListener('input', recalcPurchaseInclTax);
      if (el) el.addEventListener('change', recalcPurchaseInclTax);
    });
    recalcPurchaseInclTax();

    ['p_sale_price','p_gst','p_tax_type'].forEach(function(fieldId){
      var el = document.getElementById(fieldId);
      if (el) el.addEventListener('input', recalcSaleInclTax);
      if (el) el.addEventListener('change', recalcSaleInclTax);
    });
    recalcSaleInclTax();
  });

  /**
   * Fetches product records from the API and renders them into the listing table
   */
  async function loadProducts(searchTerm){
    searchTerm = searchTerm || '';
    try {
      var apiUrl = `../api/products.php?search=${encodeURIComponent(searchTerm)}`;
      var response = await fetch(apiUrl, { credentials: 'include' });
      var responseData = await response.json();
      var productList = (responseData && responseData.success) ? (responseData.data.products || []) : [];
      var tableBody = document.getElementById('productsTbody');
      if (!tableBody) return;
      if (!productList.length) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No products found</td></tr>';
        return;
      }
      // Cache products for quick edit/view lookups
      window.productsCache = productList;
      tableBody.innerHTML = productList.map(function(product) {
        return `
        <tr>
          <td><input type="checkbox" /></td>
          <td>${escapeHtml(product.name || '')}</td>
          <td>${formatMoney(pickDisplayPrice(product))}</td>
          <td class="btn-group">
            <button class="btn btn-edit" data-id="${product.id}">Edit</button>
            <button class="btn btn-view" data-id="${product.id}">View</button>
            <button class="btn btn-delete btn-danger" data-id="${product.id}" style="background-color: #dc3545; color: white;">Delete</button>
          </td>
        </tr>
      `;
      }).join('');
      // Delegate click events on the table body for all row actions
      tableBody.onclick = function(clickEvent){
        var clickedElement = clickEvent.target;
        if (!(clickedElement instanceof HTMLElement)) return;
        var recordId = clickedElement.getAttribute('data-id');
        if (clickedElement.classList.contains('btn-edit') && recordId){
          openEditProduct(recordId);
        } else if (clickedElement.classList.contains('btn-view') && recordId){
          openViewProduct(recordId);
        } else if (clickedElement.classList.contains('btn-delete') && recordId){
          deleteProduct(recordId);
        }
      };
    } catch (loadError) {
      var tableBody = document.getElementById('productsTbody');
      if (tableBody) tableBody.innerHTML = `<tr><td colspan="4" class="text-danger">${escapeHtml(loadError.message || 'Failed to load')}</td></tr>`;
    }
  }

  /**
   * Collects form values and saves (creates or updates) a product/service record
   */
  async function onSaveProduct(){
    var statusMessage = document.getElementById('p_msg');
    if (statusMessage) { statusMessage.style.display = 'none'; statusMessage.className = ''; statusMessage.textContent = ''; }
    const payload = {
      type: document.getElementById('p_type')?.value || 'product',
      product_code: document.getElementById('p_code')?.value?.trim() || '',
      name: document.getElementById('p_name')?.value?.trim() || '',
      description: document.getElementById('p_description')?.value?.trim() || '',
      hsn_sac: document.getElementById('p_hsn')?.value?.trim() || '',
      unit: document.getElementById('p_unit')?.value?.trim() || 'pcs',
      sku: document.getElementById('p_sku')?.value?.trim() || '',
      gst_rate: parseFloat(document.getElementById('p_gst')?.value || '18') || 0,
      stock_quantity: parseInt(document.getElementById('p_stock')?.value || '0') || 0,
      tax_type: document.getElementById('p_tax_type')?.value || 'exclusive',
      eligible_itc: document.getElementById('p_itc')?.checked ? 1 : 0,
      stock_mode: document.getElementById('p_stock_mode')?.value || 'normal',
      available_qty: parseInt(document.getElementById('p_available_qty')?.value || '0') || 0,
      sale_price: parseFloat(document.getElementById('p_sale_price')?.value || '0') || 0,
      mrp: parseFloat(document.getElementById('p_sale_price_incl')?.value || '0') || 0,
      purchase_price: parseFloat(document.getElementById('p_purchase')?.value || '0') || 0,
      purchase_price_incl_tax: parseFloat(document.getElementById('p_purchase_incl')?.value || '0') || 0,
      product_group: document.getElementById('p_group')?.value?.trim() || '',
      discount_type: document.getElementById('p_discount_type')?.value || 'none',
      visible_all_docs: document.getElementById('p_visible_docs')?.checked ? 1 : 0,
      track_inventory: document.getElementById('p_track_inventory')?.checked ? 1 : 0,
      // Main 'price' field for backward compatibility or simple listings
      price: parseFloat(document.getElementById('p_sale_price')?.value || '0') || 0
    };
    if (!payload.name) {
      showToast('Name is required', 'error');
      return;
    }
    if (!payload.hsn_sac) {
      showToast('HSN/SAC is required', 'error');
      return;
    }
    if (isNaN(payload.gst_rate) || payload.gst_rate === null || document.getElementById('p_gst').value === '') {
      showToast('GST Rate is required', 'error');
      return;
    }
    try {
      const btn = document.getElementById('btnSaveProduct');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
      let res;
      if (window.currentProductEditId) {
        res = await apiHandler.updateProduct(window.currentProductEditId, payload);
      } else {
        res = await apiHandler.createProduct(payload);
      }
      if (res && res.success) {
        showSuccess(window.currentProductEditId ? 'Product updated successfully!' : 'Product added successfully!');
        // reset
        ['p_code','p_name','p_description','p_hsn','p_unit','p_sku','p_gst','p_stock','p_available_qty','p_sale_price','p_purchase','p_purchase_incl','p_sale_price_incl','p_group'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value='';});
        document.getElementById('p_type').value = 'product';
        setTimeout(()=>{
          const modalEl = document.getElementById('addProductModal');
          if (modalEl) {
            const m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            m.hide();
          }
          window.currentProductEditId = null;
          const titleEl = document.getElementById('addProductLabel');
          if (titleEl) titleEl.textContent = 'Add Product / Service';
          const btnEl = document.getElementById('btnSaveProduct');
          if (btnEl) btnEl.textContent = 'Save';
          loadProducts();
        }, 600);
      } else {
        showError((res && res.error) ? res.error : 'Save failed');
      }
    } catch (e) {
      showError(e.message || 'Save failed');
    } finally {
      const btn = document.getElementById('btnSaveProduct');
      if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    }
  }

  function openEditProduct(id){
    const item = (window.productsCache||[]).find(x=> String(x.id) === String(id));
    if (!item) return;
    const set = (k,v)=>{ const el=document.getElementById(k); if(el!=null){ if(el.type==='checkbox'){ el.checked = !!v; } else { el.value = v ?? ''; } } };
    set('p_code', item.product_code||'');
    set('p_type', item.type||'product');
    set('p_name', item.name||'');
    set('p_description', item.description||'');
    set('p_hsn', item.hsn_sac||'');
    set('p_unit', item.unit||'');
    set('p_sku', item.sku||'');
    set('p_gst', item.gst_rate);
    set('p_stock', item.stock_quantity);
    set('p_tax_type', item.tax_type||'exclusive');
    set('p_itc', item.eligible_itc?1:0);
    set('p_stock_mode', item.stock_mode||'normal');
    set('p_available_qty', item.available_qty);
    set('p_mrp', item.mrp); // keep for logic, but UI uses p_sale_price_incl
    set('p_purchase', item.purchase_price);
    set('p_purchase_incl', item.purchase_price_incl_tax);
    set('p_sale_price', item.sale_price);
    set('p_sale_price_incl', item.mrp);
    set('p_group', item.product_group||'');
    set('p_discount_type', item.discount_type||'none');
    set('p_visible_docs', item.visible_all_docs?1:0);
    set('p_track_inventory', item.track_inventory?1:0);
    window.currentProductEditId = item.id;
    const titleEl = document.getElementById('addProductLabel');
    if (titleEl) titleEl.textContent = 'Edit Product / Service';
    const btnEl = document.getElementById('btnSaveProduct');
    if (btnEl) btnEl.textContent = 'Update';
    recalcPurchaseInclTax();
    const modalEl = document.getElementById('addProductModal');
    if (modalEl) { (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show(); }
  }

  function openViewProduct(id){
    const item = (window.productsCache||[]).find(x=> String(x.id) === String(id));
    if (!item) return;
    const setText = (k,v)=>{ const el=document.getElementById(k); if(el) el.textContent = (v??''); };
    setText('pv_name', item.name||'');
    setText('pv_type', item.type||'');
    setText('pv_desc', item.description||'');
    setText('pv_hsn', item.hsn_sac||'');
    setText('pv_unit', item.unit||'');
    setText('pv_price', formatMoney(pickDisplayPrice(item)));
    setText('pv_gst', (item.gst_rate??'') );
    setText('pv_stock', (item.stock_quantity??''));
    const modalEl = document.getElementById('viewProductModal');
    if (modalEl) { (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show(); }
  }

  async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product/service? This action cannot be undone.')) return;
    try {
      const res = await apiHandler.deleteProduct(id);
      if (res && res.success) {
        showSuccess('Product deleted successfully');
        loadProducts(); 
      } else {
        showError(res.error || 'Failed to delete');
      }
    } catch (err) {
      showError('Error deleting product: ' + err.message);
    }
  }

  function escapeHtml(str){
    return (str||'').toString()
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  /**
   * Selects the most meaningful non-zero price to display in the listing table.
   * Priority: price → purchase_incl → purchase → sale → MRP
   */
  function pickDisplayPrice(product){
    var priceCandidates = [product.price, product.purchase_price_incl_tax, product.purchase_price, product.sale_price, product.mrp];
    for (var i = 0; i < priceCandidates.length; i++){
      var numericValue = parseFloat(priceCandidates[i]);
      if (!isNaN(numericValue) && numericValue > 0) return numericValue;
    }
    return 0;
  }

  /**
   * Formats a numeric value as a fixed two-decimal currency string
   */
  function formatMoney(amount){
    var numericAmount = parseFloat(amount || 0);
    return isNaN(numericAmount) ? '-' : numericAmount.toFixed(2);
  }

  /**
   * Recalculates "Purchase Price (Incl. Tax)" based on base price, GST rate, and tax type.
   * Exclusive: Inclusive = Purchase × (1 + GST/100)
   * Inclusive: Inclusive = Purchase (already includes tax)
   */
  function recalcPurchaseInclTax(){
    try {
      var basePurchasePrice = parseFloat(document.getElementById('p_purchase')?.value || '');
      var gstRatePercent    = parseFloat(document.getElementById('p_gst')?.value || '');
      var selectedTaxType   = document.getElementById('p_tax_type')?.value || 'exclusive';
      var inclusiveField    = document.getElementById('p_purchase_incl');
      if (!inclusiveField) return;

      var purchaseAmount = isNaN(basePurchasePrice) ? 0 : basePurchasePrice;
      var gstRate        = isNaN(gstRatePercent) ? 0 : gstRatePercent;

      var inclusiveAmount = 0;
      if (selectedTaxType === 'exclusive') {
        inclusiveAmount = purchaseAmount * (1 + (gstRate / 100));
      } else {
        inclusiveAmount = purchaseAmount;
      }

      inclusiveField.value = (!purchaseAmount && !gstRate) ? '' : inclusiveAmount.toFixed(2);
    } catch (calcError) {
      /* Non-critical calculation error */
    }
  }

  /**
   * Recalculates "Sale Price (Incl. Tax)" using the same logic as purchase inclusive
   */
  function recalcSaleInclTax(){
    try {
      var baseSalePrice   = parseFloat(document.getElementById('p_sale_price')?.value || '');
      var gstRatePercent  = parseFloat(document.getElementById('p_gst')?.value || '');
      var selectedTaxType = document.getElementById('p_tax_type')?.value || 'exclusive';
      var inclusiveField  = document.getElementById('p_sale_price_incl');
      if (!inclusiveField) return;

      var saleAmount = isNaN(baseSalePrice) ? 0 : baseSalePrice;
      var gstRate    = isNaN(gstRatePercent) ? 0 : gstRatePercent;

      var inclusiveAmount = 0;
      if (selectedTaxType === 'exclusive') {
        inclusiveAmount = saleAmount * (1 + (gstRate / 100));
      } else {
        inclusiveAmount = saleAmount;
      }

      inclusiveField.value = (!saleAmount && !gstRate) ? '' : inclusiveAmount.toFixed(2);
    } catch (calcError) { /* Non-critical calculation error */ }
  }

</script>
<?php include '../includes/footer.php'; ?>
