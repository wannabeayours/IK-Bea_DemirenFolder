<?php
// Download endpoint for invoice PDFs with proper headers and CORS
// Usage: GET download_invoice.php?file=<filename.pdf>
// Optional: GET download_invoice.php?ping=1 (returns simple OK JSON)

// Include CORS headers
require_once __DIR__ . '/headers.php';

// If ping is requested, return a small JSON to allow preflight checks
if (isset($_GET['ping'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Validate and sanitize filename
$filename = isset($_GET['file']) ? $_GET['file'] : '';
if (!$filename || !preg_match('/^[A-Za-z0-9_.\-]+\.pdf$/', $filename)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid file parameter']);
    exit;
}

$invoicesDir = realpath(__DIR__ . '/invoices');
if ($invoicesDir === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invoices directory not found']);
    exit;
}

$filePath = realpath($invoicesDir . DIRECTORY_SEPARATOR . $filename);
// Ensure the resolved path is inside the invoices directory
if ($filePath === false || strpos($filePath, $invoicesDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Stream the PDF with download headers
$filesize = filesize($filePath);
$basename = basename($filePath);

// Override content type for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $basename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clean output buffers and read file
while (ob_get_level()) { ob_end_clean(); }
readfile($filePath);
exit;