<?php require_once __DIR__ . '/../ui-icons.php';
$payments = $walkInPayments ?? [];
$pendingPaymentCount = count(array_filter($payments, static fn(array $payment): bool => ($payment['status'] ?? '') === 'Pending'));
$paidPaymentCount = count(array_filter($payments, static fn(array $payment): bool => ($payment['status'] ?? '') === 'Paid'));
$collectedTotal = array_reduce($payments, static fn(float $total, array $payment): float => $total + (($payment['status'] ?? '') === 'Paid' ? (float) ($payment['amount_paid'] ?? 0) : 0), 0.0);
$paymentEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$paymentIcon = static function (string $kind): string {
  $paths = [
    'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 3"/>',
    'document'=>'<path d="M14 2H6v20h14V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    'cash'=>'<ellipse cx="9" cy="5" rx="6" ry="3"/><path d="M3 5v5c0 4 12 4 12 0V5M3 10v5c0 2 4 3 7 3"/><ellipse cx="16" cy="14" rx="5" ry="3"/><path d="M11 14v5c0 4 10 4 10 0v-5"/>',
    'person'=>'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
    'eye'=>'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  ];
  return barangay_ui_icon($kind);
};
?>
<div class="page-intro"><h2>Walk-in Payments</h2><p>Review payments for walk-in documents and resident requests. Payments recorded during issuance appear here automatically.</p></div>
<div class="stats-grid payment-stats-grid payment-summary-cards">
  <?php foreach ([['clock','Pending collection',$pendingPaymentCount],['document','Payments received',$paidPaymentCount],['cash','Cash recorded','PHP ' . number_format($collectedTotal,2)]] as [$icon,$label,$value]): ?>
  <article class="stat-card"><span class="payment-summary-icon payment-summary-icon--<?= $icon ?>"><?= $paymentIcon($icon) ?></span><div><span class="label"><?= $label ?></span><strong class="value"><?= $value ?></strong></div></article>
  <?php endforeach; ?>
</div>
<section class="panel-card payment-directory" data-payment-directory>
  <div class="payment-directory-head">
    <div><h3>Payment ledger</h3><p>Fee-bearing documents create payment entries. Free documents need no payment entry.</p></div>
    <div class="payment-ledger-tools">
      <div class="payment-ledger-search"><?= $paymentIcon('search') ?><input type="search" placeholder="Search payment, resident, or receipt…" aria-label="Search payment ledger" data-payment-search></div>
      <button type="button" class="btn btn-outline" data-payment-date-toggle aria-expanded="false" aria-controls="paymentDateFilters"><?= $paymentIcon('calendar') ?>Date range</button>
    </div>
  </div>
  <div id="paymentDateFilters" class="payment-date-filters" hidden>
    <label>From<input type="date" data-payment-from aria-label="Payments from date"></label>
    <label>To<input type="date" data-payment-to aria-label="Payments to date"></label>
    <button type="button" class="btn btn-outline" data-payment-date-reset>Reset dates</button>
    <p role="status" data-payment-date-error hidden>From date must be on or before To date.</p>
  </div>
  <div class="payment-card-list">
    <?php foreach ($payments as $payment):
      $id = (int) $payment['id'];
      $reference = $payment['document_number'] ?: $payment['request_code'];
      $dateRaw = $payment['received_at'] ?: $payment['created_at'];
      $stamp = strtotime($dateRaw ?? '');
      $dateLabel = $stamp ? date('M/j/Y g:i A', $stamp) : '-';
      $nameParts = preg_split('/\s+/', trim($payment['resident_name']));
      $initials = strtoupper(substr($nameParts[0] ?? '',0,1) . (count($nameParts)>1 ? substr(end($nameParts),0,1) : ''));
      $badgeClass = ['Paid'=>'active','Pending'=>'pending','Voided'=>'open'][$payment['status']] ?? 'pending';
      $statusLabel = $payment['status'] === 'Pending' ? 'Unpaid' : $payment['status'];
    ?>
    <article class="payment-ledger-card" data-payment-card data-date="<?= $stamp ? date('Y-m-d',$stamp) : '' ?>">
      <div class="payment-card-resident"><span class="payment-resident-avatar"><?= $paymentEscape($initials) ?></span><div><h4><?= $paymentEscape($payment['resident_name']) ?></h4><small><?= $paymentEscape($payment['payment_code']) ?></small></div></div>
      <div class="payment-card-field"><span class="payment-field-icon"><?= $paymentIcon('document') ?></span><div><span class="payment-field-label">Document / request</span><strong><?= $paymentEscape($reference ?: '-') ?></strong><?php if ($payment['document_number'] && $payment['request_code']): ?><small><?= $paymentEscape($payment['request_code']) ?></small><?php endif; ?></div></div>
      <div class="payment-card-field"><span class="payment-field-icon"><?= $paymentIcon('document') ?></span><div><span class="payment-field-label">Document type</span><strong><?= $paymentEscape($payment['document_type']) ?></strong></div></div>
      <div class="payment-card-field"><span class="payment-field-icon"><?= $paymentIcon('cash') ?></span><div><span class="payment-field-label">Amount due</span><strong>PHP <?= number_format((float)$payment['amount_due'],2) ?></strong><span class="payment-field-label payment-received-label">Amount received</span><strong><?= $payment['amount_paid'] === null ? '-' : 'PHP ' . number_format((float)$payment['amount_paid'],2) ?></strong></div></div>
      <div class="payment-card-status"><span class="badge badge-<?= $badgeClass ?>"><?= $paymentEscape($statusLabel) ?></span><small><?= $paymentIcon('calendar') ?><?= $paymentEscape($dateLabel) ?></small><small><?= $paymentIcon('person') ?><?= $paymentEscape($payment['received_by'] ?: 'Not yet collected') ?></small><?php if ($payment['receipt_number']): ?><small>Receipt: <?= $paymentEscape($payment['receipt_number']) ?></small><?php endif; ?></div>
      <div class="payment-card-actions"><button type="button" class="btn btn-outline" data-modal-open="paymentDetails<?= $id ?>" aria-label="View payment <?= $paymentEscape($payment['payment_code']) ?>"><?= $paymentIcon('eye') ?><span>View</span></button><?php if ($payment['status']==='Pending'): ?><button type="button" class="btn btn-primary" data-modal-open="paymentDetails<?= $id ?>">Record payment</button><?php endif; ?></div>
    </article>
    <?php endforeach; ?>
  </div>
  <p class="payment-ledger-empty" role="status" data-payment-empty><?= $payments ? 'No payments match your search or date range.' : 'No payment records yet.' ?></p>
  <footer class="payment-directory-footer"><span data-payment-count role="status" aria-live="polite"></span><div><button type="button" class="btn btn-outline" data-payment-previous aria-label="Previous payment page">Previous</button><span data-payment-page></span><button type="button" class="btn btn-outline" data-payment-next aria-label="Next payment page">Next</button></div></footer>
</section>
<?php foreach ($payments as $payment): $id=(int)$payment['id']; ?>
<div id="paymentDetails<?= $id ?>" class="modal" aria-hidden="true" role="dialog" aria-labelledby="paymentTitle<?= $id ?>">
  <div class="modal-backdrop" data-modal-close></div><div class="modal-panel">
    <header class="modal-header"><h2 id="paymentTitle<?= $id ?>">Payment details</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body">
      <dl class="payment-detail-grid">
        <?php foreach (['Payment ID'=>$payment['payment_code'],'Resident'=>$payment['resident_name'],'Document / request'=>$payment['document_number'] ?: $payment['request_code'],'Document type'=>$payment['document_type'],'Amount due'=>'PHP '.number_format((float)$payment['amount_due'],2),'Amount received'=>$payment['amount_paid']===null ? '-' : 'PHP '.number_format((float)$payment['amount_paid'],2),'Receipt number'=>$payment['receipt_number'] ?: '-','Status'=>$payment['status']==='Pending' ? 'Unpaid' : $payment['status'],'Payment method'=>$payment['payment_method'] ?? 'Walk-in Cash','Received by'=>$payment['received_by'] ?: '-','Date'=>date('M/j/Y g:i A',strtotime($payment['received_at'] ?: $payment['created_at']))] as $label=>$value): ?>
        <div><dt><?= $label ?></dt><dd><?= $paymentEscape($value) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <?php if ($payment['status']==='Pending'): ?>
      <form action="staff-actions.php" method="post" class="payment-record-form table-edit-form">
        <input type="hidden" name="action" value="record_walk_in_payment"><input type="hidden" name="redirect" value="walk-in-payments"><input type="hidden" name="payment_id" value="<?= $id ?>">
        <label>Amount received (PHP)<input type="text" name="amount_paid" readonly value="<?= $paymentEscape(number_format((float)$payment['amount_due'],2,'.','')) ?>"></label>
        <label>Receipt number (optional)<input type="text" name="receipt_number"></label>
        <label>Notes (optional)<input type="text" name="notes"></label>
        <button type="submit" class="btn btn-primary">Record payment</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<script src="js/payment-ledger.js?v=<?= filemtime(__DIR__ . '/../../js/payment-ledger.js') ?>" defer></script>

