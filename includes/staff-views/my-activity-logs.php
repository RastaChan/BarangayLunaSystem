<div class="page-intro">
  <h2>My Activity Logs</h2>
  <p>View your own recorded actions across service modules.</p>
</div>

<section class="panel-card mobile-record-panel">
  <div class="panel-head"><h3>My system actions</h3></div>
  <div class="table-wrap">
    <table class="data-table mobile-record-table" role="table" aria-label="My system actions">
      <thead><tr><th>Date and time</th><th>Action performed</th><th>Module</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($activityLogs as $log): ?>
        <tr>
          <td data-label="Date and time"><?= htmlspecialchars($log['date']) ?></td>
          <td data-label="Action performed"><?= htmlspecialchars($log['action']) ?></td>
          <td data-label="Module"><?= htmlspecialchars($log['module']) ?></td>
          <td data-label="Status"><span class="badge badge-active"><?= htmlspecialchars($log['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$activityLogs): ?><tr><td colspan="4">No activity recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
