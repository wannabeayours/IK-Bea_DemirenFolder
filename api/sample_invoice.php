<?php
// Sample invoice generator that computes VAT Exclusive and VAT from a given total
// Usage:
//  - GET/POST sample_invoice.php?total=4720&quantity=4&stream=0
//  - If stream=1, responds with the PDF download; otherwise returns JSON with pdf_url

require_once __DIR__ . '/headers.php';

// Load bundled Dompdf directly to avoid Composer autoload conflicts
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$params = $method === 'POST' ? $_POST : $_GET;

$totalInclusive = isset($params['total']) ? floatval($params['total']) : 4720.0;
$quantity = isset($params['quantity']) ? max(intval($params['quantity']), 1) : 4;
$stream = isset($params['stream']) ? intval($params['stream']) : 0;

// Compute VAT exclusive and VAT based on total inclusive formula
$vatExclusiveRaw = $totalInclusive / 1.12; // base amount
$vatExclusive = round($vatExclusiveRaw, 2);
$vatRaw = $vatExclusiveRaw * 0.12;
$vat = round($vatRaw, 2);

// Ensure consistency: adjust VAT to be total - exclusive if rounding drift
$vat = round($totalInclusive - $vatExclusive, 2);

// Derive unit price from VAT-exclusive subtotal and nights quantity
$unitPrice = round($vatExclusive / $quantity, 2);
$lineAmount = round($unitPrice * $quantity, 2);

// Build simple invoice HTML
$issueDate = date('m/d/Y');
$subtotalFmt = number_format($vatExclusive, 2);
$vatFmt = number_format($vat, 2);
$totalFmt = number_format($totalInclusive, 2);
$unitFmt = number_format($unitPrice, 2);
$lineFmt = number_format($lineAmount, 2);

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#2b2b2b; background:#ffffff; line-height:1.5; }
.wrapper { max-width:860px; margin:0 auto; padding:40px; }
.header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.doc-title { font-size:36px; font-weight:800; letter-spacing:1px; margin:8px 0 20px; }
.meta-line { font-size:13px; margin-bottom:16px; }
.meta-line .label { font-weight:700; margin-right:8px; }
.table { width:100%; border-collapse:collapse; margin-top:10px; }
.table thead th { background:#e5e7eb; color:#111827; padding:12px; font-size:12px; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
.table thead th:nth-child(2), .table thead th:nth-child(3), .table thead th:nth-child(4) { text-align:right; }
.table tbody td { border-bottom:1px solid #e5e7eb; padding:12px; font-size:13px; }
.table tbody td:nth-child(2), .table tbody td:nth-child(3), .table tbody td:nth-child(4){ text-align:right; }
.table tfoot td { padding:12px; font-size:13px; }
.table tfoot tr.total-row td { border-top:2px solid #d1d5db; font-weight:bold; }
</style></head><body>
<div class="wrapper">
  <div class="header"><div></div><div></div></div>
  <div class="doc-title">INVOICE (Sample)</div>
  <div class="meta-line"><span class="label">Date:</span> ' . $issueDate . '</div>
  <table class="table">
    <thead>
      <tr><th>Item</th><th>Quantity</th><th>Price</th><th>Amount</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>Room (VAT Exclusive Amount)</td>
        <td>' . intval($quantity) . '</td>
        <td>&#8369; ' . $unitFmt . '</td>
        <td>&#8369; ' . $lineFmt . '</td>
      </tr>
    </tbody>
    <tfoot>
      <tr><td colspan="3" style="text-align:right">Subtotal (VAT Exclusive)</td><td style="text-align:right">&#8369; ' . $subtotalFmt . '</td></tr>
      <tr><td colspan="3" style="text-align:right">VAT (12%)</td><td style="text-align:right">&#8369; ' . $vatFmt . '</td></tr>
      <tr class="total-row"><td colspan="3" style="text-align:right">Total</td><td style="text-align:right">&#8369; ' . $totalFmt . '</td></tr>
    </tfoot>
  </table>
</div>
</body></html>';

// Render and save PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfDir = __DIR__ . DIRECTORY_SEPARATOR . 'invoices';
if (!is_dir($pdfDir)) { @mkdir($pdfDir, 0777, true); }
$pdfName = 'sample_invoice_' . date('YmdHis') . '.pdf';
$pdfPath = $pdfDir . DIRECTORY_SEPARATOR . $pdfName;
$pdfContent = $dompdf->output();
$pdf_url = null;
if ($pdfContent !== false && strlen($pdfContent) > 0) {
    $bytes = @file_put_contents($pdfPath, $pdfContent);
    if ($bytes !== false) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $baseUrl = $scheme . '://' . $host . $scriptDir . '/';
        $pdf_url = $baseUrl . 'invoices/' . $pdfName;
    }
}

if (!$pdf_url) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to generate PDF']);
    exit;
}

if ($stream) {
    $filesize = filesize($pdfPath);
    $basename = basename($pdfPath);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $basename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    while (ob_get_level()) { ob_end_clean(); }
    readfile($pdfPath);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Sample invoice generated', 'pdf_url' => $pdf_url]);

?>