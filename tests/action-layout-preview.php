<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function barangay_attachment_links(string $type, string $code): string { return ''; }
function staff_view_url(string $view): string { return '#'; }
$_SESSION = ['document_request_csrf' => 'preview'];
$request = ['id' => 'DOC-TEST', 'status' => 'Pending'];
$resident = ['id' => 'RES-TEST', 'qr_status' => 'Active'];
$payment = ['id' => 0, 'payment_code' => 'PAY-TEST', 'status' => 'Pending', 'amount_due' => 50];
$items = [
  ['staff-views/staff-document-requests.php', 'request-action-cell', 'Document requests'],
  ['views/qr-verification.php', 'qr-action-cell', 'QR status'],
  ['staff-views/walk-in-payments.php', 'payment-action-cell', 'Walk-in payments'],
];
$body = '';
foreach ($items as [$file, $class, $title]) {
  $source = file_get_contents(__DIR__ . '/../includes/' . $file);
  $start = strpos($source, '<td class="' . $class . '">');
  if ($start === false) throw new RuntimeException('Missing action cell: ' . $class);
  $end = strpos($source, '</td>', $start);
  ob_start();
  eval('?>' . substr($source, $start, $end - $start + 5));
  $cell = ob_get_clean();
  $dom = new DOMDocument();
  @$dom->loadHTML('<table><tr>' . $cell . '</tr></table>');
  $xpath = new DOMXPath($dom);
  if ($xpath->query('//form//form')->length || !$xpath->query('//button[@type="submit"]')->length) throw new RuntimeException('Invalid action form: ' . $class);
  $body .= '<section><h2>' . $title . '</h2><table class="data-table" style="min-width:0;width:100%;background:white"><tbody><tr>' . $cell . '</tr></tbody></table></section>';
}
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($dir . '/action-layout.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"><link rel="stylesheet" href="../../css/mobile-select.css"><style>body.app-dashboard{display:block;height:auto;overflow:auto;padding:16px}.previews{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:20px}h2{font-size:16px}section{min-width:0}</style></head><body class="app-dashboard"><main class="previews">' . $body . '</main><script src="../../js/mobile-select.js"></script></body></html>');
file_put_contents($dir . '/action-layout-phone.html', '<!doctype html><html><body style="margin:0"><iframe title="Phone actions" src="action-layout.html" style="border:0;width:390px;height:820px"></iframe><iframe title="Small phone actions" src="action-layout.html" style="border:0;width:320px;height:820px"></iframe></body></html>');
echo "PASS: document, QR and payment forms retain submit controls and valid nesting; desktop/mobile fixtures generated.\n";
