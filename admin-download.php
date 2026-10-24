<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

barangay_require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
  $stmt = barangay_db()->prepare('SELECT filename FROM backups WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => $id]);
} else {
  $stmt = barangay_db()->query('SELECT filename FROM backups ORDER BY created_at DESC LIMIT 1');
}

$filename = $stmt->fetchColumn();
$path = __DIR__ . '/storage/backups/' . basename((string) $filename);

if (!$filename || !is_file($path)) {
  http_response_code(404);
  echo 'Backup file not found.';
  exit;
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>
