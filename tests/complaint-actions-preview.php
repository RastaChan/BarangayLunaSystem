<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$c = $complaint = ['id' => 'CMP-TEST', 'resident' => 'Sample resident', 'status' => 'Resolved', 'remarks' => 'Follow-up recorded.'];
$cells = '';
foreach (['admin', 'staff'] as $role) {
  $complaintActionRecord = $complaint;
  $complaintUpdateUrl = $role . '-actions.php';
  ob_start();
  require __DIR__ . '/../includes/complaint-actions.php';
  $cell = '<td class="complaint-record-actions">' . ob_get_clean() . '</td>';
  $dom = new DOMDocument();
  @$dom->loadHTML('<table><tr>' . $cell . '</tr></table>');
  $xpath = new DOMXPath($dom);
  if ($xpath->query('//button[@data-post-toggle]')->length !== 1 || $xpath->query('//div[@data-post-actions and @hidden]//button[@data-view-record]')->length !== 1) throw new RuntimeException('Complaint action menu is not wired');
  $cells .= '<tr>' . $cell . '</tr>';
}
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($dir . '/complaint-actions.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"></head><body class="app-dashboard" style="padding:24px"><table class="data-table" style="min-width:0;width:320px;align-self:flex-start;background:white"><tbody>' . $cells . '</tbody></table></body></html>');
echo "PASS: Admin and Staff have compact three-dot complaint action menus.\n";
