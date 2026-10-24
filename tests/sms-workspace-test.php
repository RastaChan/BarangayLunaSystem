<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sms-workspace.php';
require_once __DIR__ . '/../includes/document-fee-helpers.php';
$_SESSION = [];

function check_sms(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
  echo 'PASS: ' . $message . PHP_EOL;
}

sms_workspace_schema();
barangay_ensure_document_fee_schema();
$db = barangay_db();
$db->beginTransaction();
try {
  $key = 'test:' . bin2hex(random_bytes(12));
  $data = ['name' => 'SMS Test Recipient', 'mobile' => '09170000000', 'type' => 'Custom message', 'message' => 'Test only.', 'source' => 'Manual', 'user' => null, 'key' => $key];
  $calls = 0;
  $accepted = static function () use (&$calls): array { $calls++; return ['success' => true, 'sms_id' => 'test-provider-id']; };
  $result = sms_record_send($data, $accepted);
  check_sms($result['delivery_state'] === 'Accepted', 'API acceptance is not called delivered');
  $repeat = sms_record_send($data, $accepted);
  check_sms(!empty($repeat['duplicate']) && $calls === 1, 'Duplicate form or event does not send twice');
  $row = $db->query('SELECT * FROM sms_logs WHERE id = ' . (int) $result['id'])->fetch();
  check_sms($row['status'] === 'Pending' && $row['provider_id'] === 'test-provider-id', 'Accepted history stores pending delivery and provider reference');
  $data['key'] .= ':failed';
  $failed = sms_record_send($data, static fn() => ['success' => false, 'http_status' => 401]);
  check_sms($failed['delivery_state'] === 'Failed' && str_contains($failed['failure_reason'], 'credentials'), 'Rejected request stores actionable failure reason');
  $data['key'] = 'retry:' . $failed['id']; $data['retry_of'] = $failed['id'];
  $retry = sms_record_send($data, $accepted);
  $duplicateRetry = sms_record_send($data, $accepted);
  check_sms($retry['success'] && !empty($duplicateRetry['duplicate']) && $calls === 2, 'Retry is a linked, duplicate-protected attempt');
  $data['key'] .= ':unknown';
  $unknown = sms_record_send($data, static fn() => ['success' => false, 'uncertain' => true]);
  check_sms($unknown['delivery_state'] === 'Unknown', 'Uncertain submission is not marked safe to retry');
  check_sms(sms_retry_problem(['delivery_state' => 'Unknown', 'age_seconds' => 90]) !== null, 'Unknown outcome cannot be retried');
  check_sms(sms_retry_problem(['delivery_state' => 'Failed', 'age_seconds' => 20]) !== null, 'Retry cooldown is enforced');
  check_sms(sms_retry_problem(['delivery_state' => 'Failed', 'age_seconds' => 90, 'retry_id' => 1]) !== null, 'Already retried attempts are blocked');
  check_sms(sms_retry_problem(['delivery_state' => 'Failed', 'age_seconds' => 90]) === null, 'Confirmed failure becomes retryable after cooldown');
  check_sms(sms_full_message('BRGY LUNA: Hello', ['sms_sender_name' => 'BRGY LUNA']) === 'BRGY LUNA: Hello', 'Sender prefix is not duplicated');
  $ready = sms_document_message('ready', 'Certificate', 'REQ-TEST', 50);
  $released = sms_document_message('released', 'Certificate', 'REQ-TEST', 50);
  check_sms(str_contains($ready, 'ready for pickup') && str_contains($ready, 'PHP 50.00'), 'Pickup template contains document fee');
  check_sms(str_contains($released, 'has been released') && !str_contains($released, 'claim') && !str_contains($released, 'Amount due'), 'Release confirmation does not ask for pickup or assume unpaid balance');
  $settings = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_auto_ready', '0') ON DUPLICATE KEY UPDATE setting_value = '0'");
  $settings->execute();
  $skip = sms_automatic_document('ready', 'Test', '09170000000', 'Test', 0, 'TEST', 0);
  check_sms(!empty($skip['skipped']), 'Disabled automatic notification is skipped without transport');
  foreach (['admin', 'staff'] as $role) {
    foreach (['send', 'broadcast', 'automatic', 'history'] as $tab) {
      $loggedInUser = ['role' => $role, 'id' => 0]; $_GET = ['sms_tab' => $tab, 'sms_search' => 'SMS Test Recipient'];
      ob_start(); require __DIR__ . '/../includes/sms-alerts-view.php'; $html = ob_get_clean();
      check_sms(str_contains($html, 'SMS Alerts') && !str_contains($html, 'Warning:'), "$role $tab screen renders");
      if ($tab === 'automatic') {
        check_sms(str_contains($html, 'Save preferences'), "$role automatic settings permission");
        check_sms(str_contains($html, 'name="templates[ready]"') && str_contains($html, 'name="templates[released]"'), "$role can edit both automatic messages");
      }
      if ($tab === 'broadcast') check_sms(str_contains($html, 'id="broadcastConfirm"') && str_contains($html, 'All registered accounts'), "$role broadcast includes audience review and confirmation");
    }
  }
} finally {
  $db->rollBack();
}
echo "All test records and setting changes rolled back. No SMS sent.\n";
