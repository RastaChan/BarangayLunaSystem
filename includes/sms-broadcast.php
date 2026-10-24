<?php
require_once __DIR__ . '/sms-workspace.php';

function sms_broadcast_recipients(array $rows): array {
  $recipients = []; $skipped = 0; $duplicates = 0;
  foreach ($rows as $row) {
    $mobile = SMSAlertsHelper::normalizePhilippineMobile((string) ($row['mobile'] ?? ''));
    if ($mobile === '') { $skipped++; continue; }
    if (isset($recipients[$mobile])) { $duplicates++; continue; }
    $recipients[$mobile] = ['name' => $row['full_name'], 'mobile' => $mobile];
  }
  return ['recipients' => array_values($recipients), 'skipped' => $skipped, 'duplicates' => $duplicates];
}

function sms_broadcast_audience(string $audience): array {
  if (!in_array($audience, ['all', 'residents'], true)) throw new RuntimeException('Choose a valid audience.');
  $where = $audience === 'residents' ? " AND role = 'resident'" : " AND role IN ('admin', 'staff', 'resident')";
  return sms_broadcast_recipients(barangay_db()->query("SELECT full_name, mobile FROM users WHERE status = 'Active'$where ORDER BY id")->fetchAll());
}

function sms_broadcast_types(): array {
  return ['disaster' => 'Disaster alert', 'emergency' => 'Emergency alert', 'announcement' => 'Community announcement', 'custom' => 'General advisory'];
}

function sms_broadcast_message(string $message, string $type): string {
  if (!isset(sms_broadcast_types()[$type])) throw new RuntimeException('Choose an alert category.');
  $message = trim($message);
  if ($message === '' || strlen(sms_full_message($message)) > 918) throw new RuntimeException('Enter a message within 918 bytes, including the sender name.');
  return sms_full_message($message);
}

function sms_broadcast_progress(array $batch): array {
  return ['id' => $batch['id'], 'total' => count($batch['recipients']), 'processed' => $batch['next'],
    'counts' => $batch['counts'], 'done' => $batch['next'] >= count($batch['recipients'])];
}
