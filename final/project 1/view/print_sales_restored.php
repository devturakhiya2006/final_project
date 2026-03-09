<?php
// Printable Sales Invoice
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../front/login.html');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) {
    http_response_code(400);
    echo 'Missing invoice id';
    exit;
}

$stmt = $pdo->prepare("SELECT si.*, c.name AS customer_name, c.address AS customer_address, c.gst_number AS customer_gst, c.mobile AS customer_phone
                       FROM sales_invoices si INNER JOIN customers c ON c.id = si.customer_id
                       WHERE si.id = ? AND si.user_id = ? LIMIT 1");
$stmt->execute([$invoice_id, $user_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) { http_response_code(404); echo 'Invoice not found'; exit; }

$stmt = $pdo->prepare("SELECT sii.*, p.name AS product_name, p.hsn_sac AS hsn
                       FROM sales_invoice_items sii LEFT JOIN products p ON p.id = sii.product_id
                       WHERE sii.invoice_id = ? ORDER BY sii.id ASC");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profile = ['logo_path' => null];
try {
    $ps = $pdo->prepare("SELECT logo_path FROM user_profiles WHERE user_id = ? LIMIT 1");
    $ps->execute([$user_id]);
    $row = $ps->fetch(PDO::FETCH_ASSOC);
    if ($row) $profile = $row;
} catch (Exception $e) {}
$logo = $profile['logo_path'] ?: '../images/invoicelogo.png';

$bank = [];
try {
    $bs = $pdo->prepare("SELECT * FROM user_bank_details WHERE user_id = ?");
    $bs->execute([$user_id]);
    $bank = $bs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function numToWords($n) {
    $n = (int)$n;
    if ($n == 0) return 'Zero';
    $w = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $t = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($n < 20) return $w[$n];
    if ($n < 100) return $t[intval($n/10)] . ($n%10 ? ' '.$w[$n%10] : '');
    if ($n < 1000) return $w[intval($n/100)] . ' Hundred' . ($n%100 ? ' '.numToWords($n%100) : '');
    if ($n < 100000) return numToWords(intval($n/1000)) . ' Thousand' . ($n%1000 ? ' '.numToWords($n%1000) : '');
    if ($n < 10000000) return numToWords(intval($n/100000)) . ' Lakh' . ($n%100000 ? ' '.numToWords($n%100000) : '');
    return numToWords(intval($n/10000000)) . ' Crore' . ($n%10000000 ? ' '.numToWords($n%10000000) : '');
}
function amountInWords($amount) {
    $amount = round($amount, 2);
    $rupees = floor($amount);
    $paise = round(($amount - $rupees) * 100);
    $result = 'Rupees ' . numToWords($rupees);
    if ($paise > 0) $result .= ' and ' . numToWords($paise) . ' Paise';
    return strtoupper($result . ' Only');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Invoice #<?php echo e($inv['invoice_number']); ?></title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Arial, sans-serif; font-size:10px; line-height:1.4; }
    body { background:#f0f2f5; display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; }
    .invoice-container { width: 850px; background:#fff; padding:30px; box-shadow:0 0 20px rgba(0,0,0,0.1); border-radius: 4px; }
    .header { display:flex; justify-content:space-between; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #333; }
    .company-info { flex:2; }
    .logo-container { flex:1; text-align:right; }
    .logo-img { height:75px; width:auto; object-fit:contain; }
    h1 { color:#1a1a1a; margin-bottom:5px; font-size:18px; font-weight: 700; text-transform: uppercase; }
    .company-details-text { color: #555; font-size: 10px; margin-bottom: 2px; }
    h2 { margin:15px 0 8px; color:#333; font-size:12px; border-bottom:1px solid #ddd; padding-bottom:4px; font-weight: 600; text-transform: uppercase; }
    .invoice-title { text-align:center; margin:15px 0; font-size:20px; font-weight:800; color: #000; letter-spacing: 1px; }
    .form-row { display:flex; gap:15px; margin-bottom:10px; }
    .form-group { flex:1; }
    .field-label { font-weight:bold; color:#444; font-size:9px; margin-right: 5px; }
    .field-value { color: #000; font-size: 10px; }
    .customer-details { background:#fcfcfc; padding:12px; margin-bottom:15px; border: 1px solid #eee; border-left:4px solid #333; border-radius: 3px; }
    table { width:100%; border-collapse:collapse; margin:15px 0; font-size:10px; }
    th, td { border:1px solid #eee; padding:8px; text-align:left; }
    th { background:#f8f9fa; color:#333; font-weight:bold; text-transform: uppercase; font-size: 9px; }
    tr:nth-child(even) { background-color: #fcfcfc; }
    .summary-table { width:100%; font-size:10px; }
    .summary-table td { border:none; padding:5px; }
    .summary-table tr.total-row { border-top: 2px solid #333; background: #f8f9fa; }
    .bank-details { background:#fcfcfc; padding:12px; margin:15px 0; border: 1px solid #eee; border-radius: 3px; }
    .terms { margin-top:20px; font-size:9px; color:#666; border-top: 1px solid #eee; padding-top: 10px; }
    .btn { background:#4a6cf7; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:10px; font-weight: 600; }
    .btn-print { background:#28a745; }
    .action-buttons { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .total-in-words { background:#fffde7; padding:10px; margin:15px 0; border-left:4px solid #f9a825; font-size:11px; font-weight:bold; color: #856404; }
    @media print { body{background:#fff;padding:0;} .invoice-container{box-shadow:none;padding:0;width:100%;margin:0;border:none;} .no-print{display:none;} }
  </style>
</head>
<body>
  <div class="invoice-container" id="invoice">
    <div class="header">
      <div class="company-info">
        <h1><?php echo e($_SESSION['business_name'] ?? 'Your Business Name'); ?></h1>
        <?php if(!empty($_SESSION['address'])): ?><div class="company-details-text"><?php echo e($_SESSION['address']); ?></div><?php endif; ?>
        <?php if(!empty($_SESSION['mobile'])): ?><div class="company-details-text">Phone: <?php echo e($_SESSION['mobile']); ?></div><?php endif; ?>
        <?php if(!empty($_SESSION['gst_number'])): ?><div class="company-details-text">GSTIN: <?php echo e($_SESSION['gst_number']); ?></div><?php endif; ?>
      </div>
      <div class="logo-container">
        <img class="logo-img" src="<?php echo e($logo); ?>" alt="Logo" onerror="this.style.display='none'">
      </div>
    </div>
    <div class="invoice-title">TAX INVOICE</div>

    <div class="customer-details">
      <h2>Customer Details</h2>
      <div class="form-row">
        <div class="form-group">
            <span class="field-label">M/S:</span>
            <span class="field-value"><?php echo e($inv['customer_name']); ?></span>
        </div>
        <div class="form-group">
            <span class="field-label">GSTIN:</span>
            <span class="field-value"><?php echo e($inv['customer_gst'] ?? 'N/A'); ?></span>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
            <span class="field-label">Address:</span>
            <span class="field-value"><?php echo e($inv['customer_address'] ?? 'N/A'); ?></span>
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <span class="field-label">Invoice No.:</span>
        <span class="field-value"><?php echo e($inv['invoice_number']); ?></span>
      </div>
      <div class="form-group" style="text-align: right;">
        <span class="field-label">Date:</span>
        <span class="field-value"><?php echo e($inv['invoice_date']); ?></span>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>St. No.</th><th>Name of Product / Service</th><th>HSM / SAC</th>
          <th>Qty</th><th>Rate</th><th>Taxable Value</th><th>CGST</th><th>SGST</th><th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $i=1; $taxable=0; $gst_total=0; $grand=0;
          foreach($items as $it):
            $qty=(float)$it['quantity']; $rate=(float)$it['unit_price'];
            $line_taxable=$qty*$rate;
            $cgst=$sgst=($line_taxable*((float)$it['gst_rate']/100))/2;
            $line_total=$line_taxable+$cgst+$sgst;
            $taxable+=$line_taxable; $gst_total+=($cgst+$sgst); $grand+=$line_total;
        ?>
        <tr>
          <td><?php echo $i++; ?></td>
          <td><?php echo e($it['product_name'] ?? ''); ?></td>
          <td><?php echo e($it['hsn'] ?? ''); ?></td>
          <td><?php echo number_format($qty,2); ?></td>
          <td><?php echo number_format($rate,2); ?></td>
          <td><?php echo number_format($line_taxable,2); ?></td>
          <td><?php echo number_format($cgst,2); ?></td>
          <td><?php echo number_format($sgst,2); ?></td>
          <td><?php echo number_format($line_total,2); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="total-in-words">
      Total in words: <?php echo amountInWords($grand); ?>
    </div>

    <div class="form-row">
      <div class="form-group">
        <div class="bank-details" style="display:flex; justify-content:space-between;">
          <div style="flex:1;">
            <h2>Bank Details</h2>
            <div class="form-row">
                <div class="form-group"><span class="field-label">Bank:</span> <span class="field-value"><?php echo e($bank['bank_name'] ?? 'N/A'); ?></span></div>
            </div>
            <div class="form-row">
                <div class="form-group"><span class="field-label">A/C Holder:</span> <span class="field-value"><?php echo e($bank['account_holder'] ?? 'N/A'); ?></span></div>
            </div>
            <div class="form-row">
                <div class="form-group"><span class="field-label">A/C No:</span> <span class="field-value"><?php echo e($bank['account_number'] ?? 'N/A'); ?></span></div>
                <div class="form-group"><span class="field-label">IFSC:</span> <span class="field-value"><?php echo e($bank['ifsc'] ?? 'N/A'); ?></span></div>
            </div>
            <div class="form-row">
                <div class="form-group"><span class="field-label">UPI ID:</span> <span class="field-value"><?php echo e($bank['upi_id'] ?? 'N/A'); ?></span></div>
            </div>
          </div>
          <?php if (!empty($bank['qr_code'])): ?>
          <div style="flex:0 0 100px; padding-left:10px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
            <p style="font-weight:bold; font-size:10px; margin-bottom:5px;">Scan to Pay</p>
            <img src="<?php echo e($bank['qr_code']); ?>" style="width:80px; height:80px; object-fit:contain; border:1px solid #eee;" alt="QR Code">
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <table class="summary-table">
          <tr><td>Taxable Amount</td><td style="text-align: right;">Rs. <?php echo number_format($taxable, 2); ?></td></tr>
          <tr><td>Add : CGST + SGST</td><td style="text-align: right;">Rs. <?php echo number_format($gst_total, 2); ?></td></tr>
          <tr class="total-row"><td><strong>GRAND TOTAL</strong></td><td style="text-align: right;"><strong>Rs. <?php echo number_format($grand, 2); ?></strong></td></tr>
        </table>
      </div>
    </div>

    <div class="terms">
      <h2>Terms and Conditions</h2>
      <p>Subject to our home Jurisdiction.</p>
      <p>Our Responsibility Ceases as soon as goods leaves our Premises.</p>
      <p>Goods once sold will not taken back.</p>
      <p>Delivery Ex-Premises.</p>
      <p>Certified that the particulars given above are true and correct.</p>
    </div>

    <div class="action-buttons no-print">
      <button class="btn btn-print" onclick="window.print()">Print Invoice</button>
    </div>
  </div>
</body>
</html>