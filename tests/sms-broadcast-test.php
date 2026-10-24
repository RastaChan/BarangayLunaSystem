<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sms-broadcast.php';
function broadcast_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
$audience = sms_broadcast_recipients([
  ['full_name' => 'Test A', 'mobile' => '09123456789'],
  ['full_name' => 'Test B', 'mobile' => '+639123456789'],
  ['full_name' => 'Test C', 'mobile' => ''],
  ['full_name' => 'Test D', 'mobile' => 'invalid'],
  ['full_name' => 'Test E', 'mobile' => '09987654321'],
]);
broadcast_check(count($audience['recipients']) === 2 && $audience['duplicates'] === 1 && $audience['skipped'] === 2, 'Normalized numbers deduplicated and invalid numbers excluded');
foreach ([['', 'emergency'], ['Test', 'unknown'], [str_repeat('a', 919), 'disaster']] as [$message, $type]) {
  $rejected = false;
  try { sms_broadcast_message($message, $type); } catch (RuntimeException $e) { $rejected = true; }
  broadcast_check($rejected, 'Invalid broadcast rejected');
}
broadcast_check(str_contains(sms_broadcast_message('Test alert', 'disaster'), 'Test alert'), 'Broadcast preserves the supplied message');
sms_workspace_schema();
$db = barangay_db();
$db->beginTransaction();
try {
  $user = (int) $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
  $calls = 0;
  $transport = function () use (&$calls): array { $calls++; return ['success' => true, 'sms_id' => 'mock-only']; };
  $prefix = 'broadcast:test-' . bin2hex(random_bytes(8));
  foreach ($audience['recipients'] as $i => $recipient) {
    $data = $recipient + ['message' => 'Test only', 'type' => 'Disaster alert', 'source' => 'Broadcast', 'user' => $user, 'key' => $prefix . ':' . $i];
    $result = sms_record_send($data, $transport);
    broadcast_check($result['delivery_state'] === 'Accepted', 'Each recipient receives a separate logged result');
    $duplicate = sms_record_send($data, $transport);
    broadcast_check(!empty($duplicate['duplicate']), 'Repeated batch attempt is blocked');
  }
  broadcast_check($calls === 2, 'Only one mock send per unique recipient');
  $failed = sms_record_send(['name' => 'Test failure', 'mobile' => '+639123456789', 'message' => 'Test', 'type' => 'Emergency alert', 'source' => 'Broadcast', 'user' => $user, 'key' => $prefix . ':failed'], static fn() => ['success' => false, 'http_status' => 429]);
  broadcast_check($failed['delivery_state'] === 'Failed', 'Failed sends remain identifiable for retry');
} finally { $db->rollBack(); }
echo "All SMS transport calls mocked; test records rolled back.\n";
