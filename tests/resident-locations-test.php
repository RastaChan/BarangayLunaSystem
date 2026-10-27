<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/resident-locations.php';
function check_location(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$lists = resident_locations();
check_location(count($lists['districts']) === 7, 'Expected 7 districts');
$db = barangay_db();
$db->beginTransaction();
try {
  $before = count($lists['puroks']);
  resident_puroks_add(1, 3);
  $added = resident_locations()['puroks'];
  check_location(count($added) === $before + 3, 'Bulk addition must add the requested count');
  foreach (array_diff_key($added, $lists['puroks']) as $item) check_location((int) $item['district_id'] === 1, 'New puroks must belong to the chosen district');
  foreach ([[0, 2], [1, 0], [1, 101]] as [$district, $count]) {
    try { resident_puroks_add($district, $count); throw new RuntimeException('Invalid addition accepted'); } catch (InvalidArgumentException $expected) {}
  }
  check_location(count(resident_locations()['puroks']) === $before + 3, 'Invalid additions must not change the catalog');
  $named = 'Named test ' . bin2hex(random_bytes(4));
  resident_puroks_add(2, 1, [['name' => $named, 'chairman' => 'Test chairman']]);
  $saved = array_values(array_filter(resident_locations()['puroks'], static fn($row) => $row['name'] === barangay_formal_text($named)));
  check_location(count($saved) === 1 && $saved[0]['chairman'] === 'Test Chairman' && (int) $saved[0]['district_id'] === 2, 'Named purok and chairman must save together');
  try { resident_puroks_add(2, 1, [['name' => $named]]); throw new RuntimeException('Duplicate new purok accepted'); } catch (InvalidArgumentException $expected) {}
  $lists = resident_locations();
  $code = 'TEST-LOC-' . bin2hex(random_bytes(4));
  $stmt = $db->prepare("INSERT INTO users (user_code, role, full_name, username, password_hash, purok) VALUES (?, 'resident', 'Location test', ?, 'not-a-login-hash', 'Original free text')");
  $stmt->execute([$code, $code]);
  $districts = array_map(static fn($row) => $row['name'], $lists['districts']);
  $puroks = array_map(static fn($row) => $row['name'], $lists['puroks']);
  $mapping = array_map(static fn($row) => $row['district_id'] ?? '', $lists['puroks']);
  $districts[1] = 'Test district ' . $code;
  $puroks[1] = 'Test purok ' . $code;
  $mapping[1] = 1;
  $chairmen = [1 => 'Test chairman'];
  resident_locations_save($districts, $puroks, $mapping, $chairmen);
  check_location(resident_locations()['puroks'][1]['chairman'] === 'Test Chairman', 'Chairman name must be saved');
  $query = $db->prepare('SELECT district_id, purok_id, purok FROM users WHERE user_code = ?');
  $query->execute([$code]);
  $row = $query->fetch();
  check_location($row['purok'] === 'Original free text' && $row['purok_id'] === null, 'Legacy location must stay untouched');
  resident_location_assign($code, 1, 1);
  $query->execute([$code]);
  $row = $query->fetch();
  check_location((int) $row['district_id'] === 1 && $row['purok'] === barangay_formal_text($puroks[1]), 'Assignment should save IDs and display name');
  try { resident_location_validate(2, 1); throw new RuntimeException('District mismatch accepted'); } catch (InvalidArgumentException $expected) {}
  try { resident_location_validate(1, 4294967295); throw new RuntimeException('Unknown purok accepted'); } catch (InvalidArgumentException $expected) {}
  $puroks[1] .= ' renamed';
  $mapping[1] = 2;
  resident_locations_save($districts, $puroks, $mapping);
  $query->execute([$code]);
  $row = $query->fetch();
  check_location($row['purok'] === barangay_formal_text($puroks[1]) && (int) $row['district_id'] === 2, 'Renaming and reassignment must synchronize');
  $rows = [['id' => $code], ['id' => 'legacy']];
  $assignments = [$code => $row];
  check_location(count(resident_location_filter($rows, $assignments, '2', '1')) === 1, 'Combined filter failed');
  check_location(count(resident_location_filter($rows, $assignments, '1', '1')) === 0, 'Mismatched filter failed');
  check_location(resident_location_filter($rows, $assignments, 'unassigned', '')[0]['id'] === 'legacy', 'Unassigned filter failed');
  $puroks[2] = $puroks[1];
  try { resident_locations_save($districts, $puroks, $mapping); throw new RuntimeException('Duplicate names accepted'); } catch (InvalidArgumentException $expected) {}
  echo "PASS: bulk addition, invalid additions, assignment, legacy preservation, rename synchronization, district validation and filters.\n";
} finally {
  $db->rollBack();
}
foreach (['admin', 'staff'] as $role) {
  $_SESSION = [];
  $_GET = ['resident_search' => 'needle'];
  $loggedInUser = ['role' => $role];
  $locationView = $role === 'admin' ? 'residents' : 'resident-management';
  $adminResidents = [['id' => 'sample-1', 'name' => 'Needle Resident'], ['id' => 'sample-2', 'name' => 'Another Resident']];
  ob_start();
  require __DIR__ . '/../includes/resident-location-controls.php';
  $html = ob_get_clean();
  check_location(count($adminResidents) === 1 && $adminResidents[0]['id'] === 'sample-1', 'Resident search must filter case-insensitively');
  check_location(str_contains($html, 'id="manageResidentLocations"') === ($role === 'admin'), 'Only admin may see location editing, including an empty catalog');
  check_location(str_contains($html, 'data-add-purok') === ($role === 'admin'), 'Only admin may add puroks');
  check_location(str_contains($html, $role === 'admin' ? 'Purok chairman' : 'Purok chairmen'), 'Both roles may view chairmen');
}
$endpoint = file_get_contents(__DIR__ . '/../resident-location-actions.php');
check_location(str_contains($endpoint, "\$user['role'] !== 'admin'") && str_contains($endpoint, 'http_response_code(403)'), 'Location editing must be protected on the server');
echo "PASS: chairman persistence, admin-only editor and resident search for both roles.\n";
