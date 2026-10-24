<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/qr-sms-helpers.php';
require_once __DIR__ . '/sms-automatic-templates.php';

function sms_workspace_schema(): void {
  static $ready = false;
  if ($ready) return;
  $columns = barangay_db()->query('SHOW COLUMNS FROM sms_logs')->fetchAll(PDO::FETCH_COLUMN);
  $definitions = [
    'source' => "VARCHAR(20) NOT NULL DEFAULT 'Legacy'",
    'delivery_state' => 'VARCHAR(30) DEFAULT NULL',
    'failure_reason' => 'VARCHAR(255) DEFAULT NULL',
    'provider_id' => 'VARCHAR(150) DEFAULT NULL',
    'delivery_key' => 'VARCHAR(100) DEFAULT NULL',
    'retry_of' => 'INT UNSIGNED DEFAULT NULL',
    'request_code' => 'VARCHAR(30) DEFAULT NULL',
  ];
  foreach ($definitions as $column => $definition) {
    if (!in_array($column, $columns, true)) barangay_db()->exec("ALTER TABLE sms_logs ADD COLUMN $column $definition");
  }
  $indexes = barangay_db()->query('SHOW INDEX FROM sms_logs')->fetchAll();
  if (!in_array('uq_sms_delivery_key', array_column($indexes, 'Key_name'), true)) {
    barangay_db()->exec('ALTER TABLE sms_logs ADD UNIQUE KEY uq_sms_delivery_key (delivery_key)');
  }
  $ready = true;
}

function sms_templates(): array {
  return [
    'custom' => 'Custom message',
    'ready' => 'Document ready for pickup',
    'reminder' => 'Pickup reminder',
    'status' => 'Document status update',
    'complaint' => 'Complaint update',
    'announcement' => 'Announcement',
    'emergency' => 'Emergency alert',
  ];
}

function sms_full_message(string $message, ?array $settings = null): string {
  $settings = $settings ?? SMSAlertsHelper::getSettings();
  $sender = SMSAlertsHelper::cleanSenderName((string) ($settings['sms_sender_name'] ?? 'BRGY LUNA'));
  $message = trim($message);
  return $sender !== '' && stripos($message, $sender . ':') !== 0 ? $sender . ': ' . $message : $message;
}

function sms_document_message(string $event, string $type, string $reference, float $fee, string $status = ''): string {
  $document = $type . ' (' . $reference . ')';
  $amount = 'PHP ' . number_format(max(0, $fee), 2);
  if ($event === 'released') return 'Your ' . $document . ' has been released. Document fee: ' . $amount . '. Thank you.';
  if ($event === 'status') return 'Your ' . $document . ' status is now ' . $status . '. Document fee: ' . $amount . '. Check your account for details.';
  $prefix = $event === 'reminder' ? 'Reminder: your ' : 'Your ';
  return $prefix . $document . ' is ready for pickup at the Barangay Luna office. Document fee: ' . $amount . '. Please bring your resident ID or QR code.';
}

function sms_retry_problem(array $row): ?string {
  if (($row['delivery_state'] ?? '') !== 'Failed') return 'Only confirmed failed attempts can be retried.';
  if ((int) ($row['age_seconds'] ?? 0) < 60) return 'Wait one minute after a failed attempt before retrying.';
  if (!empty($row['retry_id'])) return 'This attempt has already been retried. Open the newer history record.';
  return null;
}

function sms_result_state(array $result): array {
  if (!empty($result['success'])) return ['Accepted', null];
  if (!empty($result['uncertain'])) return ['Unknown', 'No reliable response from the SMS provider. Check the sender phone or httpSMS before sending again.'];
  $code = (int) ($result['http_status'] ?? 0);
  if ($code) {
    $reason = match ($code) {
      401, 403 => 'httpSMS rejected the credentials. Ask the administrator to check SMS settings.',
      429 => 'httpSMS rate limit reached. Wait before retrying.',
      default => 'httpSMS rejected the request (HTTP ' . $code . '). Check the number and sender configuration.',
    };
  } else {
    // Local validation messages contain no provider response or credentials.
    $reason = (string) ($result['message'] ?? 'SMS could not be submitted.');
  }
  return ['Failed', substr($reason, 0, 255)];
}

