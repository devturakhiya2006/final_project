<?php
/**
 * User Dashboard Page
 * Responsibility: Displays the authenticated user's operational overview including
 *                 sales/purchase totals, outstanding amounts, top products/customers,
 *                 profile completion tasks (logo, email verification, bank details),
 *                 and historical snapshots.
 */

$page_title = 'Dashboard - GoInvoice';
$additional_css = ['../view/main.css', '../view/dashbord.css'];
include '../includes/header.php';

/* ── Auth Guard ── */
if (!$is_logged_in) {
    header('Location: ../front/login.html');
    exit;
}

/* ── Fetch Email Verification Status ── */
require_once '../config/database.php';
$isEmailVerified = 0;
try {
    $verificationQuery = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
    $verificationQuery->execute([$_SESSION['user_id']]);
    $verificationRow = $verificationQuery->fetch();
    $isEmailVerified = $verificationRow['email_verified'] ?? 0;
}
catch (PDOException $e) {
    // Column might be missing if the migration hasn't run yet – default to unverified
    $isEmailVerified = 0;
}
?>


<!-- ═══════════════════════════════════════════════════
     Profile Completion Card
     ═══════════════════════════════════════════════════ -->
<div class="profile-card">
  <h2>Complete your profile</h2>

  <!-- Logo Upload Section -->
  <div class="profile-section">
    <div>
      <strong>Add Your Business Logo</strong>
      <p>Print your business logo on your invoice to impress your customer with a beautiful invoice.</p>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
      <img id="logoPreview" src="" alt="Logo" style="display:none; width:64px; height:64px; object-fit:contain; border:1px solid #eee; border-radius:6px;"/>
      <button id="btnAddLogo" class="action-button" type="button">Add Logo</button>
      <input id="logoInput" type="file" accept="image/*" style="display:none" />
    </div>
  </div>

  <!-- Email Verification Section (only shown if not yet verified) -->
  <?php if (!$isEmailVerified): ?>
  <div class="profile-section" id="verifyEmailSection">
    <div>
      <strong>Verify Email!</strong>
      <p>Please check your email and follow the link to verify your email address.</p>
    </div>
    <button class="action-button" id="btnSendVerification">Send Email</button>
  </div>
  <?php
endif; ?>

  <!-- Bank & UPI Details Section -->
  <div class="profile-section">
    <div>
      <strong>Add Your Bank & UPI Details</strong>
      <p>Get a faster payment with a UPI QR code. These UPI & Bank details will be printed on your invoice.</p>
    </div>
    <button class="action-button">Add Bank</button>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     Top Bar: Last Updated / Date Range / Refresh
     ═══════════════════════════════════════════════════ -->
<div class="top-bar-action">
  <span class="last-updated">Last Updated <span id="lastUpdated">14 minute ago</span></span>

  <div class="controls">
    <button class="date-button">
      <span>02-02-2025 To 02-08-2025</span>
      <i class="fa-solid fa-calendar-days"></i>
    </button>
    <button class="refresh-button" onclick="refreshDashboard()">
      Refresh <i class="fa-solid fa-arrows-rotate"></i>
    </button>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     Dashboard Stat Cards (Sales / Purchase / Income vs Expense)
     ═══════════════════════════════════════════════════ -->
<div class="dashboard-container11" id="dashboardStats">
  <!-- Populated dynamically by displayDashboardStats() -->
</div>

<!-- ═══════════════════════════════════════════════════
     Outstanding Receivable / Payable Summary
     ═══════════════════════════════════════════════════ -->
<div class="outstanding-container" id="outstandingStats">
  <!-- Populated dynamically by displayOutstandingAmounts() -->
</div>

<!-- ═══════════════════════════════════════════════════
     Promotional Banner
     ═══════════════════════════════════════════════════ -->
