<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms-broadcast.php';
require_once __DIR__ . '/includes/staff-functions.php';
header('Content-Type: application/json');
try {
  $user = barangay_current_user();
  if (!$user || !in_array($user['role'] ?? '', ['admin', 'staff'], true)) throw new RuntimeException('Please sign in as Admin or Staff.');
  $account = barangay_db()->prepare("SELECT role FROM users WHERE id = ? AND status = 'Active'");
  $account->execute([$user['id']]);
  if ($account->fetchColumn() !== $user['role']) throw new RuntimeException('This account is no longer authorized.');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['sms_csrf']) || !hash_equals($_SESSION['sms_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new RuntimeException('Session expired. Refresh before sending.');
  sms_workspace_schema();
  $key = 'sms_broadcast_' . $user['id'];
  $action = $_POST['action'] ?? '';
  if ($action === 'prepare') {
    $old = $_SESSION[$key] ?? null;
    if ($old && !empty($old['confirmed']) && $old['next'] < count($old['recipients'])) throw new RuntimeException('Finish the current broadcast before starting another.');
    $type = (string) ($_POST['type'] ?? '');
    $message = sms_broadcast_message((string) ($_POST['message'] ?? ''), $type);
    $audience = (string) ($_POST['audience'] ?? 'all');
    $targets = sms_broadcast_audience($audience);
    if (!$targets['recipients']) throw new RuntimeException('There are no active recipients with valid mobile numbers.');
    $_SESSION[$key] = ['id' => bin2hex(random_bytes(16)), 'type' => $type, 'message' => $message,
      'audience' => $audience, 'recipients' => $targets['recipients'], 'next' => 0, 'confirmed' => false,
      'counts' => ['Accepted' => 0, 'Failed' => 0, 'Unknown' => 0, 'Skipped' => 0]];
    echo json_encode(['success' => true, 'review' => ['id' => $_SESSION[$key]['id'], 'message' => $message,
      'total' => count($targets['recipients']), 'skipped' => $targets['skipped'], 'duplicates' => $targets['duplicates']]]);
    exit;
  }
  $batch = &$_SESSION[$key];
  if (!$batch || !hash_equals($batch['id'], (string) ($_POST['id'] ?? ''))) throw new RuntimeException('Broadcast session expired or changed. Review the alert again.');
  if ($action === 'confirm') {
    if (($_POST['confirmed'] ?? '') !== '1') throw new RuntimeException('Confirm the audience and message first.');
    if (sms_full_message($batch['message']) !== $batch['message']) throw new RuntimeException('Sender settings changed. Review a new alert.');
    if (!$batch['confirmed']) staff_log($user, 'Confirmed broadcast ' . $batch['id'] . ' to ' . count($batch['recipients']) . ' mobile numbers', 'SMS Alerts');
    $batch['confirmed'] = true;
  } elseif ($action === 'step') {
    if (!$batch['confirmed']) throw new RuntimeException('Review and confirm this alert first.');
    // Session locking serializes requests; delivery keys prevent repeat sends after interrupted responses.
    if ($batch['next'] < count($batch['recipients'])) {
      $target = $batch['recipients'][$batch['next']];
      $current = sms_broadcast_audience($batch['audience']);
      if (!in_array($target['mobile'], array_column($current['recipients'], 'mobile'), true)) {
        $state = 'Skipped';
      } else {
        if (sms_full_message($batch['message']) !== $batch['message']) throw new RuntimeException('Sender settings changed. Restore the confirmed sender settings before resuming.');
        $deliveryKey = 'broadcast:' . $batch['id'] . ':' . $batch['next'];
        $result = sms_record_send(['name' => $target['name'], 'mobile' => $target['mobile'], 'message' => $batch['message'],
          'type' => sms_broadcast_types()[$batch['type']], 'source' => 'Broadcast', 'user' => (int) $user['id'], 'key' => $deliveryKey]);
        $state = $result['delivery_state'] ?? 'Unknown';
        if (!empty($result['duplicate'])) {
          $existing = barangay_db()->prepare('SELECT delivery_state FROM sms_logs WHERE delivery_key = ?');
          $existing->execute([$deliveryKey]);
          $state = $existing->fetchColumn() ?: 'Unknown';
        }
        if (!isset($batch['counts'][$state])) $state = 'Unknown';
      }
      $batch['counts'][$state]++;
      $batch['next']++;
    }
  } else throw new RuntimeException('Unknown broadcast action.');
  echo json_encode(['success' => true, 'progress' => sms_broadcast_progress($batch)]);
} catch (Throwable $error) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => $error instanceof PDOException ? 'Could not update SMS records. Check History before retrying.' : $error->getMessage()]);
}
