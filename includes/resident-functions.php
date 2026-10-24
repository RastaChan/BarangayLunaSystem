<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/resident-submission-helpers.php';
require_once __DIR__ . '/qr-sms-helpers.php';
require_once __DIR__ . '/announcement-helpers.php';
require_once __DIR__ . '/document-request-helpers.php';
require_once __DIR__ . '/feedback-helpers.php';
require_once __DIR__ . '/document-fee-helpers.php';
require_once __DIR__ . '/husay-helpers.php';

function resident_redirect(string $view, string $status = 'saved'): void {
  header('Location: resident-dashboard.php?view=' . urlencode($view) . '&status=' . urlencode($status));
  exit;
}

function resident_status_message(): ?array {
  $status = $_GET['status'] ?? '';
  if ($status === 'validation-error' && isset($_SESSION['resident_action_error'])) {
    $message = (string) $_SESSION['resident_action_error'];
    unset($_SESSION['resident_action_error']);
    return ['error', $message];
  }
  $messages = [
    'saved' => ['success', 'Changes saved successfully.'],
    'created' => ['success', 'Your request was submitted successfully.'],
    'deleted' => ['success', 'Your submission was deleted.'],
    'updated' => ['success', 'Your account was updated successfully.'],
    'password-updated' => ['success', 'Your password was changed successfully.'],
    'missing' => ['error', 'Please complete the required fields.'],
    'invalid' => ['error', 'The selected action could not be completed.'],
    'password-mismatch' => ['error', 'The new password and confirmation do not match.'],
    'wrong-password' => ['error', 'The current password is incorrect.'],
  ];

  return $messages[$status] ?? null;
}

function resident_log(array $user, string $action, string $module, string $status = 'Success'): void {
  $stmt = barangay_db()->prepare(
    'INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at)
     VALUES (:user_id, :user_name, :user_role, :action, :module, :status, NOW())'
  );
  $stmt->execute([
    ':user_id' => $user['id'] ?? null,
    ':user_name' => $user['name'] ?? 'Resident',
    ':user_role' => 'Resident',
    ':action' => $action,
    ':module' => $module,
    ':status' => $status,
  ]);
}

