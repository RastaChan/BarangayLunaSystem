<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';
require_once __DIR__ . '/includes/resident-locations.php';
$user = barangay_current_user();
if (!$user || !in_array($user['role'], ['admin', 'staff'], true)) { http_response_code(403); exit('Access denied.'); }
$view = $user['role'] === 'admin' ? 'residents' : 'resident-management';
if (($_POST['action'] ?? '') !== 'assign' && $user['role'] !== 'admin') { http_response_code(403); exit('Only Admin can edit districts, puroks and chairmen.'); }
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['locations_csrf']) || !hash_equals($_SESSION['locations_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Refresh the page and try again.');
  resident_locations_schema();
  $db = barangay_db();
  $db->beginTransaction();
  if (in_array($_POST['action'] ?? '', ['save_lists', 'save_districts', 'save_puroks', 'save_location_manager'], true)) {
    $db->query('SELECT id FROM resident_districts ORDER BY id FOR UPDATE')->fetchAll();
    $lists = resident_locations();
    $districtOnly = $_POST['action'] === 'save_districts';
    $purokOnly = $_POST['action'] === 'save_puroks';
    resident_locations_save(
      $purokOnly ? array_map(static fn($row) => $row['name'], $lists['districts']) : (array) ($_POST['district_names'] ?? []),
      $districtOnly ? array_map(static fn($row) => $row['name'], $lists['puroks']) : (array) ($_POST['purok_names'] ?? []),
      $districtOnly ? array_map(static fn($row) => $row['district_id'], $lists['puroks']) : (array) ($_POST['purok_districts'] ?? []),
      $districtOnly ? null : (array) ($_POST['purok_chairmen'] ?? [])
    );
    if ($_POST['action'] === 'save_location_manager') {
      $entries = (array) ($_POST['new_puroks'] ?? []);
      if (count($entries) > 100) throw new InvalidArgumentException('Save up to 100 new puroks at a time.');
      foreach ($entries as $entry) {
        if (!is_array($entry)) throw new InvalidArgumentException('Invalid purok details.');
        resident_puroks_add((int) ($entry['district_id'] ?? 0), 1, [$entry]);
      }
    }
    $description = 'Updated district and purok names and assignments';
  } elseif (($_POST['action'] ?? '') === 'add_puroks') {
    $count = filter_var($_POST['purok_count'] ?? '', FILTER_VALIDATE_INT);
    $district = filter_var($_POST['district_id'] ?? '', FILTER_VALIDATE_INT);
    resident_puroks_add($district === false ? 0 : $district, $count === false ? 0 : $count);
    $description = 'Added ' . $count . ' puroks to district ' . $district;
  } elseif (($_POST['action'] ?? '') === 'assign') {
    $code = (string) ($_POST['resident_code'] ?? '');
    resident_location_assign($code, (int) ($_POST['district_id'] ?? 0), (int) ($_POST['purok_id'] ?? 0));
    $description = 'Updated district and purok for ' . $code;
  } else { throw new InvalidArgumentException('Invalid location action.'); }
  staff_log($user, $description, 'Resident Locations');
  $db->commit();
  $_SESSION['locations_flash'] = ['success', 'Resident locations saved.'];
} catch (Throwable $error) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $_SESSION['locations_flash'] = ['error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Could not save resident locations.'];
}
header('Location: ' . barangay_dashboard_for_role($user['role']) . '?view=' . $view . '#residentLocations');
exit;
