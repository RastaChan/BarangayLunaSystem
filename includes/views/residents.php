<?php include __DIR__ . '/../resident-management-nav.php'; ?>
<?php
$locationView = $locationView ?? 'residents';
require __DIR__ . '/../resident-location-controls.php';
$residentCount = count($adminResidents);
$residentPageCount = max(1, (int) ceil($residentCount / 20));
$residentPage = min($residentPageCount, max(1, (int) ($_GET['resident_page'] ?? 1)));
$residentPageRows = array_slice($adminResidents, ($residentPage - 1) * 20, 20);
$residentPageLink = static function (int $page) use ($locationView): string {
  $query = array_intersect_key($_GET, array_flip(['resident_search', 'account_filter', 'district_filter', 'purok_filter']));
  $query['view'] = $locationView;
  $query['resident_page'] = $page;
  return '?' . http_build_query($query);
};
$residentCardIcons = [
  'id' => 'M3 5h18v14H3zM7 10h3v3H7zM14 10h4M14 14h4M7 16h3',
  'user' => 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  'clock' => 'M12 8v4l3 2M3 12a9 9 0 1 0 9-9 9 9 0 0 0-9 9',
  'date' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2',
];
$residentCardIcon = static function (string $kind) use ($residentCardIcons): string {
  return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' . $residentCardIcons[$kind] . '" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};
?>
<section class="staff-directory resident-directory" aria-label="Registered residents">
  <header class="staff-directory-head">
    <div><h3>Registered residents (<?= number_format($residentCount) ?>)</h3><p>Here are the resident accounts matching your selected filters.</p></div>
    <?php if ($residentCount > 0): ?><span class="resident-directory-count">Showing <?= ($residentPage - 1) * 20 + 1 ?>–<?= min($residentPage * 20, $residentCount) ?> of <?= number_format($residentCount) ?></span><?php endif; ?>
  </header>
  <div class="staff-directory-list">
    <?php foreach ($residentPageRows as $r):
      $r['district'] = $locationLists['districts'][$locationAssignments[$r['id']]['district_id'] ?? 0]['name'] ?? 'Unassigned';
      $r['purok_chairman'] = $locationLists['puroks'][$locationAssignments[$r['id']]['purok_id'] ?? 0]['chairman'] ?? '';
      $nameParts = preg_split('/\s+/u', trim($r['name']), -1, PREG_SPLIT_NO_EMPTY);
      $initials = mb_substr($nameParts[0] ?? 'R', 0, 1) . (count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : '');
      $needsInactivityReview = $r['status'] === 'Inactive' && ($r['inactive_reason'] ?? '') === 'six_month_inactivity';
      $lastUsed = !empty($r['last_used_at']) ? date('M j, Y g:i A', strtotime($r['last_used_at'])) : 'No recorded use';
    ?>
    <article class="staff-account-card resident-account-card">
      <header class="staff-account-card-head">
        <div class="staff-account-avatar resident-account-avatar" aria-hidden="true">
          <span><?= htmlspecialchars(mb_strtoupper($initials)) ?></span>
          <?php if (!empty($r['profile_photo_path'])): ?><img src="<?= htmlspecialchars($r['profile_photo_path'], ENT_QUOTES) ?>" alt="" loading="lazy" onerror="this.remove();"><?php endif; ?>
        </div>
        <div class="staff-account-identity"><h4><?= htmlspecialchars($r['name']) ?></h4><p><?= htmlspecialchars($r['district']) ?> / <?= htmlspecialchars($r['purok'] ?: 'Unassigned purok') ?></p><p>Chairman: <?= htmlspecialchars($r['purok_chairman'] ?: 'Not yet assigned') ?></p></div>
        <div class="resident-account-status">
          <span class="badge badge-<?= $r['status'] === 'Active' ? 'active' : ($r['status'] === 'Inactive' ? 'inactive' : 'pending') ?>"><span aria-hidden="true">●</span> <?= htmlspecialchars($r['status']) ?></span>
          <?php if ($needsInactivityReview): ?><p class="field-hint">6 months without activity<br>Residency review needed</p><?php endif; ?>
        </div>
        <div class="resident-card-actions">
          <button type="button" class="btn btn-primary staff-account-view" data-view-resident data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>' aria-label="View <?= htmlspecialchars($r['name'], ENT_QUOTES) ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg> View
          </button>
          <?php if (empty($residentManagementStaff)): ?>
          <button type="button" class="btn btn-outline" data-reset-password data-id="<?= htmlspecialchars($r['id'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>" data-redirect="residents">Reset password</button>
          <?php endif; ?>
          <?php if ($needsInactivityReview): ?>
          <button type="button" class="btn btn-outline" data-residency-review data-resident-code="<?= htmlspecialchars($r['id'], ENT_QUOTES) ?>" data-resident-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>">Review residency</button>
          <?php elseif (empty($residentManagementStaff)): ?>
          <form action="admin-actions.php" method="post" class="inline-action-form">
            <input type="hidden" name="action" value="set_user_status"><input type="hidden" name="redirect" value="residents">
            <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
            <input type="hidden" name="status" value="<?= $r['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
            <button type="submit" class="btn <?= $r['status'] === 'Active' ? 'btn-danger' : 'btn-primary' ?>"><?= $r['status'] === 'Active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </header>
      <dl class="staff-account-info">
        <?php foreach ([['id','Resident ID',$r['id']],['user','Username',$r['username']],['clock','Last used',$lastUsed],['date','Registered',$r['registered']]] as [$kind,$label,$value]): ?>
        <div><span class="staff-account-info-icon"><?= $residentCardIcon($kind) ?></span><div><dt><?= $label ?></dt><dd><?= htmlspecialchars($value ?? '') ?>
          <?php if ($kind === 'clock' && !empty($r['reactivated_at'])): ?><small class="field-hint">Reactivated <?= htmlspecialchars(date('M j, Y', strtotime($r['reactivated_at']))) ?></small><?php endif; ?>
        </dd></div></div>
        <?php endforeach; ?>
      </dl>
    </article>
    <?php endforeach; ?>
    <?php if (!$adminResidents): ?><p class="staff-directory-empty">No residents match the selected filters.</p><?php endif; ?>
  </div>
  <?php if ($residentPageCount > 1): ?>
  <nav class="resident-directory-pages" aria-label="Resident list pages">
    <?php if ($residentPage > 1): ?><a class="btn btn-outline" href="<?= htmlspecialchars($residentPageLink($residentPage - 1), ENT_QUOTES) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $residentPage ?> of <?= $residentPageCount ?></span>
    <?php if ($residentPage < $residentPageCount): ?><a class="btn btn-outline" href="<?= htmlspecialchars($residentPageLink($residentPage + 1), ENT_QUOTES) ?>">Next</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
