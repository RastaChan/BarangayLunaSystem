<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/document-fee-helpers.php';

function barangay_ensure_document_payment_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_db()->exec(
    'CREATE TABLE IF NOT EXISTS document_payments (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      payment_code VARCHAR(40) NOT NULL UNIQUE,
      request_code VARCHAR(30) DEFAULT NULL,
      document_number VARCHAR(60) DEFAULT NULL,
      resident_user_id INT UNSIGNED DEFAULT NULL,
      resident_name VARCHAR(150) NOT NULL,
      document_type VARCHAR(100) NOT NULL,
      amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      amount_paid DECIMAL(10,2) DEFAULT NULL,
      payment_method VARCHAR(40) NOT NULL DEFAULT "Walk-in Cash",
      receipt_number VARCHAR(80) DEFAULT NULL,
      status ENUM("Pending", "Paid", "Voided") NOT NULL DEFAULT "Pending",
      received_by INT UNSIGNED DEFAULT NULL,
      received_at DATETIME DEFAULT NULL,
      notes TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_document_payments_status (status),
      INDEX idx_document_payments_request (request_code),
      INDEX idx_document_payments_document (document_number),
      CONSTRAINT fk_document_payments_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT fk_document_payments_receiver FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
    )'
  );
  $ready = true;
}

function barangay_next_payment_code(): string {
  $prefix = 'PAY-' . date('Ymd') . '-';
  $stmt = barangay_db()->prepare('SELECT payment_code FROM document_payments WHERE payment_code LIKE :prefix ORDER BY id DESC LIMIT 1');
  $stmt->execute([':prefix' => $prefix . '%']);
  $last = (string) ($stmt->fetchColumn() ?: '');
  $number = preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;
  return $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

function barangay_find_document_payment(?string $requestCode, ?string $documentNumber): ?array {
  if ($requestCode === '' || $requestCode === null) {
    $requestCode = null;
  }
  if ($documentNumber === '' || $documentNumber === null) {
    $documentNumber = null;
  }
  if ($requestCode === null && $documentNumber === null) {
    return null;
  }
  $stmt = barangay_db()->prepare(
    'SELECT * FROM document_payments
     WHERE (:request_code_present IS NOT NULL AND request_code = :request_code)
        OR (:document_number_present IS NOT NULL AND document_number = :document_number)
     ORDER BY id DESC LIMIT 1'
  );
  $stmt->execute([
    ':request_code_present' => $requestCode,
    ':request_code' => $requestCode,
    ':document_number_present' => $documentNumber,
    ':document_number' => $documentNumber,
  ]);
  return $stmt->fetch() ?: null;
}

function barangay_ensure_released_document_payment(array $record): int {
  barangay_ensure_document_payment_schema();
  $requestCode = trim((string) ($record['request_code'] ?? ''));
  $documentNumber = trim((string) ($record['document_number'] ?? ''));
  $existing = barangay_find_document_payment($requestCode, $documentNumber);
  if ($existing && $existing['status'] !== 'Voided') {
    $stmt = barangay_db()->prepare(
      'UPDATE document_payments
       SET request_code = COALESCE(NULLIF(:request_code, ""), request_code),
           document_number = COALESCE(NULLIF(:document_number, ""), document_number),
           amount_due = :amount_due
       WHERE id = :id'
    );
    $stmt->execute([
      ':request_code' => $requestCode,
      ':document_number' => $documentNumber,
      ':amount_due' => max(0, (float) ($record['amount_due'] ?? 0)),
      ':id' => $existing['id'],
    ]);
    return (int) $existing['id'];
  }

  $stmt = barangay_db()->prepare(
    'INSERT INTO document_payments
      (payment_code, request_code, document_number, resident_user_id, resident_name, document_type, amount_due, payment_method, status, created_at)
     VALUES
      (:payment_code, :request_code, :document_number, :resident_user_id, :resident_name, :document_type, :amount_due, "Walk-in Cash", "Pending", NOW())'
  );
  $stmt->execute([
    ':payment_code' => barangay_next_payment_code(),
    ':request_code' => $requestCode !== '' ? $requestCode : null,
    ':document_number' => $documentNumber !== '' ? $documentNumber : null,
    ':resident_user_id' => $record['resident_user_id'] ?? null,
    ':resident_name' => $record['resident_name'] ?? 'Resident',
    ':document_type' => $record['document_type'] ?? 'Document',
    ':amount_due' => max(0, (float) ($record['amount_due'] ?? 0)),
  ]);
  return (int) barangay_db()->lastInsertId();
}

function barangay_create_payment_for_released_request(string $requestCode, int $releasedBy, string $documentNumber = ''): ?int {
  barangay_ensure_document_fee_schema();
  barangay_ensure_document_payment_schema();
  $stmt = barangay_db()->prepare(
    'SELECT request_code, resident_user_id, resident_name, document_type, fee_amount
     FROM document_requests WHERE request_code = :id LIMIT 1'
  );
  $stmt->execute([':id' => $requestCode]);
  $request = $stmt->fetch();
  if (!$request) {
    return null;
  }
  return barangay_ensure_released_document_payment([
    'request_code' => $request['request_code'],
    'document_number' => $documentNumber,
    'resident_user_id' => $request['resident_user_id'],
    'resident_name' => $request['resident_name'],
    'document_type' => $request['document_type'],
    'amount_due' => $request['fee_amount'],
    'released_by' => $releasedBy,
  ]);
}

function barangay_record_walk_in_payment(int $paymentId, float $amountPaid, string $receiptNumber, string $notes, int $receivedBy): bool {
  barangay_ensure_document_payment_schema();
  $read = barangay_db()->prepare('SELECT amount_due, status FROM document_payments WHERE id = ?');
  $read->execute([$paymentId]); $payment = $read->fetch();
  if (!$payment || $payment['status'] !== 'Pending' || !is_finite($amountPaid) || round($amountPaid * 100) !== round((float) $payment['amount_due'] * 100) || $amountPaid > 99999999.99 || mb_strlen($receiptNumber) > 80) {
    throw new InvalidArgumentException('Select an unpaid entry. Amount received must match the document fee set by Admin.');
  }
  $stmt = barangay_db()->prepare(
    'UPDATE document_payments
     SET amount_paid = :amount_paid,
         receipt_number = :receipt_number,
         notes = :notes,
         payment_method = "Walk-in Cash",
         status = "Paid",
         received_by = :received_by,
         received_at = NOW()
     WHERE id = :id AND status = "Pending"'
  );
  $stmt->execute([
    ':amount_paid' => max(0, (float) $payment['amount_due']),
    ':receipt_number' => $receiptNumber !== '' ? $receiptNumber : null,
    ':notes' => $notes !== '' ? $notes : null,
    ':received_by' => $receivedBy,
    ':id' => $paymentId,
  ]);
  return $stmt->rowCount() > 0;
}
?>
