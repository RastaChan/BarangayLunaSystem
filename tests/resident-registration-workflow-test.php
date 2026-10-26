<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$testDatabase = 'barangay_registration_test_' . bin2hex(random_bytes(6));
$host = getenv('BARANGAY_DB_HOST') ?: '127.0.0.1';
$port = getenv('BARANGAY_DB_PORT') ?: '3306';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", getenv('BARANGAY_DB_USER') ?: 'root', getenv('BARANGAY_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `{$testDatabase}`");
function registration_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: {$label}\n";
}
try {
  $server->exec(str_replace('barangay_luna_system', $testDatabase, file_get_contents(__DIR__ . '/../database/schema.sql')));
  putenv('BARANGAY_DB_NAME=' . $testDatabase);
  require_once __DIR__ . '/../includes/registration-requirements.php';
  require_once __DIR__ . '/../includes/resident-account-notifications.php';
  registration_requirements_schema();
  sms_workspace_schema();
  $db = barangay_db();
  $insert = $db->prepare("INSERT INTO users (user_code, role, full_name, username, mobile, password_hash, status) VALUES (?, ?, ?, ?, '09123456789', 'test-only-unused', 'Pending')");
  $requirements = registration_requirement_inputs(['requirement_status'=>['government_id'=>'Submitted', 'residence'=>'Submitted', 'photo'=>'Not Applicable'], 'requirement_remarks'=>['government_id'=>'Original checked']], []);
  registration_check(count($requirements) === 4 && $requirements['birth_certificate']['status'] === 'Not Submitted', 'New account checklist includes all requirement types');
  foreach ([['requirement_status'=>['government_id'=>'Invalid']], ['requirement_remarks'=>['government_id'=>str_repeat('x', 4001)]], ['requirement_status'=>'invalid']] as $input) {
    try { registration_requirement_inputs($input, []); throw new LogicException('Invalid requirement accepted'); }
    catch (InvalidArgumentException $e) { echo "PASS: invalid requirement input rejected\n"; }
  }
  $sent = [];
  $transport = function ($mobile, $message) use (&$sent, $db) {
    registration_check(!$db->inTransaction(), 'SMS occurs after the account transaction commits');
    $sent[] = [$mobile, $message];
    return ['success'=>true, 'sms_id'=>'test-provider-id'];
  };
  foreach (['admin', 'staff'] as $role) {
    $insert->execute([strtoupper($role), $role, 'Test ' . $role, $role]);
    $actor = (int) $db->lastInsertId();
    $code = 'RES-' . strtoupper($role);
    $db->beginTransaction();
    $insert->execute([$code, 'resident', 'Test resident', $code]);
    $resident = (int) $db->lastInsertId();
    $withFile = $requirements;
    $withFile['government_id']['file'] = ['filename'=>'id.pdf', 'mime'=>'application/pdf', 'content'=>base64_encode('%PDF-test-only')];
    $withFile['government_id']['status'] = 'Not Submitted';
    registration_save_initial_requirements($resident, $withFile, $actor);
    $db->commit();
    registration_check((int) $db->query("SELECT COUNT(*) FROM resident_requirements WHERE resident_id = {$resident}")->fetchColumn() === 4, $role . ' saves account requirements together');
    registration_check((int) $db->query("SELECT COUNT(*) FROM record_attachments WHERE owner_id = {$resident}")->fetchColumn() === 1, 'Requirement attachment belongs to the new resident');
    registration_check($db->query("SELECT status FROM resident_requirements WHERE resident_id = {$resident} AND requirement_key = 'government_id'")->fetchColumn() === 'Submitted', 'An attached requirement is saved as Submitted even if the posted status was Not Submitted');
    $db->beginTransaction();
    registration_requirement_save($resident, 'government_id', 'Not Applicable', 'Replacement attachment test', $actor, $withFile['government_id']['file'], false);
    $db->commit();
    registration_check($db->query("SELECT status FROM resident_requirements WHERE resident_id = {$resident} AND requirement_key = 'government_id'")->fetchColumn() === 'Submitted', 'Replacing an existing requirement attachment also saves Submitted');
    $result = resident_account_notification($code, $actor, null, $transport);
    registration_check(!empty($result['success']), 'Creation sends automatic account SMS');
    $count = count($sent);
    resident_account_notification($code, $actor, null, $transport);
    registration_check(count($sent) === $count, 'Creation notification cannot send twice');
    $before = resident_account_notification_snapshot($code);
    resident_account_notification($code, $actor, $before, $transport);
    registration_check(count($sent) === $count, 'Saving unchanged account status does not send duplicate SMS');
    $db->exec("UPDATE users SET status = 'Active' WHERE id = {$resident}");
    resident_account_notification($code, $actor, $before, $transport);
    registration_check(count($sent) === $count + 1, 'Changed account status sends automatic SMS');
  }
  $expected = 'Your Barangay Luna Smart Portal account has been created or updated. Please keep your login details private';
  foreach ($sent as [$mobile, $message]) registration_check($mobile === '09123456789' && $message === $expected, 'Resident receives the exact requested text without login credentials or added prefix');
  $db->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_enabled', '0') ON DUPLICATE KEY UPDATE setting_value = '0'");
  $before = resident_account_notification_snapshot('RES-STAFF');
  $db->exec("UPDATE users SET verification_status = 'Verified' WHERE user_code = 'RES-STAFF'");
  $failed = resident_account_notification('RES-STAFF', $actor, $before);
  registration_check($failed['delivery_state'] === 'Failed' && str_contains($failed['failure_reason'], 'disabled'), 'Disabled SMS is recorded as failed without losing the account update');
  registration_check($db->query("SELECT verification_status FROM users WHERE user_code = 'RES-STAFF'")->fetchColumn() === 'Verified', 'Saved update survives SMS failure');
  $db->beginTransaction();
  try {
    $insert->execute(['ROLLBACK', 'resident', 'Rollback resident', 'rollback']);
    $resident = (int) $db->lastInsertId();
    $invalid = $requirements; $invalid['photo']['status'] = 'Invalid';
    registration_save_initial_requirements($resident, $invalid, $actor);
    throw new LogicException('Invalid checklist accepted');
  } catch (InvalidArgumentException $e) { $db->rollBack(); }
  registration_check((int) $db->query("SELECT COUNT(*) FROM users WHERE user_code = 'ROLLBACK'")->fetchColumn() === 0, 'A checklist failure rolls back account creation');
} finally {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $server->exec("DROP DATABASE `{$testDatabase}`");
  echo "Temporary test database removed. No real SMS sent.\n";
}
