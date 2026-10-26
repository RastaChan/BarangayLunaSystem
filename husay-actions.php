<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/husay-helpers.php';
$user = barangay_current_user();
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
header('Cache-Control: no-store');
if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) {
  http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => 'Admin or Staff sign-in is required.']); exit;
}
$page = $user['role'] === 'admin' ? 'dashboard.php?view=complaints' : 'staff-dashboard.php?view=staff-complaints';
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new InvalidArgumentException('Use the Mediation form to make changes.');
  if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['husay_csrf']) || !hash_equals($_SESSION['husay_csrf'], $_POST['csrf'])) {
    http_response_code(403); throw new InvalidArgumentException('Refresh the page and try again.');
  }
  $action = $_POST['action'] ?? '';
  if ($action === 'schedule_husay') $result = husay_schedule($_POST, $user);
  elseif (in_array($action, ['record_husay','cancel_husay'], true)) $result = ['id' => husay_record_session($_POST, $user)];
  else throw new InvalidArgumentException('Unknown Mediation action.');
  $message = 'Mediation changes saved.';
  session_write_close();
  if ($action === 'schedule_husay' && ($_POST['send_sms'] ?? '') === '1') {
    try {
      require_once __DIR__ . '/includes/sms-workspace.php';
      $read = barangay_db()->prepare('SELECT * FROM husay_schedules WHERE id=?'); $read->execute([$result['id']]); $row = $read->fetch();
      $sent = 0; $attempted = 0; $mobiles = [];
      foreach (['complainant','respondent'] as $party) {
        $mobile = $row[$party . '_mobile'] ?? '';
        if ($mobile === '' || isset($mobiles[$mobile])) continue;
        $mobiles[$mobile] = true; $attempted++;
        $sms = sms_record_send(['name' => $row[$party . '_name'], 'mobile' => $mobile, 'user' => $user['id'], 'source' => 'Manual', 'type' => 'Mediation schedule',
          'key' => 'husay:' . $row['id'] . ':' . $party, 'message' => 'Mediation appointment: ' . date('M j, Y g:i A', strtotime($row['starts_at'])) . ' at ' . $row['venue'] . '. Please contact the barangay office for your notice and details.']);
        if (!empty($sms['success'])) $sent++;
      }
      $message .= $attempted === 0 ? ' No mobile numbers were provided for SMS.' : " SMS submitted: $sent of $attempted. Check SMS History for delivery results.";
    } catch (Throwable $error) { $message .= ' The schedule is saved, but SMS could not be confirmed. Check SMS History before retrying.'; }
  }
  if ($json) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => true, 'message' => $message] + $result); }
  else header('Location: ' . $page . '&husay=' . (int) $result['id'] . '&status=updated', true, 303);
} catch (Throwable $error) {
  $message = $error instanceof InvalidArgumentException ? $error->getMessage() : 'The schedule could not be saved. Please try again.';
  if ($json) { if (http_response_code() !== 403) http_response_code(422); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'message' => $message]); }
  else { $_SESSION['configuration_error'] = $message; header('Location: ' . $page . '&status=validation-error', true, 303); }
}
