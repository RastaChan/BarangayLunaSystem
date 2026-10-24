<?php
$residentToolsStaff = ($currentRole ?? '') === 'staff' || !empty($residentManagementStaff);
$residentToolsPage = $residentToolsStaff ? 'staff-dashboard.php' : 'dashboard.php';
$residentToolsView = $residentToolsStaff ? 'resident-management' : 'residents';
$residentToolsQrView = $residentToolsStaff ? 'staff-qr-verification' : 'qr-verification';
$residentToolsQrActive = ($activeView ?? '') === $residentToolsQrView;
?>
<div class="page-intro">
  <h2>QR and Resident Management</h2>
  <p>Manage resident accounts and verify resident QR codes.</p>
</div>
<?php if (!$residentToolsQrActive): ?>
<div class="info-banner resident-management-notice">
  <div><strong>In-person registration only</strong>Residents cannot self-register online. Accounts are created after document verification at the front desk.</div>
  <button type="button" class="notice-hide" data-hide-resident-notice aria-label="Hide in-person registration notice">Hide</button>
</div>
<?php include __DIR__ . '/resident-inactivity-review.php'; ?>
<?php endif; ?>
<div class="resident-management-toolbar">
<nav class="resident-management-tabs" aria-label="Resident management sections">
  <a class="btn btn-primary" href="<?= $residentToolsPage ?>?view=<?= $residentToolsView ?>" <?= !$residentToolsQrActive ? 'aria-current="page"' : '' ?>>Resident Management</a>
  <a class="btn btn-primary" href="<?= $residentToolsPage ?>?view=<?= $residentToolsQrView ?>" <?= $residentToolsQrActive ? 'aria-current="page"' : '' ?>>QR Management</a>
</nav>
  <?php if (!$residentToolsQrActive && ($loggedInUser['role'] ?? $user['role'] ?? '') === 'admin'): ?>
  <button type="button" class="btn btn-primary" data-modal-open="manageResidentLocations">Manage Districts and Puroks</button>
  <?php endif; ?>
</div>