<div class="banner">
  <div class="banner-left">
    <h2>Confused where to start?<br>Let's take a quick tour</h2>
    <button class="demo-btn">
      <i class="fa-solid fa-circle-play me-1"></i>Watch Demo Video
    </button>
  </div>
  <div class="banner-right">
    <img src="../images/headerlogo.png" alt="Dashboard preview" class="device-img" />
    <div class="call-info">
      <p><i class="fa-solid fa-phone-volume me-2"></i>or Call Us For Demo</p>
      <p class="number">+91 704-314-6478</p>
      <p class="timing">(10 AM To 7 PM / Everyday)</p>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     Analytics: Top Products, Customers, Invoice Dues
     ═══════════════════════════════════════════════════ -->
<div class="dashboard" id="dashboardAnalytics">
  <!-- Populated dynamically by displayAnalytics() -->
</div>

<!-- ═══════════════════════════════════════════════════
     Bank Details Modal
     ═══════════════════════════════════════════════════ -->
<div class="modal fade" id="bankDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bank & UPI Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="bankDetailsForm">
            <div class="mb-3">
                <label class="form-label">Bank Name</label>
                <input type="text" class="form-control" name="bank_name" id="bankName" placeholder="e.g. HDFC Bank">
            </div>
            <div class="mb-3">
                <label class="form-label">Account Number</label>
                <input type="text" class="form-control" name="account_number" id="accountNumber" placeholder="Search account number">
            </div>
             <div class="mb-3">
                <label class="form-label">IFSC Code</label>
                <input type="text" class="form-control" name="ifsc" id="ifscCode" placeholder="IFSC Code">
            </div>
            <div class="mb-3">
                <label class="form-label">Account Holder Name</label>
                <input type="text" class="form-control" name="account_holder" id="accountHolder" placeholder="Holder Name">
            </div>
            <div class="mb-3">
                <label class="form-label">UPI ID</label>
                <input type="text" class="form-control" name="upi_id" id="upiId" placeholder="user@upi">
            </div>
            <div class="mb-3">
                <label class="form-label">UPI QR Code</label>
                <input type="file" class="form-control" name="qr_code" id="qrInput" accept="image/*">
                <img id="qrPreview" src="" alt="QR Preview" style="display:none; max-width: 100px; margin-top: 10px; border: 1px solid #ddd; padding: 2px;">
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Save Details</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
/**
 * ═══════════════════════════════════════════
 * Bank Details Modal – Initialization & Save
 * ═══════════════════════════════════════════
 */
