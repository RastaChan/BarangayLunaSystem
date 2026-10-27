<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/includes/announcement-helpers.php';
require_once __DIR__ . '/includes/profile-photo-helpers.php';
require_once __DIR__ . '/includes/qr-sms-helpers.php';
require_once __DIR__ . '/includes/feedback-helpers.php';
require_once __DIR__ . '/includes/document-fee-helpers.php';
require_once __DIR__ . '/includes/document-payment-helpers.php';
require_once __DIR__ . '/includes/backup-helpers.php';

$user = barangay_require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  admin_redirect('dashboard', 'invalid');
}

$_POST = barangay_formal_inputs($_POST);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? 'dashboard';

try {
  switch ($action) {
    case 'update_account_profile':
      $username = trim($_POST['username'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');

      if ($username === '') {
        admin_redirect($redirect, 'missing');
      }

      $stmt = barangay_db()->prepare(
        "UPDATE users
         SET username = :username, mobile = :mobile
         WHERE id = :id AND role = 'admin'"
      );
      $stmt->execute([
        ':id' => $user['id'],
        ':username' => $username,
        ':mobile' => $mobile,
      ]);
      barangay_handle_profile_photo_upload($user);
      $user['username'] = $username;
      $user['contact'] = $mobile;
      barangay_sign_in($user);
      admin_log($user, 'Updated own profile settings', 'Profile Settings');
      admin_redirect($redirect, 'saved');

    case 'create_staff':
      barangay_validate_password((string) ($_POST['password'] ?? ''));
      $name = trim($_POST['full_name'] ?? '');
      $position = 'Barangay Staff';
      $username = trim($_POST['username'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');
      $password = (string) ($_POST['password'] ?? '');
      $status = $_POST['status'] ?? 'Active';

      if ($name === '' || $position === '' || $username === '' || strlen($password) < 8) {
        admin_redirect($redirect, 'missing');
      }

      $stmt = barangay_db()->prepare(
        "INSERT INTO users (user_code, role, full_name, position_title, username, mobile, password_hash, status)
         VALUES (:code, 'staff', :name, :position, :username, :mobile, :password_hash, :status)"
      );
      $stmt->execute([
        ':code' => admin_next_code('users', 'user_code', 'STF'),
        ':name' => $name,
        ':position' => $position,
        ':username' => $username,
        ':mobile' => $mobile,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':status' => in_array($status, ['Active', 'Inactive', 'Pending'], true) ? $status : 'Active',
      ]);
      admin_log($user, 'Created staff account: ' . $name, 'User Management');
      admin_redirect($redirect, 'created');

    case 'update_staff':
      $id = trim($_POST['id'] ?? '');
      $name = barangay_formal_text(trim($_POST['name'] ?? ''));
      $position = trim($_POST['position'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');
      $status = $_POST['status'] ?? 'Active';

      if ($id === '' || $name === '' || $position === '' || $username === '') {
        admin_redirect($redirect, 'missing');
      }

      $stmt = barangay_db()->prepare(
        "UPDATE users
         SET full_name = :name, position_title = :position, username = :username, mobile = :mobile, status = :status
         WHERE user_code = :id AND role = 'staff'"
      );
      $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':position' => $position,
        ':username' => $username,
        ':mobile' => $mobile,
        ':status' => in_array($status, ['Active', 'Inactive', 'Pending'], true) ? $status : 'Active',
      ]);
      admin_log($user, 'Updated staff account: ' . $id, 'User Management');
      admin_redirect($redirect, 'updated');

    case 'set_user_status':
      $id = trim($_POST['id'] ?? '');
      $status = $_POST['status'] ?? 'Inactive';
      if ($id === '' || !in_array($status, ['Active', 'Inactive', 'Pending'], true)) {
        admin_redirect($redirect, 'invalid');
      }
      // Inactivity holds require the residency-review workflow for both roles.
      resident_require_inactivity_review($id);
      $stmt = barangay_db()->prepare("UPDATE users SET
        reactivated_at = IF(role = 'resident' AND :active_status = 'Active' AND status <> 'Active', NOW(), reactivated_at),
        inactive_reason = NULL, inactivated_at = IF(:inactive_status = 'Inactive', NOW(), NULL), status = :status
        WHERE user_code = :id AND role <> 'admin'");
      $stmt->execute([':id' => $id, ':status' => $status, ':active_status' => $status, ':inactive_status' => $status]);
      admin_log($user, 'Changed user status: ' . $id . ' to ' . $status, 'User Management');
      admin_redirect($redirect, 'updated');

    case 'reset_user_password':
      if (empty($_SESSION['admin_password_reset_csrf']) || !hash_equals($_SESSION['admin_password_reset_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        throw new InvalidArgumentException('Refresh the page and try resetting the password again.');
      }
      barangay_validate_password((string) ($_POST['password'] ?? ''));
      $id = trim($_POST['id'] ?? '');
      $password = (string) ($_POST['password'] ?? '');
      if ($id === '' || strlen($password) < 8) {
        admin_redirect($redirect, 'missing');
      }
      $stmt = barangay_db()->prepare("UPDATE users SET password_hash = :password_hash WHERE user_code = :id AND role IN ('staff', 'resident')");
      $stmt->execute([
        ':id' => $id,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
      ]);
      if ($stmt->rowCount() !== 1) {
        admin_redirect($redirect, 'invalid');
      }
      admin_log($user, 'Reset password for account: ' . $id, 'User Management');
      admin_redirect($redirect, 'updated');

    case 'update_qr_status':
      $id = trim($_POST['resident_id'] ?? '');
      $qrStatus = $_POST['qr_status'] ?? 'Inactive';
      if ($id === '' || !in_array($qrStatus, ['Active', 'Inactive'], true)) {
        admin_redirect($redirect, 'invalid');
      }
      if ($qrStatus === 'Active') {
        $stmt = barangay_db()->prepare("SELECT full_name, qr_code FROM users WHERE user_code = :id AND role = 'resident' LIMIT 1");
        $stmt->execute([':id' => $id]);
        $resident = $stmt->fetch();
        $qrCode = $resident && !empty($resident['qr_code'])
          ? $resident['qr_code']
          : QRVerificationHelper::generateQRCode($id, $resident['full_name'] ?? $id);
        $stmt = barangay_db()->prepare("UPDATE users SET qr_status = 'Active', qr_code = :qr_code, qr_generated_at = COALESCE(qr_generated_at, NOW()) WHERE user_code = :id AND role = 'resident'");
        $stmt->execute([':id' => $id, ':qr_code' => $qrCode]);
      } else {
        $stmt = barangay_db()->prepare("UPDATE users SET qr_status = 'Inactive' WHERE user_code = :id AND role = 'resident'");
        $stmt->execute([':id' => $id]);
      }
      admin_log($user, 'Updated QR status for resident: ' . $id . ' to ' . $qrStatus, 'QR Resident Verification');
      admin_redirect($redirect, 'updated');

    case 'log_qr_scan':
      $residentCode = trim($_POST['resident_code'] ?? '');
      if ($residentCode === '') {
        admin_redirect($redirect, 'missing');
      }
      if (preg_match('/^LUNA\|([^|]+)\|/i', $residentCode, $matches)) {
        $residentCode = strtoupper(trim($matches[1]));
      }
      $stmt = barangay_db()->prepare("SELECT id, user_code, full_name, status, verification_status, qr_status FROM users WHERE (user_code = :user_code OR qr_code = :qr_code) AND role = 'resident' LIMIT 1");
      $stmt->execute([':user_code' => strtoupper($residentCode), ':qr_code' => $residentCode]);
      $resident = $stmt->fetch();
      $result = 'Not Found';
      $residentName = 'Unknown resident';
      $residentId = null;
      if ($resident) {
        $residentId = $resident['id'];
        $residentName = $resident['full_name'];
        $result = ($resident['status'] === 'Active' && $resident['qr_status'] === 'Active') ? 'Verified' : 'Inactive';
      }
      $insert = barangay_db()->prepare(
        'INSERT INTO qr_scan_logs (resident_user_id, resident_code, resident_name, result, scanned_by, scanned_at)
         VALUES (:resident_user_id, :resident_code, :resident_name, :result, :scanned_by, NOW())'
      );
      $insert->execute([
        ':resident_user_id' => $residentId,
        ':resident_code' => $resident['user_code'] ?? substr($residentCode, 0, 30),
        ':resident_name' => $residentName,
        ':result' => $result,
        ':scanned_by' => $user['id'],
      ]);
      admin_log($user, 'QR checked: ' . $residentCode . ' result ' . $result, 'QR Resident Verification', $result === 'Verified' ? 'Success' : 'Warning');
      admin_redirect($redirect, 'updated');

    case 'send_sms':
      $recipientName = trim($_POST['recipient_name'] ?? '');
      $recipientMobile = trim($_POST['recipient_mobile'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $messageType = trim($_POST['message_type'] ?? 'Custom SMS');
      $residentCode = trim($_POST['resident_code'] ?? '');

      if ($residentCode !== '') {
        $stmt = barangay_db()->prepare("SELECT full_name, mobile FROM users WHERE user_code = :code AND role = 'resident' LIMIT 1");
        $stmt->execute([':code' => $residentCode]);
        $resident = $stmt->fetch();
        if ($resident) {
          $recipientName = $resident['full_name'];
          $recipientMobile = $resident['mobile'] ?? $recipientMobile;
        }
      }

      if ($recipientName === '' || $recipientMobile === '' || $message === '') {
        admin_redirect($redirect, 'missing');
      }

      $_SESSION['sms_draft'] = ['template' => 'custom', 'resident_code' => $residentCode, 'recipient_name' => $recipientName, 'recipient_mobile' => $recipientMobile, 'message' => $message];
      $_SESSION['sms_flash'] = ['success', 'Review the recipient and message before sending. No SMS has been sent yet.'];
      header('Location: dashboard.php?view=sms-alerts');
      exit;

    case 'create_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      announcement_create_post($_POST, (int) $user['id']);
      admin_log($user, 'Created community post: ' . trim($_POST['title'] ?? ''), 'Announcements');
      admin_redirect($redirect, 'created');
    case 'update_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      $id = (int) ($_POST['id'] ?? 0);
      announcement_update_post($id, $_POST);
      admin_log($user, 'Updated community post ID: ' . $id, 'Announcements');
      admin_redirect($redirect, 'updated');

    case 'delete_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      $id = (int) ($_POST['id'] ?? 0);
      announcement_delete_post($id);
      admin_log($user, 'Deleted community post ID: ' . $id, 'Announcements');
      admin_redirect($redirect, 'deleted');

    case 'update_document_status':
      admin_redirect($redirect, 'invalid');

    case 'update_complaint_status':
      if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['husay_csrf']) || !hash_equals($_SESSION['husay_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Refresh the complaint details and try again.');
      $id = trim($_POST['id'] ?? '');
      husay_update_complaint($_POST, $user);
      admin_log($user, 'Updated complaint: ' . $id . ' to ' . $_POST['status'], 'Complaints');
      admin_redirect($redirect, 'updated');

    case 'archive_feedback':
      $id = trim($_POST['id'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE feedbacks SET status = 'Archived', archived_at = NOW() WHERE feedback_code = :id");
      $stmt->execute([':id' => $id]);
      admin_log($user, 'Archived feedback: ' . $id, 'Feedbacks');
      admin_redirect($redirect, 'archived');

    case 'mark_feedback_read':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE feedbacks SET status = 'Read' WHERE feedback_code = :id");
      $stmt->execute([':id' => $id]);
      admin_log($user, 'Marked feedback read: ' . $id, 'Feedbacks');
      admin_redirect($redirect, 'updated');

    case 'mark_feedback_unread':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE feedbacks SET status = 'Unread' WHERE feedback_code = :id");
      $stmt->execute([':id' => $id]);
      admin_log($user, 'Marked feedback unread: ' . $id, 'Feedbacks');
      admin_redirect($redirect, 'updated');

    case 'delete_feedback':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      if ($id === '') {
        admin_redirect($redirect, 'invalid');
      }
      $stmt = barangay_db()->prepare('SELECT image_path FROM feedbacks WHERE feedback_code = :id LIMIT 1');
      $stmt->execute([':id' => $id]);
      $imagePath = (string) ($stmt->fetchColumn() ?: '');
      $stmt = barangay_db()->prepare('DELETE FROM feedbacks WHERE feedback_code = :id');
      $stmt->execute([':id' => $id]);
      if ($imagePath !== '') {
        $fullPath = __DIR__ . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);
        if (is_file($fullPath)) {
          @unlink($fullPath);
        }
      }
      admin_log($user, 'Deleted feedback: ' . $id, 'Feedbacks');
      admin_redirect($redirect, 'deleted');

    case 'delete_backup':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id < 1) {
        admin_redirect($redirect, 'invalid');
      }
      $stmt = barangay_db()->prepare('SELECT filename FROM backups WHERE id = :id LIMIT 1');
      $stmt->execute([':id' => $id]);
      $filename = (string) ($stmt->fetchColumn() ?: '');
      $stmt = barangay_db()->prepare('DELETE FROM backups WHERE id = :id');
      $stmt->execute([':id' => $id]);
      if ($filename !== '') {
        $backupPath = __DIR__ . '/storage/backups/' . basename($filename);
        if (is_file($backupPath)) {
          @unlink($backupPath);
        }
      }
      admin_log($user, 'Deleted backup ID: ' . $id, 'Backup & Restore');
      admin_redirect($redirect, 'deleted');

    case 'restore_backup':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id < 1) {
        admin_redirect($redirect, 'invalid');
      }
      $stmt = barangay_db()->prepare('SELECT filename FROM backups WHERE id = :id LIMIT 1');
      $stmt->execute([':id' => $id]);
      $filename = (string) ($stmt->fetchColumn() ?: '');
      $path = __DIR__ . '/storage/backups/' . basename($filename);
      if ($filename === '' || !is_file($path)) {
        admin_redirect($redirect, 'invalid');
      }
      $payload = json_decode((string) file_get_contents($path), true);
      if (!is_array($payload) || empty($payload['tables']) || !is_array($payload['tables'])) {
        admin_redirect($redirect, 'invalid');
      }

      $allowedTables = barangay_backup_tables();
      $db = barangay_db();
      $db->beginTransaction();
      try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($allowedTables as $table) {
          if (!array_key_exists($table, $payload['tables'])) {
            continue;
          }
          $db->exec('DELETE FROM `' . $table . '`');
          foreach ($payload['tables'][$table] as $row) {
            if (!is_array($row) || !$row) {
              continue;
            }
            $columns = array_keys($row);
            $columnSql = implode(', ', array_map(static fn($column) => '`' . str_replace('`', '', (string) $column) . '`', $columns));
            $placeholders = implode(', ', array_map(static fn($column) => ':' . preg_replace('/[^a-z0-9_]/i', '_', (string) $column), $columns));
            $insert = $db->prepare("INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholders})");
            $params = [];
            foreach ($columns as $column) {
              $params[':' . preg_replace('/[^a-z0-9_]/i', '_', (string) $column)] = $row[$column];
            }
            $insert->execute($params);
          }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $db->commit();
      } catch (Throwable $restoreError) {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        if ($db->inTransaction()) {
          $db->rollBack();
        }
        throw $restoreError;
      }
      admin_log($user, 'Restored backup: ' . $filename, 'Backup & Restore');
      admin_redirect($redirect, 'updated');

    case 'save_settings':
      if (empty($_SESSION['settings_csrf']) || !hash_equals($_SESSION['settings_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Refresh the settings page and try again.');
      $values = barangay_configuration_values($_POST['settings'] ?? [], SMSAlertsHelper::getSettings(), $user);
      $logo = barangay_logo_upload($_FILES['logo'] ?? []);
      barangay_attachment_schema();
      $db = barangay_db();
      $db->beginTransaction();
      foreach ($values as $key => $value) barangay_setting_write($key, $value, $user['id']);
      barangay_logo_save($logo, isset($_POST['remove_logo']), $user['id']);
      admin_log($user, 'Updated system settings', 'System Settings');
      $db->commit();
      admin_redirect($redirect, 'saved');

    case 'create_backup':
      $filename = 'backup_' . date('Y-m-d_His') . '.json';
      $backupDir = __DIR__ . '/storage/backups';
      if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
      }
      $tables = barangay_backup_tables();
      $payload = [
        'created_at' => date('c'),
        'created_by' => $user['name'],
        'tables' => [],
      ];
      foreach ($tables as $table) {
        $payload['tables'][$table] = admin_fetch_all("SELECT * FROM {$table}");
      }
      $path = $backupDir . '/' . $filename;
      $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
      if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Could not write the complete backup.');
      }
      $size = filesize($path);
      $sizeLabel = $size >= 1048576 ? round($size / 1048576, 2) . ' MB' : round($size / 1024, 2) . ' KB';
      $stmt = barangay_db()->prepare(
        "INSERT INTO backups (filename, created_by, status, file_size, created_at)
         VALUES (:filename, :created_by, 'Complete', :file_size, NOW())"
      );
      $stmt->execute([':filename' => $filename, ':created_by' => $user['name'], ':file_size' => $sizeLabel]);
      admin_log($user, 'Created backup record: ' . $filename, 'Backup & Restore');
      admin_redirect($redirect, 'backup-created');

    default:
      admin_redirect($redirect, 'invalid');
  }
} catch (Throwable $e) {
  if (barangay_db()->inTransaction()) barangay_db()->rollBack();
  if ($e instanceof InvalidArgumentException) {
    $_SESSION['configuration_error'] = $e->getMessage();
    admin_redirect($redirect, 'validation-error');
  }
  admin_log($user, 'Failed admin action: ' . $action, 'System', 'Error');
  admin_redirect($redirect, 'invalid');
}
?>
