<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['admin', 'staff'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false]);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo json_encode(['ok' => false]);
  exit;
}
if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['feedback_csrf']) || !hash_equals($_SESSION['feedback_csrf'], $_POST['csrf'])) {
  http_response_code(403);
  echo json_encode(['ok' => false]);
  exit;
}
$id = is_string($_POST['id'] ?? null) ? trim($_POST['id']) : '';
if ($id === '') {
  http_response_code(400);
  echo json_encode(['ok' => false]);
  exit;
}
$db = barangay_db();
try {
  $db->beginTransaction();
  $stmt = $db->prepare('SELECT status FROM feedbacks WHERE feedback_code = :id AND archived_at IS NULL FOR UPDATE');
  $stmt->execute([':id' => $id]);
  $status = $stmt->fetchColumn();
  if ($status === false || $status === 'Archived') {
    $db->rollBack();
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
  }
  if ($status !== 'Read') {
    $stmt = $db->prepare("UPDATE feedbacks SET status = 'Read' WHERE feedback_code = :id");
    $stmt->execute([':id' => $id]);
    admin_log($user, 'Viewed and marked feedback read: ' . $id, 'Feedbacks');
  }
  $db->commit();
  echo json_encode(['ok' => true, 'status' => 'Read']);
} catch (Throwable $error) {
  if ($db->inTransaction()) $db->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false]);
}