function initBankDetailsSection() {
    // Locate the "Add Bank" button by its text content
    var addBankButton = null;
    var allActionButtons = document.querySelectorAll('button.action-button');
    for (var i = 0; i < allActionButtons.length; i++) {
        if (allActionButtons[i].textContent.trim() === 'Add Bank') {
            addBankButton = allActionButtons[i];
            break;
        }
    }

    // Fallback: locate by profile section index
    if (!addBankButton) {
        var profileSections = document.querySelectorAll('.profile-section');
        if (profileSections.length >= 3) {
            addBankButton = profileSections[2].querySelector('button');
        }
    }

    var bankModalElement = document.getElementById('bankDetailsModal');
    if (!bankModalElement) return;

    var bankModal = new bootstrap.Modal(bankModalElement);

    // Pre-fill bank details from the user profile when opening the modal
    if (addBankButton) {
        addBankButton.addEventListener('click', async function(event) {
            event.preventDefault();
            try {
                var profileResponse = await window.apiHandler.getProfile();
                if (profileResponse.success && profileResponse.data) {
                    var profileData = profileResponse.data;
                    document.getElementById('bankName').value       = profileData.bank_name || '';
                    document.getElementById('accountNumber').value   = profileData.account_number || '';
                    document.getElementById('ifscCode').value        = profileData.ifsc || '';
                    document.getElementById('accountHolder').value   = profileData.account_holder || '';
                    document.getElementById('upiId').value           = profileData.upi_id || '';

                    var qrPreviewImage = document.getElementById('qrPreview');
                    if (profileData.qr_code) {
                        qrPreviewImage.src = profileData.qr_code;
                        qrPreviewImage.style.display = 'block';
                    } else {
                        qrPreviewImage.style.display = 'none';
                    }
                }
            } catch (prefillError) { console.error(prefillError); }
            bankModal.show();
        });
    }

    // QR Code Preview immediate update
    var qrInput = document.getElementById('qrInput');
    var qrPreviewImage = document.getElementById('qrPreview');
    if (qrInput && qrPreviewImage) {
        qrInput.addEventListener('change', function(event) {
            var file = event.target.files[0];
            if (file) {
                qrPreviewImage.src = URL.createObjectURL(file);
                qrPreviewImage.style.display = 'block';
            }
        });
    }

    // Handle bank details form submission
    var bankForm = document.getElementById('bankDetailsForm');
    if (bankForm) {
        bankForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            var submitButton = bankForm.querySelector('button[type="submit"]');
            var originalButtonText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            try {
                var formPayload = new FormData(bankForm);
                var saveResponse = await fetch('../api/profile.php?action=update_bank', {
                    method: 'POST',
                    body: formPayload
                });
                var saveResult = await saveResponse.json();

                if (saveResult.success) {
                    showSuccess('Bank details saved successfully!');
                    // Update profileData reference or reset form if needed
                    if (saveResult.data && saveResult.data.qr_code) {
                        qrPreviewImage.src = saveResult.data.qr_code;
                        qrPreviewImage.style.display = 'block';
                    }
                    bankModal.hide();
                } else {
                    showError(saveResult.error || 'Failed to save bank details');
                }
            } catch (saveError) {
                showError('Error: ' + saveError.message);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        });
    }d
}

/**
 * ═══════════════════════════════════════════
 * Dashboard Data Loading & Rendering
 * ═══════════════════════════════════════════
 */
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    initLogoUploadSection();
    initBankDetailsSection();
});

/**
 * Fetches all dashboard data from the API and dispatches rendering to sub-functions
 */
async function loadDashboardData() {
    try {
        showLoading(document.getElementById('dashboardStats'));

        var dashboardResponse = await makeAPICall('../api/dashboard.php');

        if (dashboardResponse.success) {
            displayDashboardStats(dashboardResponse.data.stats);
            displayOutstandingAmounts(dashboardResponse.data.outstanding_amounts);
            displayAnalytics(dashboardResponse.data);

            loadMonthlySnapshots();
            autoSaveCurrentMonthSnapshot(dashboardResponse.data);
        } else {
            showError('Failed to load dashboard data');
        }
    } catch (loadError) {
        showError('Error loading dashboard: ' + loadError.message);
    }
}

/**
 * Loads and renders recent monthly snapshots from the API
 */
async function loadMonthlySnapshots() {
    try {
        var snapshotResponse = await makeAPICall('../api/dashboard.php?action=get_snapshots&period_type=month');
        if (snapshotResponse.success && snapshotResponse.data) {
            displaySnapshots(snapshotResponse.data);
        }
    } catch (snapshotError) {
        console.warn('Snapshots load failed:', snapshotError);
    }
}

/**
 * Renders up to 3 recent monthly snapshot cards below the analytics section
 */
