<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/qr-sms-helpers.php';

function sms_verification_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_db()->exec("CREATE TABLE IF NOT EXISTS sms_verifications (
    user_id INT UNSIGNED PRIMARY KEY, code_hash VARCHAR(255) NOT NULL DEFAULT '',
    token_hash VARCHAR(64) NOT NULL DEFAULT '', mobile VARCHAR(30) NOT NULL DEFAULT '',
    expires_at BIGINT NOT NULL DEFAULT 0, last_sent BIGINT NOT NULL DEFAULT 0,
    window_start BIGINT NOT NULL DEFAULT 0, sends INT NOT NULL DEFAULT 0, attempts INT NOT NULL DEFAULT 0,
    verified_mobile VARCHAR(30) NOT NULL DEFAULT '', verified_at BIGINT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $ready = true;
}

function sms_verification_required(array $user): bool {
  $settings = barangay_security_settings();
  if (($settings['sms_2fa'] ?? '0') === '1') return true;
  if (($settings['mobile_verification'] ?? '0') !== '1') return false;
  sms_verification_schema();
  $stmt = barangay_db()->prepare('SELECT verified_mobile FROM sms_verifications WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  $phone = SMSAlertsHelper::normalizePhilippineMobile($user['contact'] ?? '');
  return $phone === '' || $stmt->fetchColumn() !== $phone;
}

function sms_verification_begin(array $user): void {
  barangay_start_session();
  session_regenerate_id(true);
  $stmt = barangay_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
  $stmt->execute([$user['id']]);
  unset($_SESSION['barangay_user']);
  $_SESSION['pending_verification'] = ['id' => $user['id'], 'password_hash' => $stmt->fetchColumn(), 'started' => time(), 'token' => bin2hex(random_bytes(32))];
  $_SESSION['verification_csrf'] = bin2hex(random_bytes(32));
}

function sms_verification_account(array $pending): array {
  if (time() - (int) ($pending['started'] ?? 0) > 900) throw new RuntimeException('Verification expired. Sign in again.');
  $stmt = barangay_db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'Active'");
  $stmt->execute([$pending['id'] ?? 0]);
  $row = $stmt->fetch();
  if (!$row || !hash_equals($row['password_hash'], (string) ($pending['password_hash'] ?? ''))) throw new RuntimeException('The account changed. Sign in again.');
  return $row;
}

function sms_verification_send(array $pending, ?callable $transport = null): void {
  sms_verification_schema();
  $row = sms_verification_account($pending);
  $mobile = SMSAlertsHelper::normalizePhilippineMobile($row['mobile'] ?? '');
  if ($mobile === '') throw new RuntimeException('No valid mobile number is recorded. Ask the administrator to update your account.');
  $db = barangay_db();
  $ownsTransaction = !$db->inTransaction();
  if ($ownsTransaction) $db->beginTransaction();
  try {
    $db->prepare('INSERT IGNORE INTO sms_verifications (user_id) VALUES (?)')->execute([$row['id']]);
    $stmt = $db->prepare('SELECT * FROM sms_verifications WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$row['id']]);
    $state = $stmt->fetch();
    $now = time();
    if ($now - (int) $state['last_sent'] < 60) throw new RuntimeException('Wait 60 seconds before requesting another code.');
    $fresh = $now - (int) $state['window_start'] >= 900;
    if (!$fresh && ((int) $state['sends'] >= 3 || (int) $state['attempts'] >= 5)) throw new RuntimeException('Verification limit reached. Try again after 15 minutes.');
    $code = (string) random_int(100000, 999999);
    $stmt = $db->prepare('UPDATE sms_verifications SET code_hash = ?, token_hash = ?, mobile = ?, expires_at = ?, last_sent = ?, window_start = ?, sends = ?, attempts = ? WHERE user_id = ?');
    $stmt->execute([password_hash($code, PASSWORD_DEFAULT), hash('sha256', $pending['token']), $mobile, $now + 300, $now, $fresh ? $now : $state['window_start'], $fresh ? 1 : (int) $state['sends'] + 1, $fresh ? 0 : $state['attempts'], $row['id']]);
    if ($ownsTransaction) $db->commit();
  } catch (Throwable $e) { if ($ownsTransaction && $db->inTransaction()) $db->rollBack(); throw $e; }
  // Never put a usable verification code into the staff-visible SMS history.
  $message = 'Your sign-in verification code is ' . $code . '. It expires in 5 minutes. Do not share this code.';
  $result = $transport ? $transport($mobile, $message) : SMSAlertsHelper::sendSMS($mobile, $message, $row['id'], 'Sign-in verification');
  if (empty($result['success'])) throw new RuntimeException('The SMS provider did not confirm submission. Check your phone; wait 60 seconds before requesting a new code.');
}

function sms_verification_check(array $pending, string $code): array {
  sms_verification_schema();
  $row = sms_verification_account($pending);
  $db = barangay_db();
  $ownsTransaction = !$db->inTransaction();
  if ($ownsTransaction) $db->beginTransaction();
  try {
    $stmt = $db->prepare('SELECT * FROM sms_verifications WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$row['id']]);
    $state = $stmt->fetch();
    if (!$state || !$state['code_hash'] || $state['expires_at'] < time() || $state['attempts'] >= 5 || !hash_equals($state['token_hash'], hash('sha256', $pending['token'])) || $state['mobile'] !== SMSAlertsHelper::normalizePhilippineMobile($row['mobile'] ?? '')) throw new RuntimeException('Code expired or verification is locked. Request a new code or sign in again.');
    $db->prepare('UPDATE sms_verifications SET attempts = attempts + 1 WHERE user_id = ?')->execute([$row['id']]);
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $state['code_hash'])) {
      if ($ownsTransaction) $db->commit();
      throw new RuntimeException('Incorrect verification code.');
    }
    $db->prepare("UPDATE sms_verifications SET code_hash = '', token_hash = '', expires_at = 0, verified_mobile = mobile, verified_at = ? WHERE user_id = ?")->execute([time(), $row['id']]);
    if ($ownsTransaction) $db->commit();
    return barangay_format_user($row);
  } catch (Throwable $e) { if ($ownsTransaction && $db->inTransaction()) $db->rollBack(); throw $e; }
}
