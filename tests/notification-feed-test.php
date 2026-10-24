<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array($argv[1] ?? '', ['--feed', '--read'], true)) {
  putenv('BARANGAY_DB_NAME=' . $argv[2]);
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir()); barangay_start_session();
  if ($argv[3] !== 'anonymous') {
    $stmt = barangay_db()->prepare('SELECT * FROM users WHERE role = ? LIMIT 1');
    $stmt->execute([$argv[3]]); $row = $stmt->fetch();
    $_SESSION['barangay_user'] = barangay_format_user($row) + ['auth_version' => 2];
    $_SESSION['last_activity'] = time();
  }
  if ($argv[1] === '--read') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['feedback_csrf'] = 'test-token';
    $_POST = ['id' => $argv[4], 'csrf' => 'test-token'];
    require __DIR__ . '/../feedback-read.php';
  } else require __DIR__ . '/../notification-feed.php';
  exit;
}
function notification_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
function notification_read(string $name, string $role, ?string $feedbackId = null): array {
  $pipes = [];
  $process = proc_open([PHP_BINARY, __FILE__, $feedbackId === null ? '--feed' : '--read', $name, $role, $feedbackId ?? ''], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
  if ($exit || $err) throw new RuntimeException($out . $err);
  return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
$name = 'luna_notification_test_' . bin2hex(random_bytes(6));
$db = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE DATABASE `$name`");
try {
  $db->exec("USE `$name`");
  $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s', '', file_get_contents(__DIR__ . '/../database/schema.sql'));
  $db->exec(str_replace('USE barangay_luna_system;', '', $schema));
  $db->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('ADM-TEST','admin','Test Admin','test-admin','unused','Active'),('STF-TEST','staff','Test Staff','test-staff','unused','Active'),('RES-TEST','resident','Test Resident','test-resident','unused','Active')");
  $insert = $db->prepare("INSERT INTO document_requests(request_code,resident_name,document_type,status,requested_at) VALUES (?,'Another Resident','Barangay Certification','Pending','2026-10-24 08:00:00')");
  for ($i = 1; $i <= 35; $i++) $insert->execute(['REQ-' . $i]);
  $db->exec("INSERT INTO feedbacks(feedback_code,resident_name,message,status,submitted_at,archived_at) VALUES ('FDB-LIVE','Test Resident','Live feedback','Unread','2026-10-24 10:00:00',NULL),('FDB-ARCHIVED','Test Resident','Archived feedback','Unread','2026-10-24 11:00:00','2026-10-24 11:01:00')");
  foreach (['admin' => 'feedbacks', 'staff' => 'staff-feedbacks'] as $role => $view) {
    $feed = notification_read($name, $role);
    notification_check(str_starts_with($feed['owner'], $role . ':'), "$role feed identifies the signed-in account");
    notification_check(count($feed['items']) === 31 && $feed['sideCounts'][$view] === 1, "$role feedback stays in the feed alongside 30 document alerts; archived feedback is excluded");
    notification_check(str_contains($feed['items'][0]['href'], 'feedback=FDB-LIVE'), "$role newest feedback comes first and links to its specific record");
  }
  $read = notification_read($name, 'staff', 'FDB-LIVE');
  notification_check($read['ok'] && $read['status'] === 'Read', 'Opening feedback records its read status through the authenticated endpoint');
  $feed = notification_read($name, 'staff');
  notification_check($feed['sideCounts']['staff-feedbacks'] === 0 && count($feed['items']) === 30, 'Reading feedback removes its alert and clears the unread count');
  $read = notification_read($name, 'staff', 'FDB-ARCHIVED');
  notification_check(!$read['ok'] && $db->query("SELECT status FROM feedbacks WHERE feedback_code='FDB-ARCHIVED'")->fetchColumn() === 'Unread', 'Archived feedback cannot be marked read through a stale notification');
  $feed = notification_read($name, 'resident');
  notification_check(!array_filter($feed['items'], static fn(array $item): bool => $item['view'] === 'my-document-requests' || str_contains($item['view'], 'feedback')), 'Residents cannot see other residents’ requests or staff feedback alerts');
  $feed = notification_read($name, 'anonymous');
  notification_check($feed['items'] === [] && $feed['sideCounts'] === [], 'Signed-out sessions cannot retrieve notifications');
} finally { $db->exec("DROP DATABASE `$name`"); }
echo "Isolated database removed. No production records changed or SMS sent.\n";
