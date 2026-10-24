<?php require_once __DIR__ . '/registration-requirements.php'; ?>
<fieldset class="registration-requirement-fields">
  <legend>Registration requirements</legend>
  <p class="field-hint">Record the documents checked during registration. Attachments are optional: JPG, PNG or PDF, up to 5 MB each. Uploading an attachment marks it Submitted.</p>
  <div class="form-grid-2">
    <?php foreach (registration_requirement_types() as $key => $title): ?>
    <div class="registration-requirement-item form-grid">
      <strong><?= htmlspecialchars($title) ?><?= $key === 'photo' ? ' (Optional)' : '' ?></strong>
      <div class="field">
        <label for="newRequirementStatus_<?= $key ?>">Status</label>
        <select id="newRequirementStatus_<?= $key ?>" name="requirement_status[<?= $key ?>]">
          <option>Not Submitted</option><option>Submitted</option><option>Not Applicable</option>
        </select>
      </div>
      <div class="field">
        <label for="newRequirementFile_<?= $key ?>">Attachment</label>
        <input type="file" id="newRequirementFile_<?= $key ?>" name="requirement_file_<?= $key ?>" accept=".jpg,.jpeg,.png,.pdf">
      </div>
      <div class="field">
        <label for="newRequirementRemarks_<?= $key ?>">Remarks</label>
        <textarea id="newRequirementRemarks_<?= $key ?>" name="requirement_remarks[<?= $key ?>]" rows="2" maxlength="1000"></textarea>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</fieldset>
