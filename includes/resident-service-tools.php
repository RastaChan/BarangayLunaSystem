<?php
require_once __DIR__ . '/database.php';

function barangay_resident_name(array $input): string {
  return trim(trim((string) ($input['first_name'] ?? '')) . ' '
    . (isset($input['no_middle_name']) ? '' : trim((string) ($input['middle_name'] ?? '')) . ' ')
    . trim((string) ($input['last_name'] ?? '')) . ' ' . trim((string) ($input['suffix'] ?? '')));
}

function barangay_identity_name(string $name): string {
  return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
}

function barangay_resident_matches(string $name, string $birthdate): array {
  if ($birthdate === '' || $name === '') return [];
  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
  if (!$date || $date->format('Y-m-d') !== $birthdate) throw new InvalidArgumentException('Enter a valid birthdate.');
  $query = barangay_db()->prepare("SELECT id, user_code, full_name, birthdate, status FROM users WHERE role = 'resident' AND birthdate = ? ORDER BY id");
  $query->execute([$birthdate]);
  return array_values(array_filter($query->fetchAll(), static fn($row) => barangay_identity_name($row['full_name']) === barangay_identity_name($name)));
}

function barangay_duplicate_token(string $name, string $birthdate, array $matches): string {
  $_SESSION['resident_review_secret'] ??= bin2hex(random_bytes(32));
  return hash_hmac('sha256', json_encode([barangay_identity_name($name), $birthdate, array_column($matches, 'id')]), $_SESSION['resident_review_secret']);
}

function barangay_require_duplicate_review(array $input): void {
  $name = barangay_resident_name($input);
  $birthdate = (string) ($input['birthdate'] ?? '');
  $matches = barangay_resident_matches($name, $birthdate);
  if (!$matches) return;
  if (($input['duplicate_reviewed'] ?? '') !== '1' || !hash_equals(barangay_duplicate_token($name, $birthdate, $matches), (string) ($input['duplicate_token'] ?? ''))) {
    throw new InvalidArgumentException('Review the existing resident with the same name and birthdate before saving.');
  }
}

function barangay_issue_resident_lookup(string $code): ?array {
  $code = trim($code);
  if ($code === '' || strlen($code) > 180) throw new InvalidArgumentException('Enter a valid resident QR code or Resident ID.');
  // Match the entire QR value: an old or altered QR must not resolve through its ID fragment.
  $query = barangay_db()->prepare("SELECT user_code, full_name, status, qr_status, COALESCE(address,'') AS address,
    TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) AS age, COALESCE(gender,'') AS gender,
    COALESCE(civil_status,'') AS civil_status FROM users WHERE role = 'resident' AND (qr_code = ? OR user_code = ?) LIMIT 1");
  $query->execute([$code, strtoupper($code)]);
  $row = $query->fetch();
  if (!$row) return null;
  if ($row['status'] !== 'Active' || (strcasecmp($code, $row['user_code']) !== 0 && $row['qr_status'] !== 'Active')) throw new InvalidArgumentException('This account or QR is inactive. Review the resident record before continuing.');
  return $row;
}

function barangay_issue_resident_search(string $search): array {
  $search = trim($search);
  if (mb_strlen($search) < 1 || mb_strlen($search) > 150) throw new InvalidArgumentException('Enter 1 to 150 characters of the name or Resident ID.');
  $literal = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search);
  $query = barangay_db()->prepare("SELECT user_code, full_name FROM users WHERE role = 'resident' AND status = 'Active'
    AND (full_name LIKE ? ESCAPE '=' OR user_code LIKE ? ESCAPE '=') ORDER BY full_name, user_code LIMIT 21");
  $query->execute(['%' . $literal . '%', '%' . $literal . '%']);
  $rows = $query->fetchAll();
  return ['residents'=>array_slice($rows,0,20), 'more'=>count($rows)>20];
}
