<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--action') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]);
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir()); barangay_start_session();
  $read = barangay_db()->prepare('SELECT * FROM users WHERE role = ? LIMIT 1'); $read->execute([$argv[3]]);
  $_SESSION['barangay_user'] = barangay_format_user($read->fetch()) + ['auth_version' => 2];
  $_SESSION['last_activity'] = time(); $_SESSION['community_post_csrf'] = 'test-token';
  $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = json_decode($argv[4], true, 512, JSON_THROW_ON_ERROR);
  require __DIR__ . '/../' . ($argv[3] === 'admin' ? 'admin-actions.php' : 'staff-actions.php'); exit;
}
function post_check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
function post_action(string $name, string $role, array $input): void {
  $pipes = [];
  $process = proc_open([PHP_BINARY, __FILE__, '--action', $name, $role, json_encode($input)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
  if (proc_close($process) || $out || $err) throw new RuntimeException($out . $err);
}
$name = 'luna_post_management_' . bin2hex(random_bytes(6));
$db = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE DATABASE `$name`");
$photoPath = 'storage/announcement-photos/test-post-management-' . bin2hex(random_bytes(6)) . '.png';
$photoTarget = __DIR__ . '/../' . $photoPath;
try {
  $db->exec("USE `$name`");
  $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s', '', file_get_contents(__DIR__ . '/../database/schema.sql'));
  $db->exec(str_replace('USE barangay_luna_system;', '', $schema));
  putenv('BARANGAY_DB_NAME=' . $name);
  require_once __DIR__ . '/../includes/announcement-helpers.php';
  announcement_ensure_photo_columns();
  $db->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('ADM-POST','admin','Test Admin','post-admin','unused','Active'),('STF-POST','staff','Test Staff','post-staff','unused','Active'),('RES-POST','resident','Test Resident','post-resident','unused','Active')");
  if (!is_dir(dirname($photoTarget))) mkdir(dirname($photoTarget), 0775, true);
  file_put_contents($photoTarget, 'test-only-photo');
  $insert = $db->prepare("INSERT INTO announcements(title,body,publish_date,publish_at,status,post_type,photo_path) VALUES ('Original','Details','2026-01-01','2026-01-01 08:00:00','Published',?,?)");
  $insert->execute(['announcement', $photoPath]); $postId = (int) $db->lastInsertId();
  $insert->execute(['event', $photoPath]); $eventId = (int) $db->lastInsertId();
  $input = ['action' => 'update_announcement', 'id' => $postId, 'csrf' => 'test-token', 'title' => 'Updated by staff', 'body' => 'Updated content', 'post_type' => 'announcement'];
  post_action($name, 'staff', $input);
  $post = $db->query('SELECT * FROM announcements WHERE id = ' . $postId)->fetch();
  post_check($post['title'] === 'Updated by staff' && $post['body'] === 'Updated content', 'Staff can save edits to a post through the authenticated handler');
  post_check($post['photo_path'] === $photoPath && $post['publish_at'] === '2026-01-01 08:00:00', 'Editing keeps the current photo and original publication time');
  post_action($name, 'staff', array_replace($input, ['csrf' => 'wrong', 'title' => 'Must not save']));
  post_action($name, 'resident', array_replace($input, ['title' => 'Must not save']));
  post_check($db->query('SELECT title FROM announcements WHERE id = ' . $postId)->fetchColumn() === 'Updated by staff', 'Invalid tokens and Resident edits are rejected');
  post_action($name, 'admin', array_replace($input, ['id' => $eventId, 'title' => 'Updated event', 'post_type' => 'event', 'event_at' => '2099-12-25T09:00', 'event_location' => 'Barangay Hall', 'schedule_enabled' => '1', 'publish_date' => '2099-12-01', 'publish_time' => '08:00']));
  $event = $db->query('SELECT * FROM announcements WHERE id = ' . $eventId)->fetch();
  post_check($event['title'] === 'Updated event' && $event['event_at'] === '2099-12-25 09:00:00' && $event['event_location'] === 'Barangay Hall' && $event['status'] === 'Scheduled', 'Admin can edit event details and schedule publication');
  post_action($name, 'resident', ['action' => 'delete_announcement', 'id' => $eventId, 'csrf' => 'test-token']);
  post_check((int) $db->query('SELECT COUNT(*) FROM announcements WHERE id = ' . $eventId)->fetchColumn() === 1, 'Residents cannot delete events');
  post_action($name, 'staff', ['action' => 'delete_announcement', 'id' => $eventId, 'csrf' => 'test-token']);
  post_check((int) $db->query('SELECT COUNT(*) FROM announcements WHERE id = ' . $eventId)->fetchColumn() === 0 && is_file($photoTarget), 'Staff can delete events while a photo used by another post is retained');
  post_action($name, 'admin', ['action' => 'delete_announcement', 'id' => $postId, 'csrf' => 'test-token']);
  clearstatcache(true, $photoTarget);
  post_check((int) $db->query('SELECT COUNT(*) FROM announcements WHERE id = ' . $postId)->fetchColumn() === 0 && !is_file($photoTarget), 'Admin can delete posts and their unused uploaded photos');
} finally {
  if (is_file($photoTarget)) unlink($photoTarget);
  $db->exec("DROP DATABASE `$name`");
}
echo "Isolated database and test photo removed. No production posts changed.\n";
