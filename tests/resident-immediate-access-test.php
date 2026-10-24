<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Exercise the real POST handler in a child process because it redirects and exits.
if (($argv[1] ?? '') === '--create') {
  if (!preg_match('/^barangay_access_test_[a-f0-9]{12}$/', $argv[2] ?? '')) exit(2);
  putenv('BARANGAY_DB_NAME=' . $argv[2]);
  session_save_path(sys_get_temp_dir());
  require_once __DIR__ . '/../includes/auth.php';
  barangay_start_session();
  register_shutdown_function(static function (): void { if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });
  $_SESSION['barangay_user'] = ['id'=>(int)$argv[3], 'role'=>$argv[4], 'name'=>'Test operator', 'auth_version'=>2];
  $_SESSION['locations_csrf'] = 'test-csrf';
  $_SERVER['REQUEST_METHOD'] = 'POST';
  $_POST = [
    'action'=>'create_resident', 'redirect'=>'resident-account-registration', 'locations_csrf'=>'test-csrf',
    'district_id'=>1, 'purok_id'=>1, 'first_name'=>'New', 'last_name'=>$argv[4],
    'username'=>'new-' . $argv[4], 'password'=>'TestOnly!42', 'confirm_password'=>'TestOnly!42',
    // A stale form must not leave new accounts awaiting verification.
    'status'=>'Pending', 'verification_status'=>'Pending', 'qr_status'=>'Inactive',
  ];
  $_FILES = [];
  require __DIR__ . '/../staff-actions.php';
  exit;
}

function access_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: {$label}\n";
}
$testDatabase = 'barangay_access_test_' . bin2hex(random_bytes(6));
$host = getenv('BARANGAY_DB_HOST') ?: '127.0.0.1';
$port = getenv('BARANGAY_DB_PORT') ?: '3306';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", getenv('BARANGAY_DB_USER') ?: 'root', getenv('BARANGAY_DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `{$testDatabase}`");
try {
  $server->exec(str_replace('barangay_luna_system', $testDatabase, file_get_contents(__DIR__ . '/../database/schema.sql')));
  putenv('BARANGAY_DB_NAME=' . $testDatabase);
  require_once __DIR__ . '/../includes/auth.php';
  require_once __DIR__ . '/../includes/staff-functions.php';
  require_once __DIR__ . '/../includes/resident-locations.php';
  resident_locations_schema();
  $db = barangay_db();
  $db->exec("INSERT INTO resident_puroks (id, name, district_id) VALUES (1, 'Test Purok', 1)");
  $db->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_enabled','0') ON DUPLICATE KEY UPDATE setting_value='0'");
  foreach (['admin','staff'] as $role) {
    $stmt = $db->prepare("INSERT INTO users (user_code, role, full_name, username, password_hash, status) VALUES (?, ?, 'Test operator', ?, 'unused', 'Active')");
    $stmt->execute([strtoupper($role),$role,$role]);
    $actor = (int)$db->lastInsertId();
    $process = proc_open([PHP_BINARY, __FILE__, '--create', $testDatabase, (string)$actor, $role], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    access_check(proc_close($process) === 0 && $output === '', $role . ' registration handler completes without errors: ' . $output);
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute(['new-' . $role]); $resident = $stmt->fetch();
    access_check($resident && $resident['status'] === 'Active' && $resident['verification_status'] === 'Verified', $role . ' creates an immediately active account');
    access_check($resident['qr_status'] === 'Active' && !empty($resident['qr_code']), 'QR is generated and active without a separate step');
    access_check(barangay_authenticate('new-' . $role, 'TestOnly!42', 'resident') !== null, 'Resident can sign in immediately');
    $data = staff_load_dashboard_data(['id'=>$actor,'role'=>$role,'name'=>'Test operator']);
    $records = array_filter($data['residentRecords'], static fn($r)=>$r['id'] === $resident['user_code']);
    access_check(count($records) === 1 && reset($records)['eligible'] === 'Eligible', 'New resident is available to Issue Documents');
    $db->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?")->execute([$resident['id']]);
    access_check(barangay_authenticate('new-' . $role, 'TestOnly!42', 'resident') === null, 'Inactive account remains blocked');
  }
} finally {
  $server->exec("DROP DATABASE `{$testDatabase}`");
  echo "Temporary test database removed. No real SMS sent.\n";
}
