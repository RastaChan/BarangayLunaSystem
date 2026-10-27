<?php
$he = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
$followupDetails = array_intersect_key($hearing, array_flip(['complaint_code','complainant_name','complainant_mobile','respondent_name','respondent_mobile','respondent_address','venue','assigned_user_id']));
$captainHusay = ($user['role'] ?? $husayActor['role'] ?? $loggedInUser['role'] ?? '') === 'admin';
$pendingHusay = $hearing['status'] === 'Pending Approval';
$activeHusay = in_array($hearing['status'], ['Scheduled','In Progress'], true);
?>
<div class="husay-detail">
  <p><strong><?= $he($hearing['complaint_code']) ?></strong> · <?= $he($hearing['subject']) ?></p>
  <span class="badge husay-status <?= in_array($hearing['status'], ['Pending Approval','Cancelled','Rescheduled','Declined'], true) ? 'badge-pending' : 'badge-active' ?>"><?= $he($hearing['status']) ?><?= $hearing['outcome'] ? ' · ' . $he($hearing['outcome']) : '' ?></span>
  <dl class="husay-details-grid husay-session-details">
    <?php foreach (['Date and time'=>date('M j, Y · g:i A',strtotime($hearing['starts_at'])).'–'.date('g:i A',strtotime($hearing['ends_at'])),'Venue'=>$hearing['venue'],'Assigned official'=>$hearing['assigned_name']] as $label=>$value): ?>
    <div <?= $label === 'Assigned official' ? 'class="husay-session-wide"' : '' ?>><dt><?= $he($label) ?></dt><dd><?= $he($value) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <div class="husay-parties-grid">
    <?php foreach (['complainant'=>'Complainant details','respondent'=>'Respondent details'] as $party=>$title): ?>
    <section class="husay-party-card" aria-labelledby="husay-party-<?= $party ?>">
      <h3 id="husay-party-<?= $party ?>"><?= $title ?></h3>
      <dl class="husay-party-fields">
        <?php $partyFields = ['Name'=>$hearing[$party.'_name'],'Mobile number'=>$hearing[$party.'_mobile'] ?: 'Not provided'];
        if ($party === 'respondent') $partyFields['Address'] = $hearing['respondent_address'] ?: 'Not provided';
        $partyFields['Attendance'] = $hearing[$party.'_attendance'];
        foreach ($partyFields as $label=>$value): ?>
        <div><dt><?= $he($label) ?></dt><dd><?= $he($value) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php endforeach; ?>
  </div>
  <dl class="husay-details-grid"><div class="husay-session-wide"><dt>Instructions</dt><dd><?= $he($hearing['instructions'] ?: 'None') ?></dd></div></dl>
  <?php if ($hearing['change_reason']): ?><p><strong>Schedule change:</strong> <?= $he($hearing['change_reason']) ?></p><?php endif; ?>
  <?php if ($hearing['public_summary']): ?><p class="husay-summary"><strong>Resident-visible update:</strong><br><?= nl2br($he($hearing['public_summary'])) ?></p><?php endif; ?>
  <?php if ($hearing['private_notes']): ?><p class="husay-summary"><strong>Private staff notes:</strong><br><?= nl2br($he($hearing['private_notes'])) ?></p><?php endif; ?>
  <?php if (!empty($hearing['decision_reason'])): ?><p><strong>Captain's decision:</strong> <?= $he($hearing['decision_reason']) ?></p><?php endif; ?>
  <?php if ($pendingHusay): ?>
  <p class="field-hint">Proposed appointment only. The Barangay Captain must approve it before the parties are notified. A rescheduling proposal does not replace the original appointment until approved.</p>
  <?php if ($captainHusay): ?>
  <form action="husay-actions.php" method="post" class="form-grid husay-session-form" data-husay-form>
    <h3>Review proposed schedule</h3>
    <input type="hidden" name="action" value="approve_husay"><input type="hidden" name="csrf" value="<?= $he($husayToken) ?>"><input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>"><input type="hidden" name="version" value="<?= (int)$hearing['version'] ?>">
    <label class="field">Review notes (optional)<textarea name="decision_reason" maxlength="1000" rows="2"></textarea></label>
    <label class="check check-left"><input type="checkbox" name="review_confirmed" value="1" required><span>As Barangay Captain, I reviewed the complaint and approve this date, time, and venue.</span></label>
    <label class="check check-left"><input type="checkbox" name="send_sms" value="1"><span>Send the approved appointment by SMS to the parties.</span></label>
    <p class="husay-error" data-husay-error role="alert" hidden></p><button type="submit" class="btn btn-primary">Approve schedule</button>
  </form>
  <details class="husay-cancel"><summary>Decline this proposal</summary>
    <form action="husay-actions.php" method="post" class="form-grid" data-husay-form>
      <input type="hidden" name="action" value="decline_husay"><input type="hidden" name="csrf" value="<?= $he($husayToken) ?>"><input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>"><input type="hidden" name="version" value="<?= (int)$hearing['version'] ?>">
      <label class="field">Reason / instructions for Staff<textarea name="decision_reason" rows="2" maxlength="1000" required></textarea></label>
      <p class="husay-error" data-husay-error role="alert" hidden></p><button type="submit" class="btn btn-danger">Decline proposal</button>
    </form>
  </details>
  <?php endif; endif; ?>
  <div class="husay-actions">
    <?php if ($hearing['status'] === 'Completed' && $hearing['outcome'] === 'For follow-up'): ?><button type="button" class="btn btn-primary" data-husay-followup='<?= $he(json_encode($followupDetails)) ?>'>Schedule follow-up hearing</button><?php endif; ?>
    <?php if (!$pendingHusay && $hearing['status'] !== 'Declined'): ?><a class="btn btn-outline" target="_blank" rel="noopener" href="husay-notice.php?id=<?= (int)$hearing['id'] ?>">Print schedule notice</a><?php endif; ?>
    <?php if ($activeHusay): ?><button type="button" class="btn btn-outline" data-husay-reschedule='<?= $he(json_encode($hearing)) ?>'><?= $captainHusay ? 'Reschedule' : 'Request rescheduling' ?></button><?php endif; ?>
  </div>
  <?php if ($activeHusay): ?>
  <form action="husay-actions.php" method="post" class="form-grid husay-session-form" data-husay-followup-details='<?= $he(json_encode($followupDetails)) ?>' data-husay-form>
    <h3><?= $captainHusay ? 'Attendance and outcome' : 'Attendance' ?></h3>
    <?php if ($captainHusay): ?><p class="field-hint">You can save an outcome anytime. For a settlement reported outside the barangay, choose Settled and explain who reported it and what was agreed in the summary. If no hearing took place, select Not applicable for attendance.</p><?php else: ?><p class="field-hint">Staff may record attendance and private notes. Only the Barangay Captain can confirm the outcome or cancel the session.</p><?php endif; ?>
    <input type="hidden" name="action" value="record_husay"><input type="hidden" name="csrf" value="<?= $he($husayToken) ?>"><input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>"><input type="hidden" name="version" value="<?= (int)$hearing['version'] ?>">
    <div class="form-grid-2">
      <?php foreach (['complainant_attendance'=>'Complainant attendance','respondent_attendance'=>'Respondent attendance'] as $key=>$label): ?>
      <label class="field"><?= $label ?><select name="<?= $key ?>"><?php foreach (['Not recorded','Present','Absent','Not applicable'] as $choice): ?><option <?= $hearing[$key] === $choice ? 'selected' : '' ?>><?= $choice ?></option><?php endforeach; ?></select></label>
      <?php endforeach; ?>
    </div>
    <?php if ($captainHusay): ?><label class="field">Outcome<select name="outcome"><option value="">Keep session in progress</option><?php foreach (['Settled','For follow-up','Referred','No agreement'] as $choice): ?><option><?= $choice ?></option><?php endforeach; ?></select></label>
    <p class="field-hint">Choose For follow-up to save this session and open the next hearing’s scheduling form. You will choose and confirm its date and time.</p>
    <label class="field">Session summary / next steps (visible to the resident)<textarea name="public_summary" maxlength="4000" rows="3"><?= $he($hearing['public_summary'] ?? '') ?></textarea></label><?php endif; ?>
    <label class="field">Private staff notes<textarea name="private_notes" maxlength="6000" rows="3"><?= $he($hearing['private_notes'] ?? '') ?></textarea></label>
    <p class="field-hint">Completing this session does not automatically resolve or close the complaint.</p>
    <p class="husay-error" data-husay-error role="alert" hidden></p><button class="btn btn-primary" type="submit">Save session</button>
  </form>
  <?php endif; ?>
  <?php if ($activeHusay && $captainHusay): ?>
  <details class="husay-cancel"><summary>Cancel this session</summary>
    <form action="husay-actions.php" method="post" class="form-grid" data-husay-form>
      <input type="hidden" name="action" value="cancel_husay"><input type="hidden" name="csrf" value="<?= $he($husayToken) ?>"><input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>"><input type="hidden" name="version" value="<?= (int)$hearing['version'] ?>">
      <label class="field">Reason (visible to the resident)<textarea name="change_reason" maxlength="1000" rows="2" required></textarea></label>
      <p class="husay-error" data-husay-error role="alert" hidden></p><button class="btn btn-danger" type="submit">Confirm cancellation</button>
    </form>
  </details>
  <?php endif; ?>
  <h3>Session history</h3>
  <ol class="husay-history">
    <?php $history = barangay_db()->prepare('SELECT h.*,u.full_name FROM husay_history h JOIN users u ON u.id=h.actor_id WHERE hearing_id=? ORDER BY h.id DESC'); $history->execute([$hearing['id']]); foreach ($history as $entry): ?>
    <li><strong><?= $he($entry['action']) ?></strong><p><?= $he($entry['description']) ?></p><small><?= $he($entry['created_at'] . ' · ' . $entry['full_name']) ?></small></li>
    <?php endforeach; ?>
  </ol>
</div>
