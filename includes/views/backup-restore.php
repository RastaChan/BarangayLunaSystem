      <div class="backup-workspace">
      <div class="page-intro">
        <h2>Backup & Restore</h2>
        <p>Manage system backups and restore barangay records from previous backups.</p>
      </div>

      <div class="backup-actions">
        <div class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="database" class="system-icon"><path d="M216,80c0,26.51-39.4,48-88,48S40,106.51,40,80s39.4-48,88-48S216,53.49,216,80Z" opacity="0.2"/><path d="M128,24C74.17,24,32,48.6,32,80v96c0,31.4,42.17,56,96,56s96-24.6,96-56V80C224,48.6,181.83,24,128,24Zm80,104c0,9.62-7.88,19.43-21.61,26.92C170.93,163.35,150.19,168,128,168s-42.93-4.65-58.39-13.08C55.88,147.43,48,137.62,48,128V111.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64ZM69.61,53.08C85.07,44.65,105.81,40,128,40s42.93,4.65,58.39,13.08C200.12,60.57,208,70.38,208,80s-7.88,19.43-21.61,26.92C170.93,115.35,150.19,120,128,120s-42.93-4.65-58.39-13.08C55.88,99.43,48,89.62,48,80S55.88,60.57,69.61,53.08ZM186.39,202.92C170.93,211.35,150.19,216,128,216s-42.93-4.65-58.39-13.08C55.88,195.43,48,185.62,48,176V159.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64V176C208,185.62,200.12,195.43,186.39,202.92Z"/></svg>
          </div>
          <h3>Create Backup</h3>
          <p>Create a new complete system backup with all resident records, documents, and settings.</p>
          <form action="admin-actions.php" method="post">
            <input type="hidden" name="action" value="create_backup">
            <input type="hidden" name="redirect" value="backup-restore">
          <button class="btn btn-primary" type="submit">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="database" class="system-icon"><path d="M216,80c0,26.51-39.4,48-88,48S40,106.51,40,80s39.4-48,88-48S216,53.49,216,80Z" opacity="0.2"/><path d="M128,24C74.17,24,32,48.6,32,80v96c0,31.4,42.17,56,96,56s96-24.6,96-56V80C224,48.6,181.83,24,128,24Zm80,104c0,9.62-7.88,19.43-21.61,26.92C170.93,163.35,150.19,168,128,168s-42.93-4.65-58.39-13.08C55.88,147.43,48,137.62,48,128V111.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64ZM69.61,53.08C85.07,44.65,105.81,40,128,40s42.93,4.65,58.39,13.08C200.12,60.57,208,70.38,208,80s-7.88,19.43-21.61,26.92C170.93,115.35,150.19,120,128,120s-42.93-4.65-58.39-13.08C55.88,99.43,48,89.62,48,80S55.88,60.57,69.61,53.08ZM186.39,202.92C170.93,211.35,150.19,216,128,216s-42.93-4.65-58.39-13.08C55.88,195.43,48,185.62,48,176V159.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64V176C208,185.62,200.12,195.43,186.39,202.92Z"/></svg>
            Create Backup Now
          </button>
          </form>
        </div>

        <div class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="database" class="system-icon"><path d="M216,80c0,26.51-39.4,48-88,48S40,106.51,40,80s39.4-48,88-48S216,53.49,216,80Z" opacity="0.2"/><path d="M128,24C74.17,24,32,48.6,32,80v96c0,31.4,42.17,56,96,56s96-24.6,96-56V80C224,48.6,181.83,24,128,24Zm80,104c0,9.62-7.88,19.43-21.61,26.92C170.93,163.35,150.19,168,128,168s-42.93-4.65-58.39-13.08C55.88,147.43,48,137.62,48,128V111.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64ZM69.61,53.08C85.07,44.65,105.81,40,128,40s42.93,4.65,58.39,13.08C200.12,60.57,208,70.38,208,80s-7.88,19.43-21.61,26.92C170.93,115.35,150.19,120,128,120s-42.93-4.65-58.39-13.08C55.88,99.43,48,89.62,48,80S55.88,60.57,69.61,53.08ZM186.39,202.92C170.93,211.35,150.19,216,128,216s-42.93-4.65-58.39-13.08C55.88,195.43,48,185.62,48,176V159.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64V176C208,185.62,200.12,195.43,186.39,202.92Z"/></svg>
          </div>
          <h3>Restore Backup</h3>
          <p>Restore system data from a previous backup. This will overwrite current data.</p>
          <button class="btn btn-outline" type="button" onclick="document.getElementById('backupHistoryPanel')?.scrollIntoView({behavior:'smooth', block:'start'})">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="database" class="system-icon"><path d="M216,80c0,26.51-39.4,48-88,48S40,106.51,40,80s39.4-48,88-48S216,53.49,216,80Z" opacity="0.2"/><path d="M128,24C74.17,24,32,48.6,32,80v96c0,31.4,42.17,56,96,56s96-24.6,96-56V80C224,48.6,181.83,24,128,24Zm80,104c0,9.62-7.88,19.43-21.61,26.92C170.93,163.35,150.19,168,128,168s-42.93-4.65-58.39-13.08C55.88,147.43,48,137.62,48,128V111.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64ZM69.61,53.08C85.07,44.65,105.81,40,128,40s42.93,4.65,58.39,13.08C200.12,60.57,208,70.38,208,80s-7.88,19.43-21.61,26.92C170.93,115.35,150.19,120,128,120s-42.93-4.65-58.39-13.08C55.88,99.43,48,89.62,48,80S55.88,60.57,69.61,53.08ZM186.39,202.92C170.93,211.35,150.19,216,128,216s-42.93-4.65-58.39-13.08C55.88,195.43,48,185.62,48,176V159.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64V176C208,185.62,200.12,195.43,186.39,202.92Z"/></svg>
            Restore from Backup
          </button>
        </div>

        <div class="action-card">
          <div class="action-icon">
            <svg width="32" height="32" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="download" class="system-icon"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg>
          </div>
          <h3>Download Backup</h3>
          <p>Download a backup file to your local machine for offline storage.</p>
          <a class="btn btn-outline" href="admin-download.php">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="download" class="system-icon"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg>
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
                        <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="download" class="system-icon"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg>
                      </a>
                      <button class="btn-icon" title="Restore" type="button" data-modal-open="restoreBackupModal<?= (int) $backup['id'] ?>">
                        <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="database" class="system-icon"><path d="M216,80c0,26.51-39.4,48-88,48S40,106.51,40,80s39.4-48,88-48S216,53.49,216,80Z" opacity="0.2"/><path d="M128,24C74.17,24,32,48.6,32,80v96c0,31.4,42.17,56,96,56s96-24.6,96-56V80C224,48.6,181.83,24,128,24Zm80,104c0,9.62-7.88,19.43-21.61,26.92C170.93,163.35,150.19,168,128,168s-42.93-4.65-58.39-13.08C55.88,147.43,48,137.62,48,128V111.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64ZM69.61,53.08C85.07,44.65,105.81,40,128,40s42.93,4.65,58.39,13.08C200.12,60.57,208,70.38,208,80s-7.88,19.43-21.61,26.92C170.93,115.35,150.19,120,128,120s-42.93-4.65-58.39-13.08C55.88,99.43,48,89.62,48,80S55.88,60.57,69.61,53.08ZM186.39,202.92C170.93,211.35,150.19,216,128,216s-42.93-4.65-58.39-13.08C55.88,195.43,48,185.62,48,176V159.36c17.06,15,46.23,24.64,80,24.64s62.94-9.68,80-24.64V176C208,185.62,200.12,195.43,186.39,202.92Z"/></svg>
                      </button>
                      <button class="btn-icon" title="Delete" type="button" data-modal-open="deleteBackupModal<?= (int) $backup['id'] ?>">
                        <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="trash-2" class="system-icon"><path d="M200,56V208a8,8,0,0,1-8,8H64a8,8,0,0,1-8-8V56Z" opacity="0.2"/><path d="M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96Zm96,168H64V64H192ZM112,104v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Zm48,0v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Z"/></svg>
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
  box-shadow: 0 3px 8px rgba(19, 55, 35, .10), 0 8px 20px rgba(19, 55, 35, .06);
  transition: box-shadow 0.2s, border-color 0.2s;
}

.backup-workspace .action-card:hover {
  border-color: var(--green-100);
  box-shadow: 0 5px 12px rgba(19, 55, 35, .13), 0 12px 26px rgba(19, 55, 35, .08);
}

.backup-workspace #backupHistoryPanel {
  border-color: #cbded2;
  box-shadow: 0 3px 8px rgba(19, 55, 35, .10), 0 8px 20px rgba(19, 55, 35, .06);
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
