<?php $_SESSION['feedback_submit_csrf'] ??= bin2hex(random_bytes(32)); ?>
<div class="page-intro"><h2>Send Feedback</h2><p>Rate your experience and send suggestions about barangay services.</p></div>
<section class="panel-card"><div class="panel-head"><h3>Feedback form</h3></div><div class="panel-body">
  <form action="resident-actions.php" method="post" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="action" value="submit_feedback">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['feedback_submit_csrf'], ENT_QUOTES) ?>">
    <fieldset class="feedback-rating-field"><legend>How would you rate your experience?</legend><div class="feedback-rating-options">
      <?php foreach ([1=>'Very poor',2=>'Poor',3=>'Fair',4=>'Good',5=>'Excellent'] as $score=>$label): ?>
      <label><input type="radio" name="rating" value="<?= $score ?>" required><span><strong><?= $score ?></strong><?= $label ?></span></label>
      <?php endforeach; ?>
    </div></fieldset>
    <input type="hidden" name="redirect" value="send-feedback">
    <div class="field"><label>Feedback category</label><select name="category"><option>Service feedback</option><option>Suggestion</option><option>Website/Portal concern</option><option>Staff service</option><option>Community concern</option><option>Other</option></select></div>
    <div class="field"><label>Feedback message</label><textarea name="message" rows="6" placeholder="Write your feedback or suggestion" required></textarea></div>
    <div class="field">
      <label>Attach image</label>
      <label class="file-upload-field">
        <input type="file" name="feedback_image" accept="image/*" data-file-input data-empty-label="No image selected">
        <span class="btn btn-outline">Upload image</span>
        <small data-file-name>No image selected</small>
      </label>
      <p class="field-hint">Optional. Upload a photo or screenshot related to your feedback. Maximum 50MB.</p>
    </div>
    <label class="check-row"><input type="checkbox" name="anonymous" value="1"><span>Submit as anonymous</span></label>
    <button type="submit" class="btn btn-primary">Submit feedback</button>
  </form>
</div></section>
<style>
.feedback-rating-field{border:0;padding:0;margin:0;min-width:0;grid-column:1/-1}.feedback-rating-field legend{font-weight:600;margin-bottom:12px}.feedback-rating-options{display:flex;flex-wrap:wrap;gap:8px}.feedback-rating-options label{flex:1 1 90px;cursor:pointer;position:relative}.feedback-rating-options input{position:absolute;opacity:0;inset:0;width:100%;height:100%;cursor:pointer}.feedback-rating-options span{display:flex;flex-direction:column;gap:4px;align-items:center;padding:12px 8px;border:1px solid #d5e3db;border-radius:8px;transition:background .2s,border-color .2s;font-size:.85rem}.feedback-rating-options strong{font-size:1.2rem}.feedback-rating-options input:checked+span{background:#e7f5eb;border-color:#16803d;color:#126032}.feedback-rating-options input:focus-visible+span{outline:2px solid #16803d;outline-offset:3px}
</style>
