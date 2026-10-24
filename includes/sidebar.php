<?php
/**
 * Admin sidebar — Barangay Luna MIS
 * MySQL later: show/hide items based on $_SESSION['role'] permissions
 */
$currentRole = $currentRole ?? 'admin';
$activeView  = $activeView ?? 'dashboard';
$sidebarName = $loggedInUser['name'] ?? $adminName ?? 'Administrator';
$adminDocumentBadgeCount = count(array_filter($adminDocuments ?? [], static fn(array $document): bool => in_array($document['status'] ?? '', ['Pending', 'Processing'], true)));
$adminComplaintBadgeCount = count(array_filter($adminComplaints ?? [], static fn(array $complaint): bool => in_array($complaint['status'] ?? '', ['Open', 'In Progress'], true)));
$adminFeedbackBadgeCount = count(array_filter($adminFeedbacks ?? [], static fn(array $feedback): bool => ($feedback['status'] ?? 'Unread') !== 'Read'));
?>
<aside class="sidebar" id="appSidebar">
  <div class="sidebar-brand">
    <!-- IMAGE PLACEHOLDER: Sidebar logo -->
    <img src="branding-logo.php" alt="Barangay Luna" class="sidebar-logo"
         onerror="this.style.visibility='hidden';">
    <div class="sidebar-brand-text">
      <strong><?= htmlspecialchars($sidebarName) ?></strong>
      <span>Admin Portal</span>
    </div>
  </div>

  <div class="sidebar-role">
    <span class="role-pill">ADMINISTRATOR</span>
  </div>

  <nav class="sidebar-nav" id="sidebarNav" aria-label="Admin navigation">
    <p class="nav-section">Overview</p>
    <a href="?role=admin&view=dashboard" class="nav-link <?= $activeView === 'dashboard' ? 'is-active' : '' ?>" data-nav-view="dashboard" title="Dashboard">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 11 12 4l8 7v9H4v-6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
      <span class="nav-label">Dashboard</span>
    </a>

    <p class="nav-section">Management</p>
    <a href="?role=admin&view=user-management" class="nav-link <?= $activeView === 'user-management' ? 'is-active' : '' ?>" data-nav-view="user-management" title="User Management">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM6 20v-1a6 6 0 0 1 12 0v1" stroke="currentColor" stroke-width="1.5"/><path d="M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">User Management</span>
    </a>
    <a href="?role=admin&view=residents" class="nav-link <?= in_array($activeView, ['residents', 'qr-verification'], true) ? 'is-active' : '' ?>" data-nav-view="residents" title="QR and Resident Management">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5"/></svg>
      <span class="nav-label">QR and Resident Management</span>
    </a>

    <p class="nav-section">Operations</p>
    <a href="?role=admin&view=issue-documents" class="nav-link <?= $activeView === 'issue-documents' ? 'is-active' : '' ?>" data-nav-view="issue-documents" data-notification-count-view="documents" title="Issue Documents">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l4 4v14H7V3Zm7 0v5h5M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="nav-label">Issue Documents</span>
    </a>
    <a href="?role=admin&view=resident-account-registration" class="nav-link <?= $activeView === 'resident-account-registration' ? 'is-active' : '' ?>" data-nav-view="resident-account-registration" title="Resident Account Registration">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20v-1a7 7 0 0 1 14 0v1M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">Resident Account Registration</span>
    </a>
    <a href="?role=admin&view=sms-alerts" class="nav-link <?= $activeView === 'sms-alerts' ? 'is-active' : '' ?>" data-nav-view="sms-alerts" title="SMS Alerts">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 10h6M9 14h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">SMS Alerts</span>
    </a>
    <a href="?role=admin&view=walk-in-payments" class="nav-link <?= $activeView === 'walk-in-payments' ? 'is-active' : '' ?>" data-nav-view="walk-in-payments" title="Walk-in Payments">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 3h12v18H6V3Zm3 4h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="nav-label">Walk-in Payments</span>
    </a>
    <a href="?role=admin&view=husay-schedule" class="nav-link <?= $activeView === 'husay-schedule' ? 'is-active' : '' ?>" data-nav-view="husay-schedule" title="Husay Schedule">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16v16H4V5Zm4-3v6m8-6v6M4 11h16m-12 4h2m4 0h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="nav-label">Husay Schedule</span>
    </a>
    <a href="?role=admin&view=complaints" class="nav-link <?= $activeView === 'complaints' ? 'is-active' : '' ?>" data-nav-view="complaints" title="Complaints">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.5"/></svg>
      <span class="nav-label">Complaints</span>
      <?php if ($adminComplaintBadgeCount > 0): ?><span class="nav-count" data-notification-side-count hidden><?= htmlspecialchars($adminComplaintBadgeCount > 99 ? '99+' : (string) $adminComplaintBadgeCount) ?></span><?php endif; ?>
    </a>
    <a href="?role=admin&view=feedbacks" class="nav-link <?= $activeView === 'feedbacks' ? 'is-active' : '' ?>" data-nav-view="feedbacks" title="Feedbacks">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="1.5"/></svg>
      <span class="nav-label">Feedbacks</span>
      <?php if ($adminFeedbackBadgeCount > 0): ?><span class="nav-count" data-notification-side-count hidden><?= htmlspecialchars($adminFeedbackBadgeCount > 99 ? '99+' : (string) $adminFeedbackBadgeCount) ?></span><?php endif; ?>
    </a>

    <p class="nav-section">Reports</p>
    <a href="?role=admin&view=reports-analytics" class="nav-link <?= $activeView === 'reports-analytics' ? 'is-active' : '' ?>" data-nav-view="reports-analytics" title="Reports &amp; Analytics">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-6M22 20H2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">Reports &amp; Analytics</span>
    </a>
    <a href="?role=admin&view=service-reports" class="nav-link <?= $activeView === 'service-reports' ? 'is-active' : '' ?>" data-nav-view="service-reports" title="Service Reports">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-6M22 20H2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">Service Reports</span>
    </a>
    <a href="?role=admin&view=audit-logs" class="nav-link <?= $activeView === 'audit-logs' ? 'is-active' : '' ?>" data-nav-view="audit-logs" title="Audit Logs">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 11l3 3L22 4M11 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">Audit Logs</span>
    </a>

    <p class="nav-section">System</p>
    <a href="?role=admin&view=backup-restore" class="nav-link <?= $activeView === 'backup-restore' ? 'is-active' : '' ?>" data-nav-view="backup-restore" title="Backup &amp; Restore">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-9 9Zm9-5v4l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <span class="nav-label">Backup &amp; Restore</span>
    </a>
    <details class="settings-nav-group" <?= in_array($activeView, ['system-settings', 'template-placeholders', 'document-fees'], true) ? 'open' : '' ?>>
      <summary class="nav-link settings-nav-toggle" title="System Settings">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM4 9h2a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Zm12-2h2a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Z" stroke="currentColor" stroke-width="1.5"/></svg>
        <span class="nav-label">System Settings</span>
      </summary>
      <div class="settings-nav-children">
        <a href="?role=admin&view=system-settings" class="nav-link <?= $activeView === 'system-settings' ? 'is-active' : '' ?>" data-nav-view="system-settings" title="General Settings" <?= $activeView === 'system-settings' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" stroke="currentColor" stroke-width="1.5"/></svg>
          <span class="nav-label">General Settings</span>
        </a>
        <a href="?role=admin&view=template-placeholders" class="nav-link <?= $activeView === 'template-placeholders' ? 'is-active' : '' ?>" data-nav-view="template-placeholders" title="Template Placeholders" <?= $activeView === 'template-placeholders' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z" stroke="currentColor" stroke-width="1.5"/><path d="M14 2v6h6M10 13l-2 2 2 2m4-4 2 2-2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="nav-label">Template Placeholders</span>
        </a>
        <a href="?role=admin&view=document-fees" class="nav-link <?= $activeView === 'document-fees' ? 'is-active' : '' ?>" data-nav-view="document-fees" title="Document Fees" <?= $activeView === 'document-fees' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M6 12h.01M18 12h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span class="nav-label">Document Fees</span>
        </a>
      </div>
    </details>
  </nav>

</aside>
