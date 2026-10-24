<?php
// Shared card for the staff preview and the resident's own Profile Settings.
$residentPreview = $residentPreview ?? [];
$previewName = (string) ($residentPreview['name'] ?? 'No resident selected');
$previewStatus = $residentPreview['status'] ?? $residentPreview['resident_status'] ?? $residentPreview['account_status'] ?? 'Pending';
$previewInitials = implode('', array_map(static fn($part) => mb_substr($part, 0, 1), array_slice(preg_split('/\s+/', trim($previewName)), 0, 2)));
$previewPhoto = $residentPreview['profile_photo_path'] ?? '';
$previewQr = $residentPreview['qr_code'] ?? '';
$previewQrImage = $residentPreview['qr_image_url'] ?? ($previewQr ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($previewQr) : '');
$previewFields = [
  'username' => ['Username', $residentPreview['username'] ?? ''],
  'mobile' => ['Mobile', $residentPreview['mobile'] ?? $residentPreview['contact'] ?? ''],
  'purok' => ['Purok/Sitio', $residentPreview['purok'] ?? ''],
  'household' => ['Household', $residentPreview['household'] ?? ''],
  'birthdate' => ['Birthdate / Age', ($residentPreview['birthdate'] ?? '') . (isset($residentPreview['age']) && $residentPreview['age'] !== null ? ' (' . (int) $residentPreview['age'] . ' yrs old)' : '')],
  'gender' => ['Gender', $residentPreview['gender'] ?? ''],
  'civil-status' => ['Civil Status', $residentPreview['civil_status'] ?? ''],
  'verification' => ['Verification', $residentPreview['verification_status'] ?? ''],
  'registered' => ['Registered', $residentPreview['registered'] ?? ''],
  'address' => ['Address', $residentPreview['address'] ?? ''],
];
?>
<section class="resident-id-card resident-id-card--admin" data-resident-id-card>
  <div class="resident-id-card__header">
    <img src="branding-logo.php" alt="Barangay Luna seal" onerror="this.style.visibility='hidden';">
    <div><span>Barangay Luna</span><strong>Resident Identification Profile</strong></div>
    <span class="badge <?= $previewStatus === 'Active' ? 'badge-active' : ($previewStatus === 'Inactive' ? 'badge-inactive' : 'badge-pending') ?>" data-resident-card-status><?= htmlspecialchars($previewStatus) ?></span>
  </div>
  <div class="resident-id-card__body">
    <div class="resident-id-card__photo" data-resident-card-photo>
      <?php if ($previewPhoto): ?><img src="<?= htmlspecialchars($previewPhoto) ?>" alt="<?= htmlspecialchars($previewName) ?> profile photo"><?php else: ?><?= htmlspecialchars($residentPreview ? mb_strtoupper($previewInitials) : '?') ?><?php endif; ?>
    </div>
    <div class="resident-id-card__info">
      <span>Resident ID</span><strong data-resident-card-id><?= htmlspecialchars($residentPreview['id'] ?? '-') ?></strong>
      <h3 data-resident-card-name><?= htmlspecialchars($previewName) ?></h3>
      <dl>
        <?php foreach ($previewFields as $key => [$label, $value]): ?>
        <div <?= $key === 'address' ? 'class="resident-id-card__address-row"' : '' ?>><dt><?= $label ?></dt><dd data-resident-card-<?= $key ?>><?= htmlspecialchars((string) $value ?: '-') ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </div>
    <div class="resident-id-card__qr" data-resident-card-qr>
      <?php if ($previewQr && $previewQrImage): ?><img src="<?= htmlspecialchars($previewQrImage) ?>" alt="Resident QR code"><?php else: ?><div class="resident-id-card__qr-empty">QR</div><?php endif; ?>
      <span data-resident-card-qr-code><?= htmlspecialchars($previewQr ?: 'No QR code yet') ?></span>
    </div>
  </div>
</section>
