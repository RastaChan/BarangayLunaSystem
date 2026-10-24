<?php
$he = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
$followupDetails = array_intersect_key($hearing, array_flip(['complaint_code','complainant_name','complainant_mobile','respondent_name','respondent_mobile','respondent_address','venue','assigned_user_id']));
$activeHusay = in_array($hearing['status'], ['Scheduled','In Progress'], true);
?>
<div class="husay-detail">
  <p><strong><?= $he($hearing['complaint_code']) ?></strong> · <?= $he($hearing['subject']) ?></p>
  <span class="badge badge-active"><?= $he($hearing['status']) ?><?= $hearing['outcome'] ? ' · ' . $he($hearing['outcome']) : '' ?></span>
  <dl class="husay-details-grid">
    <?php foreach (['Date and time'=>date('M j, Y · g:i A',strtotime($hearing['starts_at'])).'–'.date('g:i A',strtotime($hearing['ends_at'])),'Venue'=>$hearing['venue'],'Assigned official'=>$hearing['assigned_name'],'Complainant'=>$hearing['complainant_name'],'Complainant mobile'=>$hearing['complainant_mobile'] ?: 'Not provided','Respondent'=>$hearing['respondent_name'],'Respondent mobile'=>$hearing['respondent_mobile'] ?: 'Not provided','Respondent address'=>$hearing['respondent_address'] ?: 'Not provided','Instructions'=>$hearing['instructions'] ?: 'None','Complainant attendance'=>$hearing['complainant_attendance'],'Respondent attendance'=>$hearing['respondent_attendance']] as $label=>$value): ?>
    <div><dt><?= $he($label) ?></dt><dd><?= $he($value) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php if ($hearing['change_reason']): ?><p><strong>Schedule change:</strong> <?= $he($hearing['change_reason']) ?></p><?php endif; ?>
  <?php if ($hearing['public_summary']): ?><p class="husay-summary"><strong>Resident-visible update:</strong><br><?= nl2br($he($hearing['public_summary'])) ?></p><?php endif; ?>
  <?php if ($hearing['private_notes']): ?><p class="husay-summary"><strong>Private staff notes:</strong><br><?= nl2br($he($hearing['private_notes'])) ?></p><?php endif; ?>
  <div class="husay-actions">
    <?php if ($hearing['status'] === 'Completed' && $hearing['outcome'] === 'For follow-up'): ?><button type="button" class="btn btn-primary" data-husay-followup='<?= $he(json_encode($followupDetails)) ?>'>Schedule follow-up hearing</button><?php endif; ?>
    <a class="btn btn-outline" target="_blank" rel="noopener" href="husay-notice.php?id=<?= (int)$hearing['id'] ?>">Print schedule notice</a>
    <?php if ($activeHusay): ?><button type="button" class="btn btn-outline" data-husay-reschedule='<?= $he(json_encode($hearing)) ?>'>Reschedule</button><?php endif; ?>
  </div>
  <?php if ($activeHusay): ?>
  <form action="husay-actions.php" method="post" class="form-grid husay-session-form" data-husay-followup-details='<?= $he(json_encode($followupDetails)) ?>' data-husay-form>
    <h3>Attendance and outcome</h3>
    <p class="field-hint">You can save an outcome anytime. For a settlement reported outside the barangay, choose Settled and explain who reported it and what was agreed in the summary. If no hearing took place, select Not applicable for attendance.</p>
    <input type="hidden" name="action" value="record_husay"><input type="hidden" name="csrf" value="<?= $he($husayToken) ?>"><input type="hidden" name="hearing_id" value="<?= (int)$hearing['id'] ?>"><input type="hidden" name="version" value="<?= (int)$hearing['version'] ?>">
    <div class="form-grid-2">
      <?php foreach (['complainant_attendance'=>'Complainant attendance','respondent_attendance'=>'Respondent attendance'] as $key=>$label): ?>
      <label class="field"><?= $label ?><select name="<?= $key ?>"><?php foreach (['Not recorded','Present','Absent','Not applicable'] as $choice): ?><option <?= $hearing[$key] === $choice ? 'selected' : '' ?>><?= $choice ?></option><?php endforeach; ?></select></label>
      <?php endforeach; ?>
    </div>
    <label class="field">Outcome<select name="outcome"><option value="">Keep session in progress</option><?php foreach (['Settled','For follow-up','Referred','No agreement'] as $choice): ?><option><?= $choice ?></option><?php endforeach; ?></select></label>
    <p class="field-hint">Choose For follow-up to save this session and open the next hearing’s scheduling form. You will choose and confirm its date and time.</p>
    <label class="field">Session summary / next steps (visible to the resident)<textarea name="public_summary" maxlength="4000" rows="3"><?= $he($hearing['public_summary'] ?? '') ?></textarea></label>
    <label class="field">Private staff notes<textarea name="private_notes" maxlength="6000" rows="3"><?= $he($hearing['private_notes'] ?? '') ?></textarea></label>
    <p class="field-hint">Completing this session does not automatically resolve or close the complaint.</p>
    <p class="husay-error" data-husay-error role="alert" hidden></p><button class="btn btn-primary" type="submit">Save session</button>
  </form>
  <?php endif; ?>
  <?php if ($activeHusay): ?>
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
