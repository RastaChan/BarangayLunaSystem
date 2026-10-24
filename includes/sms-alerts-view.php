<?php
require_once __DIR__ . '/sms-workspace.php';
sms_workspace_schema();
barangay_ensure_document_fee_schema();
$smsAdmin = $loggedInUser['role'] === 'admin';
$smsPage = $smsAdmin ? 'dashboard.php?view=sms-alerts' : 'staff-dashboard.php?view=staff-sms-alerts';
$smsTab = in_array($_GET['sms_tab'] ?? '', ['send', 'broadcast', 'automatic', 'history'], true) ? $_GET['sms_tab'] : 'send';
$_SESSION['sms_csrf'] = $_SESSION['sms_csrf'] ?? bin2hex(random_bytes(32));
$smsToken = $_SESSION['sms_csrf'];
$smsSettings = SMSAlertsHelper::getSettings();
$smsResidents = barangay_db()->query("SELECT user_code, full_name, mobile FROM users WHERE role = 'resident' AND status = 'Active' ORDER BY full_name")->fetchAll();
$smsRequests = barangay_db()->query("SELECT d.request_code, d.resident_name, d.document_type, d.status, d.fee_amount,
  COALESCE(NULLIF(u.mobile, ''), d.contact_mobile) AS mobile, u.user_code
  FROM document_requests d LEFT JOIN users u ON u.id = d.resident_user_id
  WHERE d.archived_at IS NULL AND (u.id IS NULL OR u.status = 'Active') ORDER BY d.requested_at DESC")->fetchAll();
foreach ($smsRequests as &$smsRequest) {
  $smsRequest['ready_message'] = sms_render_automatic('ready', [
    'name' => $smsRequest['resident_name'], 'type' => $smsRequest['document_type'],
    'reference' => $smsRequest['request_code'], 'fee' => $smsRequest['fee_amount'],
  ], $smsSettings);
}
unset($smsRequest);
$smsDraft = $_SESSION['sms_draft'] ?? [];
unset($_SESSION['sms_draft']);
$smsEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$smsFlash = $_SESSION['sms_flash'] ?? null;
unset($_SESSION['sms_flash']);
?>
<link rel="stylesheet" href="css/sms-workspace.css?v=<?= filemtime(__DIR__ . '/../css/sms-workspace.css') ?>">
<div class="page-intro"><h2>SMS Alerts</h2></div>

<?php if ($smsFlash): ?><div hidden data-action-toast="<?= $smsEscape($smsFlash[0]) ?>"><?= $smsEscape($smsFlash[1]) ?></div><?php endif; ?>
<nav class="sms-tabs" aria-label="SMS views">
  <?php foreach (['send' => 'Send Message', 'broadcast' => 'Broadcast Alerts', 'automatic' => 'Automatic Alerts', 'history' => 'SMS History'] as $key => $title): ?>
  <a href="<?= $smsEscape($smsPage . '&sms_tab=' . $key) ?>" <?= $smsTab === $key ? 'aria-current="page"' : '' ?>><?= $title ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($smsTab === 'broadcast'): ?>
<?php require __DIR__ . '/sms-broadcast-view.php'; ?>
<?php elseif ($smsTab === 'send'): ?>
<form method="post" action="sms-actions.php" class="sms-compose" id="smsCompose">
  <input type="hidden" name="action" value="send">
  <input type="hidden" name="csrf" value="<?= $smsEscape($smsToken) ?>">
  <input type="hidden" name="nonce" value="<?= bin2hex(random_bytes(16)) ?>">
  <input type="hidden" name="sender_prefix" value="<?= $smsEscape(SMSAlertsHelper::cleanSenderName($smsSettings['sms_sender_name'] ?? 'BRGY LUNA')) ?>">
  <input type="hidden" name="request_status" id="smsRequestStatus">
  <input type="hidden" name="request_fee" id="smsRequestFee">
  <div class="form-grid">
    <div class="field"><label for="smsTemplate">Message template</label><select name="template" id="smsTemplate">
      <?php foreach (sms_templates() as $key => $title): ?><option value="<?= $key ?>" <?= ($smsDraft['template'] ?? 'custom') === $key ? 'selected' : '' ?>><?= $title ?></option><?php endforeach; ?>
    </select></div>
    <div id="smsRequestFields" hidden class="form-grid">
      <div class="field"><label for="smsRequestSearch">Find document request</label><input id="smsRequestSearch" type="search" placeholder="Resident, document, or reference"></div>
      <div class="field"><label for="smsRequest">Document request</label><select id="smsRequest" name="request_code"><option value="">Select document request</option>
        <?php foreach ($smsRequests as $request): ?><option value="<?= $smsEscape($request['request_code']) ?>" <?= ($smsDraft['request_code'] ?? '') === $request['request_code'] ? 'selected' : '' ?>><?= $smsEscape($request['request_code'] . ' - ' . $request['resident_name'] . ' - ' . $request['document_type'] . ' (' . $request['status'] . ')') ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div id="smsResidentFields" class="form-grid">
      <div class="field"><label for="smsResidentSearch">Find resident</label><input id="smsResidentSearch" type="search" placeholder="Name or resident ID"></div>
      <div class="field"><label for="smsResident">Recipient</label><select id="smsResident" name="resident_code"><option value="">Enter recipient manually</option>
        <?php foreach ($smsResidents as $resident): ?><option value="<?= $smsEscape($resident['user_code']) ?>" <?= ($smsDraft['resident_code'] ?? '') === $resident['user_code'] ? 'selected' : '' ?>><?= $smsEscape($resident['full_name'] . ' - ' . $resident['user_code']) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="form-grid-2">
      <div class="field"><label for="smsName">Recipient name</label><input id="smsName" name="recipient_name" maxlength="150" required value="<?= $smsEscape($smsDraft['recipient_name'] ?? '') ?>"></div>
      <div class="field"><label for="smsMobile">Mobile number</label><input id="smsMobile" name="recipient_mobile" type="tel" required placeholder="09XX XXX XXXX" value="<?= $smsEscape($smsDraft['recipient_mobile'] ?? '') ?>"></div>
    </div>
    <div class="field"><label for="smsMessage">Message</label><textarea id="smsMessage" name="message" rows="7" required><?= $smsEscape($smsDraft['message'] ?? '') ?></textarea></div>
  </div>
  <section class="sms-preview" aria-labelledby="smsPreviewTitle">
    <h3 id="smsPreviewTitle">Message preview</h3>
    <dl><dt>To</dt><dd id="smsPreviewRecipient">No recipient selected</dd><dt>Document fee</dt><dd id="smsPreviewFee">Not applicable</dd></dl>
    <p id="smsPreviewText" class="sms-message-text"></p>
    <p id="smsLength" class="field-hint" aria-live="polite"></p>
    <p id="smsComposeError" role="alert" class="sms-error"></p>
    <button type="button" id="smsReview" class="btn btn-primary">Review &amp; send</button>
  </section>
  <dialog id="smsConfirm" class="sms-dialog">
    <h3>Send this SMS?</h3>
    <p id="smsConfirmRecipient"></p><p id="smsConfirmMessage" class="sms-message-text"></p>
    <label class="sms-check"><input type="checkbox" name="confirmed" value="1" id="smsConfirmed"> Recipient and message are correct</label>
    <div class="sms-actions"><button type="button" class="btn btn-outline" id="smsCancel">Back</button><button type="submit" class="btn btn-primary">Send SMS</button></div>
  </dialog>
</form>
<script id="smsComposeData" type="application/json"><?= json_encode(['residents' => $smsResidents, 'requests' => $smsRequests, 'prefix' => SMSAlertsHelper::cleanSenderName($smsSettings['sms_sender_name'] ?? 'BRGY LUNA'), 'draft' => (bool) $smsDraft, 'draftStatus' => $smsDraft['request_status'] ?? null, 'draftFee' => $smsDraft['request_fee'] ?? null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="js/sms-workspace.js?v=<?= filemtime(__DIR__ . '/../js/sms-workspace.js') ?>"></script>
<?php elseif ($smsTab === 'automatic'): ?>
<section class="sms-automatic">
  <h3>Document notifications</h3>
  <p>SMS service: <strong><?= ($smsSettings['sms_enabled'] ?? '1') === '1' ? 'Enabled' : 'Disabled' ?></strong></p>
  <form method="post" action="sms-actions.php" class="form-grid">
    <input type="hidden" name="action" value="settings"><input type="hidden" name="csrf" value="<?= $smsEscape($smsToken) ?>">
    <?php foreach (sms_automatic_defaults() as $event => $default): ?>
    <div class="field" data-auto-sms-editor data-prefix="<?= $smsEscape(SMSAlertsHelper::cleanSenderName($smsSettings['sms_sender_name'] ?? 'BRGY LUNA')) ?>">
      <label class="sms-rule"><input type="checkbox" name="<?= $event ?>" value="1" <?= ($smsSettings['sms_auto_' . $event] ?? '1') === '1' ? 'checked' : '' ?>><strong><?= ['received'=>'Request received','rejected'=>'Request rejected','ready'=>'Ready for pickup','released'=>'Document released'][$event] ?></strong></label>
      <label for="smsTemplate_<?= $event ?>">Message</label>
      <textarea id="smsTemplate_<?= $event ?>" name="templates[<?= $event ?>]" rows="5" maxlength="600" required data-auto-sms-template><?= $smsEscape($smsSettings['sms_template_' . $event] ?? $default) ?></textarea>
      <div class="field-hint"><code>{{resident_name}}</code> &middot; <code>{{document_type}}</code> &middot; <code>{{reference}}</code> &middot; <code>{{fee}}</code></div>
      <details><summary>Sample preview</summary><p data-auto-sms-preview style="white-space:pre-wrap;overflow-wrap:anywhere"></p></details>
      <button type="button" class="btn btn-outline" data-auto-sms-default="<?= $smsEscape($default) ?>">Restore default message</button>
    </div>
    <?php endforeach; ?>
    <div class="sms-actions"><button class="btn btn-primary" type="submit">Save preferences</button><?php if ($smsAdmin): ?><a class="btn btn-outline" href="dashboard.php?view=system-settings">SMS connection settings</a><?php endif; ?></div>
  </form>
</section>
<script src="js/sms-automatic.js?v=<?= filemtime(__DIR__ . '/../js/sms-automatic.js') ?>"></script>
<?php else:
  $filter = in_array($_GET['sms_source'] ?? '', ['Manual', 'Automatic', 'Broadcast', 'Legacy'], true) ? $_GET['sms_source'] : '';
  $state = in_array($_GET['sms_state'] ?? '', ['Accepted', 'Failed', 'Unknown', 'Submitting'], true) ? $_GET['sms_state'] : '';
  $search = substr(trim((string) ($_GET['sms_search'] ?? '')), 0, 100);
  $pageNumber = max(1, (int) ($_GET['sms_page'] ?? 1));
  $where = []; $params = [];
  if ($filter !== '') { $where[] = 's.source = :source'; $params[':source'] = $filter; }
  if ($state !== '') { $where[] = 's.delivery_state = :state'; $params[':state'] = $state; }
  if ($search !== '') { $where[] = '(s.recipient_name LIKE :name OR s.recipient_mobile LIKE :mobile OR s.request_code LIKE :request)'; foreach ([':name', ':mobile', ':request'] as $key) $params[$key] = '%' . $search . '%'; }
  $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
  $count = barangay_db()->prepare('SELECT COUNT(*) FROM sms_logs s' . $condition); $count->execute($params);
  $total = (int) $count->fetchColumn(); $pages = max(1, (int) ceil($total / 25)); $pageNumber = min($pageNumber, $pages);
  $query = barangay_db()->prepare('SELECT s.*, u.full_name AS sender, child.id AS retry_id, TIMESTAMPDIFF(SECOND, s.created_at, NOW()) AS age_seconds FROM sms_logs s LEFT JOIN users u ON u.id = s.sent_by LEFT JOIN sms_logs child ON child.delivery_key = CONCAT("retry:", s.id)' . $condition . ' ORDER BY s.id DESC LIMIT 25 OFFSET ' . (($pageNumber - 1) * 25));
  $query->execute($params); $logs = $query->fetchAll();
?>
<form method="get" class="sms-filters">
  <input type="hidden" name="view" value="<?= $smsAdmin ? 'sms-alerts' : 'staff-sms-alerts' ?>"><input type="hidden" name="sms_tab" value="history">
  <div class="field"><label for="smsSearch">Recipient or reference</label><input type="search" id="smsSearch" name="sms_search" value="<?= $smsEscape($search) ?>"></div>
  <div class="field"><label for="smsSource">Source</label><select id="smsSource" name="sms_source"><option value="">All sources</option><?php foreach (['Manual', 'Automatic', 'Broadcast', 'Legacy'] as $source): ?><option <?= $filter === $source ? 'selected' : '' ?>><?= $source ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="smsState">Status</label><select id="smsState" name="sms_state"><option value="">All statuses</option><?php foreach (['Accepted', 'Failed', 'Unknown', 'Submitting'] as $status): ?><option <?= $state === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
  <button type="submit" class="btn btn-outline">Filter</button>
</form>
<p class="field-hint">Accepted: delivery not confirmed. Unknown or stalled Submitting: check httpSMS before resending. Legacy: older, unverified delivery record.</p>
<div class="table-wrap"><table class="data-table sms-history"><thead><tr><th>Recipient</th><th>Category / Source</th><th>Message</th><th>Status</th><th>Sent by / Date</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($logs as $log): ?>
  <tr><td data-label="Recipient"><?= $smsEscape($log['recipient_name']) ?><small><?= $smsEscape($log['recipient_mobile']) ?></small></td>
    <td data-label="Category / source"><?= $smsEscape($log['message_type']) ?><small><?= $smsEscape($log['source']) ?><?= $log['retry_of'] ? ' (retry of #' . (int) $log['retry_of'] . ')' : '' ?></small></td>
    <td data-label="Message"><details><summary>View message #<?= (int) $log['id'] ?></summary><p class="sms-message-text"><?= $smsEscape($log['message']) ?></p><?php if ($log['provider_id']): ?><small>Provider reference: <?= $smsEscape($log['provider_id']) ?></small><?php endif; ?></details></td>
    <td data-label="Status"><span class="badge <?= $log['delivery_state'] === 'Failed' ? 'badge-rejected' : 'badge-pending' ?>"><?= $smsEscape($log['delivery_state'] ?: ('Legacy ' . $log['status'])) ?></span><?php if ($log['failure_reason']): ?><small><?= $smsEscape($log['failure_reason']) ?></small><?php endif; ?></td>
    <td data-label="Sent by / date"><?= $smsEscape($log['sender'] ?: 'Former account') ?><small><?= $smsEscape($log['created_at']) ?></small></td>
    <td data-label="Action"><?php if ($log['delivery_state'] === 'Failed' && !$log['retry_id']): ?>
      <details><summary>Retry</summary><form action="sms-actions.php" method="post" class="sms-retry">
        <input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?= (int) $log['id'] ?>"><input type="hidden" name="csrf" value="<?= $smsEscape($smsToken) ?>">
        <p><?= $smsEscape($log['recipient_name'] . ' - ' . $log['recipient_mobile']) ?></p><p class="sms-message-text"><?= $smsEscape($log['message']) ?></p>
        <label class="sms-check"><input type="checkbox" name="confirmed" value="1" required> Resend this exact message</label><button type="submit" class="btn btn-outline" <?= (int) $log['age_seconds'] < 60 ? 'disabled' : '' ?>>Retry SMS</button>
        <?php if ((int) $log['age_seconds'] < 60): ?><small>Retry available after one minute.</small><?php endif; ?>
      </form></details>
      <?php elseif ($log['retry_id']): ?>Retried as #<?= (int) $log['retry_id'] ?><?php else: ?>-<?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="6">No SMS records match these filters.</td></tr><?php endif; ?>
</tbody></table></div>
<nav class="sms-actions" aria-label="History pages"><span><?= $total ?> records | Page <?= $pageNumber ?> of <?= $pages ?></span><?php foreach (['Previous' => $pageNumber - 1, 'Next' => $pageNumber + 1] as $label => $number): if ($number < 1 || $number > $pages) continue; ?><a class="btn btn-outline" href="<?= $smsEscape($smsPage . '&' . http_build_query(['sms_tab' => 'history', 'sms_page' => $number, 'sms_source' => $filter, 'sms_state' => $state, 'sms_search' => $search])) ?>"><?= $label ?></a><?php endforeach; ?></nav>
<?php endif; ?>
