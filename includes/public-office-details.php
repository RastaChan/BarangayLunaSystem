<?php
require_once __DIR__ . '/database.php';
/** Only the office details intended for public visitors; never include private settings. */
function barangay_public_office_details(): array {
  static $details;
  if (isset($details)) return $details;
  $details = [];
  try {
    $rows = barangay_db()->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('barangay_address','contact_number','hotline_number','office_hours')");
    $details = $rows->fetchAll(PDO::FETCH_KEY_PAIR);
  } catch (Throwable $error) {
    // The login screen must remain available if office settings cannot be loaded.
  }
  return $details;
}
function barangay_public_contact_text(array $details): string {
  $numbers = array_unique(array_filter(array_map('trim', [$details['contact_number'] ?? '', $details['hotline_number'] ?? ''])));
  return $numbers ? implode(' / ', $numbers) : 'Please visit the barangay office for contact details.';
}
