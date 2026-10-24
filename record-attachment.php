<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/record-attachments.php';
$user = barangay_current_user();
header('Cache-Control: private, no-store');
if (!$user) { http_response_code(403); exit('Please sign in.'); }
$active = barangay_db()->prepare("SELECT 1 FROM users WHERE id = ? AND role = ? AND status = 'Active'");
$active->execute([$user['id'], $user['role']]);
if (!$active->fetchColumn()) { http_response_code(403); exit('Account unavailable.'); }
barangay_attachment_schema();
$stmt = barangay_db()->prepare('SELECT * FROM record_attachments WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$file = $stmt->fetch();
if (!$file || !barangay_attachment_authorized($user, $file)) { http_response_code(404); exit('Attachment unavailable.'); }
$content = base64_decode($file['content_base64'], true);
if ($content === false) { http_response_code(404); exit('Attachment unavailable.'); }
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
foreach (barangay_attachment_display_headers($file, ($_GET['download'] ?? '') === '1') as $name => $value) {
  header($name . ': ' . $value);
}
header('Content-Length: ' . strlen($content));
echo $content;
