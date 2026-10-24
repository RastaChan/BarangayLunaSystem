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
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
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
