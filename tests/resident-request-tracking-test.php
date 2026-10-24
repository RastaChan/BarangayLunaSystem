<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$loggedInUser = ['id' => 17];
$myDocumentRequests = [];
foreach (['Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Released', 'Rejected'] as $status) {
  $myDocumentRequests[] = ['id' => 'TEST', 'type' => 'Certificate', 'copies' => 2, 'fee_amount' => 60, 'unit_fee' => 30, 'purpose' => 'Test', 'date' => '2026-09-19', 'status' => $status, 'remarks' => $status === 'Rejected' ? 'Please provide <supporting details>' : ''];
}
$myDocumentRequests[] = ['id' => 'EMPTY-REMARKS', 'type' => 'Certificate', 'copies' => 1, 'fee_amount' => 30, 'unit_fee' => 30, 'purpose' => 'Test', 'date' => '2026-09-19', 'status' => 'Rejected', 'remarks' => ''];
// Keep this presentation test independent of the attachment database.
$view = file_get_contents(__DIR__ . '/../includes/resident-views/my-document-requests.php');
$view = str_replace("<?php require_once __DIR__ . '/../record-attachments.php'; ?>", '', $view);
$view = str_replace("<?= barangay_attachment_links('document', \$request['id']) ?>", '', $view);
ob_start(); eval('?>' . $view); $html = ob_get_clean();
foreach (['data-overview-owner="resident-requests-17"', 'data-live-overview="resident-request-tracking"', 'Staff is preparing your document.', 'Your document is ready.', 'handed over.', '&lt;supporting details&gt;'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing tracking content: ' . $expected);
}
foreach ($myDocumentRequests as $request) {
  if (!str_contains($html, '>' . $request['status'] . '</span>')) throw new RuntimeException('Missing status');
}
if (substr_count($html, 'data-request-remarks data-request-id=') !== 2) throw new RuntimeException('Only rejected requests should have View remarks');
foreach (['id="requestRejectionRemarksModal"', 'data-remarks="Please provide &lt;supporting details&gt;"', 'data-label="Status"', 'resident-request-table'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing rejection remarks UI: ' . $expected);
}
echo "PASS: resident statuses, escaped rejection remarks, rejected-only actions, mobile labels and account-scoped live refresh render.\n";
