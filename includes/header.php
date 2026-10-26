<?php
/** Admin top header */
$adminName = $adminName ?? 'Barangay Administrator';
$pageTitles = [
  'husay-schedule' => 'Mediation Schedule',
  'dashboard'     => 'Dashboard',
  'announcements' => 'Announcements',
  'residents'     => 'QR and Resident Management',
  'documents'     => 'Document Requests',
  'complaints'    => 'Complaints',
  'feedbacks'     => 'Feedbacks',
  'analytics'     => 'Analytics',
  'user-management' => 'User Management',
  'qr-verification' => 'QR and Resident Management',
  'sms-alerts'    => 'SMS Alerts',
  'reports-analytics' => 'Reports & Analytics',
  'audit-logs'    => 'Audit Logs',
  'backup-restore' => 'Backup & Restore',
  'system-settings' => 'System Settings',
  'template-placeholders' => 'Template Placeholders',
  'document-fees' => 'Document Fees',
  'issue-documents' => 'Issue Documents',
  'resident-account-registration' => 'Resident Account Registration',
  'service-reports' => 'Service Reports',
  'walk-in-payments' => 'Walk-in Payments',
];
$pageTitle = $pageTitles[$activeView ?? 'dashboard'] ?? 'Dashboard';
$notificationItems = [];
foreach ($adminDocuments ?? [] as $document) {
  if (in_array($document['status'] ?? '', ['Pending', 'Processing'], true)) {
    $notificationItems[] = [
      'id' => 'admin-doc-' . ($document['id'] ?? '') . '-' . ($document['status'] ?? ''),
      'title' => 'Document request needs review',
      'message' => ($document['resident'] ?? 'Resident') . ' requested ' . ($document['type'] ?? 'a document') . '.',
      'date' => $document['date'] ?? '',
      'href' => 'dashboard.php?view=documents',
      'view' => 'documents',
    ];
  }
}
foreach ($adminComplaints ?? [] as $complaint) {
  if (in_array($complaint['status'] ?? '', ['Open', 'In Progress'], true)) {
    $notificationItems[] = [
      'id' => 'admin-complaint-' . ($complaint['id'] ?? '') . '-' . ($complaint['status'] ?? ''),
      'title' => 'Complaint needs action',
      'message' => ($complaint['subject'] ?? 'Complaint') . ' from ' . ($complaint['resident'] ?? 'resident') . '.',
      'date' => $complaint['date'] ?? '',
      'href' => 'dashboard.php?view=complaints',
      'view' => 'complaints',
    ];
  }
}
foreach ($adminFeedbacks ?? [] as $feedback) {
  if (($feedback['status'] ?? 'Unread') === 'Unread') {
    $notificationItems[] = [
      'id' => 'admin-feedback-' . ($feedback['id'] ?? '') . '-' . ($feedback['status'] ?? ''),
      'title' => 'New resident feedback',
      'message' => $feedback['message'] ?? '',
      'date' => $feedback['date'] ?? '',
      'href' => 'dashboard.php?view=feedbacks&feedback=' . rawurlencode($feedback['id']),
      'view' => 'feedbacks',
    ];
  }
}
$notificationCount = count($notificationItems);
?>
<header class="top-header header">
  <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation" title="Toggle sidebar">
    <svg width="22" height="22" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="menu" class="system-icon"><path d="M216,64V192H40V64Z" opacity="0.2"/><path d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,72H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z"/></svg>
  </button>

  <div class="header-title">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <p>Barangay Luna Smart Barangay Portal</p>
  </div>

  <div class="header-actions">
    <?php include __DIR__ . '/notification-bell.php'; ?>
    <?php
      $profileName = $adminName;
      $profileRole = $loggedInUser['title'] ?? 'Administrator';
      $profileUsername = $loggedInUser['username'] ?? '';
      $profileContact = $loggedInUser['contact'] ?? '09170000000';
      include __DIR__ . '/profile-dropdown.php';
    ?>
  </div>
</header>
