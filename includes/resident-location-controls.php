<?php
require_once __DIR__ . '/resident-locations.php';
$locationLists = resident_locations();
$_SESSION['locations_csrf'] ??= bin2hex(random_bytes(32));
$locationView = $locationView ?? 'resident-account-registration';
$locationAssignments = barangay_db()->query("SELECT user_code, district_id, purok_id, purok, full_name FROM users WHERE role = 'resident' ORDER BY full_name")->fetchAll(PDO::FETCH_UNIQUE);
$districtFilter = (string) ($_GET['district_filter'] ?? '');
$purokFilter = (string) ($_GET['purok_filter'] ?? '');
$locationAdmin = ($loggedInUser['role'] ?? $user['role'] ?? '') === 'admin';
if (isset($adminResidents)) $adminResidents = resident_location_filter($adminResidents, $locationAssignments, $districtFilter, $purokFilter);
$residentAccountFilter = (string) ($_GET['account_filter'] ?? '');
if (isset($adminResidents) && in_array($residentAccountFilter, ['Active', 'Inactive', 'Pending', 'inactivity_review'], true)) {
  $adminResidents = array_values(array_filter($adminResidents, static fn($row) => $residentAccountFilter === 'inactivity_review'
    ? ($row['status'] ?? '') === 'Inactive' && ($row['inactive_reason'] ?? '') === 'six_month_inactivity'
    : ($row['status'] ?? '') === $residentAccountFilter));
}
$residentSearch = trim((string) ($_GET['resident_search'] ?? ''));
if (isset($adminResidents) && $residentSearch !== '') $adminResidents = array_values(array_filter($adminResidents, static function ($row) use ($residentSearch) {
  return mb_stripos(implode(' ', array_map(static fn($key) => (string) ($row[$key] ?? ''), ['id', 'name', 'username', 'mobile', 'address', 'purok'])), $residentSearch) !== false;
}));
if (isset($residentRegistrations)) $residentRegistrations = resident_location_filter($residentRegistrations, $locationAssignments, $districtFilter, $purokFilter);
$locationEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
$locationFlash = $_SESSION['locations_flash'] ?? null;
unset($_SESSION['locations_flash']);
?>
<section class="panel-card" id="residentLocations" style="margin-bottom:1.25rem">
  <div class="panel-head"><h3>Search residents</h3></div>
  <div class="panel-body">
    <?php if ($locationFlash): ?><div hidden data-action-toast="<?= $locationEscape($locationFlash[0]) ?>"><?= $locationEscape($locationFlash[1]) ?></div><?php endif; ?>
    <form method="get" class="resident-search-filters" data-location-selects>
      <input type="hidden" name="view" value="<?= $locationEscape($locationView) ?>">
      <div class="field"><label>Search residents<input type="search" name="resident_search" value="<?= $locationEscape($residentSearch) ?>" placeholder="Name, resident ID, username, mobile or address"></label></div>
      <?php if (isset($adminResidents)): ?>
      <div class="field"><label>Account status<select name="account_filter"><option value="">All accounts</option>
        <?php foreach (['inactivity_review' => 'Needs residency review (6 months inactive)', 'Active' => 'Active', 'Inactive' => 'Inactive', 'Pending' => 'Pending'] as $value => $label): ?>
        <option value="<?= $locationEscape($value) ?>" <?= $residentAccountFilter === $value ? 'selected' : '' ?>><?= $locationEscape($label) ?></option>
        <?php endforeach; ?>
      </select></label></div>
      <?php endif; ?>
        <?php foreach (['districts' => ['District', 'district_filter', $districtFilter], 'puroks' => ['Purok', 'purok_filter', $purokFilter]] as $kind => [$label, $field, $selected]): ?>
        <div class="field"><label><?= $label ?><select name="<?= $field ?>" <?= $kind === 'districts' ? 'data-location-district' : 'data-location-purok' ?>><option value="">All</option><option value="unassigned" <?= $selected === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
          <?php foreach ($locationLists[$kind] as $id => $item): ?><option value="<?= (int) $id ?>" <?= $kind === 'puroks' ? 'data-district="' . (int) $item['district_id'] . '"' : '' ?> <?= $selected === (string) $id ? 'selected' : '' ?>><?= $locationEscape($item['name']) ?></option><?php endforeach; ?>
        </select></label></div>
        <?php endforeach; ?>
      <div class="actions resident-search-filter-actions" style="display:flex;gap:8px;flex-wrap:wrap"><button type="submit" class="btn btn-primary">Apply filters</button><a class="btn btn-outline" href="?view=<?= $locationEscape($locationView) ?>">Clear filters</a></div>
    </form>
    <?php if (!$locationAdmin): ?><details style="margin-top:1rem"><summary>Purok chairmen</summary><div class="table-wrap"><table class="data-table"><thead><tr><th>Purok</th><th>District</th><th>Chairman</th></tr></thead><tbody>
      <?php foreach ($locationLists['puroks'] as $item): ?><tr><td><?= $locationEscape($item['name']) ?></td><td><?= $locationEscape($locationLists['districts'][$item['district_id'] ?? 0]['name'] ?? 'Unassigned') ?></td><td><?= $locationEscape($item['chairman'] ?: 'Not yet assigned') ?></td></tr><?php endforeach; ?>
    </tbody></table></div></details><?php endif; ?>

  </div>
