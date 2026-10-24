<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';
require_once __DIR__ . '/includes/document-template-helpers.php';

$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['staff', 'admin'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Please sign in first.']);
  exit;
}
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Invalid request.']);
  exit;
}

foreach (glob(staff_document_preview_dir() . '/*.pdf') ?: [] as $file) {
  if (is_file($file) && filemtime($file) < time() - 86400) {
    @unlink($file);
  }
}

if (trim($_POST['request_code'] ?? '') !== '') {
  try {
    $_POST = barangay_request_issue_input($_POST, barangay_locked_document_request(trim($_POST['request_code'])));
  } catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
  }
}
$residentCode = trim($_POST['resident_code'] ?? '');
$documentType = staff_canonical_document_type(trim($_POST['document_type'] ?? ''));
$purpose = trim($_POST['purpose'] ?? '');
$documentNumber = trim($_POST['document_number'] ?? ('BL-' . date('Ymd-His')));
$dateIssued = trim($_POST['date_issued'] ?? date('Y-m-d'));
$beneficiaryName = trim($_POST['beneficiary_name'] ?? '');
$beneficiaryRelationship = trim($_POST['beneficiary_relationship'] ?? '');
$residencyYears = max(0, (int) ($_POST['residency_years'] ?? 0));
$partnerName = trim($_POST['partner_name'] ?? '');
$cohabitationStartDate = trim($_POST['cohabitation_start_date'] ?? '');

if ($residentCode === '' || $documentType === '') {
  echo json_encode(['success' => false, 'fallback' => true, 'message' => 'Missing preview details.']);
  exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIssued) || strtotime($dateIssued) === false) {
  $dateIssued = date('Y-m-d');
}

$residentStmt = barangay_db()->prepare(
  "SELECT id, user_code, full_name, COALESCE(address, '') AS address,
          TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
          COALESCE(gender, '') AS gender,
          COALESCE(civil_status, '') AS civil_status
   FROM users
   WHERE user_code = :code AND role = 'resident'
   LIMIT 1"
);
$residentStmt->execute([':code' => $residentCode]);
$resident = $residentStmt->fetch();
if (empty($_POST['request_code']) && $residentCode === '__walkin__') {
  try {
    $resident = barangay_walkin_resident($_POST, true);
  } catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'fallback' => true, 'message' => $e->getMessage()]);
    exit;
  }
}
if (!$resident) {
  echo json_encode(['success' => false, 'fallback' => true, 'message' => 'Resident was not found.']);
  exit;
}

$templateStmt = barangay_db()->prepare(
  "SELECT template_file_path
   FROM document_templates
   WHERE LOWER(TRIM(document_type)) = LOWER(:document_type)
     AND status = 'Active'
     AND template_file_path IS NOT NULL
     AND template_file_path <> ''
   ORDER BY updated_at DESC
   LIMIT 1"
);
$templateStmt->execute([':document_type' => $documentType]);
$templatePath = (string) ($templateStmt->fetchColumn() ?: '');
if ($templatePath === '') {
  echo json_encode(['success' => false, 'fallback' => true, 'message' => 'No active Word template is uploaded for this document type.']);
  exit;
}

$values = staff_document_template_values($resident, $purpose, $dateIssued, 'Hon. Barangay Captain', $documentNumber, [
  'beneficiary_name' => $beneficiaryName,
  'beneficiary_relationship' => $beneficiaryRelationship,
  'residency_years' => $residencyYears,
  'partner_name' => $partnerName,
  'cohabitation_start_date' => $cohabitationStartDate,
]);

if ($residentCode === '__walkin__' && ($resident['age'] ?? '') === '') {
  $values['{{age}}'] = '';
}
$previewError = null;
$previewCode = null;
session_write_close();
$preview = staff_generate_word_pdf_preview($templatePath, $values, $documentNumber, $previewError, $previewCode);
if (!$preview) {
  echo json_encode([
    'success' => false,
    'fallback' => true,
    'code' => $previewCode,
    'message' => $previewError ?: 'The print preview could not be prepared. Please retry.',
  ]);
  exit;
}

echo json_encode([
  'success' => true,
  'mode' => 'word',
  'url' => $preview['path'] . '?v=' . time(),
]);
?>

