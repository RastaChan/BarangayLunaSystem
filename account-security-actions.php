<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/account-security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = barangay_current_user();
if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(403);
  echo json_encode(['error' => 'Please sign in again.']);
  exit;
}
if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['account_security_csrf']) || !hash_equals($_SESSION['account_security_csrf'], $_POST['csrf'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
  exit;
}
try {
  $db = barangay_db();
  $db->beginTransaction();
  barangay_change_own_password($user, (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
  $db->commit();
  session_regenerate_id(true);
  echo json_encode(['message' => 'Your password was changed successfully.']);
} catch (Throwable $error) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
  echo json_encode(['error' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Could not change your password. Please try again.']);
}