function displaySnapshots(snapshotRecords) {
    var snapshotContainer = document.getElementById('snapshotSection');
    if (!snapshotContainer) {
        // Create snapshot container immediately after analytics
        var analyticsSection = document.getElementById('dashboardAnalytics');
        snapshotContainer = document.createElement('div');
        snapshotContainer.id = 'snapshotSection';
        snapshotContainer.className = 'dashboard mt-4';
        analyticsSection.parentNode.insertBefore(snapshotContainer, analyticsSection.nextSibling);
    }

    if (snapshotRecords.length === 0) {
        snapshotContainer.innerHTML = '<h3>Recent Snapshots</h3><p>No snapshots found.</p>';
        return;
    }

    var snapshotHtml = '<h3>Recent Snapshots</h3><div class="row">';
    snapshotRecords.slice(0, 3).forEach(function(snapshot) {
        snapshotHtml += `
            <div class="box">
                <h5>${snapshot.period_key}</h5>
                <p>Sales: ₹ ${parseFloat(snapshot.sales_total).toLocaleString()}</p>
                <p>Purchases: ₹ ${parseFloat(snapshot.purchases_total).toLocaleString()}</p>
            </div>
        `;
    });
    snapshotHtml += '</div>';
    snapshotContainer.innerHTML = snapshotHtml;
}

/**
 * Auto-saves a monthly snapshot once per calendar month to avoid duplicate writes
 */
async function autoSaveCurrentMonthSnapshot(dashboardData) {
    try {
        var currentDate = new Date();
        var currentMonthKey = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;

        // Prevent duplicate saves within the same month
        var lastSavedMonth = localStorage.getItem('last_snapshot_saved');
        if (lastSavedMonth === currentMonthKey) return;

        var snapshotPayload = {
            period_type: 'month',
            period_key: currentMonthKey,
            stats: dashboardData.stats,
            customers: dashboardData.stats.customers,
            products: dashboardData.stats.products,
            top_customers: dashboardData.top_customers,
            top_products: dashboardData.top_products
        };

        await makeAPICall('../api/dashboard.php?action=save_snapshot', 'POST', snapshotPayload);
        localStorage.setItem('last_snapshot_saved', currentMonthKey);
    } catch (snapshotError) {
        console.warn('Auto-save snapshot failed:', snapshotError);
    }
}

/**
 * Renders the three main stat cards: Sales, Purchases, and Income vs Expense
 */
function displayDashboardStats(stats) {
    var statsContainer = document.getElementById('dashboardStats');
    var currentMonthLabel = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    statsContainer.innerHTML = `
        <div class="card">
            <p class="title"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Sale - ${currentMonthLabel}</p>
            <p class="amount">₹ ${stats.sales.total.toLocaleString()}</p>
            <p class="gst">+GST ${stats.sales.gst.toLocaleString()}</p>
            <div class="line"></div>
        </div>

        <div class="card">
            <p class="title"><i class="fa-solid fa-cart-shopping me-2 text-success"></i>Purchase - ${currentMonthLabel}</p>
            <p class="amount">₹ ${stats.purchases.total.toLocaleString()}</p>
            <p class="gst">+GST ${stats.purchases.gst.toLocaleString()}</p>
            <div class="line"></div>
        </div>

        <div class="card">
            <p class="title"><i class="fa-solid fa-money-bill-trend-up me-2 text-warning"></i>Expense - ₹ ${stats.purchases.total.toLocaleString()}</p>
            <p class="title"><i class="fa-solid fa-chart-line me-2 text-info"></i>Income - ₹ ${stats.sales.total.toLocaleString()}</p>
        </div>
    `;
}

/**
 * Renders the outstanding receivable/payable breakdown (Paid / Partial / Pending)
 */
