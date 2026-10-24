<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
function report_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
$issuedDocuments = [['id' => 42, 'document_number' => 'TEST-42', 'resident_name' => 'Test Resident',
  'document_type' => 'Certificate', 'copies' => 3, 'fee_amount' => 90, 'unit_fee' => 30,
  'purpose' => 'Test', 'date' => '2026-09-19', 'generated_file_path' => 'storage/generated-documents/test.docx',
  'generated_file_name' => 'test.docx']];
$serviceReports = ['issued_today' => 1, 'issued_month' => 1, 'processed_week' => 1, 'complaints_week' => 0];
$serviceSummary = [];
ob_start(); require __DIR__ . '/../includes/staff-views/service-reports.php'; $html = ob_get_clean();
report_check(str_contains($html, 'TEST-42') && str_contains($html, 'PHP 90.00'), 'Report renders issued records and total fee');
$markup = new DOMDocument(); @$markup->loadHTML($html); $links = new DOMXPath($markup);
report_check($links->query('//td[@data-label="Copies" and normalize-space(.)="3"]')->length === 1 && str_contains($html, 'PHP 30.00 per copy'), 'Report retains copies and unit fee');
report_check($links->query('//table[contains(@class,"issued-records-table")]//tbody/tr/td[@data-label]')->length === 8, 'Issued record cards provide readable labels for every field on phones');
report_check($links->query('//a[@href="staff-print-issued-document.php?id=42"]')->length === 1 && $links->query('//a[@download and @href="storage/generated-documents/test.docx" and normalize-space(.)="Download DOCX"]')->length === 1, 'Report provides actual document reprint and download links');
$action = file_get_contents(__DIR__ . '/../staff-actions.php');
$guard = strpos($action, "'print-generation-failed'");
$insert = strpos($action, 'INSERT INTO issued_documents');
report_check($guard !== false && $guard < $insert, 'Print generation failure is checked before recording issuance');
report_check(str_contains($action, "header('Location: staff-print-issued-document.php?id=' . \$issuedDocumentId, true, 303)"), 'Successful print submission redirects to the generated document');
echo "No issuance, SMS, or printing performed.\n";
