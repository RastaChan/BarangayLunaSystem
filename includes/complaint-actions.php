<?php $_SESSION['husay_csrf'] ??= bin2hex(random_bytes(32)); ?>
<div class="recent-post-options complaint-options" data-post-options>
  <button type="button" class="recent-post-toggle" data-post-toggle aria-expanded="false" aria-controls="complaintActions<?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" aria-label="Complaint options for <?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" title="Complaint options">
    <svg width="22" height="22" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="ellipsis-vertical" class="system-icon"><path d="M176,32V224a16,16,0,0,1-16,16H96a16,16,0,0,1-16-16V32A16,16,0,0,1,96,16h64A16,16,0,0,1,176,32Z" opacity="0.2"/><path d="M140,128a12,12,0,1,1-12-12A12,12,0,0,1,140,128ZM128,72a12,12,0,1,0-12-12A12,12,0,0,0,128,72Zm0,112a12,12,0,1,0,12,12A12,12,0,0,0,128,184Z"/></svg>
  </button>
  <div id="complaintActions<?= htmlspecialchars($complaintActionRecord['id'], ENT_QUOTES) ?>" class="recent-post-actions" data-post-actions hidden>
    <button type="button" class="btn btn-outline btn-sm" data-view-record data-title="Complaint" data-complaint-token="<?= htmlspecialchars($_SESSION['husay_csrf'], ENT_QUOTES) ?>" data-complaint-update-url="<?= $complaintUpdateUrl ?>" data-record-attachments='<?= htmlspecialchars(json_encode($complaintAttachments ?? []), ENT_QUOTES) ?>' data-json='<?= htmlspecialchars(json_encode($complaintActionRecord), ENT_QUOTES) ?>'>View details</button>
    <?php if (in_array($complaintActionRecord['status'] ?? '', ['Open', 'In Progress'], true)): ?>
    <button type="button" class="btn btn-outline btn-sm" data-husay-schedule-complaint='<?= htmlspecialchars(json_encode(['id'=>$complaintActionRecord['id'],'resident'=>$complaintActionRecord['resident'],'contact_mobile'=>$complaintActionRecord['contact_mobile'] ?? '']), ENT_QUOTES) ?>'>Schedule this complaint</button>
    <?php endif; ?>
  </div>
</div>
