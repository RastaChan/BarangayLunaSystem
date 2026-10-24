<?php
require_once __DIR__ . '/../report-metrics.php';
$serviceError = '';
try { [$serviceFrom,$serviceTo] = barangay_report_dates($_GET); }
catch (InvalidArgumentException $error) { $serviceError=$error->getMessage(); [$serviceFrom,$serviceTo]=barangay_report_dates([]); }
$timing = barangay_service_metrics($serviceFrom,$serviceTo);
$serviceView = ($_GET['view'] ?? '') === 'reports-analytics' ? 'reports-analytics' : 'service-reports';
?>
<section class="panel-card service-timing" style="margin:1.25rem 0">
  <div class="panel-head"><h3>Document processing time</h3><?php if ($serviceView === 'service-reports'): ?><a class="btn btn-outline btn-sm" href="service-processing-export.php?<?= htmlspecialchars(http_build_query(['from'=>$serviceFrom,'to'=>$serviceTo]),ENT_QUOTES) ?>">Download processing times (CSV)</a><?php endif; ?></div>
  <div class="panel-body">
    <?php if ($serviceError): ?><p role="alert"><?= htmlspecialchars($serviceError) ?></p><?php endif; ?>
    <form method="get" class="service-timing-filters"><input type="hidden" name="view" value="<?= $serviceView ?>"><label>From<?= barangay_report_date_field('from', $serviceFrom) ?></label><label>To<?= barangay_report_date_field('to', $serviceTo) ?></label><div class="report-filter-actions"><button type="submit" class="btn btn-primary">Apply dates</button><a class="btn btn-outline" href="?view=<?= $serviceView ?>">Reset dates</a></div></form>
    <div data-live-overview="service-processing-time">
      <div class="stats-grid">
        <article class="stat-card"><div class="label">Request to preparation</div><div class="value"><?= barangay_service_duration($timing['ready']['seconds']) ?></div><p class="field-hint">Average of <?= (int)$timing['ready']['measured'] ?> requests prepared in this period</p></article>
        <article class="stat-card"><div class="label">Request to release</div><div class="value"><?= barangay_service_duration($timing['release']['seconds']) ?></div><p class="field-hint">Average of <?= (int)$timing['release']['measured'] ?> measured / <?= (int)$timing['release']['released'] ?> released requests</p></article>
        <article class="stat-card"><div class="label">Walk-in preparation to release</div><div class="value"><?= barangay_service_duration($timing['walkin']['seconds']) ?></div><p class="field-hint">Average of <?= (int)$timing['walkin']['measured'] ?> measured / <?= (int)$timing['walkin']['released'] ?> walk-in releases</p></article>
        <article class="stat-card"><div class="label">Currently awaiting completion</div><div class="value"><?= (int)$timing['pending']['total'] ?></div><p class="field-hint">Oldest: <?= barangay_service_duration($timing['pending']['oldest_seconds']) ?></p></article>
      </div>
      <p class="field-hint">Elapsed calendar time, including nights and resident pickup delays. Walk-in timing starts when the document is prepared, not when the person arrives. Older records without timestamps are excluded from averages. Open requests below cover all dates; archived and rejected requests are excluded from that list.</p>
      <div class="table-wrap"><table class="data-table"><caption>Oldest open requests (up to 20)</caption><thead><tr><th>Request</th><th>Document</th><th>Status</th><th>Submitted</th><th>Waiting time</th></tr></thead><tbody>
      <?php foreach ($timing['open'] as $row): ?><tr><td><?= htmlspecialchars($row['request_code']) ?></td><td><?= htmlspecialchars($row['document_type']) ?></td><td><?= htmlspecialchars($row['status']) ?></td><td><?= htmlspecialchars($row['requested_at']) ?></td><td><?= barangay_service_duration($row['age_seconds']) ?></td></tr><?php endforeach; ?>
      <?php if (!$timing['open']): ?><tr><td colspan="5">No open requests.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</section>
<script src="js/report-date-filters.js?v=<?= filemtime(__DIR__ . '/../../js/report-date-filters.js') ?>" defer></script>
<style>.service-timing .panel-head{flex-wrap:wrap;gap:12px}.service-timing-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:20px}.service-timing-filters label{display:flex;flex-direction:column;gap:6px;font-weight:600}.service-timing-filters input{font:inherit;padding:8px;min-height:42px;border:1px solid #d5e3db;border-radius:8px;box-sizing:border-box}.service-timing .value{font-size:clamp(1.1rem,2vw,1.6rem);overflow-wrap:anywhere}.service-timing caption{text-align:left;padding:12px;font-weight:600}@media(max-width:640px){.service-timing-filters label{flex:1 1 130px;min-width:0}.service-timing-filters input{width:100%;font-size:16px}.service-timing .panel-head .btn{width:100%}}</style>
