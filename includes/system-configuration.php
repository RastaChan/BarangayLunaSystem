<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/record-attachments.php';

function barangay_security_settings(): array {
  $stmt = barangay_db()->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('session_timeout','password_uppercase','password_numbers','password_special','sms_2fa','mobile_verification')");
  return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function barangay_validate_password(string $password, ?array $settings = null): void {
  $settings ??= barangay_security_settings();
  if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) throw new InvalidArgumentException('Use a password between 8 and 72 bytes without null characters.');
  foreach (['password_uppercase' => ['/[A-Z]/', 'an uppercase letter'], 'password_numbers' => ['/\d/', 'a number'], 'password_special' => ['/[^a-zA-Z0-9\s]/', 'a special character']] as $key => [$pattern, $label]) {
    if (($settings[$key] ?? '0') === '1' && !preg_match($pattern, $password)) throw new InvalidArgumentException('The password must contain ' . $label . '.');
  }
}

function barangay_setting_write(string $key, string $value, int $user): void {
  $stmt = barangay_db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
  $stmt->execute([$key, $value, $user]);
}

function barangay_configuration_values(array $input, array $existing, array $user): array {
  $allowed = ['barangay_name','barangay_code','barangay_address','contact_number','hotline_number','office_hours','holiday_schedule','sms_provider','sms_sender_name','sms_api_key','sms_sender_number','sms_enabled','session_timeout','password_uppercase','password_numbers','password_special','sms_2fa','mobile_verification'];
  $values = [];
  $allowed[] = 'portal_public_url';
  foreach ($input as $key => $value) {
    if (!in_array($key, $allowed, true) || !is_scalar($value) || strlen((string) $value) > 2000) throw new InvalidArgumentException('Invalid settings value.');
    $values[$key] = trim((string) $value);
  }
  if (($values['sms_api_key'] ?? '') === '') unset($values['sms_api_key']);
  $merged = array_replace($existing, $values);
  $portal = $merged['portal_public_url'] ?? '';
  if ($portal !== '' && (strlen($portal) > 480 || !filter_var($portal, FILTER_VALIDATE_URL) || !in_array(parse_url($portal, PHP_URL_SCHEME), ['http','https'], true) || parse_url($portal, PHP_URL_QUERY) !== null || parse_url($portal, PHP_URL_FRAGMENT) !== null || parse_url($portal, PHP_URL_USER) !== null)) {
    throw new InvalidArgumentException('Use a valid portal URL including its folder, without a query, fragment or login details.');
  }
  if (!ctype_digit((string) ($merged['session_timeout'] ?? '30')) || (int) ($merged['session_timeout'] ?? 30) < 10 || (int) ($merged['session_timeout'] ?? 30) > 240) throw new InvalidArgumentException('Session timeout must be between 10 and 240 minutes.');
  foreach (['sms_enabled','password_uppercase','password_numbers','password_special','sms_2fa','mobile_verification'] as $key) {
    if (isset($values[$key]) && !in_array($values[$key], ['0','1'], true)) throw new InvalidArgumentException('Invalid security option.');
  }
  if (strtolower($merged['sms_provider'] ?? 'httpsms') !== 'httpsms') throw new InvalidArgumentException('Only httpSMS is supported.');
  if (($merged['sms_2fa'] ?? '0') === '1' || ($merged['mobile_verification'] ?? '0') === '1') {
    require_once __DIR__ . '/qr-sms-helpers.php';
    $phone = barangay_db()->prepare('SELECT mobile FROM users WHERE id = ?');
    $phone->execute([$user['id']]);
    if (($merged['sms_enabled'] ?? '1') !== '1' || empty($merged['sms_api_key']) || SMSAlertsHelper::normalizePhilippineMobile($merged['sms_sender_number'] ?? '') === '' || SMSAlertsHelper::normalizePhilippineMobile((string) $phone->fetchColumn()) === '') {
      throw new InvalidArgumentException('SMS verification requires enabled httpSMS, an API key, a valid sender number and a valid mobile number on your administrator profile.');
    }
  }
  return $values;
}

function barangay_logo_upload(array $file): ?array {
  $image = barangay_read_attachment_upload($file);
  if (!$image) return null;
  $bytes = base64_decode($image['content'], true);
  $size = getimagesizefromstring($bytes);
  if (!in_array($image['mime'], ['image/png','image/jpeg'], true) || !$size || $size[0] > 4000 || $size[1] > 4000 || strlen($bytes) > 2 * 1024 * 1024) {
    throw new InvalidArgumentException('Use a PNG or JPG logo up to 2 MB and 4000 x 4000 pixels.');
  }
  return $image;
}

function barangay_logo_save(?array $image, bool $remove, int $user): void {
  if ($image && $remove) throw new InvalidArgumentException('Choose a new logo or restore the default, not both.');
  if (!$image && !$remove) return;
  barangay_db()->exec("DELETE FROM record_attachments WHERE record_type = 'branding' AND record_code = 'logo'");
  if ($image) {
    $stmt = barangay_db()->prepare("INSERT INTO record_attachments (record_type, record_code, owner_id, filename, mime_type, content_base64) VALUES ('branding', 'logo', ?, ?, ?, ?)");
    $stmt->execute([$user, $image['filename'], $image['mime'], $image['content']]);
  }
}
