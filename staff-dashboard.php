<?php
/**
 * Barangay Luna MIS - Barangay Staff Dashboard
 *
 * Staff service workspace.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';

$loggedInUser = barangay_require_role('staff');
$currentRole = 'staff';
$activeView  = preg_replace('/[^a-z0-9-]/', '', $_GET['view'] ?? 'staff-dashboard');
if ($activeView === 'resident-verification') {
  header('Location: staff-dashboard.php?view=resident-account-registration');
  exit;
}
if ($activeView === 'staff-announcements') {
  header('Location: staff-dashboard.php?' . http_build_query(['view' => 'staff-dashboard', 'status' => $_GET['status'] ?? '']) . '#community-posts');
  exit;
}
if ($activeView === 'staff-document-requests') {
  header('Location: staff-dashboard.php?view=issue-documents&requests=1');
  exit;
}
$staffName   = $loggedInUser['name'] ?? 'Barangay Staff';
$staffRole   = $loggedInUser['title'] ?? 'Barangay Secretary';
$canManageTemplates = true;

$validViews = [
  'staff-dashboard',
  'issue-documents',
  'staff-document-requests',
  'walk-in-payments',
  'resident-account-registration',
  'resident-management',
  'staff-qr-verification',
  'staff-complaints',
  'husay-schedule',
  'staff-feedbacks',
  'staff-announcements',
  'staff-sms-alerts',
  'my-activity-logs',
  'service-reports',
  'my-profile',
];
if (!in_array($activeView, $validViews, true)) {
  $activeView = 'staff-dashboard';
}

$staffData = staff_load_dashboard_data($loggedInUser);
extract($staffData, EXTR_SKIP);
$statusMessage = staff_status_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0<?= $activeView === 'staff-qr-verification' ? ', minimum-scale=1.0' : '' ?>">
  <title><?= htmlspecialchars(ucwords(str_replace('-', ' ', $activeView))) ?> | Staff - Barangay Luna MIS</title>
  <link rel="stylesheet" href="css/dashboard.css?v=<?= filemtime(__DIR__ . '/css/dashboard.css') ?>">
  <link rel="stylesheet" href="css/mobile-select.css?v=<?= filemtime(__DIR__ . '/css/mobile-select.css') ?>">
  <link rel="stylesheet" href="css/motion.css?v=<?= filemtime(__DIR__ . '/css/motion.css') ?>">
  <link rel="stylesheet" href="css/portal-design.css?v=<?= filemtime(__DIR__ . '/css/portal-design.css') ?>">
</head>
<body class="app-dashboard">

  <?php include __DIR__ . '/includes/staff-sidebar.php'; ?>
  <?php include __DIR__ . '/includes/sidebar-state.php'; ?>
  <script src="js/live-search.js?v=<?= filemtime(__DIR__ . '/js/live-search.js') ?>" defer></script>
  <script src="js/time-controls.js?v=<?= filemtime(__DIR__ . '/js/time-controls.js') ?>" defer></script>
  <script src="js/registration-requirements.js?v=<?= filemtime(__DIR__ . '/js/registration-requirements.js') ?>" defer></script>

  <div class="main-area">
    <?php include __DIR__ . '/includes/staff-header.php'; ?>

    <main class="content-area">
      <?php if ($statusMessage): ?>
      <div hidden data-action-toast="<?= htmlspecialchars($statusMessage[0]) ?>">
        <?= htmlspecialchars($statusMessage[1]) ?>
      </div>
      <?php endif; ?>
      <?php include __DIR__ . '/includes/staff-views/' . $activeView . '.php'; ?>
    </main>
  </div>

  <script src="js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>"></script>
  <script src="js/document-custom-fields.js?v=<?= filemtime(__DIR__ . '/js/document-custom-fields.js') ?>"></script>
  <script src="js/template-preparation.js?v=<?= filemtime(__DIR__ . '/js/template-preparation.js') ?>"></script>
  <?php require __DIR__ . '/includes/system-icons.php'; ?>
  <script src="js/portal-design.js?v=<?= filemtime(__DIR__ . '/js/portal-design.js') ?>"></script>
  <script src="js/mobile-select.js?v=<?= filemtime(__DIR__ . '/js/mobile-select.js') ?>"></script>
  <script src="js/location-disclosures.js?v=<?= filemtime(__DIR__ . '/js/location-disclosures.js') ?>"></script>
  <?php if ($activeView === 'staff-qr-verification'): ?>
  <script src="js/staff-qr-scanner.js?v=<?= filemtime(__DIR__ . '/js/staff-qr-scanner.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
