<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$loggedInUser = barangay_current_user();
if (!$loggedInUser || !in_array($loggedInUser['role'] ?? '', ['admin','staff'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false]); exit;
}
$_SESSION['document_request_csrf'] ??= bin2hex(random_bytes(32));
session_write_close();
try {
  barangay_issuance_schema();
  $staffRequests = barangay_document_requests();
  $requestsEmbedded = true;
  ob_start(); require __DIR__ . '/includes/staff-views/staff-document-requests.php'; $html = ob_get_clean();
  echo json_encode(['ok'=>true, 'owner'=>$loggedInUser['role'] . ':' . (int)$loggedInUser['id'], 'html'=>$html,
    'choices'=>array_values(array_map(static fn($request)=>['id'=>$request['id'], 'label'=>$request['id'] . ' - ' . $request['resident'] . ' - ' . $request['type']],
      array_filter($staffRequests, static fn($request)=>in_array($request['status'], ['Pending','Processing','Approved'], true))))]);
} catch (Throwable $error) {
  if (ob_get_level()) ob_end_clean();
  error_log('Document request feed failed: ' . $error->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false]);
}
