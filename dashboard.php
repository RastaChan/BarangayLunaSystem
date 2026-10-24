<?php
/**
 * Barangay Luna MIS — Admin Dashboard
 *
 * MySQL later:
 *   Load user from $_SESSION['user_id']
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/includes/staff-functions.php';
require_once __DIR__ . '/includes/audit-log-helpers.php';

$loggedInUser = barangay_require_role('admin');
$currentRole = 'admin';
$activeView  = preg_replace('/[^a-z0-9-]/', '', $_GET['view'] ?? 'dashboard');
if ($activeView === 'resident-verification') {
  header('Location: dashboard.php?view=resident-account-registration');
  exit;
}
if ($activeView === 'announcements') {
  header('Location: dashboard.php?' . http_build_query(['view' => 'dashboard', 'status' => $_GET['status'] ?? '']) . '#community-posts');
  exit;
}
if ($activeView === 'documents') {
  header('Location: dashboard.php?view=issue-documents&requests=1');
  exit;
}
$adminName   = $loggedInUser['name'] ?? 'Hon. Barangay Captain';

$adminStaffAccessViews = [
  'issue-documents',
  'resident-account-registration',
  'service-reports',
  'walk-in-payments',
];
$validViews = [
  'dashboard', 'announcements',
  'residents', 'documents', 'complaints', 'husay-schedule', 'feedbacks', 'analytics',
  'qr-verification', 'sms-alerts', 'user-management',
  'reports-analytics', 'audit-logs', 'backup-restore', 'system-settings',
  'template-placeholders', 'document-fees',
];
$validViews = array_merge($validViews, $adminStaffAccessViews);
if (!in_array($activeView, $validViews, true)) {
  $activeView = 'dashboard';
}
$isAdminStaffAccessView = in_array($activeView, $adminStaffAccessViews, true);

$adminData = admin_load_dashboard_data();
if ($isAdminStaffAccessView) {
  $staffData = staff_load_dashboard_data($loggedInUser);
  extract($staffData, EXTR_SKIP);
}
$adminStaff = $adminData['staff'];
$adminResidents = $adminData['residents'];
$adminAnnouncements = $adminData['announcements'];
$adminDocuments = $adminData['documents'];
$adminComplaints = $adminData['complaints'];
$adminFeedbacks = $adminData['feedbacks'];
$adminQrResidents = $adminData['qrResidents'] ?? [];
$adminQrScanLogs = $adminData['qrScanLogs'] ?? [];
$adminSmsLogs = $adminData['smsLogs'] ?? [];
$stats = $adminData['stats'];
$monthlyTrends = $adminData['monthlyTrends'] ?? admin_monthly_activity_trends(6);
$auditFilters = barangay_audit_filters($_GET);
$auditLogs = $activeView === 'audit-logs' ? barangay_audit_rows($auditFilters) : [];
$backups = admin_fetch_all("SELECT id, DATE(created_at) AS date, DATE_FORMAT(created_at, '%h:%i %p') AS time, filename, created_by AS createdBy, status, file_size AS size FROM backups ORDER BY created_at DESC LIMIT 50");
$systemSettingsRows = admin_fetch_all('SELECT setting_key, setting_value FROM system_settings');
$systemSettings = [];
foreach ($systemSettingsRows as $setting) {
  $systemSettings[$setting['setting_key']] = $setting['setting_value'];
}

$staffPositions = [
  'Barangay Staff',
  'Barangay Staff - Computer Desk',
  'Barangay Secretary',
  'Barangay Treasurer',
  'Barangay Health Worker',
  'Barangay Tanod',
  'Administrative Aide',
];
$staffName = $adminName;
$staffRole = 'Administrator';
$canManageTemplates = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0<?= $activeView === 'qr-verification' ? ', minimum-scale=1.0' : '' ?>">
  <title><?= htmlspecialchars(ucwords(str_replace('-', ' ', $activeView))) ?> | Admin — Barangay Luna MIS</title>
  <link rel="stylesheet" href="css/dashboard.css?v=<?= filemtime(__DIR__ . '/css/dashboard.css') ?>">
  <link rel="stylesheet" href="css/mobile-select.css?v=<?= filemtime(__DIR__ . '/css/mobile-select.css') ?>">
  <link rel="stylesheet" href="css/motion.css?v=<?= filemtime(__DIR__ . '/css/motion.css') ?>">
  <link rel="stylesheet" href="css/portal-design.css?v=<?= filemtime(__DIR__ . '/css/portal-design.css') ?>">
</head>
<body class="app-dashboard role-admin">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main-area">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="content-area">
      <?php if ($message = ($isAdminStaffAccessView ? staff_status_message() : admin_status_message())): ?>
        <div hidden data-action-toast="<?= htmlspecialchars($message[0]) ?>">
          <?= htmlspecialchars($message[1]) ?>
        </div>
      <?php endif; ?>
      <?php
        if ($isAdminStaffAccessView) {
          include __DIR__ . '/includes/staff-views/' . $activeView . '.php';
        } else {
          include __DIR__ . '/includes/views/' . $activeView . '.php';
        }
      ?>
    </main>
  </div>

  <!-- Add Staff Modal -->
  <div id="addStaffModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <header class="modal-header">
        <h2>Add staff account</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <form id="createStaffForm" class="modal-body form-grid" action="admin-actions.php" method="post" data-server-form="true">
        <input type="hidden" name="action" value="create_staff">
        <input type="hidden" name="redirect" value="user-management">
        <div class="form-grid-2">
          <div class="field">
            <label>Full name</label>
            <input type="text" name="full_name" required placeholder="Full name">
          </div>
          <div class="field">
            <label>Mobile number</label>
            <input type="tel" name="mobile" placeholder="09XX XXX XXXX">
          </div>
        </div>
        <div class="form-grid-2">
          <div class="field">
            <label>Username</label>
            <input type="text" name="username" required placeholder="staffname@luna.gov">
            <p class="field-hint">Used for portal sign-in</p>
          </div>
          <div class="field">
            <label>Temporary password</label>
            <input type="password" name="password" required minlength="8">
            <p class="field-hint">Staff should change on first login</p>
          </div>
        </div>
        <div class="field">
          <label>Account status</label>
          <select name="status">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </form>
      <footer class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" form="createStaffForm" class="btn btn-primary">Add staff account</button>
      </footer>
    </div>
  </div>

  <!-- View Staff Modal -->
  <div id="viewStaffModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <header class="modal-header">
        <h2>Staff account details</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <div class="modal-body auto-fill-grid resident-details-grid" data-staff-view>
        <div><span>Staff ID</span><strong data-field="id">-</strong></div>
        <div><span>Name</span><strong data-field="name">-</strong></div>
        <div><span>Position</span><strong data-field="position">-</strong></div>
        <div><span>Username</span><strong data-field="username">-</strong></div>
        <div><span>Mobile</span><strong data-field="mobile">-</strong></div>
        <div><span>Status</span><strong data-field="status">-</strong></div>
        <div class="resident-detail-full"><span>Date created</span><strong data-field="created">-</strong></div>
      </div>
      <footer class="modal-footer staff-view-actions" data-staff-view-actions>
        <button type="button" class="btn btn-primary" data-edit-staff>Edit</button>
        <button type="button" class="btn btn-outline" data-reset-password>Reset Password</button>
        <form action="admin-actions.php" method="post" id="staffViewStatusForm">
          <input type="hidden" name="action" value="set_user_status">
          <input type="hidden" name="redirect" value="user-management">
          <input type="hidden" name="id">
          <input type="hidden" name="status">
          <button type="submit" class="btn btn-danger" data-staff-view-status>Deactivate</button>
        </form>
        <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      </footer>
    </div>
  </div>

  <!-- Edit Staff Modal -->
  <div id="editStaffModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <header class="modal-header">
        <h2>Edit staff account</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <form id="editStaffForm" class="modal-body form-grid" action="admin-actions.php" method="post" data-server-form="true">
        <input type="hidden" name="action" value="update_staff">
        <input type="hidden" name="redirect" value="user-management">
        <input type="hidden" name="id">
        <div class="field"><label>Name</label><input type="text" name="name" required></div>
        <div class="field"><label>Position</label>
          <select name="position" required>
            <?php foreach ($staffPositions as $pos): ?>
            <option><?= htmlspecialchars($pos) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Username</label><input type="text" name="username" required></div>
        <div class="field"><label>Mobile</label><input type="tel" name="mobile"></div>
        <div class="field"><label>Status</label>
          <select name="status"><option>Active</option><option>Inactive</option></select>
        </div>
      </form>
      <footer class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" form="editStaffForm" class="btn btn-primary">Save changes</button>
      </footer>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <?php $_SESSION['admin_password_reset_csrf'] ??= bin2hex(random_bytes(32)); ?>
  <div id="resetPasswordModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <header class="modal-header">
        <h2>Reset account password</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <form id="resetPasswordForm" class="modal-body form-grid" action="admin-actions.php" method="post" data-server-form="true">
        <input type="hidden" name="action" value="reset_user_password">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_password_reset_csrf'], ENT_QUOTES) ?>">
        <input type="hidden" name="redirect" value="user-management">
        <input type="hidden" name="id">
        <p class="field-hint">Set a new temporary password for <strong data-reset-password-name>this account</strong>.</p>
        <div class="field">
          <label>New temporary password</label>
          <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
      </form>
      <footer class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" form="resetPasswordForm" class="btn btn-primary">Reset password</button>
      </footer>
    </div>
  </div>

  <!-- View Resident Modal -->
  <div id="viewResidentModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel--wide">
      <header class="modal-header">
        <h2>Resident ID Preview</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <div class="modal-body">
        <?php $residentPreview = []; include __DIR__ . '/includes/resident-id-preview-card.php'; ?>
      </div>
      <footer class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      </footer>
    </div>
  </div>

  <!-- View Record Modal -->
  <div id="viewRecordModal" class="modal" aria-hidden="true" role="dialog">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <header class="modal-header">
        <h2 data-record-title>Record details</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
      </header>
      <div class="modal-body"><div class="auto-fill-grid resident-details-grid" data-record-view></div></div>
    </div>
  </div>

  <script src="js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>"></script>
  <script src="js/portal-design.js?v=<?= filemtime(__DIR__ . '/js/portal-design.js') ?>"></script>
  <script src="js/mobile-select.js?v=<?= filemtime(__DIR__ . '/js/mobile-select.js') ?>"></script>
  <script src="js/location-disclosures.js?v=<?= filemtime(__DIR__ . '/js/location-disclosures.js') ?>"></script>
  <?php if ($activeView === 'qr-verification'): ?>
  <script src="js/staff-qr-scanner.js?v=<?= filemtime(__DIR__ . '/js/staff-qr-scanner.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
