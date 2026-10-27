<?php
require_once __DIR__ . '/husay-helpers.php';
$todayHusayDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$todayHusayRows = array_values(array_filter(husay_rows(), static fn(array $row): bool => substr($row['starts_at'],0,10) === $todayHusayDate && !in_array($row['status'],['Pending Approval','Declined','Cancelled','Rescheduled'],true)));
usort($todayHusayRows, static fn(array $a,array $b): int => strcmp($a['starts_at'],$b['starts_at']));
$todayHusayPage = ($loggedInUser['role'] ?? '') === 'admin' ? 'dashboard.php' : 'staff-dashboard.php';
$todayHusayUrl = $todayHusayPage . '?view=husay-schedule';
$todayHusayEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES);
?>
<section class="panel-card husay-today-panel" data-live-overview="husay-today">
  <div class="panel-head"><div><h3>Today's Mediation <span class="badge badge-active"><?= count($todayHusayRows) ?></span></h3><p class="field-hint"><?= $todayHusayEscape(date('F j, Y',strtotime($todayHusayDate))) ?> · Philippine time</p></div><a class="btn btn-outline btn-sm" href="<?= $todayHusayEscape($todayHusayUrl) ?>">Open Mediation Schedule</a></div>
  <div class="panel-body husay-today-list">
    <?php foreach (array_slice($todayHusayRows,0,3) as $todaySession): ?>
    <a class="husay-today-session" href="<?= $todayHusayEscape($todayHusayUrl . '&husay=' . (int)$todaySession['id']) ?>"><strong><?= $todayHusayEscape(date('g:i A',strtotime($todaySession['starts_at']))) ?> · <?= $todayHusayEscape($todaySession['venue']) ?></strong><span><?= $todayHusayEscape($todaySession['complainant_name'] . ' / ' . $todaySession['respondent_name']) ?></span><small><?= $todayHusayEscape($todaySession['assigned_name'] . ' · ' . $todaySession['status']) ?></small></a>
    <?php endforeach; ?>
    <?php if (!$todayHusayRows): ?><p class="field-hint">No Mediation sessions today. Open the schedule to view upcoming appointments or add a walk-in.</p><?php elseif (count($todayHusayRows)>3): ?><a href="<?= $todayHusayEscape($todayHusayUrl) ?>">View all <?= count($todayHusayRows) ?> sessions for today</a><?php endif; ?>
  </div>
</section>
