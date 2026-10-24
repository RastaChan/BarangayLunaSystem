<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/resident-functions.php';
function activity_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo 'PASS: ' . $label . PHP_EOL;
}
try {
  resident_activity_history(['id' => 1, 'role' => 'staff']);
  throw new RuntimeException('Wrong role accepted');
} catch (InvalidArgumentException $expected) {
  echo 'PASS: resident role required' . PHP_EOL;
}
$db = barangay_db();
$users = $db->query("SELECT id, role FROM users WHERE role = 'resident' LIMIT 2")->fetchAll();
activity_check(count($users) > 0, 'resident account available for read-only checks');
foreach ($users as $user) {
  $history = resident_activity_history($user, -10);
  $stmt = $db->prepare('SELECT COUNT(*) FROM audit_logs WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  activity_check($history['total'] === (int) $stmt->fetchColumn(), 'only own activity counted');
  $stmt = $db->prepare('SELECT action, module, status, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20');
  $stmt->execute([$user['id']]);
  activity_check($history['items'] === $stmt->fetchAll(), 'only own activity returned in newest-first order');
  activity_check($history['page'] === 1 && count($history['items']) <= 20, 'negative page clamped and page size limited');
  $last = resident_activity_history($user, PHP_INT_MAX);
  activity_check($last['page'] === $last['pages'], 'out-of-range page clamped');
}
$loggedInUser = $users[0];
$_GET = [];
ob_start();
require __DIR__ . '/../includes/resident-views/my-activity-logs.php';
$html = ob_get_clean();
activity_check(str_contains($html, 'My Activity Logs'), 'resident activity view renders');
activity_check(str_contains($html, 'class="logs-table mobile-record-table"') && str_contains($html, 'class="logs-container"'), 'uses the shared Audit Logs design with readable mobile cards');
activity_check(!str_contains($html, 'admin-export.php') && !str_contains($html, 'User / Staff'), 'no admin controls or other-user columns added');
