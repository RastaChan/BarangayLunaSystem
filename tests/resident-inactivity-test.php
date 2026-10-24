<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// All mutations are confined to a fresh temporary database, removed in finally.
$testDatabase = 'barangay_inactivity_test_' . bin2hex(random_bytes(6));
$host = getenv('BARANGAY_DB_HOST') ?: '127.0.0.1';
$port = getenv('BARANGAY_DB_PORT') ?: '3306';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", getenv('BARANGAY_DB_USER') ?: 'root', getenv('BARANGAY_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `{$testDatabase}`");
function inactivity_check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  echo "PASS: {$message}\n";
}
try {
  $schema = str_replace('barangay_luna_system', $testDatabase, file_get_contents(__DIR__ . '/../database/schema.sql'));
  $server->exec($schema);
  putenv('BARANGAY_DB_NAME=' . $testDatabase);
  require_once __DIR__ . '/../includes/auth.php';
  barangay_start_session();
  resident_inactivity_schema();
  $db = barangay_db();
  $hash = password_hash('TestOnlyPassword!42', PASSWORD_DEFAULT);
  $seed = $db->prepare("INSERT INTO users (user_code, role, full_name, username, password_hash, status, created_at, last_login_at, last_active_at) VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL 8 MONTH, NULL, NULL)");
  foreach (['OLD'=>'resident', 'RECENT'=>'resident', 'USING'=>'resident', 'NEW'=>'resident', 'BOUNDARY'=>'resident', 'PENDING'=>'resident', 'MANUAL'=>'resident', 'ADMIN'=>'admin', 'STAFF'=>'staff'] as $code => $role) {
    $seed->execute([$code, $role, $code, strtolower($code), $hash, $code === 'PENDING' ? 'Pending' : ($code === 'MANUAL' ? 'Inactive' : 'Active')]);
  }
  $db->exec("UPDATE users SET last_login_at = NOW() - INTERVAL 1 MONTH WHERE user_code = 'RECENT'");
  $db->exec("UPDATE users SET last_login_at = NOW() - INTERVAL 7 MONTH, last_active_at = NOW() - INTERVAL 1 DAY WHERE user_code = 'USING'");
  $db->exec("UPDATE users SET created_at = NOW() - INTERVAL 1 MONTH WHERE user_code = 'NEW'");
  $db->exec("UPDATE users SET last_login_at = NOW() - INTERVAL 6 MONTH WHERE user_code = 'BOUNDARY'");
  inactivity_check(resident_deactivate_unused_accounts() === 2, 'Only unused and six-month-boundary residents expire');
  $rows = $db->query('SELECT user_code, status, inactive_reason, inactivated_at FROM users')->fetchAll(PDO::FETCH_UNIQUE);
  foreach (['OLD', 'BOUNDARY'] as $code) inactivity_check($rows[$code]['status'] === 'Inactive' && $rows[$code]['inactive_reason'] === 'six_month_inactivity' && $rows[$code]['inactivated_at'] !== null, $code . ' has an inactivity reason and date');
  foreach (['RECENT', 'USING', 'NEW', 'ADMIN', 'STAFF'] as $code) inactivity_check($rows[$code]['status'] === 'Active', $code . ' stays active');
  inactivity_check($rows['PENDING']['status'] === 'Pending' && $rows['MANUAL']['inactive_reason'] === null, 'Pending and manually inactive accounts are unchanged');
  inactivity_check(resident_deactivate_unused_accounts() === 0 && (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 2, 'Repeated sweeps do not duplicate deactivation audit logs');
  try { resident_require_inactivity_review('OLD'); throw new LogicException('Ordinary status change bypassed review'); }
  catch (InvalidArgumentException $e) { echo "PASS: Ordinary status and verification actions must use residency review for inactive accounts\n"; }
  resident_require_inactivity_review('MANUAL');
  inactivity_check(barangay_authenticate('old', 'wrong', 'resident') === null, 'Inactive-account reason is not disclosed for an incorrect password');
  try { barangay_authenticate('old', 'TestOnlyPassword!42', 'resident'); throw new LogicException('Inactive resident signed in'); }
  catch (ResidentInactivityException $e) { echo "PASS: inactive residents cannot sign in\n"; }
  $staff = $db->query("SELECT id, full_name AS name, role FROM users WHERE user_code = 'STAFF'")->fetch();
  foreach ([['role'=>'resident', 'id'=>999, 'name'=>'Other'], $staff] as $index => $actor) {
    try { resident_reactivate_after_review($actor, 'OLD', 'Reviewed address', $index === 0); throw new LogicException('Invalid review accepted'); }
    catch (InvalidArgumentException $e) { echo "PASS: role and confirmation are required for review\n"; }
  }
  resident_reactivate_after_review($staff, 'OLD', 'Confirmed current address at the barangay office', true);
  $admin = $db->query("SELECT id, full_name AS name, role FROM users WHERE user_code = 'ADMIN'")->fetch();
  try { resident_reactivate_after_review($admin, 'BOUNDARY', '', true); throw new LogicException('Empty review accepted'); }
  catch (InvalidArgumentException $e) { echo "PASS: Review note is mandatory\n"; }
  resident_reactivate_after_review($admin, 'BOUNDARY', 'Confirmed current residency', true);
  inactivity_check($db->query("SELECT status FROM users WHERE user_code = 'BOUNDARY'")->fetchColumn() === 'Active', 'Admin can also confirm residency and restore access');
  inactivity_check(resident_deactivate_unused_accounts() === 0, 'Reactivation starts a fresh six-month period');
  $restored = $db->query("SELECT * FROM users WHERE user_code = 'OLD'")->fetch();
  inactivity_check($restored['status'] === 'Active' && $restored['inactive_reason'] === null && $restored['reactivated_at'] !== null && $restored['last_active_at'] === null, 'Review restores access without inventing resident activity');
  $active = barangay_authenticate('old', 'TestOnlyPassword!42', 'resident');
  barangay_record_login($active['id']);
  inactivity_check($db->query("SELECT last_active_at IS NOT NULL AND last_login_at IS NOT NULL FROM users WHERE user_code = 'OLD'")->fetchColumn() == 1, 'Successful sign-in records usage');
  $id = (int) $db->query("SELECT id FROM users WHERE user_code = 'USING'")->fetchColumn();
  $sessionUser = barangay_authenticate('using', 'TestOnlyPassword!42', 'resident');
  $_SESSION['barangay_user'] = $sessionUser + ['auth_version'=>2];
  $_SESSION['last_activity'] = time();
  unset($_SERVER['HTTP_SEC_FETCH_MODE']);
  $before = $db->query("SELECT last_active_at FROM users WHERE id = {$id}")->fetchColumn();
  inactivity_check(barangay_current_user() !== null && $db->query("SELECT last_active_at FROM users WHERE id = {$id}")->fetchColumn() === $before, 'Background polling does not count as resident use');
  $_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
  inactivity_check(barangay_current_user() !== null && $db->query("SELECT last_active_at FROM users WHERE id = {$id}")->fetchColumn() !== $before, 'Real page navigation counts as resident use');
  unset($_SERVER['HTTP_SEC_FETCH_MODE']);
  resident_record_account_activity(['id'=>$id, 'role'=>'resident']);
  inactivity_check($db->query("SELECT last_active_at >= NOW() - INTERVAL 1 MINUTE FROM users WHERE id = {$id}")->fetchColumn() == 1, 'Actual resident interaction updates usage');
  try { resident_reactivate_after_review($staff, 'MANUAL', 'Reviewed address', true); throw new LogicException('Manual hold bypassed'); }
  catch (InvalidArgumentException $e) { echo "PASS: Staff cannot bypass a manual inactivity hold\n"; }
  $db->exec("UPDATE users SET last_active_at = NOW() - INTERVAL 7 MONTH, last_login_at = NOW() - INTERVAL 7 MONTH, reactivated_at = NOW() - INTERVAL 7 MONTH WHERE user_code = 'OLD'");
  inactivity_check(resident_deactivate_unused_accounts() === 1, 'Reactivated accounts can expire again after another six months');
  $_SESSION['barangay_user'] = $active + ['auth_version'=>2];
  $_SESSION['last_activity'] = time();
  inactivity_check(barangay_current_user() === null, 'Existing sessions lose access when the account is deactivated');
  inactivity_check($db->query("SELECT DATE_ADD('2026-03-31 10:00:00', INTERVAL 6 MONTH)")->fetchColumn() === '2026-09-30 10:00:00', 'Calendar-month cutoff handles month-end dates');
} finally {
  if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
  $server->exec("DROP DATABASE `{$testDatabase}`");
  echo "Temporary test database removed.\n";
}
