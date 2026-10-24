<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sms-verification.php';
require_once __DIR__ . '/../includes/registration-requirements.php';
require_once __DIR__ . '/../includes/backup-helpers.php';
barangay_start_session();
function pending_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
function pending_reject(callable $fn, string $label): void {
  $rejected = false;
  try { $fn(); } catch (RuntimeException | InvalidArgumentException $e) { $rejected = true; }
  pending_check($rejected, $label);
}
sms_verification_schema();
registration_requirements_schema();
$tables = barangay_backup_tables();
pending_check(in_array('resident_requirements', $tables, true), 'Checklist records are included in backups');
pending_check(!in_array('sms_verifications', $tables, true), 'Live verification challenges are excluded from backups');
$db = barangay_db();
$db->beginTransaction();
try {
  $hash = password_hash('Test-password-123', PASSWORD_DEFAULT);
  $code = 'TEST-' . bin2hex(random_bytes(6));
  $stmt = $db->prepare("INSERT INTO users (user_code, role, full_name, username, mobile, password_hash, status) VALUES (?, 'resident', 'Temporary Test', ?, '09123456789', ?, 'Active')");
  $stmt->execute([$code, $code, $hash]);
  $id = (int) $db->lastInsertId();
  $admin = (int) $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
  $file = ['filename' => 'test.pdf', 'mime' => 'application/pdf', 'content' => base64_encode('%PDF-test')];
  registration_requirement_save($id, 'residence', 'Submitted', 'Original checked', $admin, $file, false);
  $stmt = $db->prepare('SELECT * FROM resident_requirements WHERE resident_id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  pending_check($row['remarks'] === 'Original checked' && $row['status'] === 'Submitted', 'Checklist status and remarks persist');
  registration_requirement_save($id, 'residence', 'Submitted', 'Replaced', $admin, $file, false);
  $attachments = $db->query("SELECT * FROM record_attachments WHERE record_type = 'registration' AND owner_id = $id")->fetchAll();
  pending_check(count($attachments) === 1, 'Replacing an attachment leaves one current file');
  pending_check(!barangay_attachment_authorized(['role' => 'resident', 'id' => $id + 1], $attachments[0]), 'Other residents cannot access checklist files');
  registration_requirement_save($id, 'residence', 'Not Submitted', '', $admin, null, true);
  pending_check(!barangay_attachment_links('registration', $id . ':residence'), 'Attachment removal persists');
  pending_reject(fn() => registration_requirement_save($id, 'invalid', 'Submitted', '', $admin, null, false), 'Unknown requirement rejected');

  barangay_logo_save(['filename' => 'test.png', 'mime' => 'image/png', 'content' => base64_encode('test')], false, $admin);
  pending_check((int) $db->query("SELECT COUNT(*) FROM record_attachments WHERE record_type = 'branding'")->fetchColumn() === 1, 'Logo stored centrally');
  barangay_logo_save(null, true, $admin);
  pending_check((int) $db->query("SELECT COUNT(*) FROM record_attachments WHERE record_type = 'branding'")->fetchColumn() === 0, 'Restoring default removes custom logo');
  foreach ([['password_uppercase' => '1'], ['password_numbers' => '1'], ['password_special' => '1']] as $policy) {
    pending_reject(fn() => barangay_validate_password('lowercaseonly', $policy), 'Enabled password rule enforced');
  }
  barangay_validate_password('Valid-password-123', ['password_uppercase' => '1', 'password_numbers' => '1', 'password_special' => '1']);
  pending_reject(fn() => barangay_configuration_values(['session_timeout' => '0'], [], ['id' => $admin]), 'Unsafe timeout rejected');
  pending_reject(fn() => barangay_configuration_values(['sms_2fa' => '1', 'sms_enabled' => '0'], [], ['id' => $admin]), 'Cannot enable verification without SMS');

  $pending = ['id' => $id, 'password_hash' => $hash, 'started' => time(), 'token' => bin2hex(random_bytes(32))];
  $sent = '';
  $transport = function ($phone, $message) use (&$sent): array { $sent = $message; return ['success' => true]; };
  sms_verification_send($pending, $transport);
  preg_match('/\b(\d{6})\b/', $sent, $matches);
  $otp = $matches[1];
  pending_reject(fn() => sms_verification_send($pending, $transport), 'Immediate resend blocked');
  pending_reject(fn() => sms_verification_check($pending, '000000'), 'Incorrect code rejected');
  $stmt = $db->query("SELECT * FROM sms_verifications WHERE user_id = $id")->fetch();
  pending_check((int) $stmt['attempts'] === 1 && $stmt['code_hash'] !== $otp, 'Attempts persist and code is hashed');
  pending_reject(fn() => sms_verification_check(array_replace($pending, ['token' => 'another-session']), $otp), 'Code bound to the initiating session');
  pending_check(sms_verification_check($pending, $otp)['id'] === $id, 'Correct code verifies account');
  pending_reject(fn() => sms_verification_check($pending, $otp), 'Consumed code cannot be reused');
  barangay_setting_write('mobile_verification', '1', $admin);
  barangay_setting_write('sms_2fa', '0', $admin);
  pending_check(!sms_verification_required(['id' => $id, 'contact' => '09123456789']), 'Verified mobile does not need repeated first-sign-in verification');
  pending_check(sms_verification_required(['id' => $id, 'contact' => '09987654321']), 'Changed number requires verification');
  barangay_setting_write('sms_2fa', '1', $admin);
  pending_check(sms_verification_required(['id' => $id, 'contact' => '09123456789']), 'Two-factor mode requires a code every sign-in');
  $db->exec("UPDATE sms_verifications SET last_sent = 0 WHERE user_id = $id");
  sms_verification_send($pending, $transport);
  $db->exec("UPDATE sms_verifications SET expires_at = 1 WHERE user_id = $id");
  pending_reject(fn() => sms_verification_check($pending, $otp), 'Expired code rejected');
  $db->exec("UPDATE sms_verifications SET last_sent = 0, sends = 3 WHERE user_id = $id");
  pending_reject(fn() => sms_verification_send($pending, $transport), 'Account-wide send limit enforced');
  $db->exec("UPDATE sms_verifications SET last_sent = 0, sends = 1, attempts = 5 WHERE user_id = $id");
  pending_reject(fn() => sms_verification_send($pending, $transport), 'Resend cannot reset an attempt lockout');
  barangay_setting_write('session_timeout', '10', $admin);
  $_SESSION['barangay_user'] = ['id' => $id, 'role' => 'resident', 'auth_version' => 2];
  $_SESSION['last_activity'] = time() - 100;
  pending_check(barangay_current_user() !== null, 'Active session accepted');
  pending_check($_SESSION['last_activity'] <= time() - 100, 'Background requests do not extend idle timeout');
  $_SESSION['last_activity'] = time() - 601;
  pending_check(barangay_current_user() === null, 'Idle session expires');
} finally {
  $db->rollBack();
  $_SESSION = [];
}
echo "All records and settings rolled back. Only mock SMS transport used.\n";
