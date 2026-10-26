<?php
// Run from the command line only. Existing accounts are never overwritten.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/database.php';

$db = barangay_db();
$password = 'DemoLuna@2026!';
$firstNames = ['Lara','Luis','Liza','Leo','Lourdes','Ana','Ben','Carla','Daniel','Elena','Felix','Grace','Hector','Iris','Jose','Karen','Marco','Nina','Oscar','Paula'];
$lastNames = ['Santos','Reyes','Garcia','Cruz','Ramos'];
$locations = $db->query('SELECT id, name, district_id FROM resident_puroks ORDER BY id')->fetchAll();
$actor = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'Active' ORDER BY id LIMIT 1")->fetchColumn();
$find = $db->prepare('SELECT user_code, username, role, full_name, password_hash FROM users WHERE user_code = ? OR username = ? FOR UPDATE');
$insert = $db->prepare("INSERT INTO users
 (user_code, role, full_name, position_title, username, mobile, address, purok, district_id, purok_id, household, birthdate, gender, civil_status, verification_status, qr_status, qr_code, qr_generated_at, registered_by, password_hash, status, last_active_at)
 VALUES (?, 'resident', ?, 'Resident (Demo)', ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'Verified', 'Active', ?, NOW(), ?, ?, 'Active', NOW())");
$created = 0; $existing = 0; $rows = [];
$db->beginTransaction();
try {
  for ($number = 1; $number <= 100; $number++) {
    $suffix = sprintf('%03d', $number);
    $code = 'DEMO-RES-' . $suffix;
    $username = 'demo' . $suffix . '@example.test';
    $name = $firstNames[($number-1)%20] . ' ' . $lastNames[intdiv($number-1,20)] . ' (DEMO ' . $suffix . ')';
    $find->execute([$code, $username]);
    $matches = $find->fetchAll();
    if ($matches) {
      if (count($matches) !== 1 || $matches[0]['user_code'] !== $code || $matches[0]['username'] !== $username || $matches[0]['role'] !== 'resident' || $matches[0]['full_name'] !== $name || !password_verify($password, $matches[0]['password_hash'])) {
        throw new RuntimeException('A demo identifier is already used by a different account; no accounts were changed.');
      }
      $existing++;
    } else {
      $location = $locations ? $locations[($number-1)%count($locations)] : null;
      $birthdate = sprintf('%04d-%02d-%02d', 1980 + (($number-1)%25), 1 + (($number-1)%12), 1 + (($number-1)%28));
      $insert->execute([$code, $name, $username,
        'DEMO ADDRESS - ' . ($location['name'] ?? 'Barangay Luna') . ', Barangay Luna, Surigao City',
        $location['name'] ?? '', $location['district_id'] ?? null, $location['id'] ?? null,
        'DEMO-HH-' . sprintf('%03d', (int)ceil($number/4)), $birthdate,
        $number%2 ? 'Female' : 'Male', $number%3 ? 'Single' : 'Married',
        'LUNA|' . $code . '|' . date('YmdHis'), $actor ?: null, password_hash($password, PASSWORD_DEFAULT)]);
      $created++;
    }
    $rows[] = [$code, $name, $username, 'Resident'];
  }
  $db->commit();
} catch (Throwable $error) {
  if ($db->inTransaction()) $db->rollBack();
  fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
}
$file = fopen(__DIR__ . '/../docs/demo-resident-accounts.csv', 'w');
if (!$file) throw new RuntimeException('Accounts saved, but the account list could not be written.');
fputcsv($file, ['Resident ID', 'Name', 'Username', 'Login role'], ',', '"', '');
foreach ($rows as $row) fputcsv($file, $row, ',', '"', '');
fclose($file);
echo "Created {$created} demo residents; {$existing} existing demo residents retained. Total: 100.\n";
echo "Active, verified, QR enabled; phone numbers blank. Existing non-demo accounts unchanged.\n";
echo "Account list: docs/demo-resident-accounts.csv\n";
