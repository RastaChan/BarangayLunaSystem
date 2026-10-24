<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function workflow_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
barangay_ensure_document_request_detail_columns();
$db = barangay_db(); $db->beginTransaction();
try {
  $code = 'FLOW-' . bin2hex(random_bytes(6));
  $stmt = $db->prepare("INSERT INTO document_requests (request_code, resident_name, document_type, status, requested_at) VALUES (?, 'Workflow test', 'Barangay Certification', 'Pending', NOW())");
  $stmt->execute([$code]);
  $read = function () use ($db, $code) { $s = $db->prepare('SELECT status FROM document_requests WHERE request_code = ?'); $s->execute([$code]); return $s->fetchColumn(); };
  foreach (['pickup', 'ready'] as $event) {
    $rejected = false;
    try { barangay_document_request_event($code, $event); } catch (InvalidArgumentException $e) { $rejected = true; }
    workflow_check($rejected && $read() === 'Pending', 'Cannot skip preparation: ' . $event);
  }
  workflow_check(barangay_document_request_event($code, 'prepare') && $read() === 'Processing', 'Prepare advances Pending to Processing');
  workflow_check(!barangay_document_request_event($code, 'prepare'), 'Repeat prepare is a no-op');
  workflow_check($read() === 'Processing', 'No readiness event leaves request Processing');
  workflow_check(barangay_document_request_event($code, 'ready') && $read() === 'Ready for Pickup', 'Generated document advances to Ready for Pickup');
  workflow_check(!barangay_document_request_event($code, 'ready'), 'Repeat readiness is a no-op');
  workflow_check(barangay_document_request_event($code, 'pickup') && $read() === 'Released', 'Confirmed pickup advances to Released');
  workflow_check(!barangay_document_request_event($code, 'pickup'), 'Repeat pickup does not trigger another release');
  $rejected = false;
  try { barangay_document_request_event($code, 'prepare'); } catch (InvalidArgumentException $e) { $rejected = true; }
  workflow_check($rejected && $read() === 'Released', 'Released requests cannot go backwards');
  $other = $code . '-R'; $stmt->execute([$other]);
  $rejected = false;
  try { barangay_document_request_event($other, 'reject'); } catch (InvalidArgumentException $e) { $rejected = true; }
  workflow_check($rejected, 'Rejection requires a reason');
  workflow_check(barangay_document_request_event($other, 'reject', 'Incomplete supporting details'), 'Explicit rejection is supported');
} finally { $db->rollBack(); }
echo "All test requests rolled back. No SMS or documents generated.\n";
