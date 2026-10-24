<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function barangay_current_user(): array { return ['role' => $GLOBALS['testRole']]; }
function parity_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
$staffRequests = [['id' => 'REQ-TEST', 'resident' => 'Test Resident', 'type' => 'Certificate', 'copies' => 2, 'fee_amount' => 60, 'unit_fee' => 30, 'date' => '2026-09-19', 'status' => 'Pending', 'purpose' => 'Test']];
foreach (['admin', 'staff'] as $testRole) {
  $loggedInUser = ['role' => $testRole, 'id' => 0];
  $adminDocuments = $staffRequests;
  ob_start();
  require __DIR__ . ($testRole === 'admin' ? '/../includes/views/documents.php' : '/../includes/staff-views/staff-document-requests.php');
  $html = ob_get_clean();
  $portal = $testRole === 'admin' ? 'dashboard.php' : 'staff-dashboard.php';
  $view = $testRole === 'admin' ? 'documents' : 'staff-document-requests';
  parity_check(str_contains($html, 'data-view-record') && str_contains($html, 'staff-export.php?type=document-requests'), "$testRole has View and Export");
  parity_check(str_contains($html, 'name="event" value="prepare"'), "$testRole can prepare requests");
  parity_check(str_contains($html, 'action="staff-actions.php"') && str_contains($html, 'name="action" value="document_request_event"'), "$testRole uses shared automatic workflow handler");
  parity_check(substr_count($html, 'id="viewRecordModal"') === ($testRole === 'staff' ? 1 : 0), "$testRole has no duplicate details modal");
  $requestsEmbedded = true;
  ob_start(); require __DIR__ . '/../includes/staff-views/staff-document-requests.php'; $embedded = ob_get_clean();
  unset($requestsEmbedded);
  parity_check(!str_contains($embedded, 'name="status"') && str_contains($embedded, 'name="csrf"'), "$testRole has event actions instead of a status dropdown");
  parity_check(!str_contains($embedded, '<section class="panel-card">') && str_contains($embedded, 'data-view-record'), "$testRole modal retains View without nested cards");
}
barangay_ensure_document_fee_schema();
$rows = barangay_document_requests();
parity_check(is_array($rows), 'Shared request query executes');
if ($rows) parity_check(array_key_exists('beneficiary_name', $rows[0]) && array_key_exists('copies', $rows[0]) && array_key_exists('remarks', $rows[0]), 'Both portals receive complete request details');
echo "No statuses changed or SMS sent.\n";
