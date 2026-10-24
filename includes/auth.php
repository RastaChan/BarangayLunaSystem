<?php
/**
 * Session-based authentication helpers.
 *
 * User credentials are loaded from the MySQL users table.
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/profile-photo-helpers.php';
require_once __DIR__ . '/system-configuration.php';
require_once __DIR__ . '/resident-inactivity.php';

function barangay_start_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function barangay_authenticate(string $login, string $password, string $role): ?array {
  $login = trim($login);
  $role = strtolower(trim($role));
  barangay_ensure_profile_photo_column();
  resident_enforce_inactivity_rule();

  $stmt = barangay_db()->prepare(
    "SELECT id, user_code, role, full_name, position_title, username, mobile, password_hash, status, profile_photo_path, inactive_reason
     FROM users
     WHERE role = :role
       AND LOWER(username) = LOWER(:username_login)
     LIMIT 1"
  );
  $stmt->execute([
    ':role' => $role,
    ':username_login' => $login,
  ]);

  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password_hash'])) {
    return null;
  }
  if ($user['status'] !== 'Active') {
    if ($user['role'] === 'resident' && $user['inactive_reason'] === 'six_month_inactivity') {
      throw new ResidentInactivityException('Your account is inactive after 6 months without use. Visit the barangay office to confirm your residency and restore access.');
    }
    return null;
  }

  return barangay_format_user($user);
}

function barangay_record_login(int $userId): void {
  resident_enforce_inactivity_rule();
  $stmt = barangay_db()->prepare("UPDATE users SET last_login_at = NOW(), last_active_at = IF(role = 'resident', NOW(), last_active_at) WHERE id = :id AND status = 'Active'");
  $stmt->execute([':id' => $userId]);
  $check = barangay_db()->prepare("SELECT status FROM users WHERE id = ?");
  $check->execute([$userId]);
  if ($check->fetchColumn() !== 'Active') throw new ResidentInactivityException('This account is inactive. Contact the barangay office to restore access.');
}

function barangay_format_user(array $row): array {
  return [
    'id' => (int) $row['id'],
    'user_code' => $row['user_code'],
    'role' => $row['role'],
    'name' => $row['full_name'],
    'title' => $row['position_title'] ?: ucfirst($row['role']),
    'username' => $row['username'],
    'contact' => $row['mobile'] ?? '',
    'status' => $row['status'],
    'profile_photo_path' => $row['profile_photo_path'] ?? '',
  ];
}

function barangay_sign_in(array $user): void {
  barangay_start_session();
  session_regenerate_id(true);
  $user['auth_version'] = 2;
  $_SESSION['barangay_user'] = $user;
  $_SESSION['last_activity'] = time();
}

function barangay_current_user(): ?array {
  barangay_start_session();
  $user = $_SESSION['barangay_user'] ?? null;

  if (!$user || ($user['auth_version'] ?? null) !== 2 || !is_int($user['id'] ?? null)) {
    unset($_SESSION['barangay_user']);
    return null;
  }

  $settings = barangay_security_settings();
  resident_enforce_inactivity_rule();
  $timeout = max(10, min(240, (int) ($settings['session_timeout'] ?? 30))) * 60;
  if (time() - (int) ($_SESSION['last_activity'] ?? time()) >= $timeout) {
    unset($_SESSION['barangay_user'], $_SESSION['last_activity']);
    return null;
  }
  $active = barangay_db()->prepare("SELECT role FROM users WHERE id = ? AND status = 'Active'");
  $active->execute([$user['id']]);
  if ($active->fetchColumn() !== $user['role']) {
    unset($_SESSION['barangay_user']);
    return null;
  }
  $_SESSION['last_activity'] ??= time();
  if (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'navigate') {
    $_SESSION['last_activity'] = time();
    resident_record_account_activity($user);
  }
  return $user;
}

function barangay_dashboard_for_role(string $role): string {
  switch ($role) {
    case 'admin':
      return 'dashboard.php';
    case 'staff':
      return 'staff-dashboard.php';
    case 'resident':
      return 'resident-dashboard.php';
    default:
      return 'login.php';
  }
}

function barangay_require_role(string $role): array {
  $user = barangay_current_user();

  if (!$user || ($user['role'] ?? '') !== $role) {
    header('Location: login.php?error=login_required');
    exit;
  }

  return $user;
}

function barangay_sign_out(): void {
  barangay_start_session();
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }

  session_destroy();
}
?>
