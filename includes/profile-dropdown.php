<?php
/** Shared top-header profile dropdown and account modals. */
$profileName = $profileName ?? 'Barangay User';
$profileRole = $profileRole ?? 'User';
$profileUsername = $profileUsername ?? '';
$profileContact = $profileContact ?? '';
$profileInitial = strtoupper(substr(trim($profileName), 0, 1) ?: 'U');
$profilePhotoPath = $residentProfile['profile_photo_path'] ?? $loggedInUser['profile_photo_path'] ?? '';
if ($profilePhotoPath === '' && !empty($loggedInUser['id'])) {
  try {
    barangay_ensure_profile_photo_column();
    $profilePhotoStmt = barangay_db()->prepare('SELECT profile_photo_path FROM users WHERE id = :id LIMIT 1');
    $profilePhotoStmt->execute([':id' => $loggedInUser['id']]);
    $profilePhotoPath = (string) ($profilePhotoStmt->fetchColumn() ?: '');
    $loggedInUser['profile_photo_path'] = $profilePhotoPath;
  } catch (Throwable $e) {
    $profilePhotoPath = '';
  }
}
$profileAccountRole = strtolower((string)($loggedInUser['role'] ?? $currentRole ?? ''));
$profileCanEditUsername = $profileAccountRole === 'admin';
$profileActionUrls = [
  'admin' => 'admin-actions.php',
  'staff' => 'staff-actions.php',
  'resident' => 'resident-actions.php',
];
$profileFormAction = $profileActionUrls[$profileAccountRole] ?? '#';
$profileRedirectDefaults = [
  'admin' => 'dashboard',
  'staff' => 'staff-dashboard',
  'resident' => 'resident-dashboard',
];
$profileRedirect = $activeView ?? ($profileRedirectDefaults[$profileAccountRole] ?? 'dashboard');
$showResidentIdCard = $profileAccountRole === 'resident';
$residentIdCard = $residentProfile ?? [];
$_SESSION['account_security_csrf'] ??= bin2hex(random_bytes(32));
?>
<div class="profile-menu" data-profile-menu>
  <button type="button" class="user-menu user-profile profile-menu-toggle" data-profile-toggle aria-expanded="false" aria-haspopup="true">
    <span class="user-avatar">
      <?php if ($profilePhotoPath !== ''): ?>
      <img src="<?= htmlspecialchars($profilePhotoPath) ?>" alt="<?= htmlspecialchars($profileName) ?>">
      <?php else: ?>
      <?= htmlspecialchars($profileInitial) ?>
      <?php endif; ?>
    </span>
    <div class="user-meta">
      <strong class="user-name"><?= htmlspecialchars($profileName) ?></strong>
      <span><?= htmlspecialchars($profileRole) ?></span>
    </div>
    <svg data-original-class="profile-caret" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="chevron-down" class="system-icon profile-caret"><path d="M208,96l-80,80L48,96Z" opacity="0.2"/><path d="M215.39,92.94A8,8,0,0,0,208,88H48a8,8,0,0,0-5.66,13.66l80,80a8,8,0,0,0,11.32,0l80-80A8,8,0,0,0,215.39,92.94ZM128,164.69,67.31,104H188.69Z"/></svg>
  </button>

  <div class="profile-dropdown" data-profile-dropdown>
    <button type="button" class="profile-dropdown-item" data-modal-open="profileSettingsModal">
      <span>Profile Settings</span>
    </button>
    <button type="button" class="profile-dropdown-item" data-modal-open="securitySettingsModal">
      <span>Security Settings</span>
    </button>
    <a href="logout.php" class="profile-dropdown-item profile-dropdown-item--danger">
      <span>Logout</span>
    </a>
  </div>
</div>

