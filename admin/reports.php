<?php
/**
 * Admin Financial Reports
 * Responsibility: Aggregates business metrics (revenue, expenses, taxes, profit)
 *                 and generates a quarterly tax summary for the current fiscal year.
 */

require_once 'includes/auth_session.php';
require_once 'includes/db.php';

/* ── Initialize Report Variables ── */
$totalRevenue = 0;
$totalExpense = 0;
$taxCollectionTotal = 0;
$netProfit = 0;
$quarterlyTaxSummary = [];

try {
  // 1. Total Revenue (sum of all sales invoices)
  $revenueQuery = $pdo->query("SELECT SUM(total_amount) as total FROM sales_invoices");
  $totalRevenue = $revenueQuery->fetch()['total'] ?? 0;

  // 2. Total Expense (sum of all purchase invoices)
  $expenseQuery = $pdo->query("SELECT SUM(total_amount) as total FROM purchase_invoices");
  $totalExpense = $expenseQuery->fetch()['total'] ?? 0;

  // 3. Tax Collection (sum of GST from sales invoices)
  $taxQuery = $pdo->query("SELECT SUM(gst_amount) as total FROM sales_invoices");
  $taxCollectionTotal = $taxQuery->fetch()['total'] ?? 0;

  // 4. Net Profit = Revenue − Expense
  $netProfit = $totalRevenue - $totalExpense;

  // 5. Quarterly Tax Summary for the current fiscal year
  $currentYear = date('Y');

  $quarterDefinitions = [
    1 => ['start' => "$currentYear-01-01", 'end' => "$currentYear-03-31", 'label' => 'Q1 (Jan - Mar)'],
    2 => ['start' => "$currentYear-04-01", 'end' => "$currentYear-06-30", 'label' => 'Q2 (Apr - Jun)'],
    3 => ['start' => "$currentYear-07-01", 'end' => "$currentYear-09-30", 'label' => 'Q3 (Jul - Sep)'],
    4 => ['start' => "$currentYear-10-01", 'end' => "$currentYear-12-31", 'label' => 'Q4 (Oct - Dec)'],
  ];

  foreach ($quarterDefinitions as $quarterNumber => $quarterRange) {
    $quarterTaxQuery = $pdo->prepare("
            SELECT 
                SUM(subtotal) as taxable, 
                SUM(gst_amount) as gst, 
                SUM(total_amount) as total 
            FROM sales_invoices 
            WHERE invoice_date BETWEEN ? AND ?
        ");
    $quarterTaxQuery->execute([$quarterRange['start'], $quarterRange['end']]);
    $quarterResult = $quarterTaxQuery->fetch();

    // Determine filing status based on today's date relative to the quarter
    $todayDate = date('Y-m-d');
    if ($todayDate > $quarterRange['end']) {
      $filingStatus = 'Filed';
      $filingStatusClass = 'filed';
    }
    elseif ($todayDate >= $quarterRange['start'] && $todayDate <= $quarterRange['end']) {
      $filingStatus = 'Pending';
      $filingStatusClass = 'pending';
    }
    else {
      $filingStatus = 'Upcoming';
      $filingStatusClass = 'upcoming';
    }

    $quarterlyTaxSummary[$quarterNumber] = [
      'label' => $quarterRange['label'],
      'taxable' => $quarterResult['taxable'] ?? 0,
      'gst' => $quarterResult['gst'] ?? 0,
      'total' => $quarterResult['total'] ?? 0,
      'status' => $filingStatus,
      'status_class' => $filingStatusClass
    ];
  }

}
catch (PDOException $dbException) {
  error_log("Admin Reports Error: " . $dbException->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Go Invoice - Reports</title>
  <link rel="stylesheet" href="reports.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    .analytics-summary {
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-left: 4px solid #11b181;
      border-radius: 4px;
      font-size: 0.9rem;
      color: #333;
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <span class="logo-dot"></span>
        <span class="logo-text">Go Invoice</span>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-item" href="index.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <a class="nav-item" href="invoices.php"><i class="fa-solid fa-file-invoice"></i><span>Invoices</span></a>
        <a class="nav-item" href="customers.php"><i class="fa-solid fa-users"></i><span>Customers</span></a>
        <a class="nav-item" href="products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Products</span></a>
        <a class="nav-item active" href="reports.php"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a class="nav-item" href="support.php"><i class="fa-solid fa-headset"></i><span>Support</span></a>
        <a class="nav-item" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      </nav>
    </aside>

    <main class="main">
      <div id="report-content" style="padding: 20px;">
      <header class="topbar">
        <h1 class="page-title">Reports</h1>
        <div class="topbar-right">
          <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
              <div class="user-name"><?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?></div>
              <div class="user-role">Administrator</div>
            </div>
          </div>
          <a href="logout.php"><button class="btn small logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
        </div>
      </header>

      <section class="cards">
        <div class="card">
          <div class="card-label">Total Revenue</div>
          <div class="card-value">₹<?php echo number_format($totalRevenue, 2); ?></div>
          <div class="card-change positive">-- <span>from last month</span></div>
        </div>
        <div class="card">
          <div class="card-label">Total Expense</div>
          <div class="card-value">₹<?php echo number_format($totalExpense, 2); ?></div>
          <div class="card-change positive">-- <span>from last month</span></div>
        </div>
        <div class="card">
          <div class="card-label">Tax Collection</div>
          <div class="card-value">₹<?php echo number_format($taxCollectionTotal, 2); ?></div>
          <div class="card-change negative">-- <span>from last month</span></div>
        </div>
        <div class="card">
          <div class="card-label">Net Profit</div>
          <div class="card-value">₹<?php echo number_format($netProfit, 2); ?></div>
          <div class="card-change positive">-- <span>from last month</span></div>
        </div>
      </section>

      <section class="panel tax-summary-card">
        <div class="tax-summary-header">
          <div class="tax-summary-title">Tax Summary (<?php echo $currentYear; ?>)</div>
          <button class="btn icon" onclick="exportToPDF()">
            <span class="icon-download"></span>
            Export PDF
          </button>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Time Period</th>
                <th>Taxable Income</th>
                <th>GST/VAT(18%)</th>
                <th>Total Collection</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quarterlyTaxSummary as $q): ?>
              <tr>
                <td><?php echo $q['label']; ?></td>
                <td>₹<?php echo number_format($q['taxable'], 2); ?></td>
                <td>₹<?php echo number_format($q['gst'], 2); ?></td>
                <td><a class="link" href="#">₹<?php echo number_format($q['total'], 2); ?></a></td>
                <td><span class="status <?php echo $q['status_class']; ?>"><?php echo $q['status']; ?></span></td>
              </tr>
              <?php
endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Charts Section (Placeholder for now) -->
      <div class="reports-grid">
        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Financial Analytics</h2>
            <button class="dropdown-btn">Overall <span class="caret"></span></button>
          </div>
          <div class="sales-content" style="padding: 20px; display: flex; flex-direction: column; align-items: center;">
            <div style="width: 250px; height: 250px;">
                <canvas id="analyticsChart"></canvas>
            </div>
            
            <div class="analytics-summary">
                <strong>Analytics Summary:</strong><br>
                Based on the current data, your total revenue is <strong>₹<?php echo number_format($totalRevenue, 2); ?></strong>. 
                After deducting total expenses of <strong>₹<?php echo number_format($totalExpense, 2); ?></strong>, 
                your net profit stands at <strong>₹<?php echo number_format($netProfit, 2); ?></strong>. 
                Taxes collected amount to <strong>₹<?php echo number_format($taxCollectionTotal, 2); ?></strong>.
                This chart visualizes the distribution of your revenue into profit, expenses, and taxes.
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Expense Summary</h2>
            <a class="link" href="#">Details</a>
          </div>
          <div class="donut-wrapper" style="display:flex; justify-content:center; align-items:center; padding: 20px;">
             <!-- Simple CSS Donut or Placeholder -->
             <div style="text-align:center;">
                <div style="font-size:24px; font-weight:bold;">₹<?php echo number_format($totalExpense, 0); ?></div>
                <div style="color:#666;">Total Expenses</div>
             </div>
          </div>
        </section>
      </div> <!-- End reports-grid -->
      </div> <!-- End report-content -->
    </main>
  </div>

  <script>
    // Export PDF Functionality (Business Style)
    function exportToPDF() {
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;

        // Create a hidden container for the PDF content
        const pdfContainer = document.createElement('div');
        pdfContainer.id = 'pdfExportWrapper';
        pdfContainer.style.padding = '40px';
        pdfContainer.style.fontFamily = "'Poppins', sans-serif";
        pdfContainer.style.color = '#333';
        pdfContainer.style.backgroundColor = '#fff';
        pdfContainer.style.width = '1000px';

        // 1. Header Section
        const date = new Date().toLocaleDateString();
        const headerHTML = `
            <div style="border-bottom: 2px solid #11b181; padding-bottom: 15px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="color: #1a1e23; margin: 0; font-size: 28px; font-weight: 700;">Financial Analytics Report</h1>
                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">Fiscal Year: <?php echo $currentYear; ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0; font-weight: 600; font-size: 20px; color: #11b181;">Go Invoice</p>
                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">Generated on: ${date}</p>
                </div>
            </div>
        `;

        // 2. Metrics Section
        const metricsHTML = `
            <div style="display: flex; gap: 20px; margin-bottom: 30px;">
                <div style="flex: 1; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; background: #f8fafc;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Revenue</div>
                    <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 5px;">₹<?php echo number_format($totalRevenue, 2); ?></div>
                </div>
                <div style="flex: 1; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; background: #f8fafc;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Expense</div>
                    <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 5px;">₹<?php echo number_format($totalExpense, 2); ?></div>
                </div>
                <div style="flex: 1; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; background: #f8fafc;">
                    <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Tax Collection</div>
                    <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 5px;">₹<?php echo number_format($taxCollectionTotal, 2); ?></div>
                </div>
                <div style="flex: 1; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; background: #f0fdf4;">
                    <div style="font-size: 12px; color: #15803d; font-weight: 600; text-transform: uppercase;">Net Profit</div>
                    <div style="font-size: 22px; font-weight: 700; color: #166534; margin-top: 5px;">₹<?php echo number_format($netProfit, 2); ?></div>
                </div>
            </div>
        `;

        // 3. Tax Table Section
        let tableRows = '';
        <?php foreach ($quarterlyTaxSummary as $q): ?>
        tableRows += `
            <tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; color: #1e293b;"><?php echo $q['label']; ?></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; color: #1e293b;">₹<?php echo number_format($q['taxable'], 2); ?></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; color: #1e293b;">₹<?php echo number_format($q['gst'], 2); ?></td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-weight: 600;">₹<?php echo number_format($q['total'], 2); ?></td>
            </tr>
        `;
        <?php endforeach; ?>

        const tableHTML = `
            <div style="margin-bottom: 40px;">
                <h2 style="font-size: 18px; color: #0f172a; margin-bottom: 15px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;">Quarterly Tax Summary</h2>
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #f1f5f9;">
                            <th style="padding: 12px 15px; font-weight: 600; color: #475569;">Time Period</th>
                            <th style="padding: 12px 15px; font-weight: 600; color: #475569;">Taxable Income</th>
                            <th style="padding: 12px 15px; font-weight: 600; color: #475569;">GST/VAT (18%)</th>
                            <th style="padding: 12px 15px; font-weight: 600; color: #475569;">Total Collection</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </div>
        `;

        // 4. Analytics Summary Text
        const summaryTextHTML = `
            <div style="margin-bottom: 30px;">
                <h2 style="font-size: 18px; color: #0f172a; margin-bottom: 10px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;">Executive Summary</h2>
                <p style="font-size: 14px; color: #475569; line-height: 1.6; text-align: justify; margin: 0;">
                    Based on the current fiscal data, the total generated revenue is <strong>₹<?php echo number_format($totalRevenue, 2); ?></strong>.
                    After deducting operational and overall expenses amounting to <strong>₹<?php echo number_format($totalExpense, 2); ?></strong>,
                    the net business profit stands at <strong>₹<?php echo number_format($netProfit, 2); ?></strong>. 
                    Furthermore, estimated tax collections applicable for the referenced periods total <strong>₹<?php echo number_format($taxCollectionTotal, 2); ?></strong>.
                </p>
            </div>
        `;

        // 5. Chart
        const originalCanvas = document.getElementById('analyticsChart');
        const chartImageOutput = originalCanvas ? `<div style="text-align: center; margin-top: 30px;"><img src="\${originalCanvas.toDataURL('image/png')}" style="max-width: 350px; height: auto;" /></div>` : '';

        // Combine all parts
        const fullHTML = `
            <div style="padding: 40px; font-family: 'Poppins', sans-serif; color: #333; background-color: #fff; width: 1000px;">
                ${headerHTML}
                ${metricsHTML}
                ${tableHTML}
                ${summaryTextHTML}
                ${chartImageOutput}
            </div>
        `;

        const opt = {
            margin:       0.5,
            filename:     'Business_Report_<?php echo $currentYear; ?>.pdf',
            image:        { type: 'jpeg', quality: 1 },
            html2canvas:  { scale: 2, useCORS: true, windowWidth: 1000 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        // Generate PDF directly from HTML string
        html2pdf().set(opt).from(fullHTML).save().then(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(err => {
            console.error("PDF generation error:", err);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    // Chart.js Configuration
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('analyticsChart').getContext('2d');
        const analyticsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Net Profit', 'Total Expenses', 'Tax Collected'],
                datasets: [{
                    data: [
                        <?php echo $netProfit; ?>, 
                        <?php echo $totalExpense; ?>, 
                        <?php echo $taxCollectionTotal; ?>
                    ],
                    backgroundColor: [
                        '#11b181', // Green for profit
                        '#f56565', // Red for expenses
                        '#ed8936'  // Orange for tax
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(context.parsed);
                                }
                                return label;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
  </script>
</body>
</html>
