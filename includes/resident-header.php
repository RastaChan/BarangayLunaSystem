<?php
/** Resident/User top header */
$residentName = $residentName ?? 'Resident';
$residentRole = $residentRole ?? 'Resident';
$pageTitles = [
  'resident-dashboard' => 'Dashboard',
  'request-document' => 'Request Document',
  'my-document-requests' => 'My Document Requests',
  'resident-announcements' => 'Announcements',
  'upcoming-events' => 'Upcoming Events',
  'submit-complaint' => 'Submit Complaint',
  'my-complaints' => 'My Complaints',
  'send-feedback' => 'Send Feedback',
  'sms-updates' => 'SMS Updates',
  'my-activity-logs' => 'My Activity Logs',
  'my-profile' => 'My Profile',
  'change-password' => 'Change Password',
];
$pageTitle = $pageTitles[$activeView ?? 'resident-dashboard'] ?? 'Dashboard';
$notificationItems = [];
foreach ($residentNotifications ?? [] as $notification) {
  $notificationItems[] = [
    'id' => $notification['id'] ?? '',
    'title' => $notification['title'] ?? 'Notification',
    'message' => $notification['message'] ?? '',
    'date' => $notification['date'] ?? '',
    'status' => $notification['status'] ?? '',
    'href' => $notification['href'] ?? 'resident-dashboard.php?view=resident-dashboard',
    'view' => $notification['view'] ?? '',
  ];
}
$notificationCount = count(array_filter($notificationItems, static fn(array $item): bool => ($item['status'] ?? '') === 'Unread'));
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
      $profileName = $residentName;
      $profileRole = $residentRole;
      $profileUsername = $loggedInUser['username'] ?? '';
      $profileContact = $loggedInUser['contact'] ?? ($residentProfile['contact'] ?? '');
      include __DIR__ . '/profile-dropdown.php';
    ?>
  </div>
</header>
