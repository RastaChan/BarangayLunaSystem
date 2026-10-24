<?php
require_once __DIR__ . '/sms-broadcast.php';
$broadcast = $_SESSION['sms_broadcast_' . $loggedInUser['id']] ?? null;
$inProgress = $broadcast && !empty($broadcast['confirmed']) && $broadcast['next'] < count($broadcast['recipients']);
?>
<form id="smsBroadcastForm" class="form-grid" style="max-width:800px" data-csrf="<?= $smsEscape($smsToken) ?>" data-batch="<?= $inProgress ? $smsEscape($broadcast['id']) : '' ?>">
  <fieldset <?= $inProgress ? 'disabled' : '' ?> style="border:0;padding:0;margin:0" class="form-grid">
    <div class="form-grid-2">
      <div class="field"><label for="broadcastAudience">Recipients</label><select id="broadcastAudience" name="audience"><option value="all">All registered accounts (Admin, Staff, Residents)</option><option value="residents">All registered residents</option></select></div>
      <div class="field"><label for="broadcastType">Alert category</label><select id="broadcastType" name="type"><?php foreach (sms_broadcast_types() as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label for="broadcastMessage">Alert message</label><textarea id="broadcastMessage" name="message" rows="6" required maxlength="918"></textarea></div>
    <button class="btn btn-primary" type="submit">Review broadcast</button>
  </fieldset>
  <p class="field-hint">Active accounts with valid mobile numbers only. Shared numbers receive one message. SMS charges may apply per recipient. Keep this page open while sending; return to this tab in the same session to resume.</p>
  <p id="broadcastError" role="alert"></p>
  <div id="broadcastProgress" aria-live="polite"><?= $inProgress ? 'An unfinished broadcast is available. Resume to continue with the remaining recipients.' : '' ?></div>
  <?php if ($inProgress): ?><p class="sms-message-text"><?= $smsEscape($broadcast['message']) ?></p><?php endif; ?>
  <div class="sms-actions"><button id="broadcastResume" type="button" class="btn btn-primary" <?= !$inProgress ? 'hidden' : '' ?>>Resume broadcast</button><button id="broadcastPause" type="button" class="btn btn-outline" hidden>Pause</button><a class="btn btn-outline" href="<?= $smsEscape($smsPage . '&sms_tab=history&sms_source=Broadcast') ?>">Broadcast history</a></div>
</form>
<dialog id="broadcastConfirm" style="max-width:600px;width:calc(100% - 40px);border:1px solid #ccd8d0;border-radius:8px;padding:24px">
  <h2>Confirm broadcast</h2>
  <p id="broadcastCount"></p><p id="broadcastSkipped"></p>
  <p id="broadcastText" class="sms-message-text" style="white-space:pre-wrap;overflow-wrap:anywhere"></p>
  <label class="sms-check"><input id="broadcastConsent" type="checkbox"> I have checked the message and recipient count.</label>
  <div class="sms-actions"><button id="broadcastCancel" type="button" class="btn btn-outline">Cancel</button><button id="broadcastSend" type="button" class="btn btn-primary" disabled>Send broadcast</button></div>
</dialog>
<script src="js/sms-broadcast.js?v=<?= filemtime(__DIR__ . '/../js/sms-broadcast.js') ?>"></script>
