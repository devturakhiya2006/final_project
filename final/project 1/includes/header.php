<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$business_name = $_SESSION['business_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'GoInvoice'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
     integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" 
     crossorigin="anonymous"></script>

     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" 
     integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
     
     <link rel="stylesheet" href="../front/style.css" />
     <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>" />
        <?php
    endforeach; ?>
     <?php
endif; ?>
     <!-- Global API handler (defines window.apiHandler and window.offlineHandler) -->
     <script src="../front/js/api-handler.js"></script>
</head>
<body>
    <?php if ($is_logged_in): ?>
        <!-- Logo and brand for logged-in users -->
        <nav class="navbar navbar-expand-md bg-body-light border-bottom sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="#" style="padding: 0.5rem 1rem;">
                    <img src="../images/invoicelogo.png" width="60" height="60" class="d-inline-block" alt="GoInvoice Logo" style="margin-right: 10px;">
                    <span class="h4 mb-0" style="color: #333; font-weight: 600;">GoInvoice</span>
                </a>
            </div>
        </nav>

        <!-- Dashboard Navigation -->
        <header class="top-bar">
            <div class="left-section">Welcome, <strong id="welcomeNameHeader"><?php echo htmlspecialchars($business_name ?: $user_name); ?></strong></div>
            <div class="right-section">
                <a href="dashbord.php" title="Edit Logo in Dashboard" class="header-logo-link" style="text-decoration:none;">
                    <div class="header-logo-box">
                        <img id="headerLogo" src="" alt="Business Logo" />
                    </div>
                </a>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-light create-btn dropdown-toggle" type="button" id="createMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-plus"></i> Create
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="createMenuBtn">
                        <li><a class="dropdown-item" href="dashbord.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="customer.php">Customer / Vendor</a></li>
                        <li><a class="dropdown-item" href="product.php">Products / Services</a></li>
                        <li><a class="dropdown-item" href="salesinvoice.php">Sales Invoice</a></li>
                        <li><a class="dropdown-item" href="purchase.php">Purchase Invoice</a></li>
                        <li><a class="dropdown-item" href="payment.php">Payment</a></li>
                    </ul>
                </div>
                <select class="fiscal-select">
                    <option>F.Y. 2025-2026</option>
                </select>
                <!-- <span class="icon"><i class="fa-regular fa-calendar"></i></span> -->
                <!-- <span class="icon"><i class="fa-regular fa-bell"></i></span> -->
                <!-- <span class="icon"><i class="fa-solid fa-circle-user"></i></span> -->
                <a href="pending_payments.php" class="btn btn-sm btn-light position-relative me-3" id="notificationBtn" title="Pending Payments">
                    <i class="fa-solid fa-bell"></i>
                    <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                        0
                    </span>
                </a>
                <a href="../api/auth.php?action=logout" class="btn btn-sm btn-dark" id="logoutBtn">Logout</a>
            </div>
        </header>
        <script>
        (function(){
            // Fallback to localStorage if PHP session name is not set
            var el = document.getElementById('welcomeNameHeader');
            if (el && (!el.textContent || el.textContent.trim().length === 0)) {
                try {
                    var saved = localStorage.getItem('goinvoice_user');
                    if (saved) {
                        var data = JSON.parse(saved);
                        if (data && (data.business_name || data.name)) el.textContent = data.business_name || data.name;
                    }
                } catch(_) {}
            }

            // Logout handler: call API then redirect to main.html
            var logout = document.getElementById('logoutBtn');
            if (logout) {
                logout.addEventListener('click', function(e){
                    e.preventDefault();
                    fetch('../api/auth.php?action=logout', { method: 'GET', credentials: 'include' })
                        .catch(function(){ /* ignore errors */ })
                        .finally(function(){
                            try { localStorage.removeItem('goinvoice_user'); } catch(_) {}
                            try { localStorage.removeItem('goinvoice_logo_path'); } catch(_) {}
                            window.location.href = '../front/main.html';
                        });
                });
            }

            // Load business logo into the single header tile
            ;(async function loadHeaderLogo(){
                var img = document.getElementById('headerLogo');
                if (!img) return;
                // Use a per-user cache key so one user's logo doesn't show for another user
                var uid = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
                var cacheKey = 'goinvoice_logo_path_' + uid;
                try {
                    // Clean up old global key if present
                    try { localStorage.removeItem('goinvoice_logo_path'); } catch(_){ }
                    var cached = (uid ? localStorage.getItem(cacheKey) : null);
                    if (cached && cached.length > 0) { img.src = cached; }
                } catch(_) {}
                try {
                    if (typeof window.apiHandler === 'object' && window.apiHandler && typeof window.apiHandler.getProfile === 'function') {
                        var prof = await window.apiHandler.getProfile();
                        var path = (prof && prof.success && prof.data && prof.data.logo_path) ? prof.data.logo_path : '';
                        if (path) {
                            img.src = path;
                            try { if (uid) localStorage.setItem(cacheKey, path); } catch(_) {}
                        }
                    }
                } catch(_) {}
                if (!img.getAttribute('src')) { img.src = '../images/invoicelogo.png'; }
            })();
        })();
        </script>
        
        <div class="nav-tabs">
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'dashbord.php' ? 'active' : ''; ?>">
                <a href="dashbord.php">
                <div class="nav-icon"><i class="fa-solid fa-gauge"></i></div>
                <div class="label">Dashboard</div></a>
            </div>
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'customer.php' ? 'active' : ''; ?>">
                <a href="customer.php">
                <div class="nav-icon"><i class="fa-solid fa-users"></i></div>
                <div class="label">Customer / Vendor</div></a>
            </div>
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'product.php' ? 'active' : ''; ?>">
                <a href="product.php">
                <div class="nav-icon"><i class="fa-solid fa-box-open"></i></div>
                <div class="label">Products / Services</div></a>
            </div>
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'salesinvoice.php' ? 'active' : ''; ?>">
                <a href="salesinvoice.php">
                <div class="nav-icon"><i class="fa-solid fa-receipt"></i></div>
                <div class="label">Sale<br>Invoice</div></a>
            </div>
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'purchase.php' ? 'active' : ''; ?>">
                <a href="purchase.php">
                <div class="nav-icon"><i class="fa-solid fa-cart-arrow-down"></i></div>
                <div class="label">Purchase<br>Invoice</div></a>
            </div>
            <div class="tab <?php echo basename($_SERVER['PHP_SELF']) == 'payment.php' ? 'active' : ''; ?>">
                <a href="payment.php">
                <div class="nav-icon"><i class="fa-solid fa-credit-card"></i></div>
                <div class="label">Payment</div></a>
            </div>
        </div>
    <?php
else: ?>
        <!-- Public Navigation -->
        <nav class="navbar navbar-expand-md bg-body-light border-bottom sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="../front/main.html">
                    <img src="../images/invoicelogo.png" width="100" height="100" class="d-inline-block align-text-top" alt="GoInvoice Logo">
                    <p>GoInvoice</p>
                </a>
                <button class="navbar-toggler" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#navbarNavAltMarkup" 
                >
                  <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse ms-5" id="navbarNavAltMarkup">
                  <div class="navbar-nav ms-5">
                    <a class="nav-link" href="../front/main.html">Home</a>
                    <a class="nav-link" href="../front/feactures.html">Features</a>
                    <a class="nav-link" href="../front/patner.html">Partner</a>
                    <a class="nav-link" href="../front/about.html">About</a>
                    <a class="nav-link" href="../front/contact.html">Contact Us</a>
                  </div>

                    <div class="navbar-nav ms-auto">
                        <a class="nav-link" href="../front/login.html">Login</a>
                        <a class="nav-link" href="../front/singup.html">Register</a>
                    </div>
                </div>
            </div>
        </nav>
    <?php
endif; ?>
