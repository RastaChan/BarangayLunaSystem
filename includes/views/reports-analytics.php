      <div class="page-intro">
        <h2>Reports & Analytics</h2>
        <p>Comprehensive reports and analytics for system activity and service requests.</p>
      </div>

      <div class="page-toolbar">
        <a class="btn btn-primary btn-sm" href="admin-export.php?type=report">
          <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          Download system report (CSV)
        </a>
      </div>

      <div class="stats-grid" data-live-overview="analytics-stats" data-overview-owner="<?= (int) $loggedInUser['id'] ?>">
        <article class="stat-card">
          <div class="label">Documents Released</div>
          <div class="value"><?= (int) admin_count('issued_documents', "issuance_status = 'Released'") ?></div>
          <div class="stat-change">Confirmed walk-in and resident releases</div>
        </article>
        <article class="stat-card">
          <div class="label">Complaints Resolved</div>
          <div class="value"><?= (int) admin_count('complaints', "status = 'Resolved'") ?></div>
          <div class="stat-change">Resolved records</div>
        </article>
        <article class="stat-card">
          <div class="label">Active Staff</div>
          <div class="value"><?= (int) $stats['staff'] ?></div>
          <div class="stat-change">Current users</div>
        </article>
        <article class="stat-card">
          <div class="label">Residents</div>
          <div class="value"><?= (int) $stats['residents'] ?></div>
          <div class="stat-change">Registered accounts</div>
        </article>
      </div>

      <?php require __DIR__ . '/research-metrics.php'; ?>
      <?php
        $monthlyTrends = $monthlyTrends ?? admin_monthly_activity_trends(6);
        $trendKeys = $monthlyTrends['keys'] ?? [];
        $trendLabels = $monthlyTrends['labels'] ?? [];
        $trendSeries = $monthlyTrends['series'] ?? [];
        $trendMax = max(1, (int) ($monthlyTrends['max'] ?? 1));
        $documentTypeRows = admin_fetch_all(
          'SELECT document_type, COUNT(*) AS total
           FROM issued_documents
           WHERE issuance_status = "Released"
           GROUP BY document_type
           ORDER BY total DESC, document_type ASC
           LIMIT 5'
        );
      ?>
      <section class="panel-card">
        <div class="panel-head">
          <h3>Monthly Activity Trends</h3>
          <span class="panel-subtitle">Complaints, confirmed document releases, and resident reports</span>
        </div>
        <div class="panel-body">
          <div class="monthly-trend-card" data-live-overview="analytics-trends">
            <div class="monthly-trend-legend" aria-label="Chart legend">
              <?php foreach ($trendSeries as $series): ?>
              <span>
                <i style="--trend-color: <?= htmlspecialchars($series['color'] ?? '#15803d') ?>"></i>
                <?= htmlspecialchars($series['label'] ?? '') ?>
              </span>
              <?php endforeach; ?>
            </div>
            <div class="monthly-trend-chart" role="img" aria-label="Monthly activity line chart">
              <div class="monthly-trend-ylabels" aria-hidden="true">
                <span><?= (int) $trendMax ?></span>
                <span><?= (int) ceil($trendMax / 2) ?></span>
                <span>0</span>
              </div>
              <div class="monthly-trend-plot">
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                  <line x1="0" y1="16" x2="100" y2="16" class="trend-grid-line" />
                  <line x1="0" y1="52" x2="100" y2="52" class="trend-grid-line" />
                  <line x1="0" y1="88" x2="100" y2="88" class="trend-grid-line" />
                  <?php foreach ($trendSeries as $series): ?>
                  <polyline
                    class="trend-line"
                    points="<?= htmlspecialchars(admin_line_chart_points($series['values'] ?? [], $trendKeys, $trendMax)) ?>"
                    style="--trend-color: <?= htmlspecialchars($series['color'] ?? '#15803d') ?>" />
                  <?php endforeach; ?>
                </svg>
                <div class="monthly-trend-points" aria-hidden="true">
                  <?php
                    $pointCount = max(1, count($trendKeys) - 1);
                    foreach ($trendSeries as $series):
                      foreach ($trendKeys as $pointIndex => $key):
                        $pointValue = (int) (($series['values'] ?? [])[$key] ?? 0);
                        $pointLeft = $pointCount === 0 ? 50 : ($pointIndex / $pointCount) * 100;
                        $pointTop = 88 - (($pointValue / max(1, $trendMax)) * 72);
                  ?>
                  <span
                    class="trend-point"
                    style="--trend-color: <?= htmlspecialchars($series['color'] ?? '#15803d') ?>; left: <?= round($pointLeft, 2) ?>%; top: <?= round($pointTop, 2) ?>%"
                    title="<?= htmlspecialchars(($series['label'] ?? '') . ': ' . $pointValue) ?>"></span>
                  <?php
                      endforeach;
                    endforeach;
                  ?>
                </div>
              </div>
            </div>
            <div class="monthly-trend-months" style="--month-count: <?= max(1, count($trendKeys)) ?>" aria-hidden="true">
              <?php foreach ($trendKeys as $key): ?>
              <span><?= htmlspecialchars($trendLabels[$key] ?? $key) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <div class="grid-2" style="margin-top:1.25rem">
        <section class="panel-card">
          <div class="panel-head"><h3>Complaints by Status</h3></div>
          <div class="panel-body">
            <ul style="margin:0;padding:0;list-style:none;font-size:0.9rem">
              <li style="padding:0.5rem 0;border-bottom:1px solid #e2e8e4"><strong>Open:</strong> <span style="color:var(--green-700)"><?= (int) admin_count('complaints', "status = 'Open'") ?></span></li>
              <li style="padding:0.5rem 0;border-bottom:1px solid #e2e8e4"><strong>In Progress:</strong> <span style="color:var(--green-700)"><?= (int) admin_count('complaints', "status = 'In Progress'") ?></span></li>
              <li style="padding:0.5rem 0"><strong>Resolved:</strong> <span style="color:var(--green-700)"><?= (int) admin_count('complaints', "status = 'Resolved'") ?></span></li>
            </ul>
          </div>
        </section>
        <section class="panel-card">
          <div class="panel-head"><h3>Released Document Types</h3></div>
          <div class="panel-body" data-live-overview="analytics-document-types">
            <ul style="margin:0;padding:0;list-style:none;font-size:0.9rem">
              <?php if ($documentTypeRows): ?>
                <?php foreach ($documentTypeRows as $index => $row): ?>
                <li style="padding:0.5rem 0;<?= $index < count($documentTypeRows) - 1 ? 'border-bottom:1px solid #e2e8e4' : '' ?>">
                  <strong><?= htmlspecialchars($row['document_type'] ?? 'Document') ?>:</strong>
                  <span style="color:var(--green-700)"><?= (int) ($row['total'] ?? 0) ?></span>
                </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li style="padding:0.5rem 0;color:var(--text-muted)">No released documents yet.</li>
              <?php endif; ?>
            </ul>
          </div>
        </section>
      </div>

      <section class="panel-card" style="margin-top:1.25rem">
        <div class="panel-head"><h3>Resident Report</h3></div>
        <div class="panel-body">
          <p style="font-size:0.9rem;color:var(--text-muted);margin:0 0 1rem">Total registered residents: <strong style="color:var(--text)"><?= (int) $stats['residents'] ?></strong></p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:0.85rem">
            <div><strong>Active:</strong> <span style="color:var(--green-700)"><?= (int) admin_count('users', "role = 'resident' AND status = 'Active'") ?></span></div>
            <div><strong>Pending Verification:</strong> <span style="color:#f59e0b"><?= (int) admin_count('users', "role = 'resident' AND status = 'Pending'") ?></span></div>
            <div><strong>Inactive:</strong> <span style="color:#6b7280"><?= (int) admin_count('users', "role = 'resident' AND status = 'Inactive'") ?></span></div>
            <div><strong>New This Month:</strong> <span style="color:var(--green-700)"><?= (int) admin_count('users', "role = 'resident' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())") ?></span></div>
          </div>
        </div>
      </section>

<style>
.page-toolbar {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.page-toolbar .btn-sm {
  padding: 0.65rem 1.15rem;
  font-size: 0.85rem;
}

.stat-change {
  font-size: 0.75rem;
  color: var(--green-600);
  margin-top: 0.25rem;
}

</style>
