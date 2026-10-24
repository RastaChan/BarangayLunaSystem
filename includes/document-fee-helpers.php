<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/qr-sms-helpers.php';
require_once __DIR__ . '/sms-workspace.php';

function barangay_document_fee_key(string $type): string {
  return strtolower(preg_replace('/\s+/', ' ', trim($type)));
}

function barangay_ensure_document_fee_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_db()->exec(
    'CREATE TABLE IF NOT EXISTS document_fees (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      document_type VARCHAR(100) NOT NULL,
      fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      updated_by INT UNSIGNED DEFAULT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_document_fees_type (document_type),
      CONSTRAINT fk_document_fees_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    )'
  );

  $definitions = [
    'fee_amount' => 'ALTER TABLE document_requests ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER document_type',
    'released_sms_sent_at' => 'ALTER TABLE document_requests ADD COLUMN released_sms_sent_at DATETIME DEFAULT NULL AFTER status',
  ];
  $requestColumns = barangay_db()->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests'
       AND COLUMN_NAME IN ('fee_amount', 'released_sms_sent_at')"
  )->fetchAll(PDO::FETCH_COLUMN);
  foreach ($definitions as $column => $sql) {
    if (!in_array($column, $requestColumns, true)) {
      barangay_db()->exec($sql);
    }
  }

  $issuedColumns = barangay_db()->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'issued_documents'
       AND COLUMN_NAME = 'fee_amount'"
  )->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('fee_amount', $issuedColumns, true)) {
    barangay_db()->exec('ALTER TABLE issued_documents ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER purpose');
  }
  foreach (['document_requests', 'issued_documents'] as $table) {
    $columns = barangay_db()->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('copies', $columns, true)) {
      barangay_db()->exec("ALTER TABLE `$table` ADD copies INT UNSIGNED NOT NULL DEFAULT 1");
    }
    if (!in_array('unit_fee', $columns, true)) {
      barangay_db()->exec("ALTER TABLE `$table` ADD unit_fee DECIMAL(10,2) DEFAULT NULL");
    }
  }
  $ready = true;
}

function barangay_document_copies($value): int {
  if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string) $value) || (int) $value > 100) {
    throw new InvalidArgumentException('Enter between 1 and 100 copies.');
  }
  return (int) $value;
}

function barangay_document_total(float $unitFee, int $copies): float {
  $copies = barangay_document_copies($copies);
  $cents = (int) round($unitFee * 100);
  if ($cents < 0 || $cents * $copies > 9999999999) {
    throw new InvalidArgumentException('The total document fee is too large.');
  }
  return $cents * $copies / 100;
}

function barangay_document_fee_map(): array {
  barangay_ensure_document_fee_schema();
  $rows = barangay_db()->query('SELECT document_type, fee_amount FROM document_fees')->fetchAll();
  $fees = [];
  foreach ($rows as $row) {
    $fees[barangay_document_fee_key((string) $row['document_type'])] = (float) $row['fee_amount'];
  }
  return $fees;
}

function barangay_document_fee_for_type(string $type): ?float {
  $fees = barangay_document_fee_map();
  $key = barangay_document_fee_key($type);
  return array_key_exists($key, $fees) ? (float) $fees[$key] : null;
}

function barangay_format_document_fee(?float $amount): string {
  return $amount === null ? 'Not set' : 'PHP ' . number_format(max(0, $amount), 2);
}

function barangay_save_document_fee(string $type, float $amount, int $userId): void {
  barangay_ensure_document_fee_schema();
  $type = preg_replace('/\s+/', ' ', trim($type));
  $stmt = barangay_db()->prepare(
    'INSERT INTO document_fees (document_type, fee_amount, updated_by, updated_at)
     VALUES (:type, :amount, :user_id, NOW())
     ON DUPLICATE KEY UPDATE fee_amount = VALUES(fee_amount), updated_by = VALUES(updated_by), updated_at = NOW()'
  );
  $stmt->execute([':type' => $type, ':amount' => max(0, $amount), ':user_id' => $userId]);
}

function barangay_validate_document_fee_batch(array $selected, array $available, string $amount): array {
  if (!$selected || count($selected) > count($available) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) {
    throw new InvalidArgumentException('Select valid documents and enter a valid fee.');
  }
  $allowed = [];
  foreach ($available as $type) $allowed[barangay_document_fee_key($type)] = $type;
  $types = [];
  foreach ($selected as $type) {
    if (!is_string($type) || !isset($allowed[barangay_document_fee_key($type)])) {
      throw new InvalidArgumentException('Invalid document type.');
    }
    $key = barangay_document_fee_key($type);
    $types[$key] = $allowed[$key];
  }
  return array_values($types);
}

function barangay_send_document_release_sms(string $residentName, string $mobile, string $documentType, float $feeAmount, string $reference, int $sentBy, string $requestCode = ''): array {
  return sms_automatic_document('released', $residentName, $mobile, $documentType, $feeAmount, $reference, $sentBy, $requestCode);
}

function barangay_notify_document_request_released(string $requestCode, int $sentBy, string $reference = ''): array {
  barangay_ensure_document_fee_schema();
  $stmt = barangay_db()->prepare(
    'SELECT document_requests.request_code, document_requests.resident_name, document_requests.contact_mobile,
            document_requests.document_type, document_requests.fee_amount,
            COALESCE(users.mobile, "") AS resident_mobile
     FROM document_requests
     LEFT JOIN users ON users.id = document_requests.resident_user_id
     WHERE document_requests.request_code = :id
     LIMIT 1'
  );
  $stmt->execute([':id' => $requestCode]);
  $request = $stmt->fetch();
  if (!$request) {
    return ['success' => false, 'message' => 'Document request was not found.'];
  }

  $mobile = trim((string) ($request['resident_mobile'] ?: $request['contact_mobile']));
  $result = barangay_send_document_release_sms(
    (string) $request['resident_name'],
    $mobile,
    (string) $request['document_type'],
    (float) $request['fee_amount'],
    $reference !== '' ? $reference : ('Request ' . $requestCode),
    $sentBy,
    $requestCode
  );
  if (!empty($result['success'])) {
    $sent = barangay_db()->prepare('UPDATE document_requests SET released_sms_sent_at = NOW() WHERE request_code = :id');
    $sent->execute([':id' => $requestCode]);
  }
  return $result;
}
?>
