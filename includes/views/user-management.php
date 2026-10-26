<?php require_once __DIR__ . '/../ui-icons.php';
$staffCardIcons = [
  'id' => 'M4 3h10l4 4v14H4zM14 3v5h4M8 12h6M8 16h6',
  'user' => 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  'phone' => 'M5 3h4l2 5-3 2c2 3 3 4 6 6l2-3 5 2v4c-9 2-20-9-16-16z',
  'date' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2',
];
$staffCardIcon = static function (string $kind) use ($staffCardIcons): string {
  return barangay_ui_icon($kind);
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
          <svg viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="eye" class="system-icon"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Zm0,112a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/></svg> View
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