function displayOutstandingAmounts(outstanding) {
    var outstandingContainer = document.getElementById('outstandingStats');
    outstandingContainer.innerHTML = `
        <div class="outstanding-card">
            <h5><i class="fa-solid fa-hand-holding-dollar me-2 text-primary"></i>Sales Outstanding</h5>
            <p class="subtext">Total Receivables ₹ ${(outstanding.sales.pending + outstanding.sales.partial).toLocaleString()}</p>
            <div class="progress-bar"></div>

            <div class="row">
                <div class="col">
                    <p><span class="dot green"></span> ₹ ${outstanding.sales.paid.toLocaleString()}</p>
                    <p class="label">PAID</p>
                </div>
                <div class="col">
                    <p><span class="dot yellow"></span> ₹ ${outstanding.sales.partial.toLocaleString()}</p>
                    <p class="label">PARTIAL</p>
                </div>
                <div class="col">
                    <p><span class="dot orange"></span> ₹ ${outstanding.sales.pending.toLocaleString()}</p>
                    <p class="label">PENDING</p>
                </div>
            </div>
        </div>

        <div class="outstanding-card">
            <h5><i class="fa-solid fa-file-invoice me-2 text-danger"></i>Purchase Outstanding</h5>
            <p class="subtext">Total Payables ₹ ${(outstanding.purchases.pending + outstanding.purchases.partial).toLocaleString()}</p>
            <div class="progress-bar"></div>

            <div class="row">
                <div class="col">
                    <p><span class="dot green"></span> ₹ ${outstanding.purchases.paid.toLocaleString()}</p>
                    <p class="label">PAID</p>
                </div>
                <div class="col">
                    <p><span class="dot yellow"></span> ₹ ${outstanding.purchases.partial.toLocaleString()}</p>
                    <p class="label">PARTIAL</p>
                </div>
                <div class="col">
                    <p><span class="dot orange"></span> ₹ ${outstanding.purchases.pending.toLocaleString()}</p>
                    <p class="label">PENDING</p>
                </div>
            </div>
        </div>
    `;
}

/**
 * Renders the analytics section: top products, customers, vendors, and invoice dues
 * Dynamically populates each card with real data from the API response.
 */
