<?php require_once __DIR__ . '/../record-attachments.php'; ?>
<div class="page-intro"><h2>My Complaints</h2><p>Track complaints you submitted and review staff remarks.</p></div>
<section class="panel-card" data-complaint-list data-overview-owner="resident-complaints-<?= (int)$loggedInUser['id'] ?>">
  <div class="panel-head"><h3>Complaint list</h3></div>
  <?php require __DIR__ . '/../complaint-search.php'; ?>
  <div class="table-wrap">
    <table class="data-table complaint-list-table">
      <thead><tr><th>Complaint ID</th><th>Subject</th><th>Category</th><th>Date submitted</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody data-live-overview="resident-complaints">
        <?php foreach ($myComplaints as $complaint): ?>
        <tr id="complaint-<?= htmlspecialchars($complaint['id'], ENT_QUOTES) ?>" data-complaint-search="<?= htmlspecialchars(implode(' ', [$complaint['id'], $complaint['subject'], $complaint['category'], $complaint['date'], $complaint['status'], $complaint['remarks'] ?? '']), ENT_QUOTES) ?>">
          <td class="complaint-list-id" data-label="Complaint ID"><?= htmlspecialchars($complaint['id']) ?></td>
          <td class="complaint-list-subject" data-label="Subject"><?= htmlspecialchars($complaint['subject']) ?><?= barangay_attachment_links('complaint', $complaint['id']) ?></td>
          <td data-label="Category"><?= htmlspecialchars($complaint['category']) ?></td>
          <td data-label="Date submitted"><?= htmlspecialchars($complaint['date']) ?></td>
          <?php
            $statusBadge = match ($complaint['status']) {
              'Resolved', 'Closed' => 'badge-resolved',
              'In Progress' => 'badge-progress',
              default => 'badge-open',
            };
          ?>
          <td data-label="Status"><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($complaint['status']) ?></span></td>
          <td class="complaint-list-remarks" data-label="Staff remarks"><?= htmlspecialchars($complaint['remarks'] ?? '') ?: 'No remarks yet.' ?>
          <?php if ($complaint['status'] === 'Open' && (int) ($complaint['resident_user_id'] ?? 0) === (int) $loggedInUser['id']): ?>
          <?= resident_delete_submission_form('complaint', $complaint['id'], $complaint['id'] . ' — ' . $complaint['subject']) ?>
          <?php endif; ?>
          </td>
          <?php require __DIR__ . '/../husay-resident-summary.php'; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$myComplaints): ?>
        <tr><td colspan="6">No complaints yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
