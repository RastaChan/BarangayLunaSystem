<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/husay-helpers.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
$_SESSION['husay_csrf'] ??= bin2hex(random_bytes(32));
$husayToken = $_SESSION['husay_csrf']; session_write_close();
try {
  $code = is_string($_GET['complaint'] ?? null) ? trim($_GET['complaint']) : null;
  $hearingId = (int) ($_GET['hearing'] ?? 0);
  $husayRows = husay_rows($code);
  ob_start();
  if ($hearingId) {
    $hearing = null;
    foreach ($husayRows as $candidate) if ((int) $candidate['id'] === $hearingId) { $hearing = $candidate; break; }
    if (!$hearing) { ob_end_clean(); http_response_code(404); echo json_encode(['ok' => false, 'message' => 'Husay session unavailable.']); exit; }
    require __DIR__ . '/includes/husay-detail-content.php';
  } else require __DIR__ . '/includes/husay-list.php';
  $html = ob_get_clean();
  $complaintsHtml = null;
  if (!$code && !$hearingId) { ob_start(); require __DIR__ . '/includes/husay-complaint-options.php'; $complaintsHtml = ob_get_clean(); }
  $activeSessions = array_values(array_filter($husayRows, static fn(array $row): bool => in_array($row['status'], ['Scheduled','In Progress'], true)));
  echo json_encode(['ok' => true, 'owner' => $user['role'] . ':' . $user['id'], 'html' => $html,
    'complaintsHtml' => $complaintsHtml,
    'hasActive' => (bool)$activeSessions, 'activeId' => $code && $activeSessions ? (int)$activeSessions[0]['id'] : null]);
} catch (Throwable $error) { if (ob_get_level()) ob_end_clean(); http_response_code(500); echo json_encode(['ok' => false, 'message' => 'Husay schedules could not be loaded.']); }
