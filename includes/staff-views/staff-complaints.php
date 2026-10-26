<?php require_once __DIR__ . '/../record-attachments.php'; ?>
<div class="page-intro">
  <h2>Complaints</h2>
  <p>View resident complaints, update status, and add staff remarks for follow-up.</p>
</div>

<section class="panel-card" data-complaint-list data-overview-owner="staff-complaints-<?= (int)($loggedInUser['id'] ?? 0) ?>">
  <div class="panel-head"><h3>Complaint list</h3><button type="button" class="btn btn-primary" data-husay-walkin>Add walk-in Mediation</button></div>
  <?php require __DIR__ . '/../complaint-search.php'; ?>
  <div class="table-wrap">
    <table class="data-table complaint-list-table">
      <thead><tr><th>ID</th><th>Resident name</th><th>Subject</th><th>Date submitted</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody data-live-overview="staff-complaints">
        <?php foreach ($staffComplaints as $complaint): $complaintAttachments = barangay_attachment_files('complaint', $complaint['id']); ?>
        <tr data-complaint-search="<?= htmlspecialchars(implode(' ', [$complaint['id'], $complaint['resident'], $complaint['subject'], $complaint['category'] ?? '', $complaint['details'] ?? '', $complaint['location'] ?? '', $complaint['date'], $complaint['priority'], $complaint['status'], $complaint['remarks'] ?? '']), ENT_QUOTES) ?>">
          <td class="complaint-list-id" data-label="Complaint ID"><?= htmlspecialchars($complaint['id']) ?></td>
          <td data-label="Resident"><?= htmlspecialchars($complaint['resident']) ?></td>
          <td class="complaint-list-subject" data-label="Subject"><?= htmlspecialchars($complaint['subject']) ?></td>
          <td data-label="Date submitted"><?= htmlspecialchars($complaint['date']) ?></td>
          <td data-label="Priority"><?= htmlspecialchars($complaint['priority']) ?></td>
          <td data-label="Status"><span class="badge <?= in_array($complaint['status'], ['Resolved', 'Closed'], true) ? 'badge-resolved' : ($complaint['status'] === 'In Progress' ? 'badge-progress' : 'badge-open') ?>"><?= htmlspecialchars($complaint['status']) ?></span></td>
          <td class="complaint-record-actions">
            <?php $complaintActionRecord = $complaint; $complaintUpdateUrl = 'staff-actions.php'; require __DIR__ . '/../complaint-actions.php'; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$staffComplaints): ?>
        <tr><td colspan="7">No complaints yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../husay-panel.php'; ?>

<?php if (staff_portal_page() !== 'dashboard.php'): ?>
<div id="viewRecordModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="complaintRecordTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="complaintRecordTitle" data-record-title>Complaint</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body"><div class="auto-fill-grid resident-details-grid" data-record-view></div></div>
  </div>
</div>
<?php endif; ?>
