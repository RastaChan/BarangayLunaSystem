<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/includes/audit-log-helpers.php';

barangay_require_role('admin');
barangay_issuance_schema();

$type = in_array($_GET['type'] ?? '', ['audit','revenue'], true) ? $_GET['type'] : 'report';
if ($type === 'revenue') {
  require_once __DIR__ . '/includes/report-metrics.php';
  try { [$from,$to] = barangay_report_dates($_GET); $revenue = barangay_revenue_metrics($from,$to); }
  catch (InvalidArgumentException $error) { http_response_code(422); exit($error->getMessage()); }
  require_once __DIR__ . '/includes/revenue-export.php';
  $statement = barangay_db()->prepare("SELECT payment_code, document_number, document_type, amount_due, amount_paid, receipt_number, received_at FROM document_payments WHERE status = 'Paid' AND received_at >= ? AND received_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY received_at");
  $statement->execute([$from,$to]);
  $revenueRows = barangay_revenue_export_rows($from, $to, $revenue, $statement->fetchAll(PDO::FETCH_NUM));
  if (($_GET['format'] ?? '') === 'xlsx') {
    $bytes = barangay_revenue_export_xlsx($revenueRows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="revenue_' . date('Y-m-d_His') . '.xlsx"');
    echo $bytes;
    exit;
  }
}
$filename = $type . '_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($type === 'revenue') {
  fwrite($out, "\xEF\xBB\xBF");
  foreach ($revenueRows as $row) fputcsv($out, array_map(static fn($v) => preg_match('/^[=+@\-\t\r\n]/', (string) $v) ? "'" . $v : $v, $row));
  exit;
}

if ($type === 'audit') {
  fputcsv($out, ['Date', 'User', 'Role', 'Action', 'Module', 'Status']);
  foreach (barangay_audit_rows(barangay_audit_filters($_GET), null) as $row) {
    fputcsv($out, [$row['date'], $row['user'], $row['role'], $row['action'], $row['module'], $row['status']]);
  }
  exit;
}

fputcsv($out, ['Metric', 'Value']);
fputcsv($out, ['Active staff', admin_count('users', "role = 'staff' AND status = 'Active'")]);
fputcsv($out, ['Registered residents', admin_count('users', "role = 'resident'")]);
fputcsv($out, ['Open complaints', admin_count('complaints', "status <> 'Resolved' AND archived_at IS NULL")]);
fputcsv($out, ['Pending documents', admin_count('document_requests', "status IN ('Pending', 'Processing') AND archived_at IS NULL")]);
fputcsv($out, ['Documents released', admin_count('issued_documents', "issuance_status = 'Released'")]);
fputcsv($out, []);
fputcsv($out, ['Released Documents']);
fputcsv($out, ['Document Number', 'Resident', 'Document Type', 'Copies', 'Total Fee', 'Release Date']);
foreach (admin_fetch_all("SELECT document_number, resident_name, document_type, copies, fee_amount, issued_at FROM issued_documents WHERE issuance_status = 'Released' ORDER BY issued_at DESC") as $row) {
  $values = [$row['document_number'], $row['resident_name'], $row['document_type'], $row['copies'], $row['fee_amount'], $row['issued_at']];
  fputcsv($out, array_map(static fn($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) ? "'" . $value : $value, $values));
}
fputcsv($out, []);
fputcsv($out, ['Document Requests']);
fputcsv($out, ['Code', 'Resident', 'Document Type', 'Status', 'Requested At']);
foreach (admin_fetch_all('SELECT request_code, resident_name, document_type, status, requested_at FROM document_requests ORDER BY requested_at DESC') as $row) {
  fputcsv($out, [$row['request_code'], $row['resident_name'], $row['document_type'], $row['status'], $row['requested_at']]);
}
exit;
?>