<div id="profileSettingsModal" class="modal <?= $showResidentIdCard ? 'resident-profile-modal' : '' ?>" aria-hidden="true" role="dialog" aria-labelledby="profileSettingsTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel <?= $showResidentIdCard ? 'modal-panel--wide resident-profile-panel' : '' ?>">
    <header class="modal-header">
      <h2 id="profileSettingsTitle">Profile Settings</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button>
    </header>
    <form id="profileSettingsForm" class="modal-body form-grid" action="<?= htmlspecialchars($profileFormAction) ?>" method="post" enctype="multipart/form-data" data-profile-settings-form>
      <input type="hidden" name="action" value="update_account_profile">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($profileRedirect) ?>">
      <input type="hidden" name="profile_photo_cropped" id="profilePhotoCropped">

      <?php if ($showResidentIdCard): ?>
      <?php $residentPreview = $residentIdCard; $editableResidentPhoto = true; include __DIR__ . '/resident-id-preview-card.php'; $editableResidentPhoto = false; ?>
      <?php endif; ?>

      <div class="profile-photo-tools" <?= $showResidentIdCard ? 'hidden' : '' ?>>
      <div class="profile-photo-upload">
        <span class="profile-photo-preview" <?= $showResidentIdCard ? '' : 'id="profilePhotoPreview"' ?>>
          <?php if ($profilePhotoPath !== ''): ?>
          <img src="<?= htmlspecialchars($profilePhotoPath) ?>" alt="<?= htmlspecialchars($profileName) ?>">
          <?php else: ?>
          <?= htmlspecialchars($profileInitial) ?>
          <?php endif; ?>
        </span>
        <div class="field">
          <label for="profilePhotoUpload">Profile picture</label>
          <input type="file" id="profilePhotoUpload" name="profile_photo" accept="image/jpeg,image/png,image/webp">
          <p class="field-hint">Upload a JPG, PNG, or WEBP photo. Drag or pinch the preview to crop before saving.</p>
        </div>
      </div>
      <?php if ($showResidentIdCard && !empty($residentIdCard['qr_code']) && !empty($residentIdCard['qr_image_url'])): ?>
      <a class="btn btn-outline btn-sm profile-qr-download" href="<?= htmlspecialchars($residentIdCard['qr_image_url']) ?>" download="resident-qr.png">Download QR</a>
      <?php endif; ?>
      </div>
      <div class="profile-cropper" id="profilePhotoCropper" hidden>
        <div class="profile-cropper__stage" id="profileCropStage">
          <img id="profileCropImage" alt="Selected profile crop preview">
          <span class="profile-cropper__mask" aria-hidden="true"></span>
        </div>
        <div class="profile-cropper__controls">
          <label for="profilePhotoZoom">Crop zoom</label>
          <input type="range" id="profilePhotoZoom" min="1" max="3" step="0.01" value="1">
        </div>
      </div>

      <div class="form-grid-2">
        <div class="field">
          <label for="profileFullName">Full name</label>
          <input type="text" id="profileFullName" value="<?= htmlspecialchars($profileName) ?>" disabled>
        </div>
        <div class="field">
          <label for="profileRole">Role</label>
          <input type="text" id="profileRole" value="<?= htmlspecialchars($profileRole) ?>" disabled>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="field">
          <label for="profileUsername">Username</label>
          <input type="text" id="profileUsername" name="username" value="<?= htmlspecialchars($profileUsername) ?>" <?= $profileCanEditUsername ? '' : 'readonly aria-readonly="true"' ?>>
          <?php if (!$profileCanEditUsername): ?>
          <p class="field-hint">Only an administrator can change usernames.</p>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="profileContact">Mobile number</label>
          <input type="tel" id="profileContact" name="mobile" value="<?= htmlspecialchars($profileContact) ?>">
        </div>
      </div>

      <p class="profile-note">If your name or personal information needs to be changed, please visit the barangay office for verification.</p>
    </form>
    <footer class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="profileSettingsForm" class="btn btn-primary">Save profile</button>
    </footer>
  </div>
</div>

<div id="securitySettingsModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="securitySettingsTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header">
      <h2 id="securitySettingsTitle">Security Settings</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button>
    </header>
    <form id="securitySettingsForm" class="modal-body form-grid" action="account-security-actions.php" method="post" data-security-form>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['account_security_csrf'], ENT_QUOTES) ?>">
      <div class="field">
        <label for="currentPassword">Current password</label>
        <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="field">
        <label for="newPassword">New password</label>
        <input type="password" id="newPassword" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" required>
      </div>
      <div class="field">
        <label for="confirmPassword">Confirm password</label>
        <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" required>
        <p class="field-hint password-match-message" data-password-message></p>
      </div>
    </form>
    <footer class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="securitySettingsForm" class="btn btn-primary">Save password</button>
    </footer>
  </div>
</div>

<script src="js/resident-id-actions.js?v=<?= filemtime(__DIR__ . '/../js/resident-id-actions.js') ?>" defer></script>
