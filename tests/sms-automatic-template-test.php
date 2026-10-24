<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sms-workspace.php';
function template_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
sms_workspace_schema();
$db = barangay_db();
$db->beginTransaction();
try {
  $id = (int) $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
  foreach (['admin', 'staff'] as $role) {
    sms_save_automatic_settings(['id' => $id, 'role' => $role], ['ready' => '1', 'released' => '1', 'templates' => [
      'ready' => 'Hello {{resident_name}}, collect {{document_type}}. Total: {{fee}}. {{reference}}',
      'released' => '{{resident_name}}, your {{document_type}} was released. {{fee}}.',
    ]]);
    $settings = SMSAlertsHelper::getSettings();
    $message = sms_render_automatic('ready', ['name' => 'Test Resident', 'type' => 'Certificate', 'reference' => 'TEST', 'fee' => 75.5], $settings);
    template_check($message === 'Hello Test Resident, collect Certificate. Total: PHP 75.50. TEST', "$role templates persist and substitute exact details");
  }
  foreach (['', '{{unknown}}', '{{fee}', str_repeat('x', 601)] as $invalid) {
    $rejected = false;
    try { sms_validate_automatic_template($invalid); } catch (RuntimeException $e) { $rejected = true; }
    template_check($rejected, 'Invalid template rejected');
  }
  $denied = false;
  try { sms_save_automatic_settings(['id' => $id, 'role' => 'resident'], []); } catch (RuntimeException $e) { $denied = true; }
  template_check($denied, 'Residents cannot configure automatic SMS');
  $db->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_enabled', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
  $sent = [];
  $transport = function ($mobile, $message) use (&$sent) { $sent[] = $message; return ['success' => true]; };
  $ref = 'TEST-' . bin2hex(random_bytes(8));
  sms_automatic_document('ready', 'Test Resident', '+639123456789', 'Certificate', 75.5, $ref, $id, '', $transport);
  sms_automatic_document('ready', 'Test Resident', '+639123456789', 'Certificate', 75.5, $ref, $id, '', $transport);
  template_check(count($sent) === 1 && str_contains($sent[0], 'Hello Test Resident'), 'Automatic sender uses saved text and blocks duplicates');
  sms_record_send(['name' => 'Test', 'mobile' => '+639123456789', 'type' => 'Test', 'message' => str_repeat('a', 919), 'user' => $id, 'source' => 'Automatic', 'key' => $ref . '-long'], $transport);
  template_check(count($sent) === 1, 'Oversized filled messages do not reach provider');
} finally { $db->rollBack(); }
echo "Settings and test logs rolled back; no real SMS sent.\n";
