<div class="page-intro service-reports-intro">
  <div>
    <h2>Service Reports</h2>
    <p>Review service totals, issued documents, copies, and fees.</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="issuedDocumentRecordsModal">View issued documents</button>
</div>

<div class="stats-grid">
  <article class="stat-card">
    <div class="label">Documents issued today</div>
    <div class="value"><?= (int) $serviceReports['issued_today'] ?></div>
    <div class="trend">Current shift</div>
  </article>
  <article class="stat-card">
    <div class="label">Documents issued this month</div>
    <div class="value"><?= (int) $serviceReports['issued_month'] ?></div>
    <div class="trend"><?= date('F Y') ?></div>
  </article>
  <article class="stat-card">
    <div class="label">Requests processed</div>
    <div class="value"><?= (int) $serviceReports['processed_week'] ?></div>
    <div class="trend">This week</div>
  </article>
  <article class="stat-card">
    <div class="label">Complaints updated</div>
    <div class="value"><?= (int) $serviceReports['complaints_week'] ?></div>
    <div class="trend">This week</div>
  </article>
</div>

<section class="panel-card">
  <div class="panel-head">
    <h3>Service summary</h3>
    <div class="actions">
      <a href="staff-export.php?type=service-summary" class="btn btn-sm btn-outline">Export</a>
      <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Service</th><th>Today</th><th>This month</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($serviceSummary as $summary): ?>
        <tr>
          <td><?= htmlspecialchars($summary['service']) ?></td>
          <td><?= (int) $summary['today'] ?></td>
          <td><?= (int) $summary['month'] ?></td>
          <td><span class="badge <?= (int) $summary['today'] > 0 ? 'badge-active' : 'badge-pending' ?>"><?= (int) $summary['today'] > 0 ? 'On track' : 'Monitor' ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$serviceSummary): ?>
        <tr><td colspan="4">No issued document records yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/issued-document-records.php'; ?>

<?php require __DIR__ . '/../views/service-processing-metrics.php'; ?>
