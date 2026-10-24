<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';

$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['staff', 'admin'], true)) {
  header('Location: login.php?error=login_required');
  exit;
}
$type = $_GET['type'] ?? 'service-summary';
barangay_issuance_schema();

if ($type === 'document-requests') {
  barangay_ensure_document_fee_schema();
  $rows = barangay_document_requests();
  staff_log($user, 'Exported document requests', 'Document Requests');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="document-requests-' . date('Y-m-d') . '.csv"');
  $output = fopen('php://output', 'w');
  fputcsv($output, ['Request ID', 'Resident', 'Document type', 'Copies', 'Unit fee', 'Total fee', 'Purpose', 'Status', 'Date']);
  foreach ($rows as $row) {
    $values = [$row['id'], $row['resident'], $row['type'], $row['copies'], $row['unit_fee'] ?? $row['fee_amount'], $row['fee_amount'], $row['purpose'], $row['status'], $row['date']];
    $values = array_map(static fn($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) ? "'" . $value : $value, $values);
    fputcsv($output, $values);
  }
  fclose($output);
  exit;
}

if ($type !== 'service-summary') {
  staff_redirect('service-reports', 'invalid');
}

$rows = staff_fetch_all(
  "SELECT document_type AS service,
          SUM(DATE(issued_at) = CURDATE()) AS today,
          SUM(YEAR(issued_at) = YEAR(CURDATE()) AND MONTH(issued_at) = MONTH(CURDATE())) AS month
   FROM issued_documents
   WHERE issuance_status = 'Released'
   GROUP BY document_type
   ORDER BY document_type"
);

staff_log($user, 'Exported service summary report', 'Service Reports');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="staff-service-summary-' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Service', 'Today', 'This Month', 'Status']);
foreach ($rows as $row) {
  fputcsv($output, [
    $row['service'],
    (int) $row['today'],
    (int) $row['month'],
    (int) $row['today'] > 0 ? 'On track' : 'Monitor',
  ]);
}
fclose($output);
exit;
?>