function displayAnalytics(dashboardData) {
    var analyticsContainer = document.getElementById('dashboardAnalytics');

    /* ── Helper: Build a simple list from an array of items ── */
    function buildItemList(items, labelKey, valueKey, valuePrefix) {
        if (!items || items.length === 0) return '<span class="text-muted">No records found</span>';
        var listHtml = '<ul style="list-style:none; padding:0; margin:8px 0 0 0;">';
        items.forEach(function(item) {
            var label = item[labelKey] || 'Unknown';
            var value = valueKey ? (valuePrefix || '') + parseFloat(item[valueKey] || 0).toLocaleString() : '';
            listHtml += '<li style="padding:4px 0; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between;">';
            listHtml += '<span>' + label + '</span>';
            if (value) listHtml += '<strong>' + value + '</strong>';
            listHtml += '</li>';
        });
        listHtml += '</ul>';
        return listHtml;
    }

    /* ── Helper: Build invoice due list with date + status badge ── */
    function buildInvoiceDueList(invoices, nameKey) {
        if (!invoices || invoices.length === 0) return '<span class="text-muted">No records found</span>';
        var listHtml = '<ul style="list-style:none; padding:0; margin:8px 0 0 0;">';
        invoices.forEach(function(inv) {
            var customerName = inv[nameKey] || 'Unknown';
            var statusBadge = inv.payment_status === 'pending'
                ? '<span style="background:#fff3cd; color:#856404; padding:2px 8px; border-radius:12px; font-size:0.75rem;">Pending</span>'
                : '<span style="background:#d1ecf1; color:#0c5460; padding:2px 8px; border-radius:12px; font-size:0.75rem;">Partial</span>';
            listHtml += '<li style="padding:6px 0; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center;">';
            listHtml += '<div><strong>' + inv.invoice_number + '</strong> &mdash; ' + customerName + '<br><small class="text-muted">' + inv.invoice_date + '</small></div>';
            listHtml += '<div style="text-align:right;">₹ ' + parseFloat(inv.total_amount).toLocaleString() + '<br>' + statusBadge + '</div>';
            listHtml += '</li>';
        });
        listHtml += '</ul>';
        return listHtml;
    }

    /* ── Build product list with quantity sold ── */
    function buildProductList(products, qtyKey) {
        if (!products || products.length === 0) return '<span class="text-muted">No records found</span>';
        var listHtml = '<ul style="list-style:none; padding:0; margin:8px 0 0 0;">';
        products.forEach(function(prod) {
            listHtml += '<li style="padding:4px 0; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between;">';
            listHtml += '<span>' + (prod.name || 'Unknown') + '</span>';
            listHtml += '<strong>' + parseInt(prod[qtyKey] || 0) + ' sold</strong>';
            listHtml += '</li>';
        });
        listHtml += '</ul>';
        return listHtml;
    }

    /* ── Build low stock list with stock quantity ── */
    function buildLowStockList(products) {
        if (!products || products.length === 0) return '<span class="text-muted">No records found</span>';
        var listHtml = '<ul style="list-style:none; padding:0; margin:8px 0 0 0;">';
        products.forEach(function(prod) {
            var stockColor = parseInt(prod.stock_quantity) <= 3 ? '#dc3545' : '#ffc107';
            listHtml += '<li style="padding:4px 0; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between;">';
            listHtml += '<span>' + (prod.name || 'Unknown') + '</span>';
            listHtml += '<strong style="color:' + stockColor + ';">' + prod.stock_quantity + ' left</strong>';
            listHtml += '</li>';
        });
        listHtml += '</ul>';
        return listHtml;
    }

    /* ── Render the complete analytics grid ── */
    analyticsContainer.innerHTML = `
        <div class="row">
            <div class="box">
                <h5><i class="fa-solid fa-star text-warning me-2"></i>Best Selling Products</h5>
                ${buildProductList((dashboardData.top_products || []).slice(0, 1), 'total_quantity')}
            </div>
            <div class="box">
                <h5><i class="fa-solid fa-arrow-down-short-wide text-muted me-2"></i>Least Selling Products</h5>
                ${buildProductList(dashboardData.least_selling_products, 'total_quantity')}
            </div>
            <div class="box">
                <h5><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Low Stock</h5>
                ${buildLowStockList(dashboardData.low_stock_products)}
            </div>
        </div>

        <div class="row">
            <div class="box wide">
                <h5><i class="fa-solid fa-users text-primary me-2"></i>Top Customers</h5>
                ${buildItemList((dashboardData.top_customers || []).slice(0, 1), 'name', 'total_amount', '₹ ')}
            </div>
            <div class="box wide">
                <h5><i class="fa-solid fa-truck-field text-success me-2"></i>Top Vendors</h5>
                ${buildItemList(dashboardData.top_vendors, 'name', 'total_amount', '₹ ')}
            </div>
        </div>

        <div class="row">
            <div class="box full">
                <h5><i class="fa-solid fa-clock text-warning me-2"></i>Sales Invoice Due</h5>
                ${buildInvoiceDueList(dashboardData.sales_invoices_due, 'customer_name')}
            </div>
        </div>

        <div class="row">
            <div class="box full">
                <h5><i class="fa-solid fa-clock-rotate-left text-danger me-2"></i>Purchase Invoice Due</h5>
                ${buildInvoiceDueList(dashboardData.purchase_invoices_due, 'vendor_name')}
            </div>
        </div>
    `;
}

/**
 * ═══════════════════════════════════════════
 * Logo Upload Section – Preview & Upload
 * ═══════════════════════════════════════════
 */
