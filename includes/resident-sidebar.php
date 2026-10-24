<?php
/** Resident/User sidebar */
$activeView = $activeView ?? 'resident-dashboard';
$sidebarName = $loggedInUser['name'] ?? $residentName ?? 'Resident';
$residentDocumentBadgeCount = count(array_filter($myDocumentRequests ?? [], static fn(array $request): bool => in_array($request['status'] ?? '', ['Pending', 'Processing'], true)));
$residentComplaintBadgeCount = count(array_filter($myComplaints ?? [], static fn(array $complaint): bool => in_array($complaint['status'] ?? '', ['Open', 'In Progress', 'Closed'], true)));
$residentAnnouncementBadgeCount = count(array_filter($residentNotifications ?? [], static fn(array $notification): bool => ($notification['title'] ?? '') === 'Barangay announcement' && ($notification['status'] ?? '') === 'Unread'));
function resident_nav_link(string $view, string $label, string $iconPath, string $activeView, int $count = 0): void {
  $active = $activeView === $view ? 'is-active' : '';
  echo '<a href="resident-dashboard.php?view=' . htmlspecialchars($view) . '" class="nav-link ' . $active . '" data-nav-view="' . htmlspecialchars($view) . '">';
  echo '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' . htmlspecialchars($iconPath) . '" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  echo '<span class="nav-link-label">' . htmlspecialchars($label) . '</span>';
  if ($count > 0) {
    echo '<span class="nav-count" data-notification-side-count hidden>' . htmlspecialchars($count > 99 ? '99+' : (string) $count) . '</span>';
  }
  echo '</a>';
}
?>
<aside class="sidebar" id="appSidebar">
  <div class="sidebar-brand">
    <img src="branding-logo.php" alt="Barangay Luna" class="sidebar-logo"
         onerror="this.style.visibility='hidden';">
    <div class="sidebar-brand-text">
      <strong><?= htmlspecialchars($sidebarName) ?></strong>
      <span>Resident Portal</span>
    </div>
  </div>

  <div class="sidebar-role">
    <span class="role-pill">RESIDENT</span>
  </div>

  <nav class="sidebar-nav" id="sidebarNav" aria-label="Resident navigation">
    <p class="nav-section">Overview</p>
    <?php resident_nav_link('resident-dashboard', 'Dashboard', 'M4 11 12 4l8 7v9H4v-9Z', $activeView); ?>

    <p class="nav-section">Services</p>
    <?php resident_nav_link('request-document', 'Request Document', 'M8 4h8l2 4v12H6V4h2Zm1 8h6M9 16h4', $activeView); ?>
    <?php resident_nav_link('my-document-requests', 'My Document Requests', 'M4 5h16M4 12h16M4 19h10', $activeView, $residentDocumentBadgeCount); ?>

    <p class="nav-section">Community</p>
    <?php resident_nav_link('submit-complaint', 'Submit Complaint', 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z', $activeView); ?>
    <?php resident_nav_link('my-complaints', 'My Complaints', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z', $activeView, $residentComplaintBadgeCount); ?>
    <?php resident_nav_link('send-feedback', 'Send Feedback', 'M4 4h16v12H7l-3 3V4Zm5 5h6M9 13h4', $activeView); ?>

    <p class="nav-section">Updates</p>
    <?php resident_nav_link('sms-updates', 'SMS Updates', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8ZM9 10h6M9 14h3', $activeView); ?>

    <p class="nav-section">Account</p>
    <?php resident_nav_link('my-activity-logs', 'Activity Logs', 'M12 8v4l3 2M3 12a9 9 0 1 0 9-9 9 9 0 0 0-9 9Z', $activeView); ?>
  </nav>

</aside>
