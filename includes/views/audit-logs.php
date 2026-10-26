      <div class="page-intro">
        <h2>Audit Logs</h2>
        <p>Track system activity and staff actions for accountability and compliance.</p>
      </div>

      <form class="page-toolbar audit-log-filters" method="get" action="dashboard.php" data-live-search-form="audit">
          <input type="hidden" name="view" value="audit-logs">
          <div class="field"><label for="auditSearch">Search activity</label>
          <input id="auditSearch" type="search" name="search" value="<?= htmlspecialchars($auditFilters['search'], ENT_QUOTES) ?>" placeholder="User, action, module, role or status"></div>
          <div class="field"><label for="auditAction">Action</label>
          <select id="auditAction" name="audit_action">
            <option value="">All actions</option>
            <?php foreach (admin_fetch_all('SELECT DISTINCT action FROM audit_logs ORDER BY action') as $auditOption): ?>
            <option value="<?= htmlspecialchars($auditOption['action'], ENT_QUOTES) ?>" <?= $auditFilters['audit_action'] === $auditOption['action'] ? 'selected' : '' ?>><?= htmlspecialchars($auditOption['action']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <button class="btn btn-primary" type="submit">Search</button>
          <a class="btn btn-outline" href="dashboard.php?view=audit-logs">Clear</a>
          <a class="btn btn-outline" data-live-search-link="export" href="admin-export.php?<?= htmlspecialchars(http_build_query(['type' => 'audit'] + $auditFilters), ENT_QUOTES) ?>">
            <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="download" class="system-icon"><path d="M216,48V208H40V48A16,16,0,0,1,56,32H200A16,16,0,0,1,216,48Z" opacity="0.2"/><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V32a8,8,0,0,0-16,0v92.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"/></svg>
            Export
          </a>
      </form>
      <p class="field-hint" data-live-search-results="audit">Showing the latest <?= count($auditLogs) ?> matching records (up to 100). Export includes all matching records.</p>

      <section class="panel-card mobile-record-panel" data-live-search-results="audit">
        <div class="panel-body">
          <div class="logs-container">
            <table class="logs-table mobile-record-table" role="table" aria-label="Audit activity records">
              <thead>
                <tr>
                  <th>Date & Time</th>
                  <th>User / Staff</th>
                  <th>Role</th>
                  <th>Action</th>
                  <th>Module</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td data-label="Date and time">
                    <span class="log-date"><?= htmlspecialchars(barangay_display_datetime($log['date'])) ?></span>
                  </td>
                  <td data-label="User / Staff">
                    <span class="log-user"><?= htmlspecialchars($log['user']) ?></span>
                  </td>
                  <td data-label="Role">
                    <span class="log-role"><?= htmlspecialchars($log['role']) ?></span>
                  </td>
                  <td data-label="Action">
                    <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
                  </td>
                  <td data-label="Module">
                    <span class="log-module"><?= htmlspecialchars($log['module']) ?></span>
                  </td>
                  <td data-label="Status">
                    <span class="log-status <?= strtolower($log['status']) ?>">
                      <?= htmlspecialchars($log['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$auditLogs): ?>
                <tr><td colspan="6">No activity matches the selected filters.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

