<?php
$_SESSION['resident_review_csrf'] ??= bin2hex(random_bytes(32));
$inactivityFlash = $_SESSION['resident_inactivity_flash'] ?? null;
unset($_SESSION['resident_inactivity_flash']);
?>
<?php if ($inactivityFlash): ?>
<div hidden data-action-toast="<?= htmlspecialchars($inactivityFlash[0]) ?>"><?= htmlspecialchars($inactivityFlash[1]) ?></div>
<?php endif; ?>
<div class="info-banner resident-management-notice">
  <div><strong>6-month account inactivity rule</strong>
    Accounts become inactive after 6 months without use. Confirm current residency before restoring access. Inactivity alone does not mean a resident has moved away.
  </div>
  <button type="button" class="notice-hide" data-hide-resident-notice aria-label="Hide account inactivity notice">Hide</button>
</div>
<div id="residentInactivityReviewModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="residentInactivityReviewTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="residentInactivityReviewTitle">Review residency</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <form action="resident-inactivity-actions.php" method="post" class="modal-body form-grid">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['resident_review_csrf'], ENT_QUOTES) ?>">
      <input type="hidden" name="resident_code" data-review-resident-code>
      <p data-review-resident-name></p>
      <p class="field-hint">This account was deactivated after 6 months without use. Verify the resident's current address before restoring access.</p>
      <label class="check check-left"><input type="checkbox" name="residency_confirmed" value="1" required><span>I confirmed this resident still lives in Barangay Luna.</span></label>
      <div class="field"><label for="residentReviewNote">Review note</label><textarea id="residentReviewNote" name="review_note" rows="3" maxlength="150" required placeholder="How current residency was confirmed"></textarea></div>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Confirm and reactivate</button></footer>
    </form>
  </div>
</div>
