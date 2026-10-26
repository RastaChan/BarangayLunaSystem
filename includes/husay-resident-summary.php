<?php
require_once __DIR__ . '/husay-helpers.php';
$residentHusayRows = husay_public_rows((int)$loggedInUser['id'], $complaint['id']);
$he = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES);
?>
<?php if ($residentHusayRows): ?>
<td class="complaint-list-remarks" data-label="Mediation appointments">
  <?php foreach ($residentHusayRows as $session): ?>
  <article class="husay-resident-session">
    <strong><?= $he($session['status']) ?><?= $session['outcome'] ? ' · ' . $he($session['outcome']) : '' ?></strong>
    <p><?= $he(date('M j, Y · g:i A',strtotime($session['starts_at']))) ?>–<?= $he(date('g:i A',strtotime($session['ends_at']))) ?> · <?= $he($session['venue']) ?></p>
    <p>Assigned official: <?= $he($session['assigned_name']) ?></p>
    <?php foreach (['instructions','public_summary','change_reason'] as $field): if (!empty($session[$field])): ?><p><?= nl2br($he($session[$field])) ?></p><?php endif; endforeach; ?>
  </article>
  <?php endforeach; ?>
</td>
<?php endif; ?>
