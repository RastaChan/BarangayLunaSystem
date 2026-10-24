<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/system-configuration.php';

function barangay_change_own_password(array $user, string $current, string $password, string $confirmation): void {
  barangay_validate_password($password);
  if ($password !== $confirmation) {
    throw new InvalidArgumentException('The new password and confirmation do not match.');
  }
  $db = barangay_db();
  $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? AND role = ? AND status = 'Active'");
  $stmt->execute([$user['id'], $user['role']]);
  $hash = $stmt->fetchColumn();
  if (!$hash || !password_verify($current, $hash)) {
    throw new InvalidArgumentException('The current password is incorrect or the account is inactive.');
  }
  $update = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
  $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $hash]);
  if ($update->rowCount() !== 1) {
    throw new RuntimeException('The password changed in another session. Please sign in again.');
  }
  $log = $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at) VALUES (?, ?, ?, 'Changed own password', 'Security Settings', 'Success', NOW())");
  $log->execute([$user['id'], $user['name'], ucfirst($user['role'])]);
}
