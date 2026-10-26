<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/notification-state.php';
function state_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo 'PASS: ' . $label . PHP_EOL;
}
barangay_notification_state_schema();
$db = barangay_db();
$db->beginTransaction();
try {
  $id = 'resident-doc-test-' . bin2hex(random_bytes(8)) . '-Pending';
  barangay_save_notification_state(-987654, 'seen', [$id]);
  barangay_save_notification_state(-987654, 'seen', [$id]);
  $state = barangay_notification_state(-987654);
  state_check(in_array($id, $state['seen'], true), 'seen state survives a fresh database read');
  state_check(count(array_keys($state['seen'], $id, true)) === 1, 'repeated clicks are idempotent');
  state_check(!in_array($id, barangay_notification_state(-987653)['seen'], true), 'state is isolated by account');
  state_check(!in_array($id . '-Approved', $state['seen'], true), 'new status remains unseen');
  state_check(!in_array($id, $state['read'], true), 'clearing the badge preserves unread highlighting');
  barangay_save_notification_state(-987654, 'read', [$id]);
  barangay_save_notification_state(-987654, 'dismissed', [$id]);
  $state = barangay_notification_state(-987654);
  state_check(in_array($id, $state['read'], true) && in_array($id, $state['dismissed'], true), 'read and removal state persist independently');
  barangay_save_notification_state(-987654, 'seen', ['admin-doc-test']);
  state_check(in_array('admin-doc-test', barangay_notification_state(-987654)['seen'], true), 'Admin notification state is supported');
  barangay_save_notification_state(-987654, 'unread', [$id]);
  $state = barangay_notification_state(-987654);
  state_check(in_array($id, $state['unread'], true) && !in_array($id, $state['read'], true), 'Mark unread replaces read state inside the caller transaction');
  barangay_save_notification_state(-987654, 'seen', ['unknown-test']);
  state_check(!in_array('unknown-test', barangay_notification_state(-987654)['seen'], true), 'Unknown notification keys are ignored');
} finally {
  $db->rollBack();
}
