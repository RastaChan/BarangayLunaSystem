<?php
/** Barangay Staff top header */
$staffName = $staffName ?? 'Barangay Staff';
$staffRole = $staffRole ?? 'Staff';
$pageTitles = [
  'husay-schedule' => 'Mediation Schedule',
  'staff-dashboard' => 'Dashboard',
  'issue-documents' => 'Issue Documents',
  'staff-document-requests' => 'Document Requests',
  'walk-in-payments' => 'Walk-in Payments',
  'resident-account-registration' => 'Resident Account Registration',
  'resident-management' => 'QR and Resident Management',
  'staff-qr-verification' => 'QR and Resident Management',
  'staff-complaints' => 'Complaints',
  'staff-feedbacks' => 'Feedbacks',
  'staff-announcements' => 'Announcements',
  'staff-sms-alerts' => 'SMS Notifications',
  'my-activity-logs' => 'My Activity Logs',
  'service-reports' => 'Service Reports',
  'my-profile' => 'My Profile',
];
$pageTitle = $pageTitles[$activeView ?? 'staff-dashboard'] ?? 'Dashboard';
$notificationItems = [];
foreach ($staffRequests ?? [] as $request) {
  if (in_array($request['status'] ?? '', ['Pending', 'Processing'], true)) {
    $notificationItems[] = [
      'id' => 'staff-doc-' . ($request['id'] ?? '') . '-' . ($request['status'] ?? ''),
      'title' => 'Document request pending',
      'message' => ($request['resident'] ?? 'Resident') . ' requested ' . ($request['type'] ?? 'a document') . '.',
      'date' => $request['date'] ?? '',
      'href' => 'staff-dashboard.php?view=staff-document-requests',
      'view' => 'staff-document-requests',
    ];
  }
}
foreach ($staffComplaints ?? [] as $complaint) {
  if (in_array($complaint['status'] ?? '', ['Open', 'In Progress'], true)) {
    $notificationItems[] = [
      'id' => 'staff-complaint-' . ($complaint['id'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'title' => 'Complaint needs update',
      'message' => ($complaint['subject'] ?? 'Complaint') . ' from ' . ($complaint['resident'] ?? 'resident') . '.',
      'date' => $complaint['date'] ?? '',
      'href' => 'staff-dashboard.php?view=staff-complaints',
      'view' => 'staff-complaints',
    ];
  }
}
foreach ($staffFeedbacks ?? [] as $feedback) {
  if (($feedback['status'] ?? '') !== 'Read') {
    $notificationItems[] = [
      'id' => 'staff-feedback-' . ($feedback['id'] ?? '') . '-' . ($feedback['status'] ?? ''),
      'title' => 'Unread feedback',
      'message' => $feedback['message'] ?? '',
      'date' => $feedback['date'] ?? '',
      'href' => 'staff-dashboard.php?view=staff-feedbacks&feedback=' . rawurlencode($feedback['id']),
      'view' => 'staff-feedbacks',
    ];
  }
}
$notificationCount = count($notificationItems);
?>
<header class="top-header header">
  <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu">
    <svg width="22" height="22" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="menu" class="system-icon"><path d="M216,64V192H40V64Z" opacity="0.2"/><path d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,72H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z"/></svg>
  </button>

  <div class="header-title">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <p>Barangay Luna Smart Barangay Portal</p>
  </div>

  <div class="header-actions">
    <?php include __DIR__ . '/notification-bell.php'; ?>
    <?php
      $profileName = $staffName;
      $profileRole = $staffRole;
      $profileUsername = $loggedInUser['username'] ?? '';
      $profileContact = $loggedInUser['contact'] ?? '09171234567';
      include __DIR__ . '/profile-dropdown.php';
    ?>
  </div>
</header>
