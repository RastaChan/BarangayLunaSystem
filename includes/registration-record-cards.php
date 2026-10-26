<?php require_once __DIR__ . '/ui-icons.php';
$registrationSearch = trim((string) ($_GET['registration_search'] ?? ''));
$registrationStatus = (string) ($_GET['registration_status'] ?? '');
$registrationPurok = trim((string) ($_GET['registration_purok'] ?? ''));
$registrationRows = array_values(array_filter($residentRegistrations, static function ($row) use ($registrationSearch, $registrationStatus, $registrationPurok) {
  $text = implode(' ', array_map(static fn($key) => (string) ($row[$key] ?? ''), ['id','name','contact','address','purok','household']));
  return ($registrationSearch === '' || mb_stripos($text, $registrationSearch) !== false)
    && ($registrationStatus === '' || $row['status'] === $registrationStatus)
    && ($registrationPurok === '' || mb_stripos((string) ($row['purok'] ?? ''), $registrationPurok) !== false);
}));
$registrationCount = count($registrationRows);
$registrationPageCount = max(1, (int) ceil($registrationCount / 20));
$registrationPage = min($registrationPageCount, max(1, (int) ($_GET['registration_page'] ?? 1)));
$registrationRows = array_slice($registrationRows, ($registrationPage - 1) * 20, 20);
$registrationPageUrl = static function ($page) use ($registrationSearch, $registrationStatus, $registrationPurok): string {
  return '?' . http_build_query(['view'=>'resident-account-registration','registration_search'=>$registrationSearch,'registration_status'=>$registrationStatus,'registration_purok'=>$registrationPurok,'registration_page'=>$page]) . '#registrationRecords';
};
$registrationEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
$registrationIcons = [
  'id'=>'M3 5h18v14H3zM7 10h3v3H7zM14 10h4M14 14h4M7 16h3',
  'phone'=>'M5 3h4l2 5-3 2c2 3 3 4 6 6l2-3 5 2v4c-9 2-20-9-16-16z',
  'qr'=>'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h4v4h-7z',
  'date'=>'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2',
  'user'=>'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  'home'=>'M3 10l9-7 9 7M5 9v12h14V9M9 21v-8h6v8',
];
?>
<section class="staff-directory registration-directory" id="registrationRecords" aria-label="Resident registration records">
  <header class="staff-directory-head">
    <div><h3 data-registration-heading>Resident registration records (<?= number_format($registrationCount) ?>)</h3><p>Manage resident registration requirements, account access, and QR status.</p></div>
    <span class="resident-directory-count" data-registration-count role="status" aria-live="polite">Showing <?= $registrationCount ? ($registrationPage - 1) * 20 + 1 : 0 ?>–<?= min($registrationPage * 20, $registrationCount) ?> of <?= number_format($registrationCount) ?></span>
  </header>
  <form method="get" action="#registrationRecords" class="registration-card-filters">
    <input type="hidden" name="view" value="resident-account-registration">
    <div class="field"><label>Search<input type="search" name="registration_search" value="<?= $registrationEscape($registrationSearch) ?>" placeholder="Name, resident ID, contact, address, or household"></label></div>
    <div class="field"><label>Account status<select name="registration_status"><option value="">All</option>
      <?php foreach (['Active','Pending','Inactive'] as $status): ?><option <?= $registrationStatus === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="field"><label>Purok/Sitio<input type="search" name="registration_purok" value="<?= $registrationEscape($registrationPurok) ?>" placeholder="Type Purok/Sitio"></label></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline" data-registration-clear href="?view=resident-account-registration#registrationRecords">Clear filters</a></div>
  </form>
  <p class="registration-filter-message" data-registration-message role="status" hidden></p>
  <div data-registration-results>
  <div class="staff-directory-list">
    <?php foreach ($registrationRows as $resident):
      $parts = preg_split('/\s+/u', trim($resident['name']), -1, PREG_SPLIT_NO_EMPTY);
      $initials = mb_substr($parts[0] ?? 'R', 0, 1) . (count($parts) > 1 ? mb_substr($parts[count($parts)-1],0,1) : '');
      $district = $locationLists['districts'][$locationAssignments[$resident['id']]['district_id'] ?? 0]['name'] ?? 'Unassigned district';
    ?>
    <article class="staff-account-card registration-account-card">
      <header class="staff-account-card-head">
        <div class="staff-account-avatar" aria-hidden="true"><?= $registrationEscape(mb_strtoupper($initials)) ?></div>
        <div class="staff-account-identity"><h4><?= $registrationEscape($resident['name']) ?></h4><p><?= $registrationEscape($district) ?> / <?= $registrationEscape($resident['purok'] ?: 'Unassigned purok') ?></p></div>
        <span class="badge badge-<?= $resident['status'] === 'Active' ? 'active' : ($resident['status'] === 'Inactive' ? 'inactive' : 'pending') ?>"><span aria-hidden="true">●</span> <?= $registrationEscape($resident['status']) ?></span>
      </header>
      <dl class="staff-account-info registration-account-info">
        <?php foreach ([['id','Resident ID',$resident['id']],['phone','Contact number',$resident['contact'] ?: '—'],['qr','QR status',$resident['qr_status']],['date','Registered',$resident['date']],['user','Registered by',$resident['registered_by']],['home','Address',$resident['address'] ?: '—']] as [$kind,$label,$value]): ?>
        <div><span class="staff-account-info-icon"><?= barangay_ui_icon($kind) ?></span><div><dt><?= $label ?></dt><dd>
          <?php if ($kind === 'qr'): ?><span class="badge <?= $value === 'Active' ? 'badge-active' : 'badge-inactive' ?>"><?= $registrationEscape($value) ?></span>
          <?php if (!empty($resident['qr_code'])): ?><small class="field-hint registration-qr-code"><?= $registrationEscape($resident['qr_code']) ?></small><?php endif; ?>
          <?php else: ?><?= $registrationEscape($value) ?><?php endif; ?>
        </dd></div></div>
        <?php endforeach; ?>
      </dl>
      <footer class="registration-card-footer">
        <form id="resident-status-<?= $registrationEscape($resident['id']) ?>" action="staff-actions.php" method="post" class="registration-card-status">
          <input type="hidden" name="action" value="update_resident_status"><input type="hidden" name="redirect" value="resident-account-registration"><input type="hidden" name="id" value="<?= $registrationEscape($resident['id']) ?>">
          <div class="field"><label>Account status<select name="status" aria-label="Account status for <?= $registrationEscape($resident['name']) ?>">
            <?php foreach (['Pending','Active','Inactive'] as $status): ?><option <?= $resident['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
          </select></label></div>
          <div class="field"><label>QR status<select name="qr_status" aria-label="QR status for <?= $registrationEscape($resident['name']) ?>">
            <?php foreach (['Inactive','Active'] as $status): ?><option <?= $resident['qr_status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
          </select></label></div>
        </form>
        <div class="registration-card-buttons">
          <a class="btn btn-outline" href="<?= $registrationEscape(staff_view_url('resident-account-registration', ['requirements_resident'=>(int)$resident['db_id']])) ?>#registrationRequirements">Requirements</a>
          <form action="staff-actions.php" method="post"><input type="hidden" name="action" value="generate_resident_qr"><input type="hidden" name="redirect" value="resident-account-registration"><input type="hidden" name="id" value="<?= $registrationEscape($resident['id']) ?>"><button type="submit" class="btn btn-outline">Generate QR</button></form>
          <button type="submit" form="resident-status-<?= $registrationEscape($resident['id']) ?>" class="btn btn-primary">Save</button>
        </div>
      </footer>
    </article>
    <?php endforeach; ?>
    <?php if (!$registrationCount): ?><p class="staff-directory-empty">No residents match the selected filters.</p><?php endif; ?>
  </div>
  <?php if ($registrationPageCount > 1): ?><nav class="resident-directory-pages" aria-label="Registration record pages">
    <?php if ($registrationPage > 1): ?><a class="btn btn-outline" data-registration-page href="<?= $registrationEscape($registrationPageUrl($registrationPage-1)) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $registrationPage ?> of <?= $registrationPageCount ?></span>
    <?php if ($registrationPage < $registrationPageCount): ?><a class="btn btn-outline" data-registration-page href="<?= $registrationEscape($registrationPageUrl($registrationPage+1)) ?>">Next</a><?php endif; ?>
  </nav><?php endif; ?>
  </div>
</section>
<script src="js/registration-record-filters.js?v=<?= filemtime(__DIR__ . '/../js/registration-record-filters.js') ?>" defer></script>
