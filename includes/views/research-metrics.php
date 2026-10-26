<?php
require_once __DIR__ . '/../report-metrics.php';
$reportError = '';
try { [$revenueStart, $revenueEnd] = barangay_report_dates($_GET); }
catch (InvalidArgumentException $error) { $reportError = $error->getMessage(); [$revenueStart,$revenueEnd] = barangay_report_dates([]); }
$revenue = barangay_revenue_metrics($revenueStart, $revenueEnd);
$money = static fn($value) => 'PHP ' . number_format((float) $value, 2);
?>
<section class="panel-card research-metrics">
  <div class="panel-head"><h3>Document revenue</h3><div class="action-buttons"><a class="btn btn-primary btn-sm" href="admin-export.php?<?= htmlspecialchars(http_build_query(['type'=>'revenue','format'=>'xlsx','from'=>$revenueStart,'to'=>$revenueEnd]), ENT_QUOTES) ?>">Download revenue (Excel)</a></div></div>
  <div class="panel-body">
    <?php if ($reportError): ?><p role="alert"><?= htmlspecialchars($reportError) ?></p><?php endif; ?>
    <form method="get" class="revenue-filters"><input type="hidden" name="view" value="reports-analytics"><label>From<?= barangay_report_date_field('from', $revenueStart) ?></label><label>To<?= barangay_report_date_field('to', $revenueEnd) ?></label><div class="report-filter-actions"><button class="btn btn-primary" type="submit">Apply dates</button><a class="btn btn-outline" href="?view=reports-analytics">Reset dates</a></div></form>
    <div data-live-overview="analytics-revenue">
      <div class="stats-grid">
        <article class="stat-card"><div class="label">Collected document fees</div><div class="value"><?= $money($revenue['revenue']) ?></div><div class="stat-change">Paid transactions in the selected period</div></article>
        <article class="stat-card"><div class="label">Paid transactions</div><div class="value"><?= (int) $revenue['transactions'] ?></div></article>
        <article class="stat-card"><div class="label">Cash tendered</div><div class="value"><?= $money($revenue['cash_received']) ?></div><div class="stat-change">Change due: <?= $money($revenue['change_due']) ?></div></article>
        <article class="stat-card"><div class="label">Unpaid balance</div><div class="value"><?= $money($revenue['unpaid_balance']) ?></div><div class="stat-change">All current unpaid transactions</div></article>
      </div>
      <p class="field-hint">Revenue follows the payment date, including payments for prepared documents. Unpaid and voided transactions are excluded. Free documents add no revenue.</p>
      <div class="grid-2">
        <div class="table-wrap revenue-type-wrap"><table class="data-table revenue-type-table"><colgroup><col class="revenue-type-name"><col class="revenue-type-count"><col class="revenue-type-fees"></colgroup><caption>Revenue by document type</caption><thead><tr><th>Document type</th><th>Paid</th><th>Fees collected</th></tr></thead><tbody>
        <?php foreach ($revenue['types'] as $row): ?><tr><td><div class="revenue-document-name"><span class="revenue-document-icon"><svg viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="file-text" class="system-icon"><path d="M208,88H152V32Z" opacity="0.2"/><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z"/></svg></span><span><?= htmlspecialchars($row['document_type']) ?></span></div></td><td><?= (int) $row['transactions'] ?></td><td><?= $money($row['revenue']) ?></td></tr><?php endforeach; ?>
        <?php if (!$revenue['types']): ?><tr><td colspan="3">No payments recorded in this period.</td></tr><?php endif; ?></tbody></table></div>
        <div class="revenue-daily"><h4>Daily collections</h4>
          <?php $dailyMax = max([1, ...array_column($revenue['daily'], 'revenue')]); foreach ($revenue['daily'] as $row): ?>
          <div class="metric-bar-row"><span><?= htmlspecialchars(barangay_report_date_display($row['date'])) ?></span><div class="metric-bar"><i style="width:<?= round((float) $row['revenue'] / $dailyMax * 100, 2) ?>%"></i></div><strong><?= $money($row['revenue']) ?></strong></div>
          <?php endforeach; if (!$revenue['daily']): ?><p class="field-hint">No daily collections yet.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<style>
.research-metrics{margin:1.25rem 0}.research-metrics .panel-head{gap:12px;flex-wrap:wrap}.revenue-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:20px}.revenue-filters label{display:flex;flex-direction:column;gap:6px;font-weight:600}.revenue-filters input{min-height:42px;padding:8px;border:1px solid #d5e3db;border-radius:8px;font:inherit;box-sizing:border-box}.research-metrics .value{font-size:clamp(1.15rem,2vw,1.8rem);overflow-wrap:anywhere}.research-metrics caption{text-align:left;font-weight:600;padding:12px}.metric-bar-row{display:grid;grid-template-columns:100px minmax(30px,1fr) 140px;gap:12px;align-items:center;margin:14px 0;font-size:.85rem}.metric-bar{background:#edf3ef;border-radius:5px;height:12px;overflow:hidden}.metric-bar i{display:block;background:#16803d;height:100%;transition:width .25s}.metric-bar-row strong{text-align:right}.revenue-daily{max-height:360px;overflow:auto;padding:0 8px}@media(max-width:640px){.revenue-filters label{flex:1 1 130px;min-width:0}.revenue-filters input{width:100%;font-size:16px}.metric-bar-row{grid-template-columns:82px minmax(20px,1fr) 108px;gap:8px}.research-metrics .panel-head .btn{width:100%}}
</style>

<script src="js/report-date-filters.js?v=<?= filemtime(__DIR__ . '/../../js/report-date-filters.js') ?>" defer></script>
