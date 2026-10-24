<?php
require_once __DIR__ . '/../registration-requirements.php';
registration_requirements_schema();
$_SESSION['requirements_csrf'] ??= bin2hex(random_bytes(32));
$requirementResidents = barangay_db()->query("SELECT id, user_code, full_name FROM users WHERE role = 'resident' ORDER BY full_name")->fetchAll();
$selectedResident = (int) ($_GET['requirements_resident'] ?? 0);
if (!in_array($selectedResident, array_map('intval', array_column($requirementResidents, 'id')), true)) $selectedResident = 0;
if (!$selectedResident) return;
$savedRequirements = [];
if ($selectedResident) {
  $stmt = barangay_db()->prepare('SELECT * FROM resident_requirements WHERE resident_id = ?');
  $stmt->execute([$selectedResident]);
  foreach ($stmt as $row) $savedRequirements[$row['requirement_key']] = $row;
}
$escapeRequirement = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
$requirementFlash = $_SESSION['requirements_flash'] ?? null;
unset($_SESSION['requirements_flash']);
?>
<div class="modal" id="registrationRequirements" aria-hidden="true" role="dialog" aria-labelledby="registrationRequirementsTitle"><div class="modal-backdrop" data-modal-close></div><div class="modal-panel">
  <header class="modal-header"><h2 id="registrationRequirementsTitle">Registration requirements</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
  <div class="modal-body">
    <?php foreach ($requirementResidents as $row): if ((int) $row['id'] !== $selectedResident) continue; ?>
    <p><strong><?= $escapeRequirement($row['full_name']) ?></strong><br><small><?= $escapeRequirement($row['user_code']) ?></small></p>
    <?php endforeach; ?>
    <?php if ($requirementFlash): ?><div hidden data-action-toast="<?= $escapeRequirement($requirementFlash[0]) ?>"><?= $escapeRequirement($requirementFlash[1]) ?></div><?php endif; ?>

    <?php if ($selectedResident): ?>
    <?php foreach (registration_requirement_types() as $key => $title): $saved = $savedRequirements[$key] ?? []; $files = barangay_attachment_links('registration', $selectedResident . ':' . $key); ?>
    <form action="registration-requirements-actions.php" method="post" enctype="multipart/form-data" class="form-grid" style="padding:1rem 0;border-top:1px solid var(--border);margin-top:1rem">
      <strong><?= $escapeRequirement($title) ?><?= $key === 'photo' ? ' (Optional)' : '' ?></strong>
      <input type="hidden" name="csrf" value="<?= $escapeRequirement($_SESSION['requirements_csrf']) ?>">
      <input type="hidden" name="resident_id" value="<?= $selectedResident ?>"><input type="hidden" name="requirement_key" value="<?= $key ?>">
      <div class="field"><label for="requirementStatus_<?= $key ?>">Status</label><select id="requirementStatus_<?= $key ?>" name="status">
        <?php foreach (['Not Submitted', 'Submitted', 'Not Applicable'] as $status): ?><option <?= ($saved['status'] ?? 'Not Submitted') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
      </select></div>
      <div class="field"><label for="requirementRemarks_<?= $key ?>">Remarks</label><textarea name="remarks" id="requirementRemarks_<?= $key ?>" rows="2" maxlength="1000"><?= $escapeRequirement($saved['remarks'] ?? '') ?></textarea></div>
      <?= $files ?>
      <div class="field"><label for="requirementFile_<?= $key ?>"><?= $files ? 'Replace attachment' : 'Upload attachment' ?> (JPG, PNG, PDF; max 5 MB)</label><input id="requirementFile_<?= $key ?>" type="file" name="requirement_file" accept=".jpg,.jpeg,.png,.pdf"></div>
      <?php if ($files): ?><label><input type="checkbox" name="remove_file" value="1"> Remove existing attachment when saved</label><?php endif; ?>
      <button class="btn btn-primary" type="submit">Save requirement</button>
    </form>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div></div>
