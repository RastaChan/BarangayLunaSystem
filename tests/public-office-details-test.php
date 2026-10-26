<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/public-office-details.php';
$db = barangay_db();
$db->beginTransaction();
try {
  $stmt = $db->prepare('INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
  foreach (['contact_number'=>'09123456789','hotline_number'=>'09987654321','office_hours'=>'Tuesday to Saturday, 9 AM to 4 PM','sms_api_key'=>'PRIVATE-TEST-KEY'] as $key=>$value) $stmt->execute([$key,$value]);
  $details = barangay_public_office_details();
  if (($details['office_hours'] ?? '') !== 'Tuesday to Saturday, 9 AM to 4 PM') throw new RuntimeException('Public hours do not follow saved settings');
  if (isset($details['sms_api_key'])) throw new RuntimeException('Public office details exposed private configuration');
  if (barangay_public_contact_text($details) !== '09123456789 / 09987654321') throw new RuntimeException('Public phone numbers do not follow saved settings');
  if (barangay_public_contact_text([]) !== 'Please visit the barangay office for contact details.') throw new RuntimeException('Missing contact information uses fictional details');
  ob_start(); require __DIR__ . '/../includes/requirements-modal-body.php'; $html = ob_get_clean();
  if (!str_contains($html, 'Tuesday to Saturday, 9 AM to 4 PM') || str_contains($html, '000-0000') || str_contains($html, 'PRIVATE-TEST-KEY')) throw new RuntimeException('Public requirements show incorrect details');
  echo "PASS: public contact details and hours follow saved settings; secrets stay private; missing details use an honest fallback.\n";
} finally { $db->rollBack(); }
