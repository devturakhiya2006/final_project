    <?php if (!$is_logged_in): ?>
        <!-- Public Footer -->
        <footer class="footer">
          <div class="footer-container">
            <div class="footer-column">
              <h2>Get in touch</h2>
              <p><strong>Sales & Support</strong></p>
              <p class="whatsapp"><img src="https://img.icons8.com/color/48/000000/whatsapp--v1.png" alt="WhatsApp Icon" width="20"> 012–345–6789</p>
              <p>(10 AM To 7 PM - Everyday)</p>
              <p><b>Email</b></p>
              <p>help@Goinvoice.com</p>
              <p><b>Follow us</b></p>
              <div class="social-icons">
                <a href="#"><img src="https://img.icons8.com/color/48/facebook-new.png" alt="Facebook" /></a>
                <a href="#"><img src="https://img.icons8.com/color/48/instagram-new--v1.png" alt="Instagram" /></a>
                <a href="#"><img src="https://img.icons8.com/color/48/linkedin.png" alt="LinkedIn" /></a>
                <a href="#"><img src="https://img.icons8.com/color/48/twitterx.png" alt="Twitter" /></a>
                <a href="#"><img src="https://img.icons8.com/color/48/youtube-play.png" alt="YouTube" /></a>
              </div>
            </div>

            <div class="footer-column">
              <h3>Document Format</h3>
              <ul>
                <li>GST Invoice</li>
                <li>Delivery Challan</li>
                <li>Quotation</li>
                <li>Purchase Order</li>
                <li>Proforma Invoice</li>
                <li>Credit & Debit Note</li>
                <li>Export Invoice</li>
              </ul>
            </div>

            <div class="footer-column">
              <h3>Resources</h3>
              <ul>
                <li>About Us</li>
                <li>Blog & News</li>
                <li>Knowledge Base</li>
                <li>Feature Request</li>
                <li>Testimonials</li>
                <li>Partner Program</li>
                <li>Industries We Serve</li>
                <li>Data Migration</li>
              </ul>
            </div>

            <div class="footer-column">
              <h3>Features</h3>
              <ul>
                <li>E-Way Bill</li>
                <li>E-Invoice</li>
                <li>Accounting Software</li>
                <li>Comparison</li>
                <li>Smart Search & Filters </li>
                <li>Product/Service Catalog </li>
                <li>Sales & Purchase Reports </li>
              </ul>
            </div>
          </div>
        </footer>
    <?php
endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert toast configuration
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Global JavaScript functions for API calls
        function makeAPICall(url, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                }
            };
            
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            return fetch(url, options)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    return data;
                });
        }
        
        // Show loading spinner
        function showLoading(element) {
            if (element) {
                element.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
            }
        }
        
        // Hide loading spinner
        function hideLoading(element, originalText) {
            if (element) {
                element.innerHTML = originalText;
            }
        }
    </script>
    
<!-- Global Notification Container -->
<div id="notification-container" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px;"></div>

<script>
  /**
   * Global notification helpers using Bootstrap alerts
   */
  function showNotification(message, type = 'success', duration = 3000) {
    const container = document.getElementById('notification-container');
    if (!container) return;

    const alertId = 'alert-' + Date.now();
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle');
    
    const alertHtml = `
      <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show shadow-sm border-0 mb-2" role="alert" style="border-radius: 8px;">
        <div class="d-flex align-items-center">
          <i class="fa-solid ${icon} me-2" style="font-size: 1.2rem;"></i>
          <div>${message}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `;

    container.insertAdjacentHTML('beforeend', alertHtml);

    // Auto-remove after duration
    if (duration > 0) {
      setTimeout(() => {
        const alertEl = document.getElementById(alertId);
        if (alertEl) {
          const bsAlert = new bootstrap.Alert(alertEl);
          bsAlert.close();
        }
      }, duration);
    }
  }

  function showSuccess(message) {
    showNotification(message, 'success', 3000);
  }

  function showError(message) {
    showNotification(message, 'danger', 5000); // Errors stay longer
  }

  function showToast(message, icon = 'info') {
    // For simplicity, toast is also an alert
    const type = icon === 'error' ? 'danger' : (icon === 'success' ? 'success' : 'info');
    showNotification(message, type, 2500);
  }

</script>
    
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php
  endforeach; ?>
    <?php
endif; ?>
</body>
</html>
