<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$user = barangay_current_user();
if (!$user) {
  http_response_code(401);
  echo json_encode(['items' => [], 'sideCounts' => []]);
  exit;
}

// Release the session lock before read-only polling queries so clicks are not queued behind the feed.
session_write_close();

function notification_feed_all(string $sql, array $params = []): array {
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function notification_feed_count(string $sql, array $params = []): int {
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

function notification_feed_item(string $id, string $title, string $message, string $date, string $href, string $view, string $status = 'Unread'): array {
  return [
    'id' => $id,
    'title' => $title,
    'message' => $message,
    'date' => $date,
    'href' => $href,
    'view' => $view,
    'status' => $status,
  ];
}

$role = $user['role'] ?? '';
$items = [];
$sideCounts = [];

if ($role === 'admin') {
  $documents = notification_feed_all(
    "SELECT request_code, resident_name, document_type, status, requested_at AS date
     FROM document_requests
     WHERE archived_at IS NULL AND status IN ('Pending', 'Processing')
     ORDER BY requested_at DESC
     LIMIT 30"
  );
  foreach ($documents as $document) {
    $items[] = notification_feed_item(
      'admin-doc-' . ($document['request_code'] ?? '') . '-' . ($document['status'] ?? ''),
      'Document request needs review',
      ($document['resident_name'] ?? 'Resident') . ' requested ' . ($document['document_type'] ?? 'a document') . '.',
      $document['date'] ?? '',
      'dashboard.php?view=documents',
      'documents'
    );
  }

  $complaints = notification_feed_all(
    "SELECT complaint_code, resident_name, subject, status, filed_at AS date
     FROM complaints
     WHERE archived_at IS NULL AND status IN ('Open', 'In Progress')
     ORDER BY filed_at DESC
     LIMIT 30"
  );
  foreach ($complaints as $complaint) {
    $items[] = notification_feed_item(
      'admin-complaint-' . ($complaint['complaint_code'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'Complaint needs action',
      ($complaint['subject'] ?? 'Complaint') . ' from ' . ($complaint['resident_name'] ?? 'resident') . '.',
      $complaint['date'] ?? '',
      'dashboard.php?view=complaints',
      'complaints'
    );
  }

  $feedbacks = notification_feed_all(
    "SELECT feedback_code, resident_name, message, status, submitted_at AS date
     FROM feedbacks
     WHERE archived_at IS NULL AND COALESCE(status, 'Unread') <> 'Read'
     ORDER BY submitted_at DESC
     LIMIT 30"
  );
  foreach ($feedbacks as $feedback) {
    $items[] = notification_feed_item(
      'admin-feedback-' . ($feedback['feedback_code'] ?? '') . '-' . ($feedback['status'] ?? ''),
      'New resident feedback',
      $feedback['message'] ?? '',
      $feedback['date'] ?? '',
      'dashboard.php?view=feedbacks&feedback=' . rawurlencode($feedback['feedback_code']),
      'feedbacks'
    );
  }

  $sideCounts = [
    'documents' => notification_feed_count("SELECT COUNT(*) FROM document_requests WHERE archived_at IS NULL AND status IN ('Pending', 'Processing')"),
    'complaints' => notification_feed_count("SELECT COUNT(*) FROM complaints WHERE archived_at IS NULL AND status IN ('Open', 'In Progress')"),
    'feedbacks' => notification_feed_count("SELECT COUNT(*) FROM feedbacks WHERE archived_at IS NULL AND COALESCE(status, 'Unread') <> 'Read'"),
  ];
}

if ($role === 'staff') {
  $documents = notification_feed_all(
    "SELECT request_code, resident_name, document_type, status, requested_at AS date
     FROM document_requests
     WHERE archived_at IS NULL AND status IN ('Pending', 'Processing')
     ORDER BY requested_at DESC
     LIMIT 30"
  );
  foreach ($documents as $document) {
    $items[] = notification_feed_item(
      'staff-doc-' . ($document['request_code'] ?? '') . '-' . ($document['status'] ?? ''),
      'Document request pending',
      ($document['resident_name'] ?? 'Resident') . ' requested ' . ($document['document_type'] ?? 'a document') . '.',
      $document['date'] ?? '',
      'staff-dashboard.php?view=staff-document-requests',
      'staff-document-requests'
    );
  }

  $complaints = notification_feed_all(
    "SELECT complaint_code, resident_name, subject, status, filed_at AS date
     FROM complaints
     WHERE archived_at IS NULL AND status IN ('Open', 'In Progress')
     ORDER BY filed_at DESC
     LIMIT 30"
  );
  foreach ($complaints as $complaint) {
    $items[] = notification_feed_item(
      'staff-complaint-' . ($complaint['complaint_code'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'Complaint needs update',
      ($complaint['subject'] ?? 'Complaint') . ' from ' . ($complaint['resident_name'] ?? 'resident') . '.',
      $complaint['date'] ?? '',
      'staff-dashboard.php?view=staff-complaints',
      'staff-complaints'
    );
  }

  $feedbacks = notification_feed_all(
    "SELECT feedback_code, message, status, submitted_at AS date
     FROM feedbacks
     WHERE archived_at IS NULL AND COALESCE(status, 'Unread') <> 'Read'
     ORDER BY submitted_at DESC
     LIMIT 30"
  );
  foreach ($feedbacks as $feedback) {
    $items[] = notification_feed_item(
      'staff-feedback-' . ($feedback['feedback_code'] ?? '') . '-' . ($feedback['status'] ?? ''),
      'Unread feedback',
      $feedback['message'] ?? '',
      $feedback['date'] ?? '',
      'staff-dashboard.php?view=staff-feedbacks&feedback=' . rawurlencode($feedback['feedback_code']),
      'staff-feedbacks'
    );
  }

  $sideCounts = [
    'staff-document-requests' => notification_feed_count("SELECT COUNT(*) FROM document_requests WHERE archived_at IS NULL AND status IN ('Pending', 'Processing')"),
    'staff-complaints' => notification_feed_count("SELECT COUNT(*) FROM complaints WHERE archived_at IS NULL AND status IN ('Open', 'In Progress')"),
    'staff-feedbacks' => notification_feed_count("SELECT COUNT(*) FROM feedbacks WHERE archived_at IS NULL AND COALESCE(status, 'Unread') <> 'Read'"),
  ];
}

if ($role === 'resident') {
  require_once __DIR__ . '/includes/husay-helpers.php';
  $items = array_merge($items, husay_resident_notifications((int)$user['id']));
  $residentParams = [':user_id' => $user['id'], ':name' => $user['name']];

  $documents = notification_feed_all(
    "SELECT request_code, document_type, status, requested_at AS date
     FROM document_requests
     WHERE archived_at IS NULL
       AND status IN ('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Released', 'Rejected')
       AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))
     ORDER BY requested_at DESC
     LIMIT 30",
    $residentParams
  );
  foreach ($documents as $document) {
    $items[] = notification_feed_item(
      'resident-doc-' . ($document['request_code'] ?? '') . '-' . ($document['status'] ?? ''),
      'Document request ' . ($document['status'] ?? ''),
      ($document['document_type'] ?? 'Document') . ' is currently ' . ($document['status'] ?? 'pending') . '.',
      $document['date'] ?? '',
      'resident-dashboard.php?view=my-document-requests',
      'my-document-requests'
    );
  }

  $complaints = notification_feed_all(
    "SELECT complaint_code, subject, status, filed_at AS date
     FROM complaints
     WHERE archived_at IS NULL
       AND status IN ('Open', 'In Progress', 'Resolved', 'Closed')
       AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))
     ORDER BY filed_at DESC
     LIMIT 30",
    $residentParams
  );
  foreach ($complaints as $complaint) {
    $items[] = notification_feed_item(
      'resident-complaint-' . ($complaint['complaint_code'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'Complaint status ' . ($complaint['status'] ?? ''),
      ($complaint['subject'] ?? 'Complaint') . ' is currently ' . ($complaint['status'] ?? 'open') . '.',
      $complaint['date'] ?? '',
      'resident-dashboard.php?view=my-complaints',
      'my-complaints'
    );
  }

  $announcements = notification_feed_all(
    "SELECT id, title, publish_date AS date
     FROM announcements
     WHERE status = 'Published' AND archived_at IS NULL
     ORDER BY publish_date DESC, created_at DESC
     LIMIT 30"
  );
  foreach ($announcements as $announcement) {
    $items[] = notification_feed_item(
      'resident-announcement-' . ($announcement['id'] ?? '') . '-' . substr($announcement['date'] ?? '', 0, 10),
      'Barangay announcement',
      $announcement['title'] ?? '',
      $announcement['date'] ?? '',
      'resident-dashboard.php?view=resident-announcements',
      'resident-announcements'
    );
  }

  $sideCounts = [
    'my-document-requests' => notification_feed_count(
      "SELECT COUNT(*) FROM document_requests
       WHERE archived_at IS NULL
         AND status IN ('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Released', 'Rejected')
         AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))",
      $residentParams
    ),
    'my-complaints' => notification_feed_count(
      "SELECT COUNT(*) FROM complaints
       WHERE archived_at IS NULL
         AND status IN ('Open', 'In Progress', 'Resolved', 'Closed')
         AND (resident_user_id = :user_id OR (resident_user_id IS NULL AND resident_name = :name))",
      $residentParams
    ),
    'resident-announcements' => notification_feed_count("SELECT COUNT(*) FROM announcements WHERE status = 'Published' AND archived_at IS NULL"),
  ];
}

usort($items, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
echo json_encode([
  'owner' => $role . ':' . (int) $user['id'],
  'items' => $items,
  'sideCounts' => $sideCounts,
  'state' => $role === 'resident' ? (function () use ($user) {
    require_once __DIR__ . '/includes/notification-state.php';
    return barangay_notification_state((int) $user['id']);
  })() : null,
]);
?>
