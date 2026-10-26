<?php
require_once __DIR__ . '/record-attachments.php';

function registration_requirement_types(): array {
  return ['government_id' => 'Valid Government-Issued ID', 'residence' => 'Proof of Residence',
    'birth_certificate' => 'PSA Birth Certificate', 'photo' => 'Recent 1x1 or 2x2 ID Photo'];
}

function registration_requirement_inputs(array $input, array $uploads): array {
  foreach (['requirement_status', 'requirement_remarks'] as $field) {
    if (isset($input[$field]) && !is_array($input[$field])) throw new InvalidArgumentException('Invalid registration requirements. Refresh the form and try again.');
  }
  $requirements = [];
  foreach (registration_requirement_types() as $key => $title) {
    $status = (string) ($input['requirement_status'][$key] ?? 'Not Submitted');
    $remarks = trim((string) ($input['requirement_remarks'][$key] ?? ''));
    if (!in_array($status, ['Submitted', 'Not Submitted', 'Not Applicable'], true) || strlen($remarks) > 4000) {
      throw new InvalidArgumentException('Check the status and remarks for ' . $title . '.');
    }
    $file = barangay_read_attachment_upload($uploads['requirement_file_' . $key] ?? []);
    if ($file) $status = 'Submitted';
    $requirements[$key] = compact('status', 'remarks', 'file');
  }
  return $requirements;
}

function registration_save_initial_requirements(int $resident, array $requirements, int $actor): void {
  foreach ($requirements as $key => $requirement) {
    registration_requirement_save($resident, $key, $requirement['status'], $requirement['remarks'], $actor, $requirement['file'], false);
  }
}

function registration_requirements_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_attachment_schema();
  barangay_db()->exec("CREATE TABLE IF NOT EXISTS resident_requirements (
    resident_id INT UNSIGNED NOT NULL,
    requirement_key VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Not Submitted',
    remarks TEXT NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (resident_id, requirement_key)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $ready = true;
}

function registration_requirement_save(int $resident, string $key, string $status, string $remarks, int $actor, ?array $file, bool $remove): void {
  if (!isset(registration_requirement_types()[$key]) || !in_array($status, ['Submitted', 'Not Submitted', 'Not Applicable'], true) || strlen($remarks) > 4000) {
    throw new InvalidArgumentException('Choose a valid requirement and status; remarks must be under 4,000 bytes.');
  }
  if ($file && $remove) throw new InvalidArgumentException('Choose either a replacement file or removal, not both.');
  if ($file) $status = 'Submitted';
  $db = barangay_db();
  $check = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'resident' FOR UPDATE");
  $check->execute([$resident]);
  if (!$check->fetchColumn()) throw new InvalidArgumentException('Resident not found.');
  $stmt = $db->prepare('INSERT INTO resident_requirements (resident_id, requirement_key, status, remarks, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), updated_by = VALUES(updated_by)');
  $stmt->execute([$resident, $key, $status, $remarks, $actor]);
  $code = $resident . ':' . $key;
  if ($file || $remove) {
    $db->prepare("DELETE FROM record_attachments WHERE record_type = 'registration' AND record_code = ? AND owner_id = ?")->execute([$code, $resident]);
  }
  barangay_save_attachment('registration', $code, $resident, $file);
}
