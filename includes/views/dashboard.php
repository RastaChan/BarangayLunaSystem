      <div class="dashboard-intro">
      <div class="page-intro">
        <h2>Admin overview</h2>
        <p>Monitor barangay operations, staff accounts, and resident services from one place.</p>
      </div>
      <button type="button" class="btn btn-primary" data-modal-open="communityPostModal">Announcement and Event</button>
      </div>

      <div class="stats-grid" data-live-overview="stats" data-overview-owner="<?= (int) $loggedInUser['id'] ?>">
        <article class="stat-card">
          <div class="label">Active staff</div>
          <div class="value"><?= (int) $stats['staff'] ?></div>
          <div class="trend">Barangay officials</div>
        </article>
        <article class="stat-card">
          <div class="label">Registered residents</div>
          <div class="value"><?= (int) $stats['residents'] ?></div>
          <div class="trend">In-person verified</div>
        </article>
        <article class="stat-card">
          <div class="label">Open complaints</div>
          <div class="value"><?= (int) $stats['complaints'] ?></div>
          <div class="trend">Needs attention</div>
        </article>
        <article class="stat-card">
          <div class="label">Pending documents</div>
          <div class="value"><?= (int) $stats['documents'] ?></div>
          <div class="trend">Awaiting action</div>
        </article>
      </div>

      <?php require __DIR__ . '/../husay-today.php'; ?>

      <div class="info-banner">
        <div>
          <strong>Staff accounts are admin-only</strong>
          Barangay staff cannot register themselves. Use <a href="?role=admin&view=user-management">User Management</a> to add officials after HR validation.
        </div>
      </div>

      <?php include __DIR__ . '/announcements.php'; ?>

      <section class="panel-card mobile-record-panel">
        <div class="panel-head"><h3>Recent complaints</h3>
          <a href="?role=admin&view=complaints" class="btn btn-sm btn-outline">View all</a>
        </div>
        <div class="table-wrap">
          <table class="data-table mobile-record-table" role="table" aria-label="Recent complaints">
            <thead><tr><th>ID</th><th>Subject</th><th>Status</th></tr></thead>
            <tbody data-live-overview="complaints">
              <?php foreach (array_slice($adminComplaints, 0, 3) as $c): ?>
              <tr>
                <td data-label="Complaint ID"><?= htmlspecialchars($c['id']) ?></td>
                <td data-label="Subject"><?= htmlspecialchars($c['subject']) ?></td>
                <td data-label="Status"><span class="badge badge-open"><?= htmlspecialchars($c['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
