<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms-verification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php');
  exit;
}

$role = $_POST['role'] ?? '';
$login = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$retryQuery = http_build_query([
  'role' => $role,
  'username' => $login,
]);

try {
  $user = barangay_authenticate($login, $password, $role);
} catch (ResidentInactivityException $e) {
  header('Location: login.php?error=inactive_resident&' . $retryQuery . '#loginAltCard');
  exit;
} catch (PDOException $e) {
  header('Location: login.php?error=database&' . $retryQuery . '#loginAltCard');
  exit;
}

if (!$user) {
  header('Location: login.php?error=invalid&' . $retryQuery . '#loginAltCard');
  exit;
}

if (sms_verification_required($user)) {
  sms_verification_begin($user);
  try { sms_verification_send($_SESSION['pending_verification']); }
  catch (Throwable $e) { $_SESSION['verification_error'] = $e instanceof PDOException ? 'Verification is temporarily unavailable.' : $e->getMessage(); }
  header('Location: verify-signin.php');
  exit;
}
try {
  barangay_record_login($user['id']);
} catch (ResidentInactivityException $e) {
  header('Location: login.php?error=inactive_resident&' . $retryQuery . '#loginAltCard');
  exit;
}
barangay_sign_in($user);
header('Location: ' . barangay_dashboard_for_role($user['role']));
exit;
?>