async function initLogoUploadSection() {
    // Load existing logo from user profile (if any)
    try {
        if (typeof window.apiHandler === 'object' && window.apiHandler && typeof window.apiHandler.getProfile === 'function') {
            var profileResponse = await window.apiHandler.getProfile();
            var existingLogoPath = (profileResponse && profileResponse.success && profileResponse.data && profileResponse.data.logo_path) ? profileResponse.data.logo_path : '';
            var logoPreviewImage = document.getElementById('logoPreview');
            var addLogoButton = document.getElementById('btnAddLogo');
            if (logoPreviewImage && existingLogoPath) {
                logoPreviewImage.src = existingLogoPath;
                logoPreviewImage.style.display = 'inline-block';
                if (addLogoButton) addLogoButton.style.display = 'none';
            }
        }
    } catch (profileLoadError) { /* Silently fail – logo preview is non-critical */ }

    // Wire up click-to-upload flow
    var addLogoBtn = document.getElementById('btnAddLogo');
    var logoFileInput = document.getElementById('logoInput');
    var logoPreview = document.getElementById('logoPreview');

    if (addLogoBtn && logoFileInput) {
        addLogoBtn.addEventListener('click', function() { logoFileInput.click(); });

        logoFileInput.addEventListener('change', async function(changeEvent) {
            var selectedFile = changeEvent.target.files && changeEvent.target.files[0];
            if (!selectedFile) return;

            try {
                addLogoBtn.disabled = true;
                addLogoBtn.textContent = 'Uploading...';

                var uploadResponse = await apiHandler.uploadLogo(selectedFile);
                if (uploadResponse && uploadResponse.success && uploadResponse.data && uploadResponse.data.logo_path) {
                    var uploadedLogoPath = uploadResponse.data.logo_path;

                    // Show preview and persist the logo path per-user
                    if (logoPreview) { logoPreview.src = uploadedLogoPath; logoPreview.style.display = 'inline-block'; }
                    try {
                        var currentUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
                        if (currentUserId) localStorage.setItem('goinvoice_logo_path_' + currentUserId, uploadedLogoPath);
                        try { localStorage.removeItem('goinvoice_logo_path'); } catch (cleanupError) {}
                    } catch (storageError) {}

                    // Reflect in header logo (if present)
                    var headerLogoImage = document.getElementById('headerLogo');
                    if (headerLogoImage) headerLogoImage.src = uploadedLogoPath;

                    addLogoBtn.style.display = 'none';
                } else {
                    alert((uploadResponse && uploadResponse.error) ? uploadResponse.error : 'Upload failed');
                }
            } catch (uploadError) {
                alert(uploadError.message || 'Upload failed');
            } finally {
                addLogoBtn.disabled = false;
                addLogoBtn.textContent = 'Add Logo';
                logoFileInput.value = '';
            }
        });
    }
}

/**
 * Refreshes all dashboard data and resets the "Last Updated" timestamp
 */
function refreshDashboard() {
    loadDashboardData();
    document.getElementById('lastUpdated').textContent = 'just now';
}

/* ── Auto-update "Last Updated" timestamp every 60 seconds ── */
setInterval(function() {
    var lastUpdatedLabel = document.getElementById('lastUpdated');
    if (lastUpdatedLabel) {
        var currentTime = new Date();
        lastUpdatedLabel.textContent = currentTime.toLocaleTimeString();
    }
}, 60000);

/**
 * ═══════════════════════════════════════════
 * Email Verification – Send Verification Link
 * ═══════════════════════════════════════════
 */
document.getElementById('btnSendVerification')?.addEventListener('click', async function() {
    var verifyButton = this;
    var originalLabel = verifyButton.textContent;
    verifyButton.disabled = true;
    verifyButton.textContent = 'Sending...';

    try {
        var verificationResponse = await fetch('../api/auth.php?action=send_verification', {
            credentials: 'include'
        });
        var verificationResult = await verificationResponse.json();

        if (verificationResult.success) {
            showSuccess('Verification link sent! (Check PHP error log for link in local dev)');
            verifyButton.textContent = 'Sent ✅';
            setTimeout(function() {
                verifyButton.textContent = originalLabel;
                verifyButton.disabled = false;
            }, 5000);
        } else {
            showError(verificationResult.error || 'Failed to send verification email');
            verifyButton.disabled = false;
            verifyButton.textContent = originalLabel;
        }
    } catch (verificationError) {
        showError('Error: ' + verificationError.message);
        verifyButton.disabled = false;
        verifyButton.textContent = originalLabel;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
