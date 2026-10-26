<?php
/**
 * Barangay Luna MIS - Resident/User Dashboard
 *
 * Resident service workspace.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resident-functions.php';

$loggedInUser = barangay_require_role('resident');
$_SESSION['resident_service_csrf'] ??= bin2hex(random_bytes(32));
$currentRole = 'resident';
$activeView = preg_replace('/[^a-z0-9-]/', '', $_GET['view'] ?? 'resident-dashboard');
if (in_array($activeView, ['resident-announcements', 'upcoming-events'], true)) {
  header('Location: resident-dashboard.php?view=resident-dashboard#' . $activeView);
  exit;
}
$residentName = $loggedInUser['name'] ?? 'Resident';
$residentRole = $loggedInUser['title'] ?? 'Resident';

$validViews = [
  'resident-dashboard',
  'request-document',
  'my-document-requests',
  'resident-announcements',
  'upcoming-events',
  'submit-complaint',
  'my-complaints',
  'send-feedback',
  'sms-updates',
  'my-activity-logs',
  'my-profile',
  'change-password',
];
if (!in_array($activeView, $validViews, true)) {
  $activeView = 'resident-dashboard';
}

$residentData = resident_load_dashboard_data($loggedInUser);
extract($residentData, EXTR_SKIP);
$statusMessage = resident_status_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(ucwords(str_replace('-', ' ', $activeView))) ?> | Resident - Barangay Luna MIS</title>
  <link rel="stylesheet" href="css/dashboard.css?v=<?= filemtime(__DIR__ . '/css/dashboard.css') ?>">
  <link rel="stylesheet" href="css/mobile-select.css?v=<?= filemtime(__DIR__ . '/css/mobile-select.css') ?>">
  <link rel="stylesheet" href="css/motion.css?v=<?= filemtime(__DIR__ . '/css/motion.css') ?>">
  <link rel="stylesheet" href="css/portal-design.css?v=<?= filemtime(__DIR__ . '/css/portal-design.css') ?>">
</head>
<body class="app-dashboard role-resident">

  <?php include __DIR__ . '/includes/resident-sidebar.php'; ?>
  <?php include __DIR__ . '/includes/sidebar-state.php'; ?>
  <script src="js/live-search.js?v=<?= filemtime(__DIR__ . '/js/live-search.js') ?>" defer></script>
  <script src="js/time-controls.js?v=<?= filemtime(__DIR__ . '/js/time-controls.js') ?>" defer></script>

  <div class="main-area">
    <?php include __DIR__ . '/includes/resident-header.php'; ?>

    <main class="content-area">
      <?php if ($statusMessage): ?>
      <div hidden data-action-toast="<?= htmlspecialchars($statusMessage[0]) ?>">
        <?= htmlspecialchars($statusMessage[1]) ?>
      </div>
      <?php endif; ?>
      <?php include __DIR__ . '/includes/resident-views/' . $activeView . '.php'; ?>
    </main>
  </div>

  <?php require __DIR__ . '/includes/resident-submission-confirmation.php'; ?>
  <script src="js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>"></script>
  <script src="js/resident-submission-confirmation.js?v=<?= filemtime(__DIR__ . '/js/resident-submission-confirmation.js') ?>" defer></script>
  <script src="js/document-custom-fields.js?v=<?= filemtime(__DIR__ . '/js/document-custom-fields.js') ?>"></script>
  <?php require __DIR__ . '/includes/system-icons.php'; ?>
  <script src="js/portal-design.js?v=<?= filemtime(__DIR__ . '/js/portal-design.js') ?>"></script>
  <script src="js/mobile-select.js?v=<?= filemtime(__DIR__ . '/js/mobile-select.js') ?>"></script>
  <script src="js/location-disclosures.js?v=<?= filemtime(__DIR__ . '/js/location-disclosures.js') ?>"></script>
</body>
</html>
