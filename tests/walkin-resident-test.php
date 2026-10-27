<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function walkin_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
$input = ['walkin_name' => 'Test Walk-in', 'walkin_address' => 'Test Address', 'walkin_age' => '24', 'walkin_gender' => 'Female', 'walkin_civil_status' => 'Single', 'walkin_mobile' => ''];
$resident = barangay_walkin_resident($input);
foreach ([[], ['walkin_name' => 'Preview Resident'], array_replace($input, ['walkin_mobile' => 'unfinished'])] as $draft) {
  $previewResident = barangay_walkin_resident($draft, true);
  walkin_check($previewResident['id'] === null && $previewResident['mobile'] === '', 'Incomplete draft can preview without an account or SMS');
}
walkin_check(barangay_walkin_resident([], true)['age'] === '', 'Missing preview age stays blank, not zero');
walkin_check(barangay_walkin_resident(['walkin_age' => '0'], true)['age'] === 0, 'Infant age zero is preserved in preview');
walkin_check($resident['id'] === null && $resident['full_name'] === 'Test Walk-In', 'Unregistered resident has no account association');
walkin_check($resident['mobile'] === '' && $resident['gender'] === 'Female', 'Optional mobile and entered gender retained');
foreach (['walkin_name' => '', 'walkin_age' => '-1', 'walkin_gender' => 'invalid', 'walkin_mobile' => 'invalid'] as $key => $value) {
  $rejected = false;
  try { barangay_walkin_resident(array_replace($input, [$key => $value])); } catch (InvalidArgumentException $e) { $rejected = true; }
  walkin_check($rejected, 'Invalid ' . $key . ' rejected');
}
$locked = barangay_request_issue_input(['resident_code' => '__walkin__'] + $input, ['resident_code' => 'RES-TEST']);
walkin_check($locked['resident_code'] === 'RES-TEST', 'Resident request cannot be switched to an unregistered person');
$_GET = ['mode' => 'walkin'];
$residentRecords = $staffRequests = $documentTemplates = $issuedDocuments = [];
$documentTypes = ['Barangay Certification']; $documentFees = []; $canManageTemplates = false;
ob_start(); require __DIR__ . '/../includes/staff-views/issue-documents.php'; $html = ob_get_clean();
walkin_check(str_contains($html, 'name="walkin_name"') && str_contains($html, '>Prepare printable document</button>'), 'Walk-in form works with no registered residents');
walkin_check(str_contains($html, 'data-resident-summary hidden style="display:none"'), 'Profile summary starts hidden for unregistered residents');
barangay_ensure_document_fee_schema();
barangay_ensure_document_payment_schema();
$db = barangay_db(); $db->beginTransaction();
try {
  $number = 'WALKIN-TEST-' . bin2hex(random_bytes(4));
  $stmt = $db->prepare('INSERT INTO issued_documents (document_number, resident_user_id, resident_name, document_type, purpose, copies, unit_fee, fee_amount, issued_at) VALUES (?, NULL, ?, ?, ?, 2, 30, 60, NOW())');
  $stmt->execute([$number, $resident['full_name'], 'Barangay Certification', 'Test']);
  barangay_ensure_released_document_payment(['document_number' => $number, 'resident_user_id' => null, 'resident_name' => $resident['full_name'], 'document_type' => 'Barangay Certification', 'amount_due' => 60]);
  $payment = barangay_find_document_payment(null, $number);
  walkin_check($payment['resident_user_id'] === null && (float) $payment['amount_due'] === 60.0, 'Issuance and payment support unregistered residents');
} finally { $db->rollBack(); }
echo "Test records rolled back; no accounts or SMS created.\n";
