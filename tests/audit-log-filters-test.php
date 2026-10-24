<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// This test creates only its own randomly named database and always removes it.
$testName = 'luna_audit_test_' . bin2hex(random_bytes(6));
$server = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName`");
putenv('BARANGAY_DB_NAME=' . $testName);
require_once __DIR__ . '/../includes/audit-log-helpers.php';
function filter_check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  echo "PASS: $message\n";
}
try {
  $db = barangay_db();
  $db->exec('CREATE TABLE audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME, user_name VARCHAR(150), user_role VARCHAR(50), action VARCHAR(150), module VARCHAR(150), status VARCHAR(50))');
  $insert = $db->prepare('INSERT INTO audit_logs (created_at, user_name, user_role, action, module, status) VALUES (NOW(), ?, ?, ?, ?, ?)');
  $insert->execute(['Alpha', 'Staff', 'Created account', 'Residents', 'Success']);
  $insert->execute(['Beta', 'Admin', 'Updated account', 'Residents', 'Success']);
  $insert->execute(['Percent%_Name', 'Admin', 'Created account', 'Documents', 'Failed']);
  filter_check(count(barangay_audit_rows(barangay_audit_filters([]))) === 3, 'Unfiltered activity uses saved records');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['search'=>'Alpha']))) === 1, 'Search finds users');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['search'=>'Documents']))) === 1, 'Search finds modules');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['audit_action'=>'Created account']))) === 2, 'Action filter matches actual actions');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['search'=>'Admin', 'audit_action'=>'Created account']))) === 1, 'Combined filters agree');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['search'=>"' OR 1=1 --"]))) === 0, 'Search cannot inject SQL');
  filter_check(count(barangay_audit_rows(barangay_audit_filters(['search'=>'%_']))) === 1, 'Wildcard characters match literally');
  $filters = barangay_audit_filters(['search'=>'Residents']);
  filter_check(count(barangay_audit_rows($filters, 1)) === 1 && count(barangay_audit_rows($filters, null)) === 2, 'Export includes matching rows beyond display limit');
  filter_check(barangay_audit_filters(['search'=>['invalid']])['search'] === '', 'Malformed filters are handled');
  require_once __DIR__ . '/../includes/admin-functions.php';
  $auditFilters=barangay_audit_filters([]);
  $longAction='Prepared the requested Barangay Certification and recorded the complete payment receipt for a resident with a long name.';
  $auditLogs=[['date'=>'2026-10-24 14:30:00','user'=>'Resident & Staff <name>','role'=>'Barangay Secretary','action'=>$longAction,'module'=>'Document Requests and Issuance','status'=>'Success']];
  ob_start();require __DIR__ . '/../includes/views/audit-logs.php';$html=ob_get_clean();
  $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
  filter_check($xpath->query('//table[contains(@class,"mobile-record-table")]/tbody/tr/td[@data-label]')->length===6,'Audit records provide all six field labels for the phone layout');
  filter_check(trim($xpath->query('//td[@data-label="Action"]')->item(0)->textContent)===$longAction && trim($xpath->query('//td[@data-label="User / Staff"]')->item(0)->textContent)==='Resident & Staff <name>','Long actions and special characters remain complete and safely displayed');
  $activityLogs=$auditLogs;
  ob_start();require __DIR__ . '/../includes/staff-views/my-activity-logs.php';$html=ob_get_clean();
  $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
  filter_check($xpath->query('//table[contains(@class,"mobile-record-table")]/tbody/tr/td[@data-label]')->length===4 && trim($xpath->query('//td[@data-label="Action performed"]')->item(0)->textContent)===$longAction,'Staff activity uses the same labeled phone layout without shortening the action');
  $activityLogs=[];
  ob_start();require __DIR__ . '/../includes/staff-views/my-activity-logs.php';$html=ob_get_clean();
  filter_check(str_contains($html,'No activity recorded yet.'),'Empty staff activity has a readable message');
} finally {
  $server->exec("DROP DATABASE `$testName`");
}
