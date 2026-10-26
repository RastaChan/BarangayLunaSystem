<div class="dashboard-intro">
<div class="page-intro">
  <h2>Daily staff overview</h2>
  <p>Track today&apos;s service work, resident assistance, and assigned barangay office activity.</p>
</div>
<button type="button" class="btn btn-primary" data-modal-open="communityPostModal">Announcement and Event</button>
</div>

<div class="stats-grid" data-live-overview="stats" data-overview-owner="<?= (int) $loggedInUser['id'] ?>">
  <article class="stat-card">
    <div class="label">Pending document requests</div>
    <div class="value"><?= (int) $staffStats['pending_requests'] ?></div>
    <div class="trend">Needs review</div>
  </article>
  <article class="stat-card">
    <div class="label">Documents issued today</div>
    <div class="value"><?= (int) $staffStats['issued_today'] ?></div>
    <div class="trend">Recorded by office staff</div>
  </article>
  <article class="stat-card">
    <div class="label">Open complaints</div>
    <div class="value"><?= (int) $staffStats['open_complaints'] ?></div>
    <div class="trend">For status updates</div>
  </article>
  <article class="stat-card">
    <div class="label">Residents registered today</div>
    <div class="value"><?= (int) $staffStats['verified_today'] ?></div>
    <div class="trend">Ready for resident services</div>
  </article>
</div>

<?php require __DIR__ . '/../husay-today.php'; ?>
<?php include __DIR__ . '/staff-announcements.php'; ?>

  <section class="panel-card mobile-record-panel">
    <div class="panel-head">
      <h3>Resident accounts</h3>
      <a href="<?= htmlspecialchars(staff_view_url('resident-account-registration')) ?>" class="btn btn-sm btn-outline">Register resident</a>
    </div>
    <div class="table-wrap">
      <table class="data-table mobile-record-table" role="table" aria-label="Resident accounts">
        <thead><tr><th>Resident ID</th><th>Name</th><th>Status</th></tr></thead>
        <tbody data-live-overview="residents">
          <?php foreach ($residentRecords as $resident): ?>
          <tr>
            <td data-label="Resident ID"><?= htmlspecialchars($resident['id']) ?></td>
            <td data-label="Name"><?= htmlspecialchars($resident['name']) ?></td>
            <td data-label="Status"><span class="badge <?= $resident['status'] === 'Active' ? 'badge-active' : 'badge-inactive' ?>"><?= htmlspecialchars($resident['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>



<section class="panel-card mobile-record-panel" style="margin-top:1.25rem">
  <div class="panel-head">
    <h3>My recent activities</h3>
    <a href="<?= htmlspecialchars(staff_view_url('my-activity-logs')) ?>" class="btn btn-sm btn-outline">View logs</a>
  </div>
  <div class="table-wrap">
    <table class="data-table mobile-record-table" role="table" aria-label="My recent activities">
      <thead><tr><th>Date and time</th><th>Action</th><th>Module</th><th>Status</th></tr></thead>
      <tbody data-live-overview="activities">
        <?php foreach (array_slice($activityLogs, 0, 3) as $log): ?>
        <tr>
          <td data-label="Date and time"><?= htmlspecialchars(barangay_display_datetime($log['date'])) ?></td>
          <td data-label="Action performed"><?= htmlspecialchars($log['action']) ?></td>
          <td data-label="Module"><?= htmlspecialchars($log['module']) ?></td>
          <td data-label="Status"><span class="badge badge-active"><?= htmlspecialchars($log['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$activityLogs): ?><tr><td colspan="4">No recent activity recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
