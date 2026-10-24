<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function copies_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
copies_check(barangay_document_total(30, 3) === 90.0, 'Fee per copy multiplied by copies');
copies_check(barangay_document_total(12.35, 3) === 37.05, 'Decimal totals use integer cents');
copies_check(barangay_document_total(0, 100) === 0.0, 'Free documents remain free');
foreach ([0, -1, 101, '1.5', '', [], '1e1'] as $bad) {
  $rejected = false;
  try { barangay_document_copies($bad); } catch (InvalidArgumentException $e) { $rejected = true; }
  copies_check($rejected, 'Invalid copy count rejected');
}
$rejected = false;
try { barangay_document_total(99999999.99, 2); } catch (InvalidArgumentException $e) { $rejected = true; }
copies_check($rejected, 'Overflow rejected');
$request = ['resident_code' => 'R-TEST', 'document_type' => 'Certificate of Indigency', 'purpose' => 'School', 'copies' => 3, 'beneficiary_name' => 'Test Beneficiary'];
$posted = array_fill_keys(['resident_code', 'document_type', 'purpose', 'copies', 'beneficiary_name', 'partner_name'], 'tampered');
$posted['date_issued'] = '2026-09-19';
$locked = barangay_request_issue_input($posted, $request);
foreach ($request as $key => $value) copies_check($locked[$key] === $value, 'Request locks ' . $key);
copies_check($locked['partner_name'] === '', 'Missing optional fields cannot be injected');
copies_check($locked['date_issued'] === '2026-09-19', 'Office issue date remains editable');
barangay_ensure_document_fee_schema();
barangay_ensure_document_payment_schema();
$db = barangay_db();
foreach (['document_requests', 'issued_documents'] as $table) {
  $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
  copies_check(in_array('copies', $columns) && in_array('unit_fee', $columns), $table . ' supports copies and unit fee');
}
$db->beginTransaction();
try {
  $resident = $db->query("SELECT id FROM users WHERE role = 'resident' LIMIT 1")->fetchColumn();
  if (!$resident) throw new RuntimeException('A resident is required for the rollback test.');
  $code = 'COPY-TEST-' . bin2hex(random_bytes(4));
  $stmt = $db->prepare("INSERT INTO document_requests (request_code, resident_user_id, resident_name, document_type, purpose, copies, unit_fee, fee_amount, status, requested_at) VALUES (?, ?, 'Copy test', 'Barangay Certification', 'Test', 3, 30, 90, 'Pending', NOW())");
  $stmt->execute([$code, $resident]);
  $saved = barangay_locked_document_request($code);
  copies_check((int) $saved['copies'] === 3 && (float) $saved['fee_amount'] === 90.0 && (float) $saved['unit_fee'] === 30.0, 'Request persists copy count, unit price and total');
  barangay_create_payment_for_released_request($code, 0);
  $payment = barangay_find_document_payment($code, null);
  copies_check((float) $payment['amount_due'] === 90.0, 'Payment amount is the total for all copies');
  copies_check($db->inTransaction(), 'Payment schema checks do not commit test records');
  $db->prepare("UPDATE document_requests SET status = 'Released' WHERE request_code = ?")->execute([$code]);
  $rejected = false;
  try { barangay_locked_document_request($code); } catch (InvalidArgumentException $e) { $rejected = true; }
  copies_check($rejected, 'Released requests cannot be issued again');
} finally { $db->rollBack(); }
echo "Test requests rolled back. No SMS sent.\n";
$residentRecords = [['id' => 'R-TEST', 'name' => 'Test Resident', 'address' => 'Test Address', 'age' => 20, 'gender' => 'Male', 'civil_status' => 'Single']];
$documentTypes = ['Certificate of Indigency'];
$documentFees = ['certificate of indigency' => 30];
$documentTemplates = $issuedDocuments = [];
$canManageTemplates = true;
$staffRequests = [array_merge($request, ['id' => 'REQ-TEST', 'type' => $request['document_type'], 'resident' => 'Test Resident', 'status' => 'Pending', 'fee_amount' => 90, 'unit_fee' => 30, 'date' => '2026-09-19'])];
$_GET = ['request' => 'REQ-TEST'];
ob_start();
require __DIR__ . '/../includes/staff-views/issue-documents.php';
$html = ob_get_clean();
$doc = new DOMDocument();
libxml_use_internal_errors(true); $doc->loadHTML($html); libxml_clear_errors();
$xpath = new DOMXPath($doc);
copies_check($xpath->query('//input[@name="resident_code" and @type="hidden"]')->length === 1 && $xpath->query('//select[@name="resident_code"]')->length === 0, 'Resident selection is locked in the submitted request');
copies_check($xpath->query('//select[@name="document_type" and @disabled]')->length === 1, 'Document type is locked');
copies_check($xpath->query('//input[@name="purpose" and @readonly]')->length === 1, 'Purpose is read-only');
copies_check($xpath->query('//input[@name="copies" and @readonly and @value="3"]')->length === 1, 'Submitted copy count is read-only');
copies_check(str_contains($html, 'Resident Requests') && str_contains($html, 'Walk-in Documents'), 'Both workflows are available');
copies_check(!str_contains($html, 'Resident request (read-only)') && str_contains($html, 'request-confirmation'), 'Large read-only form replaced by a compact confirmation panel');
copies_check($xpath->query('//form[@id="issueDocumentForm" and @hidden]')->length === 1, 'Request submission fields are retained without showing input boxes');
copies_check($xpath->query('//button[@type="submit" and @form="issueDocumentForm"]')->length === 1, 'Visible print button submits the locked request form');
copies_check(str_contains($html, 'request-confirmation-copies">3</dd>') && str_contains($html, 'PHP 90.00'), 'Confirmation panel shows submitted copies and total');
