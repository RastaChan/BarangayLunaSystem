<?php $_SESSION['husay_csrf'] ??= bin2hex(random_bytes(32)); ?>
<div class="recent-post-options complaint-options" data-post-options>
  <button type="button" class="recent-post-toggle" data-post-toggle aria-expanded="false" aria-controls="complaintActions<?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" aria-label="Complaint options for <?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" title="Complaint options">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
  </button>
  <div id="complaintActions<?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" class="recent-post-actions" data-post-actions hidden>
    <button type="button" class="btn btn-outline btn-sm" data-view-record data-title="Complaint" data-complaint-token="<?= htmlspecialchars($_SESSION['husay_csrf'], ENT_QUOTES) ?>" data-complaint-update-url="<?= $complaintUpdateUrl ?>" data-record-attachments='<?= htmlspecialchars(json_encode($complaintAttachments ?? []), ENT_QUOTES) ?>' data-json='<?= htmlspecialchars(json_encode($complaintActionRecord), ENT_QUOTES) ?>'>View details</button>
    <?php if (in_array($complaintActionRecord['status'] ?? '', ['Open', 'In Progress'], true)): ?>
    <button type="button" class="btn btn-outline btn-sm" data-husay-schedule-complaint='<?= htmlspecialchars(json_encode(['id'=>$complaintActionRecord['id'],'resident'=>$complaintActionRecord['resident'],'contact_mobile'=>$complaintActionRecord['contact_mobile'] ?? '']), ENT_QUOTES) ?>'>Schedule this complaint</button>
    <?php endif; ?>
  </div>
</div>
