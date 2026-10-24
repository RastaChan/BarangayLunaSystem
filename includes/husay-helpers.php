<?php
require_once __DIR__ . '/database.php';

function husay_schema(): void {
  static $ready = false;
  if ($ready) return;
  $db = barangay_db();
  $columns = $db->query('SHOW COLUMNS FROM complaints')->fetchAll(PDO::FETCH_COLUMN);
  foreach (['intake_source' => "VARCHAR(20) NOT NULL DEFAULT 'Online'", 'contact_mobile' => 'VARCHAR(30) DEFAULT NULL'] as $name => $definition) {
    if (!in_array($name, $columns, true)) $db->exec("ALTER TABLE complaints ADD `$name` $definition");
  }
  $db->exec("CREATE TABLE IF NOT EXISTS husay_calendar_guard (id TINYINT PRIMARY KEY) ENGINE=InnoDB");
  $db->exec('INSERT IGNORE INTO husay_calendar_guard(id) VALUES (1)');
  $db->exec("CREATE TABLE IF NOT EXISTS husay_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL,
    venue VARCHAR(180) NOT NULL, assigned_user_id INT UNSIGNED NOT NULL,
    complainant_name VARCHAR(150) NOT NULL, complainant_mobile VARCHAR(30) DEFAULT NULL,
    respondent_name VARCHAR(150) NOT NULL, respondent_mobile VARCHAR(30) DEFAULT NULL,
    respondent_address VARCHAR(255) DEFAULT NULL,
    instructions TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Scheduled', outcome VARCHAR(30) DEFAULT NULL,
    complainant_attendance VARCHAR(20) NOT NULL DEFAULT 'Not recorded',
    respondent_attendance VARCHAR(20) NOT NULL DEFAULT 'Not recorded',
    public_summary TEXT DEFAULT NULL, private_notes TEXT DEFAULT NULL,
    rescheduled_from INT UNSIGNED DEFAULT NULL, change_reason VARCHAR(1000) DEFAULT NULL,
    reviewed_by INT UNSIGNED NOT NULL, created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    request_key CHAR(32) DEFAULT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX husay_calendar (status, starts_at, ends_at), INDEX husay_complaint (complaint_id),
    CONSTRAINT fk_husay_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id),
    CONSTRAINT fk_husay_officer FOREIGN KEY (assigned_user_id) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $hearingColumns = $db->query('SHOW COLUMNS FROM husay_schedules')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('request_key', $hearingColumns, true)) $db->exec('ALTER TABLE husay_schedules ADD request_key CHAR(32) DEFAULT NULL UNIQUE');
  $db->exec("CREATE TABLE IF NOT EXISTS husay_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, hearing_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL, description TEXT NOT NULL, actor_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX husay_history_hearing (hearing_id),
    CONSTRAINT fk_husay_history FOREIGN KEY (hearing_id) REFERENCES husay_schedules(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $ready = true;
}

function husay_require_staff(array $user): void {
  if (!in_array($user['role'] ?? '', ['admin', 'staff'], true) || (int) ($user['id'] ?? 0) < 1) throw new InvalidArgumentException('Only Admin and Staff can manage Husay schedules.');
}

function husay_text(array $input, string $key, int $max, bool $required = false): string {
  $value = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
  if (($required && $value === '') || mb_strlen($value) > $max) throw new InvalidArgumentException('Check the ' . str_replace('_', ' ', $key) . ' field.');
  return $value;
}

function husay_mobile(array $input, string $key): string {
  $value = husay_text($input, $key, 30);
  if ($value !== '' && !preg_match('/^(?:09\d{9}|\+639\d{9})$/D', $value)) throw new InvalidArgumentException('Enter a valid mobile number, or leave it blank.');
  return $value;
}

function husay_times(array $input): array {
  $zone = new DateTimeZone('Asia/Manila');
  $date = husay_text($input, 'hearing_date', 10, true);
  $start = $date . ' ' . husay_text($input, 'start_time', 5, true);
  $end = $date . ' ' . husay_text($input, 'end_time', 5, true);
  $a = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $start, $zone);
  $b = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $end, $zone);
  if (!$a || !$b || $a->format('Y-m-d H:i') !== $start || $b->format('Y-m-d H:i') !== $end || $b <= $a) throw new InvalidArgumentException('Choose a valid date and an end time after the start time.');
  $now = new DateTimeImmutable('now', $zone);
  if ($a < $now->setTime((int) $now->format('H'), (int) $now->format('i'))) throw new InvalidArgumentException('Choose a current or future Husay schedule.');
  return [$a->format('Y-m-d H:i:s'), $b->format('Y-m-d H:i:s')];
}

