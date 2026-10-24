<?php $husayEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES); ?>
<div class="husay-list">
<?php foreach ($husayRows as $session): ?>
  <article class="husay-card" data-husay-date="<?= $husayEscape(substr($session['starts_at'],0,10)) ?>" data-husay-status="<?= $husayEscape($session['status']) ?>" data-husay-officer="<?= (int)$session['assigned_user_id'] ?>" data-husay-search-text="<?= $husayEscape(implode(' ', [$session['complaint_code'], $session['subject'], $session['complainant_name'], $session['respondent_name'], $session['venue'], $session['assigned_name'], $session['status'], $session['starts_at'], date('M j, Y',strtotime($session['starts_at']))])) ?>">
    <div class="husay-card-heading"><strong><?= $husayEscape($session['complaint_code']) ?></strong><span class="badge <?= in_array($session['status'], ['Cancelled','Rescheduled'], true) ? 'badge-pending' : 'badge-active' ?>"><?= $husayEscape($session['status']) ?></span></div>
    <p><strong><?= $husayEscape(date('M j, Y · g:i A', strtotime($session['starts_at']))) ?>–<?= $husayEscape(date('g:i A', strtotime($session['ends_at']))) ?></strong> · <?= $husayEscape($session['venue']) ?></p>
    <p><?= $husayEscape($session['complainant_name']) ?> / <?= $husayEscape($session['respondent_name']) ?></p>
    <p class="field-hint">Assigned official: <?= $husayEscape($session['assigned_name']) ?></p>
    <?php if (in_array($session['status'], ['Scheduled','In Progress'], true)): ?>
    <p class="field-hint">Open this session to record attendance or an outcome anytime, including a settlement reported before the hearing.</p>
    <?php endif; ?>
    <button type="button" class="btn btn-outline btn-sm" data-husay-view="<?= (int) $session['id'] ?>">View Husay session</button>
  </article>
<?php endforeach; ?>
<?php if (!$husayRows): ?><p class="empty-state">No Husay sessions scheduled yet.</p><?php endif; ?>
</div>
