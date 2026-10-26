<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function check_document_settings(bool $condition, string $label): void {
  if (!$condition) throw new RuntimeException($label);
  echo 'PASS: ' . $label . PHP_EOL;
}
$_GET = [];
ob_start();
require __DIR__ . '/../includes/views/template-placeholders.php';
require __DIR__ . '/../includes/views/document-fees.php';
$html = ob_get_clean();
check_document_settings(str_contains($html, '<h2>Template Placeholders</h2>') && str_contains($html, '<h2>Document Fees</h2>'), 'Document settings render as separate pages');
check_document_settings(substr_count($html, '<code>{{') === 38, 'All 38 placeholder tags retained');
check_document_settings(str_contains($html, 'name="redirect" value="document-fees"'), 'Fee save returns to Document Fees');
check_document_settings(!str_contains($html, 'value="issue-documents"'), 'No old fee redirect remains');
foreach (['system-settings', 'template-placeholders', 'document-fees'] as $activeView) {
  ob_start(); require __DIR__ . '/../includes/sidebar.php'; $sidebar = ob_get_clean();
  check_document_settings(str_contains($sidebar, '<details class="settings-nav-group" open>'), 'Settings sidebar dropdown stays expanded on ' . $activeView);
  check_document_settings(str_contains($sidebar, 'data-nav-view="' . $activeView . '" title=') && str_contains($sidebar, 'aria-current="page"'), 'Active settings page is indicated');
}
$general = file_get_contents(__DIR__ . '/../includes/views/system-settings.php');
check_document_settings(!str_contains($general, 'document-settings.php') && !str_contains($general, '<details'), 'General Settings no longer embeds document dropdown sections');
$doc = new DOMDocument();
libxml_use_internal_errors(true); $doc->loadHTML($html); libxml_clear_errors();
$xpath = new DOMXPath($doc);
check_document_settings($xpath->query('//form[@data-document-fee-form]//input[@name="fee_amount"]')->length === 1, 'One editable fee amount for the selected group');
check_document_settings($xpath->query('//form[@data-document-fee-form]//input[@name="document_types[]"]')->length === count(staff_document_types()), 'Every document type is selectable in the dropdown');
check_document_settings($xpath->query('//form[@data-document-fee-form]//button[@type="submit"]')->length === 1, 'One Save fees button');
$issue = file_get_contents(__DIR__ . '/../includes/staff-views/issue-documents.php');
check_document_settings(!str_contains($issue, 'id="template-history"') && !str_contains($issue, 'id="document-fees"') && !str_contains($issue, '<h3>Template placeholders</h3>'), 'Old Issue Documents sections removed');
check_document_settings(!str_contains($issue, 'Issued document records') && str_contains($issue, 'templateManagementModal'), 'Issued records removed from issuance; Template Management retained');
check_document_settings(str_contains(file_get_contents(__DIR__ . '/../includes/staff-views/service-reports.php'), "'/issued-document-records.php'"), 'Issued records are available in Service Reports');
check_document_settings(str_contains($issue, 'name="after_issue" value="print"') && str_contains($issue, '/issuance-review-controls.php') && str_contains(file_get_contents(__DIR__ . '/../includes/issuance-review-controls.php'), 'data-issuance-generate>Prepare printable document'), 'Issuance form submits the preparation workflow');
check_document_settings(substr_count($issue, 'data-scroll-document-preview>Preview document</button>') === 1, 'Only the top preview button remains');
check_document_settings(str_contains($issue, 'Save / replace template') && str_contains($issue, 'data-template-current-year=') && str_contains($issue, 'name="action" value="save_template"'), 'Template management replaces the current-year template through its saved form');
$db = barangay_db();
barangay_ensure_document_fee_schema();
$db->beginTransaction();
try {
  $actor = $db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
  $available = staff_document_types();
  $before = barangay_document_fee_map();
  $selected = barangay_validate_document_fee_batch(array_slice($available, 0, 2), $available, '123.45');
  foreach ($selected as $type) barangay_save_document_fee($type, 123.45, (int) $actor);
  foreach ($selected as $type) check_document_settings(barangay_document_fee_for_type($type) === 123.45, 'Selected fee is visible through the shared lookup');
  foreach (array_diff($available, $selected) as $type) check_document_settings(barangay_document_fee_for_type($type) === ($before[barangay_document_fee_key($type)] ?? null), 'Unselected fee unchanged');
  foreach ([['', [$available[0]]], ['-1', [$available[0]]], ['1.234', [$available[0]]], ['100000000', [$available[0]]], ['1e2', [$available[0]]], ['10', []], ['10', ['Unknown test document']]] as [$amount, $types]) {
    $rejected = false;
    try { barangay_validate_document_fee_batch($types, $available, $amount); } catch (InvalidArgumentException $error) { $rejected = true; }
    check_document_settings($rejected, 'Invalid fee or selection rejected');
  }
  check_document_settings(count(barangay_validate_document_fee_batch([$available[0]], $available, '0')) === 1, 'Zero fee is allowed');
} finally { $db->rollBack(); }
echo 'Fee test rolled back; existing fees unchanged.' . PHP_EOL;
