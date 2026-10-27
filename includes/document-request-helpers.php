<?php
require_once __DIR__ . '/document-custom-fields.php';
require_once __DIR__ . '/database.php';

function barangay_document_requests(): array {
  barangay_ensure_document_request_detail_columns();
  $sql = 'SELECT document_requests.request_code AS id,
              document_requests.resident_name AS resident,
              document_requests.document_type AS type,
              document_requests.fee_amount,
              document_requests.copies, document_requests.unit_fee, document_requests.custom_values_json,
              COALESCE(document_requests.contact_mobile, "") AS contact_mobile,
              COALESCE(document_requests.remarks, "") AS remarks,
              COALESCE(document_requests.purpose, "") AS purpose,
              COALESCE(document_requests.beneficiary_name, "") AS beneficiary_name,
              COALESCE(document_requests.beneficiary_relationship, "") AS beneficiary_relationship,
              COALESCE(document_requests.residency_years, "") AS residency_years,
              COALESCE(document_requests.partner_name, "") AS partner_name,
              COALESCE(document_requests.cohabitation_start_date, "") AS cohabitation_start_date,
              COALESCE(document_requests.resident_gender_override, "") AS resident_gender_override,
              COALESCE(users.user_code, "") AS resident_code,
              DATE(document_requests.requested_at) AS date,
              document_requests.status
       FROM document_requests
       LEFT JOIN users ON users.id = document_requests.resident_user_id
       WHERE document_requests.archived_at IS NULL
       ORDER BY document_requests.requested_at DESC';
  return barangay_db()->query($sql)->fetchAll();
}


function barangay_ensure_document_request_detail_columns(): void {
  barangay_custom_document_schema();
  $columns = [
    'ready_at' => "ALTER TABLE document_requests ADD COLUMN ready_at DATETIME DEFAULT NULL",
    'beneficiary_name' => "ALTER TABLE document_requests ADD COLUMN beneficiary_name VARCHAR(150) DEFAULT NULL AFTER remarks",
    'beneficiary_relationship' => "ALTER TABLE document_requests ADD COLUMN beneficiary_relationship VARCHAR(80) DEFAULT NULL AFTER beneficiary_name",
    'residency_years' => "ALTER TABLE document_requests ADD COLUMN residency_years INT UNSIGNED DEFAULT NULL AFTER beneficiary_relationship",
    'partner_name' => "ALTER TABLE document_requests ADD COLUMN partner_name VARCHAR(150) DEFAULT NULL AFTER residency_years",
    'cohabitation_start_date' => "ALTER TABLE document_requests ADD COLUMN cohabitation_start_date DATE DEFAULT NULL AFTER partner_name",
    'resident_gender_override' => "ALTER TABLE document_requests ADD COLUMN resident_gender_override VARCHAR(20) DEFAULT NULL AFTER cohabitation_start_date",
  ];

  $stmt = barangay_db()->prepare(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'document_requests'
       AND COLUMN_NAME IN ('" . implode("','", array_keys($columns)) . "')"
  );
  $stmt->execute();
  $existing = array_column($stmt->fetchAll(), 'COLUMN_NAME');

  foreach ($columns as $column => $sql) {
    if (!in_array($column, $existing, true)) {
      barangay_db()->exec($sql);
    }
  }
}
function barangay_locked_document_request(string $code): array {
  $stmt = barangay_db()->prepare("SELECT r.*, u.user_code AS resident_code
    FROM document_requests r JOIN users u ON u.id = r.resident_user_id
    WHERE r.request_code = :code AND r.archived_at IS NULL AND u.role = 'resident'
      AND r.status NOT IN ('Ready for Pickup', 'Released', 'Rejected') LIMIT 1");
  $stmt->execute([':code' => $code]);
  $request = $stmt->fetch();
  if (!$request) throw new InvalidArgumentException('This request is no longer available for issuance.');
  return $request;
}

function barangay_request_issue_input(array $input, array $request): array {
  foreach (['resident_code', 'document_type', 'purpose', 'beneficiary_name',
    'beneficiary_relationship', 'residency_years', 'partner_name',
    'cohabitation_start_date', 'resident_gender_override', 'copies'] as $field) {
    $input[$field] = $request[$field] ?? ($field === 'copies' ? 1 : '');
  }
  if (!array_key_exists('custom_fields', $input)) $input['custom_fields'] = barangay_custom_json($request['custom_values_json'] ?? null);
  return $input;
}

function barangay_document_request_event(string $code, string $event, string $reason = ''): bool {
  $rules = [
    'prepare' => ['Processing', ['Pending', 'Approved']],
    'ready' => ['Ready for Pickup', ['Processing']],
    'pickup' => ['Released', ['Ready for Pickup']],
    'reject' => ['Rejected', ['Pending', 'Processing', 'Approved']],
  ];
  if (!isset($rules[$event]) || $code === '') throw new InvalidArgumentException('Invalid document action.');
  [$target, $from] = $rules[$event];
  if ($event === 'reject' && trim($reason) === '') throw new InvalidArgumentException('Enter a rejection reason.');
  $sql = 'UPDATE document_requests SET status = ?' . ($event === 'ready' ? ', ready_at = COALESCE(ready_at, NOW())' : '') . ($event === 'reject' ? ', remarks = ?' : '')
    . ' WHERE request_code = ? AND archived_at IS NULL AND status IN (' . implode(',', array_fill(0, count($from), '?')) . ')';
  $params = [$target];
  if ($event === 'reject') $params[] = mb_substr(trim($reason), 0, 1000);
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute(array_merge($params, [$code], $from));
  if ($stmt->rowCount() > 0) return true;
  $current = barangay_db()->prepare('SELECT status FROM document_requests WHERE request_code = ? AND archived_at IS NULL');
  $current->execute([$code]);
  if ($current->fetchColumn() === $target) return false;
  throw new InvalidArgumentException('This request is no longer available for that action.');
}

function barangay_walkin_resident(array $input, bool $preview = false): array {
  $name = barangay_formal_text(trim((string) ($input['walkin_name'] ?? '')));
  $address = barangay_formal_text(trim((string) ($input['walkin_address'] ?? '')));
  $age = filter_var($input['walkin_age'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 130]]);
  $gender = (string) ($input['walkin_gender'] ?? '');
  $civil = (string) ($input['walkin_civil_status'] ?? '');
  $mobile = trim((string) ($input['walkin_mobile'] ?? ''));
  // A draft preview can be incomplete; issuance still uses strict validation below.
  if ($preview) {
    return ['id' => null, 'user_code' => '', 'full_name' => mb_substr($name, 0, 150),
      'address' => mb_substr($address, 0, 500), 'age' => $age === false ? '' : $age,
      'gender' => in_array($gender, ['Male', 'Female'], true) ? $gender : '',
      'civil_status' => in_array($civil, ['Single', 'Married', 'Widowed', 'Separated'], true) ? $civil : '',
      'mobile' => ''];
  }
  if ($name === '' || mb_strlen($name) > 150 || $address === '' || mb_strlen($address) > 500 || $age === false
      || !in_array($gender, ['Male', 'Female'], true)
      || !in_array($civil, ['Single', 'Married', 'Widowed', 'Separated'], true)
      || ($mobile !== '' && !preg_match('/^(?:09\d{9}|\+639\d{9})$/D', $mobile))) {
    throw new InvalidArgumentException('Complete the walk-in resident details and enter a valid mobile number, or leave it blank.');
  }
  return ['id' => null, 'user_code' => '', 'full_name' => $name, 'address' => $address,
    'age' => $age, 'gender' => $gender, 'civil_status' => $civil, 'mobile' => $mobile];
}
