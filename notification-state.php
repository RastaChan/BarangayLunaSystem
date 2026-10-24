<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notification-state.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = barangay_current_user();
if (!$user || ($user['role'] ?? '') !== 'resident') {
  http_response_code(403); echo json_encode(['ok' => false]); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo json_encode(['ok' => false]); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !is_string($input['csrf'] ?? null) || empty($_SESSION['notification_csrf']) || !hash_equals($_SESSION['notification_csrf'], $input['csrf'])) {
  http_response_code(403); echo json_encode(['ok' => false]); exit;
}
if (!in_array($input['kind'] ?? '', ['seen', 'read', 'unread', 'dismissed'], true) || !is_array($input['ids'] ?? null) || count($input['ids']) > 300) {
  http_response_code(400); echo json_encode(['ok' => false]); exit;
}
try {
  barangay_save_notification_state((int) $user['id'], $input['kind'], $input['ids']);
  echo json_encode(['ok' => true]);
} catch (Throwable $error) {
  http_response_code(500); echo json_encode(['ok' => false]);
}
