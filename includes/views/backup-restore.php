      <div class="backup-workspace">
      <div class="page-intro">
        <h2>Backup & Restore</h2>
        <p>Manage system backups and restore barangay records from previous backups.</p>
      </div>

      <div class="backup-actions">
        <div class="action-card">
          <div class="action-icon">
            <svg viewBox="0 0 24 24" fill="none" width="32" height="32" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-9 9Zm9-5v4l3 2M7 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <h3>Create Backup</h3>
          <p>Create a new complete system backup with all resident records, documents, and settings.</p>
          <form action="admin-actions.php" method="post">
            <input type="hidden" name="action" value="create_backup">
            <input type="hidden" name="redirect" value="backup-restore">
          <button class="btn btn-primary" type="submit">
            <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Create Backup Now
          </button>
          </form>
        </div>

        <div class="action-card">
          <div class="action-icon">
            <svg viewBox="0 0 24 24" fill="none" width="32" height="32" aria-hidden="true"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2m-9 5v4m-3-3v3m6-2v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <h3>Restore Backup</h3>
          <p>Restore system data from a previous backup. This will overwrite current data.</p>
          <button class="btn btn-outline" type="button" onclick="document.getElementById('backupHistoryPanel')?.scrollIntoView({behavior:'smooth', block:'start'})">
            <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Restore from Backup
          </button>
        </div>

        <div class="action-card">
          <div class="action-icon">
            <svg viewBox="0 0 24 24" fill="none" width="32" height="32" aria-hidden="true"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <h3>Download Backup</h3>
          <p>Download a backup file to your local machine for offline storage.</p>
          <a class="btn btn-outline" href="admin-download.php">
            <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Download Latest
          </a>
        </div>
      </div>

      <section class="panel-card" style="margin-top:2rem" id="backupHistoryPanel">
        <div class="panel-head"><h3>Backup History</h3></div>
        <div class="panel-body">
          <div class="backup-list">
            <table class="backup-table">
              <thead>
                <tr>
                  <th>Backup Date & Time</th>
                  <th>File Name</th>
                  <th>Created By</th>
                  <th>Status</th>
                  <th>File Size</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($backups as $backup): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($backup['date']) ?></strong>
                    <br>
                    <span style="font-size:0.85rem;color:var(--text-muted)"><?= htmlspecialchars($backup['time']) ?></span>
                  </td>
                  <td>
                    <code style="background:var(--green-50);padding:0.3rem 0.6rem;border-radius:4px;font-size:0.85rem"><?= htmlspecialchars($backup['filename']) ?></code>
                  </td>
                  <td><?= htmlspecialchars($backup['createdBy']) ?></td>
                  <td>
                    <span class="badge badge-success"><?= htmlspecialchars($backup['status']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($backup['size']) ?></td>
                  <td>
                    <div class="backup-actions-btn">
                      <a class="btn-icon" title="Download" href="admin-download.php?id=<?= (int) $backup['id'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      </a>
                      <button class="btn-icon" title="Restore" type="button" data-modal-open="restoreBackupModal<?= (int) $backup['id'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      </button>
                      <button class="btn-icon" title="Delete" type="button" data-modal-open="deleteBackupModal<?= (int) $backup['id'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3H4v2h16V7h-3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$backups): ?>
                <tr><td colspan="6">No backups yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <?php foreach ($backups as $backup): ?>
      <div id="restoreBackupModal<?= (int) $backup['id'] ?>" class="modal" aria-hidden="true" role="dialog">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-panel">
          <header class="modal-header"><h2>Restore Backup</h2></header>
          <div class="modal-body">
            <p>Restore system records from <strong><?= htmlspecialchars($backup['filename']) ?></strong>?</p>
            <p class="field-hint">This replaces current database records with the selected backup file.</p>
          </div>
          <form id="restoreBackupForm<?= (int) $backup['id'] ?>" action="admin-actions.php" method="post" data-server-form="true">
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="redirect" value="backup-restore">
            <input type="hidden" name="id" value="<?= (int) $backup['id'] ?>">
          </form>
          <footer class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            <button type="submit" form="restoreBackupForm<?= (int) $backup['id'] ?>" class="btn btn-danger">Restore backup</button>
          </footer>
        </div>
      </div>

      <div id="deleteBackupModal<?= (int) $backup['id'] ?>" class="modal" aria-hidden="true" role="dialog">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-panel">
          <header class="modal-header"><h2>Delete Backup</h2></header>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong><?= htmlspecialchars($backup['filename']) ?></strong>?</p>
            <p class="field-hint">This will permanently remove the backup record and backup file if it exists.</p>
          </div>
          <form id="deleteBackupForm<?= (int) $backup['id'] ?>" action="admin-actions.php" method="post" data-server-form="true">
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="redirect" value="backup-restore">
            <input type="hidden" name="id" value="<?= (int) $backup['id'] ?>">
          </form>
          <footer class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            <button type="submit" form="deleteBackupForm<?= (int) $backup['id'] ?>" class="btn btn-danger">Delete</button>
          </footer>
        </div>
      </div>
      <?php endforeach; ?>

      </div>
<style>
.backup-workspace .backup-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.backup-workspace .action-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 0;
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  text-align: center;
  transition: box-shadow 0.2s, border-color 0.2s;
}

.backup-workspace .action-card:hover {
  border-color: var(--green-100);
  box-shadow: var(--shadow-sm);
}

.backup-workspace .action-icon {
  width: 56px;
  height: 56px;
  background: var(--green-50);
  border-radius: 12px;
  display: grid;
  place-items: center;
  margin: 0 auto 1rem;
  color: var(--green-700);
}

.backup-workspace .action-card h3 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
}

.backup-workspace .action-card p {
  margin: 0 0 1.25rem;
  font-size: 0.9rem;
  color: var(--text-muted);
  line-height: 1.5;
}

.backup-workspace .action-card > form,
.backup-workspace .action-card > .btn { margin-top: auto; width: 100%; }
.backup-workspace .action-card > form > .btn { width: 100%; }
.backup-workspace .backup-list {
  max-width: 100%;
  overflow-x: auto;
}

.backup-workspace .backup-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.backup-workspace .backup-table thead {
  background: var(--green-50);
  border-bottom: 1px solid var(--border);
}

.backup-workspace .backup-table th {
  padding: 0.85rem;
  text-align: left;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
}

.backup-workspace .backup-table td {
  padding: 0.85rem;
  border-bottom: 1px solid var(--border);
}

.backup-workspace .backup-table tbody tr:hover {
  background: var(--green-50);
}

.backup-workspace .backup-actions-btn {
  display: flex;
  gap: 0.5rem;
}

.backup-workspace .backup-actions-btn .btn-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--white);
  color: var(--text-muted);
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: background 0.2s, border-color 0.2s;
}

.backup-workspace .backup-actions-btn .btn-icon:hover {
  border-color: var(--green-600);
  background: var(--green-50);
  color: var(--green-700);
}

.backup-workspace .badge {
  display: inline-block;
  padding: 0.35rem 0.65rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.backup-workspace .badge-success {
  background: #dcfce7;
  color: #166534;
}
</style>
