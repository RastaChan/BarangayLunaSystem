<?php
$activityPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
$history = resident_activity_history($loggedInUser, $activityPage);
?>
<div class="page-intro"><h2>My Activity Logs</h2></div>
<section class="panel-card resident-activity-panel mobile-record-panel">
  <div class="panel-body">
    <div class="logs-container">
      <table class="logs-table mobile-record-table" role="table" aria-label="My activity history">
        <thead><tr><th>Date &amp; Time</th><th>Action</th><th>Module</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($history['items'] as $entry): ?>
          <tr>
            <td data-label="Date and time"><time class="log-date" datetime="<?= htmlspecialchars(date('c', strtotime($entry['created_at']))) ?>"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($entry['created_at']))) ?></time></td>
            <td data-label="Action"><span class="log-action"><?= htmlspecialchars($entry['action']) ?></span></td>
            <td data-label="Module"><span class="log-module"><?= htmlspecialchars($entry['module']) ?></span></td>
            <td data-label="Status"><span class="log-status <?= htmlspecialchars(strtolower($entry['status']), ENT_QUOTES) ?>"><?= htmlspecialchars($entry['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$history['items']): ?><tr><td colspan="4">No activity recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($history['pages'] > 1): ?>
  <nav class="resident-activity-pages" aria-label="Activity log pages">
    <?php if ($history['page'] > 1): ?><a class="btn btn-outline btn-sm" href="resident-dashboard.php?view=my-activity-logs&amp;page=<?= $history['page'] - 1 ?>">Previous</a><?php endif; ?>
    <span>Page <?= $history['page'] ?> of <?= $history['pages'] ?></span>
    <?php if ($history['page'] < $history['pages']): ?><a class="btn btn-outline btn-sm" href="resident-dashboard.php?view=my-activity-logs&amp;page=<?= $history['page'] + 1 ?>">Next</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
