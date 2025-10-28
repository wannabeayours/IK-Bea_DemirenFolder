<?php
// Simple wrapper to generate and stream an invoice PDF for a booking
// Usage:
//  - GET/POST generate-invoice.php?booking_id=123&delivery_mode=pdf|both&email_to=user@example.com&stream=1
//  - If stream=1, responds with the PDF download; otherwise returns JSON with pdf_url

require_once __DIR__ . '/headers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$params = $method === 'POST' ? $_POST : $_GET;

$booking_id = isset($params['booking_id']) ? intval($params['booking_id']) : 0;
$delivery_mode = isset($params['delivery_mode']) && in_array($params['delivery_mode'], ['pdf','both']) ? $params['delivery_mode'] : 'pdf';
$email_to = isset($params['email_to']) ? trim($params['email_to']) : '';
$stream = isset($params['stream']) ? intval($params['stream']) : 0;
$employee_id = isset($params['employee_id']) ? intval($params['employee_id']) : 0;
if ($employee_id <= 0) { $employee_id = 1; } // fallback

if ($booking_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing or invalid booking_id']);
    exit;
}

// Build payload for transactions.php createInvoice
$payload = [
    'booking_id' => $booking_id,
    'employee_id' => $employee_id,
    'payment_method_id' => 2,
    'invoice_status_id' => 1,
    'discount_id' => null,
    'vat_rate' => 12,
    'downpayment' => 0,
    'delivery_mode' => $delivery_mode,
];
if (!empty($email_to)) {
    $payload['email_to'] = $email_to;
}

// Compute base URL to call transactions.php via HTTP
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseUrl = $scheme . '://' . $host . $scriptDir . '/';
$txnUrl = $baseUrl . 'transactions.php';

// Call transactions.php using cURL
$ch = curl_init($txnUrl);
$fields = [
    'operation' => 'createInvoice',
    'json' => json_encode($payload),
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to invoke transactions.php', 'error' => $curlErr]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['success'])) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid response from transactions.php', 'raw' => $response]);
    exit;
}

if (!$data['success']) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Extract the first result and pdf_url
$info = null;
if (isset($data['results']) && is_array($data['results']) && count($data['results']) > 0) {
    $info = $data['results'][0];
}
$pdf_url = $info['pdf_url'] ?? null;
$invoice_id = isset($info['invoice_id']) ? intval($info['invoice_id']) : null;

if (!$pdf_url) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PDF was not generated', 'info' => $info, 'response' => $data]);
    exit;
}

if ($stream) {
    // Resolve local file path and stream it
    $filename = basename(parse_url($pdf_url, PHP_URL_PATH));
    $invoicesDir = realpath(__DIR__ . '/invoices');
    if ($invoicesDir === false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invoices directory not found']);
        exit;
    }
    $filePath = realpath($invoicesDir . DIRECTORY_SEPARATOR . $filename);
    if ($filePath === false || strpos($filePath, $invoicesDir) !== 0 || !is_file($filePath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File not found', 'filename' => $filename]);
        exit;
    }
    $filesize = filesize($filePath);
    $basename = basename($filePath);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $basename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    while (ob_get_level()) { ob_end_clean(); }
    readfile($filePath);
    exit;
}

// Otherwise, return JSON with the URL
header('Content-Type: application/json');
$payload = ['success' => true, 'message' => 'Invoice generated', 'invoice_id' => $invoice_id, 'pdf_url' => $pdf_url];
if (isset($info['email_status'])) { $payload['email_status'] = $info['email_status']; }
echo json_encode($payload);