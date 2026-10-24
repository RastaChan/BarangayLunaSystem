<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents(__DIR__ . '/../includes/staff-views/resident-account-registration.php');
$start = strpos($source, '<td class="resident-registration-actions">');
if ($start === false) throw new RuntimeException('Missing resident actions');
$end = strpos($source, '</td>', $start);
$fragment = substr($source, $start, $end - $start + 5);
$resident = ['id' => 'RES-TEST', 'status' => 'Active', 'verification_status' => 'Verified', 'qr_status' => 'Active'];
ob_start();
// Render the actual action cell without loading records or a signed-in session.
eval('?>' . $fragment);
$cell = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML('<table><tr>' . $cell . '</tr></table>');
$xpath = new DOMXPath($dom);
if ($xpath->query('//select')->length !== 3 || $xpath->query('//button[@form="resident-status-RES-TEST"]')->length !== 1 || $xpath->query('//form//form')->length !== 0) throw new RuntimeException('Action form structure invalid');
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($dir . '/resident-actions.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"></head><body class="app-dashboard" style="padding:24px"><table class="data-table" style="min-width:0;width:300px;align-self:flex-start;background:white"><tbody><tr>' . $cell . '</tr></tbody></table></body></html>');
echo "PASS: three status controls, associated Save button, separate QR form; actual action-cell preview generated.\n";
