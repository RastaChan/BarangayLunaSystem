<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/account-security.php';
require_once __DIR__ . '/../includes/record-attachments.php';
require_once __DIR__ . '/../includes/backup-helpers.php';
function audit_check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  echo "PASS: $message\n";
}
barangay_attachment_schema();
$db = barangay_db();
audit_check(in_array('record_attachments', barangay_backup_tables(), true), 'Backups include uploaded evidence');
foreach (['document_fees', 'document_payments', 'notification_seen_state'] as $table) {
  audit_check(in_array($table, barangay_backup_tables(), true), "Backups include $table");
}
$db->beginTransaction();
try {
  $code = 'TEST-' . bin2hex(random_bytes(5));
  $insert = $db->prepare("INSERT INTO users (user_code, role, full_name, username, password_hash, status) VALUES (?, ?, 'Audit Test', ?, ?, 'Active')");
  foreach (['admin', 'staff', 'resident'] as $role) {
    $insert->execute([$code . $role, $role, $code . $role, password_hash('Original-test-123', PASSWORD_DEFAULT)]);
    $user = ['id' => (int) $db->lastInsertId(), 'role' => $role, 'name' => 'Audit Test'];
    foreach ([['wrong', 'Replacement-123', 'Replacement-123'], ['Original-test-123', 'short', 'short'], ['Original-test-123', 'Replacement-123', 'Mismatch-123']] as $values) {
      $rejected = false;
      try { barangay_change_own_password($user, ...$values); } catch (InvalidArgumentException $e) { $rejected = true; }
      audit_check($rejected, "$role rejects invalid password changes");
    }
    barangay_change_own_password($user, 'Original-test-123', 'Replacement-123', 'Replacement-123');
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    audit_check(password_verify('Replacement-123', $stmt->fetchColumn()), "$role password really saved");
  }
  $stmt = $db->prepare("INSERT INTO complaints (complaint_code, resident_user_id, resident_name, subject, status, filed_at) VALUES (?, ?, 'Audit Test', 'Attachment test', 'Open', NOW())");
  $stmt->execute([$code, $user['id']]);
  $file = ['filename' => 'evidence.pdf', 'mime' => 'application/pdf', 'content' => base64_encode('%PDF-test')];
  barangay_save_attachment('complaint', $code, $user['id'], $file);
  $id = (int) $db->lastInsertId();
  $attachment = $db->query('SELECT * FROM record_attachments WHERE id = ' . $id)->fetch();
  audit_check(barangay_attachment_authorized($user, $attachment), 'Owner may download evidence');
  audit_check(!barangay_attachment_authorized(['id' => $user['id'] + 1, 'role' => 'resident'], $attachment), 'Other residents cannot download evidence');
  audit_check(barangay_attachment_authorized(['id' => 1, 'role' => 'staff'], $attachment), 'Staff may access evidence on an existing complaint');
  audit_check(str_contains(barangay_attachment_links('complaint', $code), 'record-attachment.php?id=' . $id), 'Saved evidence has a download link');
  audit_check(barangay_read_attachment_upload([]) === null, 'Attachments remain optional');
} finally {
  $db->rollBack();
}
echo "All test accounts, passwords and records rolled back.\n";
