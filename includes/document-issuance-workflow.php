<?php
require_once __DIR__ . '/document-payment-helpers.php';
require_once __DIR__ . '/document-request-helpers.php';
require_once __DIR__ . '/document-verification.php';

function barangay_issuance_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_ensure_document_payment_schema();
  barangay_ensure_document_request_detail_columns();
  barangay_document_verification_schema();
  $columns = barangay_db()->query('SHOW COLUMNS FROM issued_documents')->fetchAll(PDO::FETCH_COLUMN);
  $definitions = [
    'request_code' => 'VARCHAR(30) DEFAULT NULL',
    'issuance_status' => "VARCHAR(30) NOT NULL DEFAULT 'Released'",
    'applicant_details' => 'TEXT DEFAULT NULL',
    'contact_mobile' => 'VARCHAR(30) DEFAULT NULL',
    'requirements_checked' => 'TINYINT NOT NULL DEFAULT 0',
    'released_by' => 'INT UNSIGNED DEFAULT NULL',
    'released_at' => 'DATETIME DEFAULT NULL',
    'prepared_at' => 'DATETIME DEFAULT NULL',
  ];
  foreach ($definitions as $column => $definition) {
    if (!in_array($column, $columns, true)) barangay_db()->exec("ALTER TABLE issued_documents ADD `$column` $definition");
  }
  // Link previously generated online documents through their existing payment reference.
  if (!in_array('request_code', $columns, true)) barangay_db()->exec("UPDATE issued_documents i JOIN document_payments p ON p.document_number = i.document_number
    JOIN document_requests r ON r.request_code = p.request_code
    SET i.request_code = r.request_code, i.issuance_status = r.status
    WHERE i.request_code IS NULL AND r.status IN ('Ready for Pickup', 'Released')");
  $ready = true;
}

function barangay_issuance_payment_input(array $input, float $fee): ?array {
  if ($fee <= 0 || ($input['payment_mode'] ?? 'unpaid') === 'unpaid') return null;
  if (($input['payment_mode'] ?? '') !== 'paid') throw new InvalidArgumentException('Select a valid payment option.');
  $amount = trim((string) ($input['amount_paid'] ?? ''));
  if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount) || round((float) $amount * 100) !== round($fee * 100)) {
    throw new InvalidArgumentException('Amount received must match the document fee set by Admin, or choose Unpaid.');
  }
  $receipt = trim((string) ($input['receipt_number'] ?? ''));
  if (mb_strlen($receipt) > 80) throw new InvalidArgumentException('Receipt number is too long.');
  return ['amount' => round($fee, 2), 'receipt' => $receipt];
}

function barangay_issuance_release(int $id, int $staffId, ?array $paymentInput = null): bool {
  barangay_issuance_schema();
  $db = barangay_db();
  $db->beginTransaction();
  try {
    $stmt = $db->prepare('SELECT * FROM issued_documents WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) throw new InvalidArgumentException('Document not found.');
    if ($record['issuance_status'] === 'Released') { $db->commit(); return false; }
    if ($record['issuance_status'] !== 'Ready for Pickup') throw new InvalidArgumentException('This document is not ready for release.');
    if ((float) $record['fee_amount'] > 0) {
      $paymentId = barangay_ensure_released_document_payment([
        'request_code' => $record['request_code'], 'document_number' => $record['document_number'],
        'resident_user_id' => $record['resident_user_id'], 'resident_name' => $record['resident_name'],
        'document_type' => $record['document_type'], 'amount_due' => $record['fee_amount']]);
      $payment = barangay_find_document_payment($record['request_code'], $record['document_number']);
      if (($payment['status'] ?? '') !== 'Paid' && $paymentInput !== null) {
        $capture = barangay_issuance_payment_input($paymentInput, (float) $record['fee_amount']);
        if ($capture) {
          if (($paymentInput['payment_received_confirmed'] ?? '') !== '1') {
            throw new InvalidArgumentException('Confirm that you received the payment before releasing this unpaid document.');
          }
          barangay_record_walk_in_payment($paymentId, $capture['amount'], $capture['receipt'], 'Payment receipt confirmed at release', $staffId);
        }
        $payment = barangay_find_document_payment($record['request_code'], $record['document_number']);
      }
      if (($payment['status'] ?? '') !== 'Paid' || (float) ($payment['amount_paid'] ?? 0) < (float) $record['fee_amount']) {
        throw new InvalidArgumentException('Record the full payment before confirming release.');
      }
    }
    if ($record['request_code']) barangay_document_request_event($record['request_code'], 'pickup');
    $update = $db->prepare("UPDATE issued_documents SET issuance_status = 'Released', released_by = ?, released_at = NOW(), issued_at = NOW() WHERE id = ?");
    $update->execute([$staffId, $id]);
    $db->commit();
    return true;
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
  }
}

function barangay_safe_request_sms(string $event, string $code, int $user): void {
  try {
    $stmt = barangay_db()->prepare('SELECT r.*, COALESCE(u.mobile, "") AS resident_mobile FROM document_requests r LEFT JOIN users u ON u.id = r.resident_user_id WHERE r.request_code = ?');
    $stmt->execute([$code]); $request = $stmt->fetch();
    if (!$request) return;
    $mobile = trim((string) ($request['resident_mobile'] ?: ($request['contact_mobile'] ?? '')));
    if ($mobile === '') return;
    sms_automatic_document($event, $request['resident_name'], $mobile, $request['document_type'], (float) $request['fee_amount'], $code, $user, $code);
  } catch (Throwable $error) {
    error_log('Automatic document notification failed for ' . $code);
  }
}

function barangay_remove_prepared_document(int $id): bool {
  barangay_issuance_schema();
  $db = barangay_db();
  $db->beginTransaction();
  try {
    $stmt = $db->prepare('SELECT * FROM issued_documents WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) throw new InvalidArgumentException('Prepared document not found.');
    if ($record['issuance_status'] === 'Removed') { $db->commit(); return false; }
    if ($record['issuance_status'] !== 'Ready for Pickup') throw new InvalidArgumentException('This document has already been released or is no longer awaiting release. Refresh the list.');
    $db->prepare("UPDATE issued_documents SET issuance_status = 'Removed' WHERE id = ?")->execute([$id]);
    // Keep collected payments and the document itself for audit; unpaid entries are cancelled.
    $db->prepare("UPDATE document_payments SET status = 'Voided' WHERE document_number = ? AND status = 'Pending'")->execute([$record['document_number']]);
    if (!empty($record['request_code'])) {
      $request = $db->prepare("SELECT id FROM issued_documents WHERE request_code = ? AND issuance_status IN ('Ready for Pickup','Released') LIMIT 1");
      $request->execute([$record['request_code']]);
      if (!$request->fetchColumn()) {
        $db->prepare("UPDATE document_requests SET status = 'Approved', ready_at = NULL WHERE request_code = ? AND status = 'Ready for Pickup' AND archived_at IS NULL")->execute([$record['request_code']]);
      }
    }
    $db->commit();
    return true;
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
  }
}
