<?php require_once __DIR__ . '/../ui-icons.php'; include __DIR__ . '/../resident-management-nav.php'; ?>
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
  return barangay_ui_icon($kind);
};
?>
<section class="staff-directory resident-directory" aria-label="Registered residents" data-live-search-results="residents">
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
            <svg viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="eye" class="system-icon"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Zm0,112a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/></svg> View
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
    <?php if ($residentPage > 1): ?><a class="btn btn-outline" data-live-search-page href="<?= htmlspecialchars($residentPageLink($residentPage - 1), ENT_QUOTES) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $residentPage ?> of <?= $residentPageCount ?></span>
    <?php if ($residentPage < $residentPageCount): ?><a class="btn btn-outline" data-live-search-page href="<?= htmlspecialchars($residentPageLink($residentPage + 1), ENT_QUOTES) ?>">Next</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
