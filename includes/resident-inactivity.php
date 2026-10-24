<?php
require_once __DIR__ . '/database.php';

class ResidentInactivityException extends RuntimeException {}

function resident_enforce_inactivity_rule(): void {
  static $checked = false;
  if ($checked) return;
  resident_deactivate_unused_accounts();
  $checked = true;
}

function resident_inactivity_schema(): void {
  static $ready = false;
  if ($ready) return;
  $db = barangay_db();
  $columns = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
  foreach ([
    'last_active_at' => 'DATETIME DEFAULT NULL',
    'inactive_reason' => 'VARCHAR(40) DEFAULT NULL',
    'inactivated_at' => 'DATETIME DEFAULT NULL',
    'reactivated_at' => 'DATETIME DEFAULT NULL',
  ] as $column => $definition) {
    if (!in_array($column, $columns, true)) $db->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
  }
  $ready = true;
}

/** Registration and reactivation start a grace period; office activity does not count as resident use. */
function resident_inactivity_reference_sql(): string {
  return 'GREATEST(created_at, COALESCE(last_login_at, created_at), COALESCE(last_active_at, created_at), COALESCE(reactivated_at, created_at))';
}

function resident_require_inactivity_review(string $code): void {
  resident_inactivity_schema();
  $stmt = barangay_db()->prepare("SELECT 1 FROM users WHERE user_code = ? AND role = 'resident' AND inactive_reason = 'six_month_inactivity'");
  $stmt->execute([$code]);
  if ($stmt->fetchColumn()) throw new InvalidArgumentException('Open QR and Resident Management and use Review residency before changing this inactive account.');
}

function resident_deactivate_unused_accounts(): int {
  resident_inactivity_schema();
  $db = barangay_db();
  $ownsTransaction = !$db->inTransaction();
  if ($ownsTransaction) $db->beginTransaction();
  try {
    $reference = resident_inactivity_reference_sql();
    $rows = $db->query("SELECT id, user_code FROM users WHERE role = 'resident' AND status = 'Active'
      AND DATE_ADD({$reference}, INTERVAL 6 MONTH) <= NOW() FOR UPDATE")->fetchAll();
    $update = $db->prepare("UPDATE users SET status = 'Inactive', inactive_reason = 'six_month_inactivity', inactivated_at = NOW() WHERE id = ? AND role = 'resident' AND status = 'Active'");
    $audit = $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at) VALUES (NULL, 'System', 'system', ?, 'Resident Management', 'Success', NOW())");
    foreach ($rows as $row) {
      $update->execute([$row['id']]);
      $audit->execute(['Automatically deactivated resident ' . $row['user_code'] . ' after 6 months without account activity. Residency review required.']);
    }
    if ($ownsTransaction) $db->commit();
    return count($rows);
  } catch (Throwable $e) {
    if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
    throw $e;
  }
}

/** Called only for sign-ins, page navigation and the user-interaction heartbeat, never refresh polling. */
function resident_record_account_activity(array $user): void {
  if (($user['role'] ?? '') !== 'resident') return;
  resident_inactivity_schema();
  $stmt = barangay_db()->prepare("UPDATE users SET last_active_at = NOW() WHERE id = ? AND role = 'resident' AND status = 'Active'
    AND (last_active_at IS NULL OR last_active_at < NOW() - INTERVAL 5 MINUTE)");
  $stmt->execute([(int) $user['id']]);
}

function resident_reactivate_after_review(array $actor, string $code, string $note, bool $confirmed): void {
  if (!in_array($actor['role'] ?? '', ['admin', 'staff'], true)) throw new InvalidArgumentException('Only Admin and Staff can review residency.');
  $note = trim($note);
  if (!$confirmed || $code === '' || $note === '' || strlen($note) > 150) {
    throw new InvalidArgumentException('Confirm current residency and enter a review note of up to 150 characters.');
  }
  resident_inactivity_schema();
  $db = barangay_db();
  $ownsTransaction = !$db->inTransaction();
  if ($ownsTransaction) $db->beginTransaction();
  try {
    $stmt = $db->prepare("UPDATE users SET status = 'Active', inactive_reason = NULL, inactivated_at = NULL, reactivated_at = NOW()
      WHERE user_code = ? AND role = 'resident' AND status = 'Inactive' AND inactive_reason = 'six_month_inactivity'");
    $stmt->execute([$code]);
    if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('This account is no longer awaiting an inactivity review. Refresh the page.');
    $audit = $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at) VALUES (?, ?, ?, ?, 'Resident Management', 'Success', NOW())");
    $audit->execute([$actor['id'], $actor['name'], $actor['role'], 'Confirmed residency and reactivated ' . $code . ': ' . $note]);
    if ($ownsTransaction) $db->commit();
  } catch (Throwable $e) {
    if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
    throw $e;
  }
}
