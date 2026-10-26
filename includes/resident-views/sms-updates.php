<?php require_once __DIR__ . '/../ui-icons.php';
$smsEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$smsIcon = static function (string $kind): string {
    $paths = [
        'message' => '<path d="M4 4h16v12H8l-4 4Z"/><path d="M8 8h8M8 12h5"/>',
        'released' => '<path d="M14 2H6v20h14V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'pickup' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'received' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    ];
    return barangay_ui_icon($kind);
};
?>
<section class="panel-card resident-sms-history" data-sms-history>
  <header class="resident-sms-heading">
    <span class="resident-sms-heading-icon"><?= $smsIcon('message') ?></span>
    <div><h2>SMS Notification History</h2><p>Updates sent to your registered contact number.</p></div>
  </header>
  <div class="resident-sms-filters">
    <div class="resident-sms-search"><?= $smsIcon('search') ?><input type="search" aria-label="Search SMS messages" placeholder="Search message content…" data-sms-search></div>
    <select aria-label="Filter SMS by status" data-sms-status><option value="">All statuses</option><option>Sent</option><option>Pending</option><option>Failed</option></select>
    <select aria-label="Filter SMS by date" data-sms-dates><option value="">All dates</option><option value="today">Today</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option></select>
  </div>
  <div class="resident-sms-list">
    <?php foreach ($smsUpdates as $sms):
        $message = (string) $sms['message'];
        $kind = 'message'; $title = 'SMS Update';
        if (preg_match('/\breleased\b/i', $message)) { $kind = 'released'; $title = 'Document Released'; }
        elseif (preg_match('/ready (?:for|to) pick\s*up/i', $message)) { $kind = 'pickup'; $title = 'Ready for Pickup'; }
        elseif (preg_match('/(?:received .*request|request (?:has been )?received)/i', $message)) { $kind = 'received'; $title = 'Request Received'; }
        $stamp = strtotime($sms['date']);
        $statusClass = ['Sent'=>'sent', 'Pending'=>'pending', 'Failed'=>'failed'][$sms['status']] ?? 'pending';
    ?>
    <article class="resident-sms-row resident-sms-row--<?= $kind ?>" data-sms-row data-status="<?= $smsEscape($sms['status']) ?>" data-date="<?= $stamp ? date('Y-m-d', $stamp) : '' ?>">
      <span class="resident-sms-type-icon"><?= $smsIcon($kind) ?></span>
      <div class="resident-sms-content"><h3><?= $title ?></h3><p data-sms-message><?= $smsEscape($message) ?></p></div>
      <div class="resident-sms-date"><?= $smsIcon('calendar') ?><time><?= $stamp ? date('M/j/Y', $stamp) . '<small>' . date('g:i A', $stamp) . '</small>' : $smsEscape($sms['date']) ?></time></div>
      <span class="resident-sms-badge resident-sms-badge--<?= $statusClass ?>"><?= $smsEscape($sms['status']) ?></span>
      <button type="button" class="btn btn-outline resident-sms-view" data-modal-open="residentSmsViewModal" data-sms-view><?= $smsIcon('eye') ?>View</button>
    </article>
    <?php endforeach; ?>
  </div>
  <p class="resident-sms-empty" role="status" data-sms-empty><?= $smsUpdates ? 'No messages match your search or filters.' : 'No SMS updates yet. Messages will appear here when the barangay sends you an update.' ?></p>
</section>
<div id="residentSmsViewModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="residentSmsViewTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="residentSmsViewTitle">SMS Update</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body"><p class="field-hint" data-sms-view-meta></p><p class="resident-sms-full-message" data-sms-view-message></p></div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>
<script src="js/resident-sms-history.js?v=<?= filemtime(__DIR__ . '/../../js/resident-sms-history.js') ?>" defer></script>