function husay_event(int $id, string $action, string $description, int $actor): void {
  barangay_db()->prepare('INSERT INTO husay_history(hearing_id,action,description,actor_id) VALUES (?,?,?,?)')->execute([$id, $action, $description, $actor]);
}

function husay_officers(): array {
  return barangay_db()->query("SELECT id, full_name, COALESCE(position_title, '') AS position_title FROM users WHERE role IN ('admin','staff') AND status='Active' ORDER BY full_name")->fetchAll();
}

function husay_schedule(array $input, array $user): array {
  husay_require_staff($user); husay_schema();
  [$start, $end] = husay_times($input);
  $venue = preg_replace('/\s+/u', ' ', husay_text($input, 'venue', 180, true));
  $officer = (int) ($input['assigned_user_id'] ?? 0);
  $respondent = husay_text($input, 'respondent_name', 150, true);
  $respondentMobile = husay_mobile($input, 'respondent_mobile');
  $complainantMobile = husay_mobile($input, 'complainant_mobile');
  $address = husay_text($input, 'respondent_address', 255);
  $instructions = husay_text($input, 'instructions', 2000);
  $requestKey = husay_text($input, 'request_key', 32) ?: bin2hex(random_bytes(16));
  if (!preg_match('/^[a-f0-9]{32}$/D', $requestKey)) throw new InvalidArgumentException('Refresh the scheduling form and try again.');
  if (($input['review_confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Confirm that the authorized barangay official reviewed this complaint for Husay.');
  $db = barangay_db(); $db->beginTransaction();
  try {
    $db->query('SELECT id FROM husay_calendar_guard WHERE id=1 FOR UPDATE')->fetchColumn();
    $duplicate = $db->prepare('SELECT h.id,h.created_by,c.complaint_code FROM husay_schedules h JOIN complaints c ON c.id=h.complaint_id WHERE h.request_key=?'); $duplicate->execute([$requestKey]); $saved = $duplicate->fetch();
    if ($saved) {
      if ((int)$saved['created_by'] !== (int)$user['id']) throw new InvalidArgumentException('Refresh the scheduling form and try again.');
      $db->commit(); return ['id' => (int)$saved['id'], 'complaint_code' => $saved['complaint_code']];
    }
    $read = $db->prepare("SELECT id FROM users WHERE id=? AND role IN ('admin','staff') AND status='Active'"); $read->execute([$officer]);
    if (!$read->fetchColumn()) throw new InvalidArgumentException('Select an active assigned official.');
    $oldId = (int) ($input['reschedule_id'] ?? 0); $old = null;
    if ($oldId) {
      $read = $db->prepare('SELECT * FROM husay_schedules WHERE id=? FOR UPDATE'); $read->execute([$oldId]); $old = $read->fetch();
      if (!$old || !in_array($old['status'], ['Scheduled', 'In Progress'], true) || (int) ($input['version'] ?? 0) !== (int) $old['version']) throw new InvalidArgumentException('This session changed. Refresh its details before rescheduling.');
      $reason = husay_text($input, 'change_reason', 1000, true);
      $read = $db->prepare('SELECT * FROM complaints WHERE id=? AND archived_at IS NULL FOR UPDATE'); $read->execute([$old['complaint_id']]);
    } elseif (($input['intake_mode'] ?? '') === 'walkin') {
      $name = husay_text($input, 'complainant_name', 150, true);
      $residentCode = husay_text($input, 'resident_code', 30);
      $residentId = null;
      if ($residentCode !== '') {
        $read = $db->prepare("SELECT id,full_name,mobile FROM users WHERE user_code=? AND role='resident' AND status='Active'"); $read->execute([$residentCode]); $resident = $read->fetch();
        if (!$resident) throw new InvalidArgumentException('Resident ID not found or inactive. Correct it, or leave it blank for a walk-in without an account.');
        $residentId = $resident['id']; $name = $resident['full_name'];
        if ($complainantMobile === '') $complainantMobile = (string) ($resident['mobile'] ?? '');
      }
      $code = 'CMP-W-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
      $db->prepare("INSERT INTO complaints(complaint_code,resident_user_id,resident_name,subject,category,details,location,status,intake_source,contact_mobile) VALUES (?,?,?,?,?,?,?,'Open','Walk-in',?)")
        ->execute([$code, $residentId, $name, husay_text($input, 'subject', 180, true), husay_text($input, 'category', 100) ?: 'Walk-in concern', husay_text($input, 'details', 10000, true), husay_text($input, 'location', 180), $complainantMobile ?: null]);
      $complaintId = (int) $db->lastInsertId();
      $read = $db->prepare('SELECT * FROM complaints WHERE id=? FOR UPDATE'); $read->execute([$complaintId]);
    } else {
      $read = $db->prepare('SELECT * FROM complaints WHERE complaint_code=? AND archived_at IS NULL FOR UPDATE'); $read->execute([husay_text($input, 'complaint_code', 30, true)]);
    }
    $complaint = $read->fetch();
    if (!$complaint || !in_array($complaint['status'], ['Open', 'In Progress'], true)) throw new InvalidArgumentException('Schedule Husay for an open complaint that has not been resolved or closed.');
    $read = $db->prepare("SELECT id FROM husay_schedules WHERE complaint_id=? AND status IN ('Scheduled','In Progress') AND id<>?"); $read->execute([$complaint['id'], $oldId]);
    if ($read->fetchColumn()) throw new InvalidArgumentException('This complaint already has an active Husay session. Reschedule that session instead.');
    $read = $db->prepare("SELECT id FROM husay_schedules WHERE status IN ('Scheduled','In Progress') AND id<>? AND starts_at<? AND ends_at>? AND (assigned_user_id=? OR LOWER(venue)=LOWER(?)) LIMIT 1");
    $read->execute([$oldId, $end, $start, $officer, $venue]);
    if ($read->fetchColumn()) throw new InvalidArgumentException('The assigned official or venue already has a Husay session during that time. Choose another slot.');
    if ($old) {
      $db->prepare("UPDATE husay_schedules SET status='Rescheduled',change_reason=?,version=version+1,updated_by=?,updated_at=NOW() WHERE id=?")->execute([$reason, $user['id'], $oldId]);
      husay_event($oldId, 'Rescheduled', $reason, (int) $user['id']);
    }
    $mobile = $complainantMobile ?: (string) ($complaint['contact_mobile'] ?? '');
    if ($mobile === '' && $complaint['resident_user_id']) {
      $contact = $db->prepare('SELECT mobile FROM users WHERE id=?'); $contact->execute([$complaint['resident_user_id']]); $mobile = (string)($contact->fetchColumn() ?: '');
    }
    $db->prepare('INSERT INTO husay_schedules(complaint_id,starts_at,ends_at,venue,assigned_user_id,complainant_name,complainant_mobile,respondent_name,respondent_mobile,respondent_address,instructions,rescheduled_from,change_reason,reviewed_by,created_by,updated_by,request_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$complaint['id'], $start, $end, $venue, $officer, $complaint['resident_name'], $mobile ?: null, $respondent, $respondentMobile ?: null, $address ?: null, $instructions ?: null, $oldId ?: null, $old ? $reason : null, $user['id'], $user['id'], $user['id'], $requestKey]);
    $id = (int) $db->lastInsertId();
    husay_event($id, $old ? 'Rescheduled session created' : 'Scheduled', $start . ' to ' . $end . ' at ' . $venue, (int) $user['id']);
    $db->prepare("UPDATE complaints SET status='In Progress' WHERE id=? AND status='Open'")->execute([$complaint['id']]);
    $db->commit();
    return ['id' => $id, 'complaint_code' => $complaint['complaint_code']];
  } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}

function husay_record_session(array $input, array $user): int {
  husay_require_staff($user); husay_schema();
  $id = (int) ($input['hearing_id'] ?? 0);
  $action = $input['action'] ?? '';
  if (!in_array($action, ['record_husay','cancel_husay'], true)) throw new InvalidArgumentException('Select a valid session action.');
  $db = barangay_db(); $db->beginTransaction();
  try {
    $db->query('SELECT id FROM husay_calendar_guard WHERE id=1 FOR UPDATE')->fetchColumn();
    $read = $db->prepare('SELECT h.*,c.archived_at FROM husay_schedules h JOIN complaints c ON c.id=h.complaint_id WHERE h.id=? FOR UPDATE'); $read->execute([$id]); $hearing = $read->fetch();
    if (!$hearing || $hearing['archived_at'] || !in_array($hearing['status'], ['Scheduled','In Progress'], true) || (int) ($input['version'] ?? 0) !== (int) $hearing['version']) throw new InvalidArgumentException('This session changed or is no longer active. Refresh its details.');
    if ($action === 'cancel_husay') {
      $reason = husay_text($input, 'change_reason', 1000, true);
      $db->prepare("UPDATE husay_schedules SET status='Cancelled',change_reason=?,version=version+1,updated_by=?,updated_at=NOW() WHERE id=?")->execute([$reason, $user['id'], $id]);
      husay_event($id, 'Cancelled', $reason, (int) $user['id']);
    } else {
      $a = husay_text($input, 'complainant_attendance', 20, true); $b = husay_text($input, 'respondent_attendance', 20, true);
      if (!in_array($a, ['Not recorded','Present','Absent','Not applicable'], true) || !in_array($b, ['Not recorded','Present','Absent','Not applicable'], true)) throw new InvalidArgumentException('Select valid attendance values.');
      $outcome = husay_text($input, 'outcome', 30);
      if (!in_array($outcome, ['', 'Settled','For follow-up','Referred','No agreement'], true)) throw new InvalidArgumentException('Select a valid session outcome.');
      $summary = husay_text($input, 'public_summary', 4000, $outcome !== '');
      if ($outcome !== '' && ($a === 'Not recorded' || $b === 'Not recorded')) throw new InvalidArgumentException('Record attendance for both parties before completing the session.');
      $notes = husay_text($input, 'private_notes', 6000);
      $db->prepare('UPDATE husay_schedules SET status=?,outcome=?,complainant_attendance=?,respondent_attendance=?,public_summary=?,private_notes=?,version=version+1,updated_by=?,updated_at=NOW() WHERE id=?')
        ->execute([$outcome === '' ? 'In Progress' : 'Completed', $outcome ?: null, $a, $b, $summary ?: null, $notes ?: null, $user['id'], $id]);
      husay_event($id, $outcome === '' ? 'Attendance updated' : 'Session completed', $outcome === '' ? 'Session is in progress.' : $outcome . ': ' . $summary, (int) $user['id']);
    }
    $db->commit(); return $id;
  } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}

function husay_update_complaint(array $input, array $user): void {
  husay_require_staff($user); husay_schema();
  $code = husay_text($input, 'id', 30, true); $status = husay_text($input, 'status', 30, true); $remarks = husay_text($input, 'remarks', 4000);
  if (!in_array($status, ['Open','In Progress','Resolved','Closed'], true)) throw new InvalidArgumentException('Select a valid complaint status.');
  $db = barangay_db(); $db->beginTransaction();
  try {
    $db->query('SELECT id FROM husay_calendar_guard WHERE id=1 FOR UPDATE')->fetchColumn();
    $read = $db->prepare('SELECT id FROM complaints WHERE complaint_code=? AND archived_at IS NULL FOR UPDATE'); $read->execute([$code]); $id = $read->fetchColumn();
    if (!$id) throw new InvalidArgumentException('Complaint unavailable.');
    $active = $db->prepare("SELECT id FROM husay_schedules WHERE complaint_id=? AND status IN ('Scheduled','In Progress') LIMIT 1"); $active->execute([$id]); $hasActiveSession = (bool)$active->fetchColumn();
    if ($status === 'Open' && $hasActiveSession) throw new InvalidArgumentException('A complaint with an active Husay session must remain In Progress.');
    if (in_array($status, ['Resolved','Closed'], true)) {
      if ($remarks === '') throw new InvalidArgumentException('Record a resolution or closure summary in remarks.');
      if ($hasActiveSession) throw new InvalidArgumentException('Complete or cancel the active Husay session before closing this complaint.');
    }
    $db->prepare('UPDATE complaints SET status=?,remarks=? WHERE id=?')->execute([$status, $remarks, $id]);
    $db->commit();
  } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}

function husay_rows(?string $complaintCode = null, ?int $residentId = null): array {
  husay_schema();
  $sql = "SELECT h.*,c.complaint_code,c.subject,c.status AS complaint_status,c.resident_user_id,c.intake_source,c.details,c.location,u.full_name AS assigned_name FROM husay_schedules h JOIN complaints c ON c.id=h.complaint_id JOIN users u ON u.id=h.assigned_user_id WHERE c.archived_at IS NULL";
  $params = [];
  if ($complaintCode !== null) { $sql .= ' AND c.complaint_code=?'; $params[] = $complaintCode; }
  if ($residentId !== null) { $sql .= ' AND c.resident_user_id=?'; $params[] = $residentId; }
  $sql .= " ORDER BY CASE WHEN h.status IN ('Scheduled','In Progress') THEN 0 ELSE 1 END, CASE WHEN h.status IN ('Scheduled','In Progress') THEN h.starts_at END ASC, h.updated_at DESC,h.id DESC";
  $stmt = barangay_db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function husay_available_complaints(): array {
  husay_schema();
  return barangay_db()->query("SELECT c.complaint_code,c.resident_name,c.subject,COALESCE(NULLIF(c.contact_mobile,''),u.mobile,'') AS contact_mobile FROM complaints c LEFT JOIN users u ON u.id=c.resident_user_id WHERE c.archived_at IS NULL AND c.status IN ('Open','In Progress') AND NOT EXISTS (SELECT 1 FROM husay_schedules h WHERE h.complaint_id=c.id AND h.status IN ('Scheduled','In Progress')) ORDER BY c.filed_at DESC,c.id DESC")->fetchAll();
}

function husay_public_rows(int $residentId, ?string $code = null): array {
  return array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['id','complaint_code','starts_at','ends_at','venue','assigned_name','status','outcome','instructions','public_summary','complainant_attendance','respondent_attendance','updated_at','version','change_reason'])), husay_rows($code, $residentId));
}

function husay_resident_notifications(int $residentId): array {
  $items = [];
  foreach (husay_public_rows($residentId) as $row) {
    if ($row['status'] === 'Rescheduled') continue;
    $items[] = ['id' => 'resident-husay-' . $row['id'] . '-' . $row['version'], 'title' => 'Husay: ' . $row['status'],
      'message' => $row['complaint_code'] . ' · ' . date('M j, Y g:i A', strtotime($row['starts_at'])) . ' at ' . $row['venue'],
      'date' => $row['updated_at'], 'status' => 'Unread', 'href' => 'resident-dashboard.php?view=my-complaints#complaint-' . rawurlencode($row['complaint_code']), 'view' => 'my-complaints'];
  }
  return $items;
}