</section>
    <?php if ($locationAdmin): ?>
<div id="manageResidentLocations" class="modal" aria-hidden="true" role="dialog" aria-labelledby="manageResidentLocationsTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="manageResidentLocationsTitle">Manage Districts and Puroks</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body">
      <form method="post" action="resident-location-actions.php" class="form-grid" data-purok-manager style="margin-top:1rem">
        <input type="hidden" name="action" value="save_location_manager"><input type="hidden" name="csrf" value="<?= $locationEscape($_SESSION['locations_csrf']) ?>">
          <div class="field"><label>District<select data-manager-district required><option value="">Select district</option><option value="unassigned">Unassigned puroks</option>
            <?php foreach ($locationLists['districts'] as $id => $item): ?><option value="<?= (int) $id ?>"><?= $locationEscape($item['name']) ?></option><?php endforeach; ?>
          </select></label></div>
          <?php foreach ($locationLists['districts'] as $id => $item): ?><div class="field" data-manager-name="<?= (int) $id ?>" hidden><label>District name<input name="district_names[<?= (int) $id ?>]" value="<?= $locationEscape($item['name']) ?>" maxlength="80" required></label></div><?php endforeach; ?>
        <div class="actions"><button type="button" class="btn btn-outline" data-add-purok disabled>Add Purok</button></div>
        <div data-manager-puroks>
        <?php foreach ($locationLists['puroks'] as $id => $item): ?>
        <details style="margin-top:1rem" data-manager-purok hidden><summary><?= $locationEscape($item['name']) ?><?= empty($item['district_id']) ? ' (Unassigned)' : '' ?></summary>
        <div class="form-grid-2">
          <div class="field"><label>Purok <?= (int) $id ?><input name="purok_names[<?= (int) $id ?>]" value="<?= $locationEscape($item['name']) ?>" maxlength="80" required></label></div>
          <div class="field"><label>District<select data-purok-district name="purok_districts[<?= (int) $id ?>]"><option value="">Not yet assigned</option>
            <?php foreach ($locationLists['districts'] as $districtId => $district): ?><option value="<?= (int) $districtId ?>" <?= (int) $item['district_id'] === (int) $districtId ? 'selected' : '' ?>><?= $locationEscape($district['name']) ?></option><?php endforeach; ?>
          </select></label></div>
          <div class="field field-span-2"><label>Purok chairman<input name="purok_chairmen[<?= (int) $id ?>]" maxlength="150" value="<?= $locationEscape($item['chairman']) ?>" placeholder="Not yet assigned"></label></div>
        </div>
        </details>
        <?php endforeach; ?>
        </div>
        <template data-purok-template><details data-manager-purok style="margin-top:1rem" open><summary>New purok</summary><div class="form-grid-2">
          <input type="hidden" data-purok-district data-new-field="district_id">
          <div class="field"><label>Purok name<input data-new-field="name" maxlength="80" required></label></div>
          <div class="field"><label>Purok chairman<input data-new-field="chairman" maxlength="150" placeholder="Not yet assigned"></label></div>
        </div><button type="button" class="btn btn-outline" data-discard-purok>Cancel addition</button></details></template>
        <div class="actions"><button type="submit" class="btn btn-primary">Save changes</button><button type="button" class="btn btn-outline" data-modal-close>Cancel</button></div>
      </form>
    </div>
  </div>
</div>
    <?php endif; ?>
<script src="js/resident-locations.js?v=<?= filemtime(__DIR__ . '/../js/resident-locations.js') ?>" defer></script>
<?php if ($locationAdmin): ?><script src="js/purok-manager.js?v=<?= filemtime(__DIR__ . '/../js/purok-manager.js') ?>" defer></script><?php endif; ?>
