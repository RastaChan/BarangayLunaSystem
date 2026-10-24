<?php
/** Barangay Staff sidebar */
$activeView = $activeView ?? 'staff-dashboard';
$sidebarName = $loggedInUser['name'] ?? $staffName ?? 'Barangay Staff';
$staffDocumentBadgeCount = count(array_filter($staffRequests ?? [], static fn(array $request): bool => in_array($request['status'] ?? '', ['Pending', 'Processing'], true)));
$staffComplaintBadgeCount = count(array_filter($staffComplaints ?? [], static fn(array $complaint): bool => in_array($complaint['status'] ?? '', ['Open', 'In Progress'], true)));
$staffFeedbackBadgeCount = count(array_filter($staffFeedbacks ?? [], static fn(array $feedback): bool => ($feedback['status'] ?? '') !== 'Read'));
$staffPaymentBadgeCount = count(array_filter($walkInPayments ?? [], static fn(array $payment): bool => ($payment['status'] ?? '') === 'Pending'));
function staff_nav_link(string $view, string $label, string $iconPath, string $activeView, int $count = 0): void {
  $active = $activeView === $view ? 'is-active' : '';
  echo '<a href="staff-dashboard.php?view=' . htmlspecialchars($view) . '" class="nav-link ' . $active . '" data-nav-view="' . htmlspecialchars($view) . '"' . ($view === 'issue-documents' ? ' data-notification-count-view="staff-document-requests"' : '') . '>';
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
      <span>Staff Portal</span>
    </div>
  </div>

  <div class="sidebar-role">
    <span class="role-pill">BARANGAY STAFF</span>
  </div>

  <nav class="sidebar-nav" id="sidebarNav" aria-label="Barangay Staff navigation">
    <p class="nav-section">Overview</p>
    <?php staff_nav_link('staff-dashboard', 'Dashboard', 'M4 11 12 4l8 7v9H4v-9Z', $activeView); ?>

    <p class="nav-section">Services</p>
    <?php staff_nav_link('issue-documents', 'Issue Documents', 'M7 3h7l4 4v14H7V3Zm7 0v5h5M9 13h6M9 17h4', $activeView); ?>
    <?php staff_nav_link('walk-in-payments', 'Walk-in Payments', 'M6 3h12v18H6V3Zm3 4h6M9 11h6M9 15h4', $activeView, $staffPaymentBadgeCount); ?>
    <?php staff_nav_link('resident-account-registration', 'Resident Account Registration', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20v-1a7 7 0 0 1 14 0v1M19 8v6M16 11h6', $activeView); ?>
    <?php staff_nav_link('resident-management', 'QR and Resident Management', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20v-1a7 7 0 0 1 14 0v1', $activeView === 'staff-qr-verification' ? 'resident-management' : $activeView); ?>

    <p class="nav-section">Community</p>
    <?php staff_nav_link('husay-schedule', 'Husay Schedule', 'M4 5h16v16H4V5Zm4-3v6m8-6v6M4 11h16m-12 4h2m4 0h2', $activeView); ?>
    <?php staff_nav_link('staff-complaints', 'Complaints', 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z', $activeView, $staffComplaintBadgeCount); ?>
    <?php staff_nav_link('staff-feedbacks', 'Feedbacks', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z', $activeView, $staffFeedbackBadgeCount); ?>

    <p class="nav-section">Communication</p>
    <?php staff_nav_link('staff-sms-alerts', 'SMS Notifications', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8ZM9 10h6M9 14h3', $activeView); ?>

    <p class="nav-section">Reports</p>
    <?php staff_nav_link('my-activity-logs', 'My Activity Logs', 'M9 11l3 3L22 4M11 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11', $activeView); ?>
    <?php staff_nav_link('service-reports', 'Service Reports', 'M4 20V10M10 20V4M16 20v-6M22 20H2', $activeView); ?>

  </nav>

</aside>