function resident_activity_history(array $user, int $page = 1): array {
  if (($user['role'] ?? '') !== 'resident' || (int) ($user['id'] ?? 0) < 1) {
    throw new InvalidArgumentException('A signed-in resident is required.');
  }
  $db = barangay_db();
  $params = [':user_id' => (int) $user['id']];
  $count = $db->prepare('SELECT COUNT(*) FROM audit_logs WHERE user_id = :user_id');
  $count->execute($params);
  $total = (int) $count->fetchColumn();
  $pages = max(1, (int) ceil($total / 20));
  $page = max(1, min($page, $pages));
  $offset = ($page - 1) * 20;
  $stmt = $db->prepare('SELECT action, module, status, created_at FROM audit_logs
    WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 20 OFFSET ' . $offset);
  $stmt->execute($params);
  return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function resident_next_code(string $table, string $column, string $prefix): string {
  $stmt = barangay_db()->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :prefix ORDER BY id DESC LIMIT 1");
  $stmt->execute([':prefix' => $prefix . '-%']);
  $last = $stmt->fetchColumn();
  $next = 1;

  if ($last && preg_match('/(\d+)$/', $last, $matches)) {
    $next = ((int) $matches[1]) + 1;
  }

  return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function resident_fetch_all(string $sql, array $params = []): array {
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function resident_default_document_types(): array {
  return [
    'Barangay Certification',
    'Certificate of Residency',
    'Certificate of Indigency',
    'Other Barangay Certificate',
  ];
}

function resident_retired_document_types(): array {
  return [
    'Barangay Clearance',
    'Business Clearance',
  ];
}

function resident_document_type_key(string $type): string {
  return strtolower(preg_replace('/\s+/', ' ', trim($type)));
}

function resident_preferred_document_type_label(string $type): string {
  $type = preg_replace('/\s+/', ' ', trim($type));
  if ($type === '') {
    return '';
  }

  $key = resident_document_type_key($type);
  foreach (resident_default_document_types() as $defaultType) {
    if (resident_document_type_key($defaultType) === $key) {
      return $defaultType;
    }
  }

  if (strtoupper($type) === $type) {
    $words = explode(' ', ucwords(strtolower($type)));
    $minorWords = ['of', 'and', 'for', 'the', 'or'];
    foreach ($words as $index => $word) {
      $lower = strtolower($word);
      if ($index > 0 && in_array($lower, $minorWords, true)) {
        $words[$index] = $lower;
      }
    }
    return implode(' ', $words);
  }
  return $type;
}

function resident_is_retired_document_type(string $type): bool {
  $key = resident_document_type_key($type);
  foreach (resident_retired_document_types() as $retiredType) {
    if (resident_document_type_key($retiredType) === $key) {
      return true;
    }
  }
  return false;
}

function resident_document_type_rows(): array {
  $feeMap = barangay_document_fee_map();
  $rows = [];
  foreach (resident_default_document_types() as $type) {
    $rows[resident_document_type_key($type)] = [
      'name' => $type,
      'is_default' => true,
      'added_at' => '',
      'fee_amount' => $feeMap[resident_document_type_key($type)] ?? null,
    ];
  }

  try {
    $templateRows = resident_fetch_all(
      "SELECT document_type, MAX(COALESCE(created_at, updated_at)) AS added_at
       FROM document_templates
       WHERE document_type IS NOT NULL AND document_type <> ''
       GROUP BY document_type
       ORDER BY document_type"
    );
    foreach ($templateRows as $row) {
      $type = resident_preferred_document_type_label((string) ($row['document_type'] ?? ''));
      if ($type === '' || resident_is_retired_document_type($type)) {
        continue;
      }
      $key = resident_document_type_key($type);
      if (!isset($rows[$key])) {
        $rows[$key] = [
          'name' => $type,
          'is_default' => false,
          'added_at' => (string) ($row['added_at'] ?? ''),
          'fee_amount' => $feeMap[$key] ?? null,
        ];
      }
    }
  } catch (Throwable $e) {
    // Keep default document types available during setup.
  }

  $ordered = array_values($rows);
  usort($ordered, static function (array $left, array $right): int {
    $leftOther = resident_document_type_key($left['name']) === 'other barangay certificate';
    $rightOther = resident_document_type_key($right['name']) === 'other barangay certificate';
    if ($leftOther && !$rightOther) return 1;
    if (!$leftOther && $rightOther) return -1;
    return strcasecmp($left['name'], $right['name']);
  });
  return $ordered;
}

function resident_document_types(): array {
  return array_map(static fn(array $row): string => $row['name'], resident_document_type_rows());
}

function resident_profile(array $user): array {
  $stmt = barangay_db()->prepare(
    "SELECT user_code, full_name, username, birthdate, TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
            DATE(created_at) AS registered, gender, civil_status, mobile, address, purok, household,
            profile_photo_path,
            verification_status, status, qr_status, qr_code, qr_generated_at
     FROM users
     WHERE id = :id AND role = 'resident'
     LIMIT 1"
  );
  $stmt->execute([':id' => $user['id']]);
  $profileRow = $stmt->fetch() ?: [];

  return [
    'id' => $profileRow['user_code'] ?? $user['user_code'],
    'name' => $profileRow['full_name'] ?? $user['name'],
    'username' => $profileRow['username'] ?? $user['username'] ?? '',
    'age' => $profileRow['age'] ?? null,
    'registered' => $profileRow['registered'] ?? '',
    'birthdate' => $profileRow['birthdate'] ?? '',
    'gender' => $profileRow['gender'] ?? '',
    'civil_status' => $profileRow['civil_status'] ?? '',
    'contact' => $profileRow['mobile'] ?? '',
    'address' => $profileRow['address'] ?? '',
    'purok' => $profileRow['purok'] ?? '',
    'household' => $profileRow['household'] ?? '',
    'profile_photo_path' => $profileRow['profile_photo_path'] ?? ($user['profile_photo_path'] ?? ''),
    'verification_status' => $profileRow['verification_status'] ?? 'Pending',
    'account_status' => $profileRow['status'] ?? 'Pending',
    'resident_status' => $profileRow['status'] ?? 'Pending',
    'qr_status' => $profileRow['qr_status'] ?? 'Inactive',
    'qr_code' => $profileRow['qr_code'] ?? '',
    'qr_generated_at' => $profileRow['qr_generated_at'] ?? '',
    'qr_image_url' => !empty($profileRow['qr_code']) ? QRVerificationHelper::generateQRImageURL($profileRow['qr_code'], 260) : '',
  ];
}

function resident_load_dashboard_data(array $user): array {
  husay_schema();
  announcement_publish_due();
  barangay_ensure_document_request_detail_columns();
  barangay_ensure_feedback_image_columns();
  barangay_ensure_document_fee_schema();
  $profile = resident_profile($user);

  $documentRequests = resident_fetch_all(
    'SELECT resident_user_id, request_code AS id, document_type AS type, purpose, fee_amount, copies, unit_fee, DATE(requested_at) AS date, status,
            COALESCE(remarks, "") AS remarks
     FROM document_requests
     WHERE archived_at IS NULL AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))
     ORDER BY requested_at DESC',
    [':user_id' => $user['id'], ':name' => $user['name']]
  );

  $complaints = resident_fetch_all(
    'SELECT resident_user_id, complaint_code AS id, subject, COALESCE(category, "General concern") AS category,
            DATE(filed_at) AS date, status, COALESCE(remarks, "Waiting for staff update") AS remarks
     FROM complaints
     WHERE archived_at IS NULL AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))
     ORDER BY filed_at DESC',
    [':user_id' => $user['id'], ':name' => $user['name']]
  );

  $announcements = resident_fetch_all(
    'SELECT id, title, DATE(publish_date) AS date, IF(post_type = "event", "Event", "Public Advisory") AS category, body,
            post_type, event_at, event_location,
            COALESCE(photo_file_name, "") AS photo_file_name,
            COALESCE(photo_path, "") AS photo_path
     FROM announcements
     WHERE status = "Published" AND archived_at IS NULL
     ORDER BY publish_date DESC, created_at DESC'
  );

  $smsUpdates = resident_fetch_all(
    'SELECT message, DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS date, status
     FROM sms_logs
     WHERE recipient_mobile = :mobile OR recipient_name = :name
     ORDER BY created_at DESC',
    [':mobile' => $profile['contact'], ':name' => $profile['name']]
  );

  $notifications = [];
  foreach (array_slice($documentRequests, 0, 5) as $request) {
    $notifications[] = [
      'id' => 'resident-doc-' . ($request['id'] ?? '') . '-' . ($request['status'] ?? ''),
      'title' => 'Document request ' . $request['status'],
      'message' => $request['type'] . ' is currently ' . $request['status'] . '.',
      'date' => $request['date'],
      'status' => 'Unread',
      'href' => 'resident-dashboard.php?view=my-document-requests',
      'view' => 'my-document-requests',
    ];
  }
  foreach (array_slice($complaints, 0, 3) as $complaint) {
    $notifications[] = [
      'id' => 'resident-complaint-' . ($complaint['id'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'title' => 'Complaint status ' . $complaint['status'],
      'message' => $complaint['subject'] . ' is currently ' . $complaint['status'] . '.',
      'date' => $complaint['date'],
      'status' => 'Unread',
      'href' => 'resident-dashboard.php?view=my-complaints',
      'view' => 'my-complaints',
    ];
  }
  foreach (array_slice($announcements, 0, 3) as $announcement) {
    $notifications[] = [
      'id' => 'resident-announcement-' . ($announcement['id'] ?? '') . '-' . ($announcement['date'] ?? ''),
      'title' => 'Barangay announcement',
      'message' => $announcement['title'],
      'date' => $announcement['date'],
      'status' => 'Unread',
      'href' => 'resident-dashboard.php?view=resident-announcements',
      'view' => 'resident-announcements',
    ];
  }

  $notifications = array_merge($notifications, husay_resident_notifications((int)$user['id']));
  $pendingRequests = count(array_filter($documentRequests, fn($request) => $request['status'] === 'Pending'));
  $approvedRequests = count(array_filter($documentRequests, fn($request) => in_array($request['status'], ['Approved', 'Ready for Pickup', 'Released'], true)));

  return [
    'residentProfile' => $profile,
    'documentTypes' => resident_document_types(),
    'documentTypeRows' => resident_document_type_rows(),
    'myDocumentRequests' => $documentRequests,
    'myComplaints' => $complaints,
    'residentAnnouncements' => $announcements,
    'upcomingEvents' => array_map(
      fn($item) => [
        'title' => $item['title'],
        'date' => date('Y-m-d H:i', strtotime($item['event_at'])),
        'location' => $item['event_location'],
        'photo_path' => $item['photo_path'],
        'description' => $item['body'],
        'status' => 'Posted',
      ],
      resident_fetch_all('SELECT title, body, event_at, event_location, COALESCE(photo_path, "") AS photo_path FROM announcements WHERE post_type = "event" AND status = "Published" AND archived_at IS NULL AND event_at >= NOW() ORDER BY event_at ASC LIMIT 6')
    ),
    'residentNotifications' => $notifications,
    'smsUpdates' => $smsUpdates,
    'pendingRequests' => $pendingRequests,
    'approvedRequests' => $approvedRequests,
  ];
}
?>
