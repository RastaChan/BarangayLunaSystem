<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/resident-inactivity.php';
require_once __DIR__ . '/announcement-helpers.php';
require_once __DIR__ . '/feedback-helpers.php';
require_once __DIR__ . '/document-fee-helpers.php';
require_once __DIR__ . '/document-request-helpers.php';
require_once __DIR__ . '/document-issuance-workflow.php';
require_once __DIR__ . '/husay-helpers.php';

function admin_redirect(string $view, string $status = 'saved'): void {
  header('Location: dashboard.php?view=' . urlencode($view) . '&status=' . urlencode($status));
  exit;
}

function admin_status_message(): ?array {
  if (isset($_SESSION['configuration_error'])) {
    $message = $_SESSION['configuration_error'];
    unset($_SESSION['configuration_error']);
    return ['error', $message];
  }
  $status = $_GET['status'] ?? '';
  $messages = [
    'saved' => ['success', 'Changes saved successfully.'],
    'fee-saved' => ['success', 'Document fee saved successfully.'],
    'created' => ['success', 'Record created successfully.'],
    'updated' => ['success', 'Record updated successfully.'],
    'deleted' => ['success', 'Record removed successfully.'],
    'archived' => ['success', 'Record archived successfully.'],
    'backup-created' => ['success', 'Backup record created successfully.'],
    'sent' => ['success', 'SMS sent successfully.'],
    'sms-failed' => ['error', 'SMS could not be sent. Please check System Settings and the mobile number.'],
    'missing' => ['error', 'Please complete the required fields.'],
    'invalid' => ['error', 'The selected action could not be completed.'],
  ];

  return $messages[$status] ?? null;
}

function admin_log(array $user, string $action, string $module, string $status = 'Success'): void {
  $stmt = barangay_db()->prepare(
    'INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at)
     VALUES (:user_id, :user_name, :user_role, :action, :module, :status, NOW())'
  );
  $stmt->execute([
    ':user_id' => $user['id'] ?? null,
    ':user_name' => $user['name'] ?? 'System',
    ':user_role' => $user['title'] ?? ucfirst($user['role'] ?? 'system'),
    ':action' => $action,
    ':module' => $module,
    ':status' => $status,
  ]);
}

