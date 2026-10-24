<?php
$staffCardIcons = [
  'id' => 'M4 3h10l4 4v14H4zM14 3v5h4M8 12h6M8 16h6',
  'user' => 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  'phone' => 'M5 3h4l2 5-3 2c2 3 3 4 6 6l2-3 5 2v4c-9 2-20-9-16-16z',
  'date' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2',
];
$staffCardIcon = static function (string $kind) use ($staffCardIcons): string {
  return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' . $staffCardIcons[$kind] . '" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};
?>
<div class="page-intro">
  <h2>User Management</h2>
  <p>Manage barangay staff accounts and access control.</p>
</div>
<div class="page-toolbar" style="margin-bottom:1.25rem">
  <button type="button" class="btn btn-primary" data-modal-open="addStaffModal"><span aria-hidden="true">+</span> Add Staff Account</button>
</div>
<section class="staff-directory" aria-label="Barangay staff accounts">
  <header class="staff-directory-head">
    <div><h3><?= count($adminStaff) ?> Staff <?= count($adminStaff) === 1 ? 'Member' : 'Members' ?></h3><p>Here are the registered staff accounts in the barangay system.</p></div>
    <div class="staff-directory-sort"><select aria-label="Sort staff accounts" data-staff-sort>
      <option value="latest">Latest Created</option><option value="oldest">Oldest Created</option><option value="name">Name A–Z</option>
    </select></div>
  </header>
  <div class="staff-directory-list" data-staff-directory>
    <?php foreach ($adminStaff as $staff):
      $nameParts = preg_split('/\s+/u', trim($staff['name']), -1, PREG_SPLIT_NO_EMPTY);
      $initials = mb_substr($nameParts[0] ?? 'S', 0, 1) . (count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : '');
    ?>
    <article class="staff-account-card" data-staff-card data-staff-name="<?= htmlspecialchars($staff['name'], ENT_QUOTES) ?>" data-staff-created="<?= htmlspecialchars($staff['created'], ENT_QUOTES) ?>">
      <header class="staff-account-card-head">
        <div class="staff-account-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper($initials)) ?></div>
        <div class="staff-account-identity"><h4><?= htmlspecialchars($staff['name']) ?></h4><p>Barangay Staff</p></div>
        <span class="badge <?= $staff['status'] === 'Active' ? 'badge-active' : 'badge-inactive' ?>"><span aria-hidden="true">●</span> <?= htmlspecialchars($staff['status']) ?></span>
        <button type="button" class="btn btn-primary staff-account-view" data-view-staff data-json='<?= htmlspecialchars(json_encode($staff), ENT_QUOTES) ?>' aria-label="View <?= htmlspecialchars($staff['name'], ENT_QUOTES) ?>">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg> View
        </button>
      </header>
      <dl class="staff-account-info">
        <?php foreach ([['id','Staff ID',$staff['id']],['user','Username',$staff['username']],['phone','Mobile',$staff['mobile'] ?: '—'],['date','Date created',$staff['created']]] as [$kind,$label,$value]): ?>
        <div><span class="staff-account-info-icon"><?= $staffCardIcon($kind) ?></span><div><dt><?= $label ?></dt><dd><?= htmlspecialchars($value) ?></dd></div></div>
        <?php endforeach; ?>
      </dl>
    </article>
    <?php endforeach; ?>
    <?php if (!$adminStaff): ?><p class="staff-directory-empty">No staff accounts yet. Select Add Staff Account to create one.</p><?php endif; ?>
  </div>
</section>
<script src="js/staff-directory.js?v=<?= filemtime(__DIR__ . '/../../js/staff-directory.js') ?>" defer></script>
