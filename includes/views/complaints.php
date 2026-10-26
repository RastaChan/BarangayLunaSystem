      <?php require_once __DIR__ . '/../record-attachments.php'; ?>
      <div class="page-intro">
        <h2>Complaints</h2>
        <p>Track community complaints filed by residents and assign resolution status.</p>
      </div>

      <section class="panel-card" data-complaint-list data-overview-owner="admin-complaints-<?= (int)($loggedInUser['id'] ?? 0) ?>">
        <div class="panel-head"><h3>Complaint records</h3><button type="button" class="btn btn-primary" data-husay-walkin>Add walk-in Mediation</button></div>
        <?php require __DIR__ . '/../complaint-search.php'; ?>
        <div class="table-wrap">
          <table class="data-table complaint-list-table">
            <thead>
              <tr><th>ID</th><th>Resident</th><th>Subject</th><th>Status</th><th>Date filed</th><th>Actions</th></tr>
            </thead>
            <tbody data-live-overview="staff-complaints">
              <?php foreach ($adminComplaints as $c): $complaintAttachments = barangay_attachment_files('complaint', $c['id']); ?>
              <tr data-complaint-search="<?= htmlspecialchars(implode(' ', [$c['id'], $c['resident'], $c['subject'], $c['category'] ?? '', $c['details'] ?? '', $c['location'] ?? '', $c['date'], $c['status'], $c['remarks'] ?? '']), ENT_QUOTES) ?>">
                <td class="complaint-list-id" data-label="Complaint ID"><?= htmlspecialchars($c['id']) ?></td>
                <td data-label="Resident"><?= htmlspecialchars($c['resident']) ?></td>
                <td class="complaint-list-subject" data-label="Subject"><?= htmlspecialchars($c['subject']) ?></td>
                <td data-label="Status">
                  <?php
                  $badge = 'open';
                  if ($c['status'] === 'In Progress') $badge = 'progress';
                  if (in_array($c['status'], ['Resolved', 'Closed'], true)) $badge = 'resolved';
                  ?>
                  <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($c['status']) ?></span>
                </td>
                <td data-label="Date filed"><?= htmlspecialchars($c['date']) ?></td>
                <td class="complaint-record-actions">
                  <?php $complaintActionRecord = $c; $complaintUpdateUrl = 'admin-actions.php'; require __DIR__ . '/../complaint-actions.php'; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$adminComplaints): ?>
              <tr><td colspan="6">No complaints yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php require __DIR__ . '/../husay-panel.php'; ?>
