<?php
require_once __DIR__ . '/database.php';

function resident_locations_schema(): void {
  static $ready = false;
  if ($ready) return;
  $db = barangay_db();
  $db->exec("CREATE TABLE IF NOT EXISTS resident_districts (id INT UNSIGNED PRIMARY KEY, name VARCHAR(80) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS resident_puroks (id INT UNSIGNED PRIMARY KEY, name VARCHAR(80) NOT NULL, district_id INT UNSIGNED DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $purokColumns = $db->query('SHOW COLUMNS FROM resident_puroks')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('chairman', $purokColumns, true)) $db->exec("ALTER TABLE resident_puroks ADD COLUMN chairman VARCHAR(150) NOT NULL DEFAULT ''");
  $columns = $db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  foreach (['district_id', 'purok_id'] as $column) {
    if (!in_array($column, $columns, true)) $db->exec("ALTER TABLE users ADD COLUMN $column INT UNSIGNED DEFAULT NULL");
  }
  foreach (['resident_districts' => [7, 'District']] as $table => [$count, $label]) {
    $stmt = $db->prepare("INSERT IGNORE INTO $table (id, name) VALUES (?, ?)");
    for ($id = 1; $id <= $count; $id++) $stmt->execute([$id, "$label $id"]);
  }
  $ready = true;
}

function resident_puroks_add(int $district, int $count, array $entries = []): void {
  $db = barangay_db();
  if (!$db->inTransaction()) throw new LogicException('Adding puroks requires a transaction.');
  if ($count < 1 || $count > 100) throw new InvalidArgumentException('Add between 1 and 100 puroks at a time.');
  // Serialize catalog additions across districts so IDs and placeholder names stay unique.
  $districts = $db->query('SELECT id FROM resident_districts ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array($district, array_map('intval', $districts), true)) throw new InvalidArgumentException('Select a valid district.');
  $rows = $db->query('SELECT id, name FROM resident_puroks ORDER BY id FOR UPDATE')->fetchAll();
  $id = 0;
  $names = [];
  foreach ($rows as $row) {
    $id = max($id, (int) $row['id']);
    $names[mb_strtolower($row['name'])] = true;
  }
  $insert = $db->prepare('INSERT INTO resident_puroks (id, name, district_id) VALUES (?, ?, ?)');
  $number = 1;
  for ($i = 0; $i < $count; $i++) {
    while (isset($names['purok ' . $number])) $number++;
    $name = $entries ? barangay_formal_text(trim((string) ($entries[$i]['name'] ?? ''))) : 'Purok ' . $number++;
    $chairman = barangay_formal_text(trim((string) ($entries[$i]['chairman'] ?? '')));
    if ($name === '' || mb_strlen($name) > 80 || isset($names[mb_strtolower($name)])) throw new InvalidArgumentException('Each purok needs a unique name of 1 to 80 characters.');
    if (mb_strlen($chairman) > 150) throw new InvalidArgumentException('Chairman names must be at most 150 characters.');
    $insert->execute([++$id, $name, $district]);
    if ($chairman !== '') $db->prepare('UPDATE resident_puroks SET chairman = ? WHERE id = ?')->execute([$chairman, $id]);
    $names[mb_strtolower($name)] = true;
  }
}

function resident_locations(): array {
  resident_locations_schema();
  return [
    'districts' => barangay_db()->query('SELECT * FROM resident_districts ORDER BY id')->fetchAll(PDO::FETCH_UNIQUE),
    'puroks' => barangay_db()->query('SELECT * FROM resident_puroks ORDER BY id')->fetchAll(PDO::FETCH_UNIQUE),
  ];
}

function resident_location_validate(int $district, int $purok): array {
  $lists = resident_locations();
  if (!isset($lists['districts'][$district], $lists['puroks'][$purok])) throw new InvalidArgumentException('Select a district and purok from the lists.');
  $item = $lists['puroks'][$purok];
  if ($item['district_id'] && (int) $item['district_id'] !== $district) throw new InvalidArgumentException('The selected purok belongs to a different district.');
  return ['district_id' => $district, 'purok_id' => $purok, 'purok' => $item['name']];
}

function resident_location_assign(string $code, int $district, int $purok): void {
  $location = resident_location_validate($district, $purok);
  $stmt = barangay_db()->prepare("SELECT id FROM users WHERE user_code = ? AND role = 'resident' FOR UPDATE");
  $stmt->execute([$code]);
  if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Resident not found.');
  $stmt = barangay_db()->prepare("UPDATE users SET district_id = ?, purok_id = ?, purok = ? WHERE user_code = ? AND role = 'resident'");
  $stmt->execute([$district, $purok, $location['purok'], $code]);
}

function resident_locations_save(array $districtNames, array $purokNames, array $mapping, ?array $chairmen = null): void {
  $lists = resident_locations();
  foreach (['districts' => $districtNames, 'puroks' => $purokNames] as $kind => $names) {
    $seen = [];
    foreach ($lists[$kind] as $id => $row) {
      $name = trim((string) ($names[$id] ?? ''));
      $key = mb_strtolower($name);
      if ($name === '' || mb_strlen($name) > 80 || isset($seen[$key])) throw new InvalidArgumentException('Each district and purok needs a unique name of 1 to 80 characters.');
      $seen[$key] = true;
    }
  }
  foreach ($lists['puroks'] as $id => $row) {
    if ($chairmen !== null && mb_strlen(trim((string) ($chairmen[$id] ?? ''))) > 150) throw new InvalidArgumentException('Chairman names must be at most 150 characters.');
    $district = (int) ($mapping[$id] ?? 0);
    if ($district && !isset($lists['districts'][$district])) throw new InvalidArgumentException('Choose a valid district for each purok.');
  }
  $db = barangay_db();
  $stmt = $db->prepare('UPDATE resident_districts SET name = ? WHERE id = ?');
  foreach ($lists['districts'] as $id => $row) $stmt->execute([barangay_formal_text(trim($districtNames[$id])), $id]);
  $stmt = $db->prepare('UPDATE resident_puroks SET name = ?, district_id = ?, chairman = ? WHERE id = ?');
  $sync = $db->prepare("UPDATE users SET purok = ?, district_id = COALESCE(?, district_id) WHERE purok_id = ? AND role = 'resident'");
  foreach ($lists['puroks'] as $id => $row) {
    $name = barangay_formal_text(trim($purokNames[$id]));
    $district = (int) ($mapping[$id] ?? 0) ?: null;
    $chairman = $chairmen === null ? $row['chairman'] : barangay_formal_text(trim((string) ($chairmen[$id] ?? '')));
    $stmt->execute([$name, $district, $chairman, $id]);
    $sync->execute([$name, $district, $id]);
  }
}

function resident_location_fields(array $lists, bool $required = true): void {
  ?>
  <div class="form-grid-2" data-location-selects>
    <div class="field"><label>District<select name="district_id" data-location-district <?= $required ? 'required' : '' ?>><option value="">Select district</option>
      <?php foreach ($lists['districts'] as $id => $row): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($row['name']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="field"><label>Purok<select name="purok_id" data-location-purok <?= $required ? 'required' : '' ?>><option value="">Select purok</option>
      <?php foreach ($lists['puroks'] as $id => $row): ?><option value="<?= (int) $id ?>" data-chairman="<?= htmlspecialchars($row['chairman'] ?? '', ENT_QUOTES) ?>" data-district="<?= (int) $row['district_id'] ?>"><?= htmlspecialchars($row['name']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="field-span-2 field-hint" data-location-chairman aria-live="polite">Purok chairman: Select a purok</div>
  </div>
  <?php
}

function resident_location_filter(array $rows, array $assignments, string $district, string $purok): array {
  return array_values(array_filter($rows, static function ($row) use ($assignments, $district, $purok) {
    $location = $assignments[$row['id']] ?? [];
    foreach (['district_id' => $district, 'purok_id' => $purok] as $key => $value) {
      if ($value === 'unassigned' && !empty($location[$key])) return false;
      if ($value !== '' && $value !== 'unassigned' && (int) ($location[$key] ?? 0) !== (int) $value) return false;
    }
    return true;
  }));
}
