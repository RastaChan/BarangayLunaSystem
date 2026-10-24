<?php
require_once __DIR__ . '/sms-workspace.php';

function resident_account_notification_snapshot(string $code): ?array {
  $stmt = barangay_db()->prepare("SELECT id, full_name, mobile, status, verification_status, qr_status, qr_code, qr_generated_at FROM users WHERE user_code = ? AND role = 'resident'");
  $stmt->execute([$code]);
  return $stmt->fetch() ?: null;
}

function resident_account_notification(string $code, int $actor, ?array $before = null, ?callable $transport = null): array {
  $resident = resident_account_notification_snapshot($code);
  if (!$resident || ($before !== null && $before === $resident)) return ['skipped' => true];
  return sms_record_send([
    'name' => $resident['full_name'], 'mobile' => $resident['mobile'] ?? '',
    'type' => 'Resident Account SMS', 'source' => 'Automatic', 'user' => $actor,
    'message' => 'Your Barangay Luna Smart Portal account has been created or updated. Please keep your login details private',
    'key' => $before === null ? 'resident-account-created:' . $resident['id'] : 'resident-account-updated:' . $resident['id'] . ':' . bin2hex(random_bytes(16)),
  ], $transport);
}

function resident_account_notification_flash(string $code, int $actor, ?array $before = null): void {
  try {
    $result = resident_account_notification($code, $actor, $before);
    if (!empty($result['skipped'])) return;
    $_SESSION['registration_sms_flash'] = !empty($result['success'])
      ? ['success', 'Account saved. The account notification was submitted to the SMS provider.']
      : ['error', 'Account saved, but the SMS was not confirmed. Check SMS History for the result before retrying.'];
  } catch (Throwable $e) {
    $_SESSION['registration_sms_flash'] = ['error', 'Account saved, but the SMS notification could not be recorded or sent. Check SMS settings and history.'];
  }
}