function admin_next_code(string $table, string $column, string $prefix): string {
  $stmt = barangay_db()->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :prefix ORDER BY id DESC LIMIT 1");
  $stmt->execute([':prefix' => $prefix . '-%']);
  $last = $stmt->fetchColumn();
  $next = 1;

  if ($last && preg_match('/(\d+)$/', $last, $matches)) {
    $next = ((int) $matches[1]) + 1;
  }

  return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function admin_fetch_all(string $sql, array $params = []): array {
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function admin_count(string $table, string $where = '1 = 1', array $params = []): int {
  $stmt = barangay_db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

function admin_monthly_activity_trends(int $months = 6): array {
  barangay_issuance_schema();
  $months = max(2, min(12, $months));
  $startMonth = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
  $labels = [];
  $keys = [];
  $series = [
    'complaints' => ['label' => 'Complaints', 'color' => '#dc2626', 'values' => []],
    'documents' => ['label' => 'Documents Released', 'color' => '#f59e0b', 'values' => []],
    'residents' => ['label' => 'Resident report', 'color' => '#15803d', 'values' => []],
  ];

  for ($i = 0; $i < $months; $i++) {
    $date = $startMonth->modify('+' . $i . ' months');
    $key = $date->format('Y-m');
    $keys[] = $key;
    $labels[$key] = $date->format('M');
    foreach ($series as &$item) {
      $item['values'][$key] = 0;
    }
    unset($item);
  }

  $start = $startMonth->format('Y-m-01');
  $queries = [
    'complaints' => [
      'SELECT DATE_FORMAT(filed_at, "%Y-%m") AS period, COUNT(*) AS total
       FROM complaints
       WHERE filed_at >= :start AND archived_at IS NULL
       GROUP BY period',
      [':start' => $start],
    ],
    'documents' => [
      'SELECT DATE_FORMAT(issued_at, "%Y-%m") AS period, COUNT(*) AS total
       FROM issued_documents
       WHERE issued_at >= :start AND issuance_status = "Released"
       GROUP BY period',
      [':start' => $start],
    ],
    'residents' => [
      'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period, COUNT(*) AS total
       FROM users
       WHERE role = "resident" AND created_at >= :start
       GROUP BY period',
      [':start' => $start],
    ],
  ];

  foreach ($queries as $name => [$sql, $params]) {
    foreach (admin_fetch_all($sql, $params) as $row) {
      $period = (string) ($row['period'] ?? '');
      if (isset($series[$name]['values'][$period])) {
        $series[$name]['values'][$period] = (int) ($row['total'] ?? 0);
      }
    }
  }

  $max = 1;
  foreach ($series as $item) {
    foreach ($item['values'] as $value) {
      $max = max($max, (int) $value);
    }
  }

  return [
    'keys' => $keys,
    'labels' => $labels,
    'series' => $series,
    'max' => $max,
  ];
}

function admin_line_chart_points(array $values, array $keys, int $max): string {
  $count = max(1, count($keys) - 1);
  $points = [];
  foreach ($keys as $index => $key) {
    $x = $count === 0 ? 50 : ($index / $count) * 100;
    $value = (int) ($values[$key] ?? 0);
    $y = 88 - (($value / max(1, $max)) * 72);
    $points[] = round($x, 2) . ',' . round($y, 2);
  }
  return implode(' ', $points);
}

function admin_load_dashboard_data(): array {
  husay_schema();
  resident_enforce_inactivity_rule();
  announcement_publish_due();
  barangay_ensure_feedback_image_columns();
  barangay_ensure_document_fee_schema();

  $staff = admin_fetch_all(
    "SELECT user_code AS id, full_name AS name, position_title AS position, username, mobile, status, DATE(created_at) AS created
     FROM users
     WHERE role = 'staff'
     ORDER BY created_at DESC"
  );

  $residents = admin_fetch_all(
    "SELECT user_code AS id, full_name AS name, username, mobile, address, purok, household, birthdate,
            TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age, gender, civil_status,
            verification_status, qr_status, qr_code, profile_photo_path, status, DATE(created_at) AS registered,
            GREATEST(COALESCE(last_active_at, last_login_at), COALESCE(last_login_at, last_active_at)) AS last_used_at,
            inactive_reason, inactivated_at, reactivated_at
     FROM users
     WHERE role = 'resident'
     ORDER BY created_at DESC"
  );

  $announcements = admin_fetch_all(
    'SELECT id, title, body, status, archived_at, post_type, event_at, event_location, DATE(publish_date) AS date,
            DATE_FORMAT(COALESCE(publish_at, CONCAT(publish_date, " 00:00:00")), "%H:%i") AS publish_time,
            DATE_FORMAT(COALESCE(publish_at, CONCAT(publish_date, " 00:00:00")), "%Y-%m-%d %H:%i") AS publish_schedule,
            COALESCE(photo_file_name, "") AS photo_file_name,
            COALESCE(photo_path, "") AS photo_path
     FROM announcements
     ORDER BY publish_date DESC, created_at DESC'
  );

  $documents = barangay_document_requests();

  $complaints = admin_fetch_all(
    'SELECT complaint_code AS id, resident_name AS resident, subject, category, details, location, COALESCE(NULLIF(contact_mobile, ""), (SELECT mobile FROM users WHERE users.id=complaints.resident_user_id), "") AS contact_mobile, intake_source, remarks, status, DATE(filed_at) AS date
     FROM complaints
     WHERE archived_at IS NULL
     ORDER BY filed_at DESC'
  );

  $feedbacks = admin_fetch_all(
    'SELECT feedback_code AS id, resident_name AS resident, category, message, rating,
            COALESCE(image_file_name, "") AS image_file_name,
            COALESCE(image_path, "") AS image_path,
            is_anonymous, DATE(submitted_at) AS date, status
     FROM feedbacks
     WHERE archived_at IS NULL
     ORDER BY submitted_at DESC'
  );

  $qrResidents = admin_fetch_all(
    "SELECT user_code AS id, full_name AS name, username, mobile, address, purok, household, birthdate,
            TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age, gender, civil_status, verification_status,
            status, qr_status, qr_code, DATE(qr_generated_at) AS qr_generated_at, profile_photo_path
     FROM users
     WHERE role = 'resident'
     ORDER BY full_name ASC"
  );

  $qrScanLogs = admin_fetch_all(
    "SELECT qr_scan_logs.id, qr_scan_logs.resident_code, qr_scan_logs.resident_name,
            qr_scan_logs.result, DATE_FORMAT(qr_scan_logs.scanned_at, '%Y-%m-%d %h:%i %p') AS scanned_at,
            COALESCE(users.full_name, 'Admin') AS staff
     FROM qr_scan_logs
     LEFT JOIN users ON users.id = qr_scan_logs.scanned_by
     ORDER BY qr_scan_logs.scanned_at DESC
     LIMIT 100"
  );

  $smsLogs = admin_fetch_all(
    "SELECT sms_logs.recipient_name AS recipient, sms_logs.recipient_mobile AS phone, sms_logs.message,
            sms_logs.message_type, COALESCE(users.full_name, 'Admin') AS sender,
            DATE_FORMAT(sms_logs.created_at, '%Y-%m-%d %h:%i %p') AS sent_at, sms_logs.status
     FROM sms_logs
     LEFT JOIN users ON users.id = sms_logs.sent_by
     ORDER BY sms_logs.created_at DESC
     LIMIT 100"
  );

  return [
    'staff' => $staff,
    'residents' => $residents,
    'announcements' => $announcements,
    'documents' => $documents,
    'complaints' => $complaints,
    'feedbacks' => $feedbacks,
    'qrResidents' => $qrResidents,
    'qrScanLogs' => $qrScanLogs,
    'smsLogs' => $smsLogs,
    'monthlyTrends' => admin_monthly_activity_trends(6),
    'stats' => [
      'staff' => admin_count('users', "role = 'staff' AND status = 'Active'"),
      'residents' => admin_count('users', "role = 'resident'"),
      'complaints' => admin_count('complaints', "status <> 'Resolved' AND archived_at IS NULL"),
      'documents' => admin_count('document_requests', "status IN ('Pending', 'Processing') AND archived_at IS NULL"),
    ],
  ];
}
?>