function sms_record_send(array $data, ?callable $transport = null): array {
  sms_workspace_schema();
  $db = barangay_db();
  $message = ($data['type'] ?? '') === 'Resident Account SMS' ? trim($data['message']) : sms_full_message($data['message']);
  $insert = $db->prepare("INSERT INTO sms_logs
    (recipient_name, recipient_mobile, message_type, message, status, sent_by, source, delivery_state, delivery_key, retry_of, request_code)
    VALUES (:name, :mobile, :type, :message, 'Pending', :user, :source, 'Submitting', :key, :retry, :request)");
  try {
    $insert->execute([
      ':name' => $data['name'], ':mobile' => $data['mobile'], ':type' => $data['type'], ':message' => $message,
      ':user' => $data['user'], ':source' => $data['source'], ':key' => $data['key'],
      ':retry' => $data['retry_of'] ?? null, ':request' => $data['request_code'] ?? null,
    ]);
  } catch (PDOException $error) {
    if (($error->errorInfo[1] ?? 0) !== 1062) throw $error;
    return ['success' => false, 'duplicate' => true, 'message' => 'This SMS attempt has already been recorded. Check SMS History.'];
  }
  $id = (int) $db->lastInsertId();
  try {
    $result = strlen($message) > 918
      ? ['success' => false, 'message' => 'The filled message exceeds 918 bytes. Shorten the message template before sending.']
      : ($transport ? $transport($data['mobile'], $message, $data['user'], $data['type'])
      : SMSAlertsHelper::sendSMS($data['mobile'], $message, $data['user'], $data['type']));
  } catch (Throwable $error) {
    $result = ['success' => false, 'uncertain' => true];
  }
  [$state, $reason] = sms_result_state($result);
  $update = $db->prepare('UPDATE sms_logs SET status = :status, delivery_state = :state, failure_reason = :reason, provider_id = :provider WHERE id = :id');
  $update->execute([
    ':status' => $state === 'Failed' ? 'Failed' : 'Pending', ':state' => $state, ':reason' => $reason,
    ':provider' => isset($result['sms_id']) ? substr((string) $result['sms_id'], 0, 150) : null, ':id' => $id,
  ]);
  return array_merge($result, ['id' => $id, 'delivery_state' => $state, 'failure_reason' => $reason]);
}

function sms_automatic_document(string $event, string $name, string $mobile, string $type, float $fee, string $reference, int $user, string $requestCode = '', ?callable $transport = null): array {
  $settings = SMSAlertsHelper::getSettings();
  if (($settings['sms_enabled'] ?? '1') !== '1' || ($settings['sms_auto_' . $event] ?? '1') !== '1') {
    return ['success' => false, 'skipped' => true];
  }
  return sms_record_send([
    'name' => $name, 'mobile' => $mobile, 'type' => ['ready'=>'Document ready for pickup','released'=>'Document released','received'=>'Request received','rejected'=>'Request rejected'][$event] ?? 'Document notification',
    'message' => sms_render_automatic($event, compact('name', 'type', 'reference', 'fee'), $settings), 'user' => $user, 'source' => 'Automatic',
    'key' => 'auto:' . $event . ':' . hash('sha256', $requestCode !== '' ? 'request:' . $requestCode : 'issued:' . $reference),
    'request_code' => $requestCode !== '' ? $requestCode : null,
  ], $transport);
}

function sms_notify_request_ready(string $code, int $user): array {
  $stmt = barangay_db()->prepare('SELECT d.*, COALESCE(u.mobile, "") AS current_mobile FROM document_requests d LEFT JOIN users u ON u.id = d.resident_user_id WHERE d.request_code = :code');
  $stmt->execute([':code' => $code]);
  $row = $stmt->fetch();
  if (!$row) return ['success' => false];
  return sms_automatic_document('ready', $row['resident_name'], $row['current_mobile'] ?: $row['contact_mobile'], $row['document_type'], (float) $row['fee_amount'], $code, $user, $code);
}
