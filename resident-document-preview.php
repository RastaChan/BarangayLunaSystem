<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resident-functions.php';
require_once __DIR__ . '/includes/staff-functions.php';
$user = barangay_current_user();
header('Cache-Control: private, no-store');
if (!$user || ($user['role'] ?? '') !== 'resident') { http_response_code(403); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
  $record = $_SESSION['resident_document_previews'][$token] ?? null;
  if (!$record || $record['user'] !== (int)$user['id'] || $record['expires'] < time()) { http_response_code(404); exit; }
  $path = staff_docx_absolute_path($record['path']);
  if (!is_file($path)) { http_response_code(404); exit; }
  session_write_close();
  header('Content-Type: application/pdf'); header('X-Content-Type-Options: nosniff');
  readfile($path); exit;
}
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['resident_preview_csrf']) || !is_string($_POST['preview_csrf'] ?? null) || !hash_equals($_SESSION['resident_preview_csrf'], $_POST['preview_csrf'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Refresh the page and retry the preview.']); exit; }
try {
  $type = trim((string)($_POST['document_type'] ?? ''));
  $purpose = trim((string)($_POST['purpose'] ?? ''));
  if (!in_array($type, resident_document_types(), true) || $purpose === '' || strlen($purpose) > 1000) throw new InvalidArgumentException('Choose a document type and enter its purpose first.');
  $query = barangay_db()->prepare("SELECT full_name, address, TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) AS age, gender, civil_status FROM users WHERE id = ? AND role = 'resident' AND status = 'Active'");
  $query->execute([$user['id']]); $resident = $query->fetch();
  if (!$resident) throw new InvalidArgumentException('Your resident account is not active. Please contact the barangay office.');
  $query = barangay_db()->prepare("SELECT template_file_path FROM document_templates WHERE LOWER(TRIM(document_type)) = LOWER(?) AND status = 'Active' AND template_file_path IS NOT NULL AND template_file_path <> '' ORDER BY updated_at DESC LIMIT 1");
  $query->execute([$type]); $template = $query->fetchColumn();
  if (!$template) throw new InvalidArgumentException('A preview is not available for this document type yet. You can still submit your request.');
  $extras = [];
  foreach (['beneficiary_name','beneficiary_relationship','partner_name','cohabitation_start_date'] as $field) {
    $extras[$field] = trim((string)($_POST[$field] ?? ''));
    if (strlen($extras[$field]) > 255) throw new InvalidArgumentException('Please shorten the request details.');
  }
  if ($extras['cohabitation_start_date'] !== '') {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $extras['cohabitation_start_date']);
    if (!$date || $date->format('Y-m-d') !== $extras['cohabitation_start_date']) throw new InvalidArgumentException('Select a valid living together date.');
  }
  $extras['residency_years'] = max(0, min(150, (int)($_POST['residency_years'] ?? 0)));
  $values = staff_document_template_values($resident, $purpose, date('Y-m-d'), 'Hon. Barangay Captain', 'PREVIEW ONLY', $extras);
  $customValues = barangay_custom_validate_values(barangay_custom_fields_for_type($type), $_POST['custom_fields'] ?? []);
  $values = array_merge($values, barangay_custom_template_values($customValues));
  $fingerprint = hash('sha256', json_encode([$template,$values]));
  foreach ($_SESSION['resident_document_previews'] ?? [] as $token=>$record) {
    if ($record['expires'] > time() && $record['user'] === (int)$user['id'] && $record['fingerprint'] === $fingerprint && is_file(staff_docx_absolute_path($record['path']))) {
      echo json_encode(['success'=>true,'url'=>'document-preview-viewer.html?file='.rawurlencode('resident-document-preview.php?token='.$token)]); exit;
    }
  }
  session_write_close();
  $error = null; $code = null;
  $preview = staff_generate_word_pdf_preview($template, $values, 'resident-draft-'.bin2hex(random_bytes(8)), $error, $code);
  if (!$preview) { echo json_encode(['success'=>false,'code'=>$code,'message'=>$error ?: 'Preview could not be prepared. Please retry.']); exit; }
  barangay_start_session();
  $currentUser = barangay_current_user();
  if (!$currentUser || (int)$currentUser['id'] !== (int)$user['id'] || ($currentUser['role'] ?? '') !== 'resident') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Please sign in again to preview your document.']); exit; }
  $token = bin2hex(random_bytes(24));
  $_SESSION['resident_document_previews'] = array_filter($_SESSION['resident_document_previews'] ?? [], static fn($record)=>$record['expires'] > time());
  if (count($_SESSION['resident_document_previews']) >= 5) array_shift($_SESSION['resident_document_previews']);
  $_SESSION['resident_document_previews'][$token] = ['user'=>(int)$user['id'],'path'=>$preview['path'],'expires'=>time()+600,'fingerprint'=>$fingerprint];
  echo json_encode(['success'=>true,'url'=>'document-preview-viewer.html?file='.rawurlencode('resident-document-preview.php?token='.$token)]);
} catch (InvalidArgumentException $error) {
  http_response_code(422); echo json_encode(['success'=>false,'message'=>$error->getMessage()]);
} catch (Throwable $error) {
  http_response_code(500); echo json_encode(['success'=>false,'message'=>'The preview could not be prepared. Please try again.']);
}

